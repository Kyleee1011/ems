<?php

namespace App\Controllers;

use PDO;
use Exception;
use App\Services\FileUploadService;

class HomeController
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createPost()
    {
require_once dirname(dirname(__DIR__)) . '/config_session.php';
        
        // CSRF Check
        if (!\App\Utils\AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            header("Location: " . baseUrl('home?error=csrf'));
            exit;
        }

        $userId = $_SESSION['user_id'] ?? 0;
        
        if (isset($_POST['content']) || isset($_FILES['post_image'])) {
            $content = trim($_POST['content'] ?? '');
            $isAnnouncement = isset($_POST['is_announcement']) ? 1 : 0;
            
            // Image Upload
            $imagePath = null;
            if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
                try {
                    // Corrected usage of FileUploadService
                    $imagePath = FileUploadService::handleFileUpload($_FILES['post_image'], 'post_user_' . $userId, 'posts');
                } catch (Exception $e) {
                    // Handle upload error silently or log
                }
            }

            if (!empty($content) || $imagePath) {
                $stmt = $this->pdo->prepare("INSERT INTO posts (user_id, content, image_path, is_announcement, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$userId, $content, $imagePath, $isAnnouncement]);
            }
        }
        
        header("Location: " . baseUrl('home'));
        exit;
    }

    public function likePost()
    {
require_once dirname(dirname(__DIR__)) . '/config_session.php';

        // CSRF Check
        if (!\App\Utils\AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            header("Location: " . baseUrl('home?error=csrf'));
            exit;
        }

        $userId = $_SESSION['user_id'] ?? 0;
        $postId = $_POST['post_id'] ?? 0;

        if ($userId && $postId) {
            $check = $this->pdo->prepare("SELECT * FROM post_likes WHERE post_id = ? AND user_id = ?");
            $check->execute([$postId, $userId]);
            
            if ($check->rowCount() > 0) {
                $this->pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?")->execute([$postId, $userId]);
            } else {
                $this->pdo->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)")->execute([$postId, $userId]);
            }
        }
        
        header("Location: " . baseUrl('home'));
        exit;
    }

    /**
     * Handle holiday add/remove from calendar (HR only)
     */
    public function holidayAction()
    {
require_once dirname(dirname(__DIR__)) . '/config_session.php';
        
        // CSRF Check
        if (!\App\Utils\AppHelpers::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            header("Location: " . baseUrl('home?error=csrf'));
            exit;
        }

        // Check if user is HR
        $current_role = $_SESSION['role'] ?? 'Employee';
        $is_hr = ($current_role === 'HR' || $current_role === 'Human Resources');
        
        if (!$is_hr) {
            header("Location: " . baseUrl('home'));
            exit;
        }
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_holiday') {
            $holiday_date = trim($_POST['holiday_date'] ?? '');
            $holiday_name = trim($_POST['holiday_name'] ?? '');
            $holiday_type = trim($_POST['holiday_type'] ?? 'REGULAR');
            
            if (!empty($holiday_date) && !empty($holiday_name)) {
                // Remove existing holiday on that date first (to allow updating)
                $delStmt = $this->pdo->prepare("DELETE FROM scheduler_holidays WHERE holiday_date = ?");
                $delStmt->execute([$holiday_date]);
                
                // Insert new holiday into scheduler_holidays
                $stmt = $this->pdo->prepare("INSERT INTO scheduler_holidays (holiday_date, holiday_type, holiday_name) VALUES (?, ?, ?)");
                $stmt->execute([$holiday_date, $holiday_type, $holiday_name]);
            }
        } elseif ($action === 'remove_holiday') {
            $holiday_date = trim($_POST['holiday_date'] ?? '');
            
            if (!empty($holiday_date)) {
                $stmt = $this->pdo->prepare("DELETE FROM scheduler_holidays WHERE holiday_date = ?");
                $stmt->execute([$holiday_date]);
            }
        }
        
        // Redirect back to home with the same month/year
        $month = (int)date('m', strtotime($_POST['holiday_date'] ?? 'now'));
        $year = (int)date('Y', strtotime($_POST['holiday_date'] ?? 'now'));
        header("Location: " . baseUrl("home?month={$month}&year={$year}"));
        exit;
    }

    public function show()
    {
        // 1. Centralized Setup (Session & DB)
require_once dirname(dirname(__DIR__)) . '/config_session.php';

        // 2. Authentication Check
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . baseUrl('login')); // Redirect to new login route
            exit;
        }

        $conn = $this->pdo;
        $current_user_id = $_SESSION['user_id'];
        $current_role = $_SESSION['role'] ?? 'Employee';
        $current_user_name = $_SESSION['full_name'] ?? 'User';
        $approval_role = $_SESSION['approval_role'] ?? 'Employee';
        $is_hr = ($current_role === 'HR' || $current_role === 'Human Resources');

        // --- FETCH CURRENT USER FULL DATA ---
        $stmtUser = $conn->prepare("SELECT * FROM employees WHERE emp_id = ?");
        $stmtUser->execute([$current_user_id]);
        $myProfile = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $current_ac_no = $myProfile['ac_no'] ?? 'TEMP';

        // --- AUTOMATIC BIRTHDAY POST GENERATOR (This should be a cron job, but we keep the logic for now) ---
        try {
            $bdaySql = "SELECT emp_id, first_name, last_name 
                        FROM employees 
                        WHERE MONTH(date_of_birth) = MONTH(NOW()) 
                        AND DAY(date_of_birth) = DAY(NOW())
                        AND employee_status = 'Active'";
            $bdays = $conn->query($bdaySql)->fetchAll();

            foreach ($bdays as $bday) {
                $fullName = $bday['first_name'] . ' ' . $bday['last_name'];
                $content = "🎉 Happy Birthday to " . $fullName . "! 🎂 Wishing you a fantastic day!";
                
                $checkSql = "SELECT COUNT(*) FROM posts WHERE content LIKE :namePat AND is_birthday = 1 AND DATE(created_at) = DATE(NOW())";
                $checkStmt = $conn->prepare($checkSql);
                $namePattern = "%" . $fullName . "%";
                $checkStmt->bindParam(':namePat', $namePattern);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() == 0) {
                    $insertSql = "INSERT INTO posts (user_id, content, is_announcement, is_birthday, created_at) VALUES (?, ?, 0, 1, NOW())";
                    $conn->prepare($insertSql)->execute([$current_user_id, $content]);
                }
            }
        } catch (Exception $e) {}


        // --- DATA FETCHING FOR VIEW ---
        $postSql = "SELECT p.*, e.first_name, e.last_name, e.job_title, e.dept_id, e.profile_picture,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id AND pl.user_id = :uid) as is_liked
                FROM posts p 
                JOIN employees e ON p.user_id = e.emp_id 
                ORDER BY p.created_at DESC";
        $postStmt = $conn->prepare($postSql);
        $postStmt->bindParam(':uid', $current_user_id);
        $postStmt->execute();
        $posts = $postStmt->fetchAll();

        $birthdaySql = "SELECT first_name, last_name, profile_picture, date_of_birth, day(date_of_birth) as bday_day 
                     FROM employees 
                     WHERE MONTH(date_of_birth) = MONTH(NOW()) 
                     AND employee_status = 'Active' 
                     ORDER BY DAY(date_of_birth) ASC";
        $upcoming_birthdays = $conn->query($birthdaySql)->fetchAll();

        $announceSql = "SELECT p.*, e.first_name, e.last_name, e.job_title, e.profile_picture,
                        (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id AND pl.user_id = :uid) as is_liked 
                        FROM posts p 
                        JOIN employees e ON p.user_id = e.emp_id 
                        ORDER BY p.created_at DESC LIMIT 10";
        $announceStmt = $conn->prepare($announceSql);
        $announceStmt->bindParam(':uid', $current_user_id);
        $announceStmt->execute();
        $announcements = $announceStmt->fetchAll();

        // --- CALENDAR & HOLIDAY LOGIC ---
        if (isset($_GET['month']) && isset($_GET['year'])) {
            $calMonth = (int)$_GET['month'];
            $calYear = (int)$_GET['year'];
        } else {
            $calMonth = (int)date('m');
            $calYear = (int)date('Y');
        }

        $prevMonth = $calMonth - 1; $prevYear = $calYear;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

        $nextMonth = $calMonth + 1; $nextYear = $calYear;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        
        $firstDayOfMonth = mktime(0,0,0, $calMonth, 1, $calYear);
        $numberDays = date('t', $firstDayOfMonth);
        $dateComponents = getdate($firstDayOfMonth);
        $dayOfWeek = $dateComponents['wday'];
        $monthName = $dateComponents['month'];

        // Fetch holidays from scheduler_holidays where actual data resides
        $holSql = "SELECT * FROM scheduler_holidays WHERE YEAR(holiday_date) = ?";
        $holStmt = $conn->prepare($holSql);
        $holStmt->execute([$calYear]);
        $dbHolidaysRaw = $holStmt->fetchAll(PDO::FETCH_ASSOC);
        $finalHolidays = []; // getPhilippineHolidays($calYear) can be a separate helper
        foreach($dbHolidaysRaw as $h) {
            $cleanDate = date('Y-m-d', strtotime($h['holiday_date']));
            $finalHolidays[$cleanDate] = ['type' => $h['holiday_type'], 'name' => $h['holiday_name']];
        }


        // --- Render View ---
        $this->render('home_view', compact(
            'posts', 'myProfile', 'current_user_id', 'current_role', 'current_user_name', 
            'approval_role', 'is_hr', 'upcoming_birthdays', 'announcements', 'calMonth', 
            'calYear', 'prevMonth', 'prevYear', 'nextMonth', 'nextYear', 'finalHolidays',
            'numberDays', 'dayOfWeek', 'monthName', 'conn' // Passing conn for comments for now
        ));
    }

    /**
     * A simple view renderer.
     * Extracts variables and includes the view file.
     */
    protected function render(string $view, array $data = [])
    {
        extract($data);

        // Buffer the output
        ob_start();
        
        // Include the view file from the /src/Views/ directory
        require BASE_PATH . "/src/Views/{$view}.php";
        
        // Get the buffered content
        $content = ob_get_clean();

        // You could have a main layout file here, but for now, we'll just echo
        echo $content;
    }
}


