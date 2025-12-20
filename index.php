<?php
session_start();
require_once 'config.php';

// 1. Login Verification
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("location: login.php");
    exit;
}

$conn = getDBConnection();
$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'];
$current_user_name = $_SESSION['full_name'];
$approval_role = $_SESSION['approval_role'] ?? 'Employee';
$is_hr = ($current_role === 'HR' || $current_role === 'Human Resources');

// --- FETCH CURRENT USER FULL DATA (For My Profile Modal) ---
$stmtUser = $conn->prepare("SELECT * FROM Employees WHERE emp_id = ?");
$stmtUser->execute([$current_user_id]);
$myProfile = $stmtUser->fetch(PDO::FETCH_ASSOC);
$current_user_pic = $myProfile['profile_picture'] ?? null;
$current_ac_no = $myProfile['ac_no'] ?? 'TEMP';

// --- AUTOMATIC BIRTHDAY POST GENERATOR ---
try {
    $bdaySql = "SELECT emp_id, first_name, last_name 
                FROM Employees 
                WHERE MONTH(date_of_birth) = MONTH(GETDATE()) 
                AND DAY(date_of_birth) = DAY(GETDATE())
                AND employee_status = 'Active'";
    $bdays = $conn->query($bdaySql)->fetchAll();

    foreach ($bdays as $bday) {
        $fullName = $bday['first_name'] . ' ' . $bday['last_name'];
        $content = "🎉 Happy Birthday to " . $fullName . "! 🎂 Wishing you a fantastic day!";
        
        $checkSql = "SELECT COUNT(*) FROM Posts WHERE content LIKE :namePat AND is_birthday = 1 AND CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)";
        $checkStmt = $conn->prepare($checkSql);
        $namePattern = "%" . $fullName . "%";
        $checkStmt->bindParam(':namePat', $namePattern);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() == 0) {
            $insertSql = "INSERT INTO Posts (user_id, content, is_announcement, is_birthday, created_at) VALUES (?, ?, 0, 1, GETDATE())";
            $conn->prepare($insertSql)->execute([$current_user_id, $content]);
        }
    }
} catch (Exception $e) {}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 0. UPDATE SELF PROFILE (New Feature)
    if (isset($_POST['action']) && $_POST['action'] == 'update_self_profile') {
        try {
            // 1. Handle Profile Picture Upload (Save as AC_No.png)
            $newProfilePicPath = $myProfile['profile_picture']; // Default to existing
            
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                $uploadDir = 'uploads/profile_pictures/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $fileExt = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                if (in_array($fileExt, ['png', 'jpg', 'jpeg'])) {
                    $targetFile = $uploadDir . $current_ac_no . '.png'; // Force PNG naming based on AC No
                    
                    // Delete old if exists to ensure refresh
                    if (file_exists($targetFile)) unlink($targetFile);
                    
                    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFile)) {
                        $newProfilePicPath = $targetFile;
                    }
                }
            }

            // 2. Update DB
            $upSql = "UPDATE Employees SET 
                      address = ?, contact_number = ?, email_address = ?, 
                      emergency_contact_name = ?, emergency_contact_number = ?, emergency_contact_relationship = ?,
                      profile_picture = ?
                      WHERE emp_id = ?";
            $upStmt = $conn->prepare($upSql);
            $upStmt->execute([
                $_POST['address'], $_POST['contact_number'], $_POST['email_address'],
                $_POST['emergency_name'], $_POST['emergency_number'], $_POST['emergency_rel'],
                $newProfilePicPath,
                $current_user_id
            ]);

            header("Location: index.php?msg=profile_updated");
            exit;
        } catch (Exception $e) {
            // Handle error
        }
    }

    // 1. SAVE HOLIDAY (HR ONLY)
    if (isset($_POST['action']) && $_POST['action'] == 'save_holiday' && $is_hr) {
        $date = $_POST['holiday_date'];
        $type = $_POST['holiday_type']; 
        $name = $_POST['holiday_name'];

        if ($type === 'DELETE') {
            $stmt = $conn->prepare("DELETE FROM Holidays WHERE holiday_date = ?");
            $stmt->execute([$date]);
        } else {
            $check = $conn->prepare("SELECT id FROM Holidays WHERE holiday_date = ?");
            $check->execute([$date]);
            if ($check->fetch()) {
                $stmt = $conn->prepare("UPDATE Holidays SET holiday_type = ?, holiday_name = ? WHERE holiday_date = ?");
                $stmt->execute([$type, $name, $date]);
            } else {
                $stmt = $conn->prepare("INSERT INTO Holidays (holiday_date, holiday_type, holiday_name) VALUES (?, ?, ?)");
                $stmt->execute([$date, $type, $name]);
            }
        }
        $redirectY = date('Y', strtotime($date));
        $redirectM = date('m', strtotime($date));
        header("Location: index.php?month=$redirectM&year=$redirectY");
        exit;
    }

    // 2. Create Post
    if (isset($_POST['action']) && $_POST['action'] == 'create_post') {
        $content = $_POST['content'] ?? '';
        $is_announcement = isset($_POST['is_announcement']) ? 1 : 0;
        $imagePath = null;
        
        if(!$is_hr) { $is_announcement = 0; }

        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
            $targetDir = "uploads/";
            if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
            $fileName = time() . '_' . basename($_FILES["post_image"]["name"]);
            $targetFile = $targetDir . $fileName;
            if (move_uploaded_file($_FILES["post_image"]["tmp_name"], $targetFile)) {
                $imagePath = $targetFile;
            }
        }

        if ($content || $imagePath) {
            $stmt = $conn->prepare("INSERT INTO Posts (user_id, content, image_path, is_announcement, created_at) VALUES (?, ?, ?, ?, GETDATE())");
            $stmt->execute([$current_user_id, $content, $imagePath, $is_announcement]);
        }
        header("Location: index.php");
        exit;
    }

    // 3. Like Post
    if (isset($_POST['action']) && $_POST['action'] == 'like_post') {
        $post_id = $_POST['post_id'];
        $check = $conn->prepare("SELECT like_id FROM PostLikes WHERE post_id = ? AND user_id = ?");
        $check->execute([$post_id, $current_user_id]);
        
        if ($check->fetch()) {
            $conn->prepare("DELETE FROM PostLikes WHERE post_id = ? AND user_id = ?")->execute([$post_id, $current_user_id]);
            $conn->prepare("UPDATE Posts SET like_count = like_count - 1 WHERE post_id = ?")->execute([$post_id]);
        } else {
            $conn->prepare("INSERT INTO PostLikes (post_id, user_id) VALUES (?, ?)")->execute([$post_id, $current_user_id]);
            $conn->prepare("UPDATE Posts SET like_count = like_count + 1 WHERE post_id = ?")->execute([$post_id]);
        }
        header("Location: index.php#post-" . $post_id);
        exit;
    }

    // 4. Comment Post
    if (isset($_POST['action']) && $_POST['action'] == 'comment_post') {
        $post_id = $_POST['post_id'];
        $comment = $_POST['comment_text'];
        if (!empty($comment)) {
            $conn->prepare("INSERT INTO PostComments (post_id, user_id, comment_text, created_at) VALUES (?, ?, ?, GETDATE())")->execute([$post_id, $current_user_id, $comment]);
            $conn->prepare("UPDATE Posts SET comment_count = comment_count + 1 WHERE post_id = ?")->execute([$post_id]);
        }
        header("Location: index.php#post-" . $post_id);
        exit;
    }
}

// --- DATA FETCHING ---
$sql = "SELECT p.*, e.first_name, e.last_name, e.job_title, e.dept_id, e.profile_picture,
        (SELECT COUNT(*) FROM PostLikes pl WHERE pl.post_id = p.post_id AND pl.user_id = :uid) as is_liked
        FROM Posts p 
        JOIN Employees e ON p.user_id = e.emp_id 
        ORDER BY p.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':uid', $current_user_id);
$stmt->execute();
$posts = $stmt->fetchAll();

$monthSql = "SELECT first_name, last_name, profile_picture, date_of_birth, day(date_of_birth) as bday_day 
             FROM Employees 
             WHERE MONTH(date_of_birth) = MONTH(GETDATE()) 
             AND employee_status = 'Active' 
             ORDER BY DAY(date_of_birth) ASC";
$upcoming_birthdays = $conn->query($monthSql)->fetchAll();

$announceSql = "SELECT TOP 3 p.content, p.created_at, e.first_name, e.last_name 
                FROM Posts p 
                JOIN Employees e ON p.user_id = e.emp_id 
                WHERE p.is_announcement = 1 
                ORDER BY p.created_at DESC";
$announcements = $conn->query($announceSql)->fetchAll();

// ==========================================
// CALENDAR & HOLIDAY LOGIC
// ==========================================
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

function getPhilippineHolidays($year) {
    $holidays = [];
    $holidays[$year . '-01-01'] = ['type' => 'REGULAR', 'name' => "New Year's Day"];
    $holidays[$year . '-04-09'] = ['type' => 'REGULAR', 'name' => "Araw ng Kagitingan"];
    $holidays[$year . '-05-01'] = ['type' => 'REGULAR', 'name' => "Labor Day"];
    $holidays[$year . '-06-12'] = ['type' => 'REGULAR', 'name' => "Independence Day"];
    $holidays[$year . '-11-30'] = ['type' => 'REGULAR', 'name' => "Bonifacio Day"];
    $holidays[$year . '-12-25'] = ['type' => 'REGULAR', 'name' => "Christmas Day"];
    $holidays[$year . '-12-30'] = ['type' => 'REGULAR', 'name' => "Rizal Day"];
    $holidays[$year . '-08-21'] = ['type' => 'SPECIAL', 'name' => "Ninoy Aquino Day"];
    $holidays[$year . '-11-01'] = ['type' => 'SPECIAL', 'name' => "All Saints' Day"];
    $holidays[$year . '-12-08'] = ['type' => 'SPECIAL', 'name' => "Feast of Immaculate Conception"];
    $holidays[$year . '-12-31'] = ['type' => 'SPECIAL', 'name' => "Last Day of the Year"];
    
    $easterTimestamp = easter_date($year); 
    $maundyThursday = date('Y-m-d', strtotime('-3 days', $easterTimestamp));
    $goodFriday     = date('Y-m-d', strtotime('-2 days', $easterTimestamp));
    $blackSaturday  = date('Y-m-d', strtotime('-1 day', $easterTimestamp));

    $holidays[$maundyThursday] = ['type' => 'REGULAR', 'name' => "Maundy Thursday"];
    $holidays[$goodFriday]     = ['type' => 'REGULAR', 'name' => "Good Friday"];
    $holidays[$blackSaturday]  = ['type' => 'SPECIAL', 'name' => "Black Saturday"];
    
    $lastMondayAug = date('Y-m-d', strtotime("last monday of august $year"));
    $holidays[$lastMondayAug] = ['type' => 'REGULAR', 'name' => "National Heroes Day"];
    $holidays[$year . '-02-25'] = ['type' => 'SPECIAL', 'name' => "EDSA Revolution Anniversary"];
    if($year == 2025) $holidays['2025-01-29'] = ['type' => 'SPECIAL', 'name' => "Chinese New Year"];
    return $holidays;
}

$holSql = "SELECT * FROM Holidays WHERE YEAR(holiday_date) = ?";
$holStmt = $conn->prepare($holSql);
$holStmt->execute([$calYear]);
$dbHolidaysRaw = $holStmt->fetchAll(PDO::FETCH_ASSOC);
$finalHolidays = getPhilippineHolidays($calYear);

foreach($dbHolidaysRaw as $h) {
    $cleanDate = date('Y-m-d', strtotime($h['holiday_date']));
    $finalHolidays[$cleanDate] = ['type' => $h['holiday_type'], 'name' => $h['holiday_name']];
}

$firstDayOfMonth = mktime(0,0,0, $calMonth, 1, $calYear);
$numberDays = date('t', $firstDayOfMonth);
$dateComponents = getdate($firstDayOfMonth);
$dayOfWeek = $dateComponents['wday'];
$monthName = $dateComponents['month'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed | EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --background: 0 0% 100%;
            --foreground: 0 0% 12.5%; /* Approximate from oklch */
            --card: 0 0% 100%;
            --card-foreground: 0 0% 37%;
            --primary: 133 76% 59%; /* Approximating the bright green */
            --primary-foreground: 0 0% 12.5%;
            --muted: 0 0% 96%;
            --muted-foreground: 0 0% 45%;
            --border: 0 0% 90%;
            --radius: 0.625rem;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #FAFAFA; /* Lightest gray for contrast */
            color: #1F1F1F;
        }

        .primary-gradient {
            background: linear-gradient(135deg, #a3e635 0%, #4ade80 100%);
        }

        .dropdown-menu { display: none; }
        .dropdown-menu.show { display: block; animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        
        .no-scrollbar::-webkit-scrollbar { display: none; }
        
        .calendar-day { min-height: 32px; font-size: 0.8rem; }
        .calendar-day.clickable:hover { background-color: #f3f4f6; cursor: pointer; transform: scale(1.05); font-weight: bold; transition: all 0.2s; }
        .holiday-REGULAR { background-color: #fee2e2; color: #b91c1c; font-weight: 700; border-radius: 6px; } 
        .holiday-SPECIAL { background-color: #ffedd5; color: #c2410c; font-weight: 700; border-radius: 6px; } 
        
        /* Modern Profile Card Styling */
        .profile-card-banner {
            height: 100px;
            background: linear-gradient(120deg, #dcfce7 0%, #bbf7d0 100%);
            border-radius: var(--radius) var(--radius) 0 0;
            position: relative;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        border: 'hsl(var(--border))',
                        background: 'hsl(var(--background))',
                        foreground: 'hsl(var(--foreground))',
                        primary: {
                            DEFAULT: '#a3e635', // Bright Green
                            foreground: '#1F1F1F',
                            50: '#f7fee7',
                            100: '#ecfccb',
                            500: '#84cc16',
                            600: '#65a30d',
                        },
                        muted: {
                            DEFAULT: '#f3f4f6',
                            foreground: '#6b7280'
                        },
                        card: {
                            DEFAULT: '#ffffff',
                            foreground: '#374151'
                        }
                    },
                    borderRadius: {
                        lg: 'var(--radius)',
                        md: 'calc(var(--radius) - 2px)',
                        sm: 'calc(var(--radius) - 4px)',
                    },
                    boxShadow: {
                        'soft': '0 2px 10px rgba(0,0,0,0.03)',
                        'card': '0 0 0 1px rgba(0,0,0,0.03), 0 1px 3px rgba(0,0,0,0.05)'
                    }
                }
            }
        }
    </script>
</head>
<body class="antialiased min-h-screen">

    <nav class="bg-white/80 backdrop-blur-md sticky top-0 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">
            
            <div class="flex items-center gap-8">
                <a href="index.php" class="text-xl font-extrabold tracking-tight flex items-center gap-2 text-gray-900">
                    <div class="w-8 h-8 rounded-lg bg-primary text-primary-foreground flex items-center justify-center">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    EMS
                </a>

                <div class="relative">
                    <button onclick="toggleDropdown()" class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 px-3 py-2 rounded-md transition-all">
                        <i class="fa-solid fa-bars text-gray-400"></i>
                        <span>Menu</span>
                    </button>

                    <div id="navDropdown" class="dropdown-menu absolute top-full left-0 mt-2 w-64 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50 ring-1 ring-black/5">
                        <div class="px-4 py-2 border-b border-gray-50 bg-gray-50/50">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Employee Services</span>
                        </div>
                        
                        <a href="timecard.php" class="group flex items-center gap-3 px-4 py-3 text-sm text-gray-600 hover:bg-primary-50 hover:text-gray-900 transition-colors">
                            <i class="fa-regular fa-clock w-5 text-center text-gray-400 group-hover:text-primary-600"></i> View DTR
                        </a>
                        <a href="schedule/schedule.php" class="group flex items-center gap-3 px-4 py-3 text-sm text-gray-600 hover:bg-primary-50 hover:text-gray-900 transition-colors">
                            <i class="fa-regular fa-calendar-alt w-5 text-center text-gray-400 group-hover:text-primary-600"></i> View Schedule
                        </a>
                        <a href="leave.php" class="group flex items-center gap-3 px-4 py-3 text-sm text-gray-600 hover:bg-primary-50 hover:text-gray-900 transition-colors">
                            <i class="fa-solid fa-person-walking-luggage w-5 text-center text-gray-400 group-hover:text-primary-600"></i> Leave Application
                        </a>
                        <a href="payslip.php" class="group flex items-center gap-3 px-4 py-3 text-sm text-gray-600 hover:bg-primary-50 hover:text-gray-900 transition-colors">
                            <i class="fa-solid fa-file-invoice-dollar w-5 text-center text-gray-400 group-hover:text-primary-600"></i> View Payslip
                        </a>

                        <?php if($is_hr): ?>
                        <div class="my-1 border-t border-gray-100"></div>
                        <a href="dashboard.php" class="group flex items-center gap-3 px-4 py-3 text-sm font-semibold text-gray-900 bg-gray-50 hover:bg-primary hover:text-primary-foreground transition-all">
                            <i class="fa-solid fa-users-gear w-5 text-center"></i> HR Portal
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="text-right hidden md:block leading-tight">
                    <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($current_user_name); ?></div>
                    <div class="text-[10px] uppercase tracking-wide font-semibold text-gray-500"><?php echo $approval_role; ?></div>
                </div>
                
                <?php if(!empty($current_user_pic) && file_exists($current_user_pic)): ?>
                    <img src="<?php echo htmlspecialchars($current_user_pic) . '?t=' . time(); ?>" class="w-10 h-10 rounded-full object-cover shadow-sm ring-2 ring-white cursor-pointer hover:opacity-80 transition" onclick="openMyProfile()">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-primary text-primary-foreground flex items-center justify-center font-bold text-sm shadow-sm ring-2 ring-white cursor-pointer hover:opacity-80 transition" onclick="openMyProfile()">
                        <?php echo substr($current_user_name, 0, 1); ?>
                    </div>
                <?php endif; ?>
                
                <a href="logout.php" class="text-gray-400 hover:text-red-500 ml-2 p-2 hover:bg-red-50 rounded-full transition" title="Logout">
                    <i class="fa-solid fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <div class="hidden lg:block lg:col-span-3 space-y-6">
                <div onclick="openMyProfile()" class="bg-white rounded-lg shadow-card overflow-hidden group cursor-pointer hover:shadow-soft transition-all duration-300 border border-border">
                    <div class="profile-card-banner relative">
                        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-20"></div>
                    </div>
                    
                    <div class="px-6 relative">
                        <div class="-mt-12 mb-3 flex justify-start">
                            <?php if(!empty($current_user_pic) && file_exists($current_user_pic)): ?>
                                <img src="<?php echo htmlspecialchars($current_user_pic) . '?t=' . time(); ?>" class="w-24 h-24 rounded-full object-cover border-[4px] border-white shadow-sm group-hover:scale-105 transition-transform duration-300">
                            <?php else: ?>
                                <div class="w-24 h-24 bg-primary text-primary-foreground rounded-full flex items-center justify-center text-3xl font-bold border-[4px] border-white shadow-sm group-hover:scale-105 transition-transform duration-300">
                                    <?php echo substr($current_user_name, 0, 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="pb-6">
                            <h3 class="font-bold text-xl text-gray-900 group-hover:text-primary-600 transition-colors"><?php echo htmlspecialchars($current_user_name); ?></h3>
                            <p class="text-sm text-gray-500 font-medium mb-3"><?php echo htmlspecialchars($myProfile['job_title'] ?? 'Employee'); ?></p>
                            
                            <div class="flex items-center gap-2 mb-4">
                                <span class="bg-gray-100 text-gray-600 text-[10px] uppercase font-bold px-2 py-1 rounded-md border border-gray-200"><?php echo $approval_role; ?></span>
                                <span class="bg-green-50 text-green-600 text-[10px] uppercase font-bold px-2 py-1 rounded-md border border-green-100">Active</span>
                            </div>

                            <p class="text-xs text-gray-400 flex items-center gap-1 group-hover:text-gray-600 transition-colors">
                                <span>View Full Profile</span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-1 lg:col-span-6 space-y-6">
                
                <?php if($is_hr): ?>
                <div class="bg-white rounded-lg shadow-card border border-border p-4">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create_post">
                        <div class="flex gap-4">
                            <div class="flex-shrink-0">
                                <?php if(!empty($current_user_pic) && file_exists($current_user_pic)): ?>
                                    <img src="<?php echo htmlspecialchars($current_user_pic) . '?t=' . time(); ?>" class="w-10 h-10 rounded-full object-cover ring-2 ring-gray-50">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-primary text-primary-foreground flex items-center justify-center font-bold ring-2 ring-gray-50">
                                        <?php echo substr($current_user_name, 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex-1">
                                <textarea name="content" rows="2" placeholder="What's happening? Post an announcement..." 
                                    class="w-full bg-gray-50 border-0 rounded-lg p-3 text-sm focus:ring-2 focus:ring-primary-500 focus:bg-white resize-none outline-none transition placeholder-gray-400"></textarea>
                                
                                <div id="imagePreviewContainer" class="hidden mt-3 relative">
                                    <img id="imagePreview" src="" class="max-h-60 rounded-lg border border-gray-200">
                                    <button type="button" onclick="clearImage()" class="absolute top-2 right-2 bg-gray-900/50 text-white rounded-full p-1 w-6 h-6 flex items-center justify-center hover:bg-gray-900 transition">
                                        <i class="fa-solid fa-times text-xs"></i>
                                    </button>
                                </div>

                                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-50">
                                    <div class="flex items-center gap-4">
                                        <label class="cursor-pointer flex items-center gap-2 text-sm text-gray-500 hover:text-primary-600 hover:bg-primary-50 px-3 py-1.5 rounded-full transition-colors">
                                            <i class="fa-solid fa-image"></i>
                                            <span class="font-medium">Photo</span>
                                            <input type="file" name="post_image" id="postImageInput" accept="image/*" class="hidden" onchange="previewImage(this)">
                                        </label>
                                        <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
                                            <input type="checkbox" name="is_announcement" value="1" class="rounded text-primary-600 focus:ring-primary-500 border-gray-300">
                                            Make Announcement
                                        </label>
                                    </div>
                                    <button type="submit" class="bg-primary text-primary-foreground hover:bg-primary-600 text-sm font-bold px-6 py-2 rounded-full transition-all shadow-sm hover:shadow-md">
                                        Post
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php foreach($posts as $post): ?>
                    <?php 
                        $isBday = isset($post['is_birthday']) && $post['is_birthday'] == 1; 
                        $isAnnounce = isset($post['is_announcement']) && $post['is_announcement'] == 1;
                        $cardClass = "bg-white border-border";
                        if ($isBday) $cardClass = "bg-gradient-to-r from-pink-50 to-white border-pink-100";
                        if ($isAnnounce) $cardClass = "bg-white border-blue-100 shadow-md ring-1 ring-blue-50";
                    ?>
                
                <div id="post-<?php echo $post['post_id']; ?>" class="rounded-lg shadow-card border <?php echo $cardClass; ?> overflow-hidden transition-all hover:shadow-soft">
                    
                    <div class="p-4 flex items-center gap-3">
                        <?php if($isBday): ?>
                            <div class="w-10 h-10 rounded-full bg-pink-100 text-pink-500 flex items-center justify-center font-bold text-lg border border-pink-200">
                                <i class="fa-solid fa-cake-candles"></i>
                            </div>
                        <?php elseif(!empty($post['profile_picture']) && file_exists($post['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($post['profile_picture']) . '?t=' . time(); ?>" class="w-10 h-10 rounded-full object-cover border border-gray-100">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold border border-gray-200">
                                <?php echo substr($post['first_name'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <div class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                                
                                <?php if($isBday): ?>
                                    <span class="text-[10px] font-bold text-pink-600 bg-pink-100 px-2 py-0.5 rounded-full border border-pink-200">BIRTHDAY</span>
                                <?php elseif($isAnnounce): ?>
                                    <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-100">ANNOUNCEMENT</span>
                                <?php elseif(strpos(strtolower($post['job_title'] ?? ''), 'hr') !== false): ?>
                                    <span class="text-[10px] font-bold text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200">HR ADMIN</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?php echo date('F j, Y \a\t g:i a', strtotime($post['created_at'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 pb-3">
                        <p class="text-gray-800 whitespace-pre-line text-sm leading-relaxed <?php echo $isBday ? 'text-lg font-medium text-center py-2 text-pink-600' : ''; ?>">
                            <?php echo htmlspecialchars($post['content']); ?>
                        </p>
                    </div>

                    <?php if($post['image_path']): ?>
                    <div class="bg-gray-50 border-t border-b border-gray-100">
                        <img src="<?php echo htmlspecialchars($post['image_path']); ?>" class="w-full h-auto max-h-[500px] object-cover">
                    </div>
                    <?php endif; ?>

                    <div class="px-4 py-2 flex items-center justify-between text-xs text-gray-500 border-t <?php echo $isBday ? 'border-pink-100' : 'border-gray-50'; ?> mt-2">
                        <div class="flex items-center gap-1 font-medium">
                            <i class="fa-solid fa-thumbs-up text-primary-600"></i> <?php echo $post['like_count']; ?> Likes
                        </div>
                        <div class="font-medium"><?php echo $post['comment_count']; ?> Comments</div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 px-2 py-1 border-t <?php echo $isBday ? 'border-pink-100' : 'border-gray-50'; ?>">
                        <form method="POST" class="w-full">
                            <input type="hidden" name="action" value="like_post">
                            <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                            <button class="w-full flex items-center justify-center gap-2 py-2 rounded-md hover:bg-gray-50 text-sm font-semibold transition <?php echo $post['is_liked'] ? 'text-primary-600' : 'text-gray-500'; ?>">
                                <i class="<?php echo $post['is_liked'] ? 'fa-solid' : 'fa-regular'; ?> fa-thumbs-up"></i> Like
                            </button>
                        </form>
                        <button onclick="toggleComments(<?php echo $post['post_id']; ?>)" class="w-full flex items-center justify-center gap-2 py-2 rounded-md hover:bg-gray-50 text-sm font-semibold text-gray-500">
                            <i class="fa-regular fa-comment-alt"></i> Comment
                        </button>
                    </div>

                    <div id="comments-<?php echo $post['post_id']; ?>" class="bg-gray-50 px-4 py-3 border-t border-gray-100 hidden">
                        <?php 
                            $c_stmt = $conn->prepare("SELECT c.*, e.first_name, e.last_name, e.profile_picture FROM PostComments c JOIN Employees e ON c.user_id = e.emp_id WHERE c.post_id = ? ORDER BY c.created_at ASC");
                            $c_stmt->execute([$post['post_id']]);
                            $comments = $c_stmt->fetchAll();
                        ?>
                        
                        <?php if(count($comments) > 0): ?>
                        <div class="space-y-3 mb-4 max-h-60 overflow-y-auto no-scrollbar">
                            <?php foreach($comments as $comment): ?>
                            <div class="flex gap-2 text-sm">
                                <div class="shrink-0">
                                    <?php if(!empty($comment['profile_picture']) && file_exists($comment['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($comment['profile_picture']) . '?t=' . time(); ?>" class="w-8 h-8 rounded-full object-cover border border-gray-200">
                                    <?php else: ?>
                                        <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-xs font-bold text-gray-500">
                                            <?php echo substr($comment['first_name'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="font-bold text-gray-900 text-xs">
                                        <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>
                                    </div>
                                    <div class="bg-white p-2.5 rounded-lg shadow-sm border border-gray-200 text-gray-700 text-xs mt-1">
                                        <?php echo htmlspecialchars($comment['comment_text']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" class="flex gap-2">
                            <input type="hidden" name="action" value="comment_post">
                            <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                            
                            <div class="shrink-0 hidden md:block">
                                <?php if(!empty($current_user_pic) && file_exists($current_user_pic)): ?>
                                    <img src="<?php echo htmlspecialchars($current_user_pic) . '?t=' . time(); ?>" class="w-8 h-8 rounded-full object-cover border border-gray-200">
                                <?php else: ?>
                                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-xs font-bold text-gray-500">
                                        <?php echo substr($current_user_name, 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="relative flex-1">
                                <input type="text" name="comment_text" placeholder="Write a comment..." required 
                                    class="w-full rounded-full border border-gray-300 py-2 pl-4 pr-10 text-xs focus:ring-1 focus:ring-primary-500 outline-none bg-white">
                                <button type="submit" class="absolute right-1 top-1 text-primary-600 hover:bg-primary-50 p-1.5 rounded-full transition">
                                    <i class="fa-solid fa-paper-plane text-sm"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="hidden lg:block lg:col-span-3 space-y-6">
                
                <div class="bg-white rounded-lg shadow-card border border-border p-5">
                    <div class="flex justify-between items-center mb-4">
                        <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="text-gray-400 hover:text-primary-600 p-1 rounded-full hover:bg-gray-50 transition">
                            <i class="fa-solid fa-chevron-left text-xs"></i>
                        </a>
                        
                        <h3 class="font-bold text-gray-800 text-sm tracking-wide">
                            <?php echo $monthName . ' ' . $calYear; ?>
                        </h3>

                        <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="text-gray-400 hover:text-primary-600 p-1 rounded-full hover:bg-gray-50 transition">
                            <i class="fa-solid fa-chevron-right text-xs"></i>
                        </a>
                    </div>
                    <?php if($is_hr): ?>
                        <div class="text-center mb-2"><span class="text-[10px] text-gray-400">Click a date to edit</span></div>
                    <?php endif; ?>

                    <div class="grid grid-cols-7 gap-1 text-center text-[10px] mb-2 text-gray-400 font-bold uppercase">
                        <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
                    </div>

                    <div class="grid grid-cols-7 gap-1 text-center">
                        <?php
                            for($k=0; $k<$dayOfWeek; $k++){ echo "<div></div>"; }
                            for($day=1; $day<=$numberDays; $day++){
                                $currentDate = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $day);
                                $isToday = ($currentDate == date('Y-m-d'));
                                
                                $holClass = "text-gray-700 hover:bg-gray-50";
                                $holTitle = "";
                                if(isset($finalHolidays[$currentDate])) {
                                    $type = $finalHolidays[$currentDate]['type'];
                                    $holName = $finalHolidays[$currentDate]['name'];
                                    $holClass = "holiday-" . $type;
                                    $holTitle = "$type: $holName";
                                }

                                $todayClass = $isToday ? "bg-primary text-primary-foreground font-bold shadow-md ring-1 ring-offset-1 ring-primary" : "";
                                $clickClass = $is_hr ? "clickable" : "";
                                $dbType = $finalHolidays[$currentDate]['type'] ?? '';
                                $dbName = $finalHolidays[$currentDate]['name'] ?? '';
                                $jsName = addslashes($dbName); 
                                $safeName = htmlspecialchars($jsName, ENT_QUOTES);
                                $onClick = $is_hr ? "onclick=\"openHolidayModal('$currentDate', '$dbType', '$safeName')\"" : "";

                                echo "<div $onClick class='calendar-day rounded flex items-center justify-center transition-all $todayClass $holClass $clickClass' title='$holTitle'>$day</div>";
                            }
                        ?>
                    </div>
                    
                    <div class="mt-4 flex justify-center gap-4 text-[10px] text-gray-500">
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-red-200"></div> Regular</div>
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-orange-200"></div> Special</div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-card border border-border p-5">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2 text-sm uppercase tracking-wider">
                        <i class="fa-solid fa-cake-candles text-pink-500"></i> Birthdays
                    </h3>
                    <div class="space-y-4">
                        <?php if(count($upcoming_birthdays) > 0): ?>
                            <?php foreach($upcoming_birthdays as $bday): ?>
                                <div class="flex items-center gap-3 group p-2 rounded-md hover:bg-pink-50/50 transition-colors">
                                    <?php if(!empty($bday['profile_picture']) && file_exists($bday['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($bday['profile_picture']) . '?t=' . time(); ?>" class="w-9 h-9 rounded-full object-cover border border-gray-100">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-full bg-pink-100 text-pink-500 flex items-center justify-center font-bold text-xs border border-pink-50">
                                            <?php echo substr($bday['first_name'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-800 text-xs">
                                            <?php echo htmlspecialchars($bday['first_name'] . ' ' . $bday['last_name']); ?>
                                        </p>
                                        <p class="text-[10px] text-gray-400">
                                            <?php 
                                                $bDate = DateTime::createFromFormat('!m', date('m'))->format('M') . ' ' . $bday['bday_day'];
                                                echo $bDate;
                                                if ($bday['bday_day'] == date('j')) echo " <span class='text-pink-600 font-bold ml-1'>Today!</span>";
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-gray-400 italic text-center py-2">No birthdays coming up.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-card border border-border p-5">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2 text-sm uppercase tracking-wider">
                        <i class="fa-solid fa-bullhorn text-blue-500"></i> Updates
                    </h3>
                    <div class="space-y-4">
                        <?php if(count($announcements) > 0): ?>
                            <?php foreach($announcements as $ann): ?>
                                <div class="flex gap-3 pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                                    <div class="bg-blue-50 rounded px-2 py-1 text-center w-10 shrink-0 h-10 flex flex-col justify-center border border-blue-100">
                                        <div class="text-[9px] text-blue-500 font-bold uppercase"><?php echo date('M', strtotime($ann['created_at'])); ?></div>
                                        <div class="text-xs font-bold text-gray-800 leading-none"><?php echo date('d', strtotime($ann['created_at'])); ?></div>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-800 line-clamp-2 mb-0.5">
                                            <?php echo htmlspecialchars($ann['content']); ?>
                                        </p>
                                        <p class="text-[10px] text-gray-400">
                                            <?php echo htmlspecialchars($ann['first_name']); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-gray-400 italic text-center py-2">No recent updates.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center text-[10px] text-gray-400">
                    &copy; 2025 Employee Management System
                </div>
            </div>
        </div>
    </div>

    <div id="myProfileModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden max-h-[90vh] flex flex-col animate-in fade-in zoom-in duration-200">
            <div class="bg-primary px-6 py-4 flex justify-between items-center shrink-0">
                <h3 class="font-bold text-primary-foreground text-lg">Edit Profile</h3>
                <button onclick="closeMyProfile()" class="text-primary-foreground/70 hover:text-primary-foreground hover:bg-black/10 rounded-full p-1 w-8 h-8 flex items-center justify-center transition">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto">
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_self_profile">
                    
                    <div class="flex items-center gap-6 mb-8">
                        <div class="relative group">
                             <?php if(!empty($current_user_pic) && file_exists($current_user_pic)): ?>
                                <img src="<?php echo htmlspecialchars($current_user_pic) . '?t=' . time(); ?>" id="profilePreview" class="w-24 h-24 rounded-full object-cover border-4 border-gray-100 shadow-md">
                            <?php else: ?>
                                <div class="w-24 h-24 bg-primary text-primary-foreground rounded-full flex items-center justify-center text-3xl font-bold border-4 border-gray-100 shadow-md">
                                    <?php echo substr($current_user_name, 0, 1); ?>
                                </div>
                            <?php endif; ?>
                            
                            <label class="absolute bottom-0 right-0 bg-gray-900 text-white rounded-full p-2 cursor-pointer shadow-lg hover:bg-black transition" title="Change Photo">
                                <i class="fa-solid fa-camera text-xs"></i>
                                <input type="file" name="profile_pic" accept="image/png, image/jpeg" class="hidden" onchange="previewProfile(this)">
                            </label>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($myProfile['first_name'] . ' ' . $myProfile['last_name']); ?></h2>
                            <p class="text-primary-600 font-medium"><?php echo htmlspecialchars($myProfile['job_title']); ?> <span class="text-gray-400 font-normal">| <?php echo htmlspecialchars($myProfile['job_level'] ?? 'Employee'); ?></span></p>
                            <p class="text-sm text-gray-400 mt-1 font-mono"><?php echo htmlspecialchars($myProfile['dept_id']); ?></p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg border border-gray-100">
                             <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Employee ID</label>
                                <div class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($myProfile['ac_no']); ?></div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Date Hired</label>
                                <div class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($myProfile['date_hired'] ?? '-'); ?></div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Work Schedule</label>
                                <div class="text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($myProfile['work_schedule']); ?></div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Status</label>
                                <div class="text-sm font-semibold text-green-600"><?php echo htmlspecialchars($myProfile['employment_status']); ?></div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4">Contact Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Phone Number</label>
                                    <input type="text" name="contact_number" value="<?php echo htmlspecialchars($myProfile['contact_number']); ?>" class="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Email Address</label>
                                    <input type="email" name="email_address" value="<?php echo htmlspecialchars($myProfile['email_address']); ?>" class="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Home Address</label>
                                    <input type="text" name="address" value="<?php echo htmlspecialchars($myProfile['address']); ?>" class="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4">Emergency Contact</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Name</label>
                                    <input type="text" name="emergency_name" value="<?php echo htmlspecialchars($myProfile['emergency_contact_name']); ?>" class="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Phone</label>
                                    <input type="text" name="emergency_number" value="<?php echo htmlspecialchars($myProfile['emergency_contact_number']); ?>" class="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Relationship</label>
                                    <input type="text" name="emergency_rel" value="<?php echo htmlspecialchars($myProfile['emergency_contact_relationship']); ?>" class="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" onclick="closeMyProfile()" class="px-5 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-md transition">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 text-sm font-bold text-primary-foreground bg-primary hover:bg-primary-600 rounded-md shadow-sm transition">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if($is_hr): ?>
    <div id="holidayModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-96 shadow-xl animate-in fade-in zoom-in duration-200">
            <h3 class="font-bold text-lg mb-4 text-gray-800">Manage Holiday</h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_holiday">
                <input type="hidden" name="holiday_date" id="modalDate">
                
                <div class="mb-3">
                    <label class="block text-xs font-bold text-gray-500 mb-1">Date</label>
                    <div id="displayDate" class="font-mono text-sm bg-gray-50 p-2 rounded border border-gray-200 text-gray-700"></div>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-bold text-gray-500 mb-1">Holiday Type</label>
                    <select name="holiday_type" id="modalType" class="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                        <option value="REGULAR">Regular Holiday (200%)</option>
                        <option value="SPECIAL">Special Non-Working (130%)</option>
                        <option value="DOUBLE">Double Holiday (300%)</option>
                        <option value="DELETE" class="text-red-600 font-bold">-- Remove Holiday --</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 mb-1">Holiday Name</label>
                    <input type="text" name="holiday_name" id="modalName" class="w-full border border-gray-300 rounded-md p-2 text-sm focus:ring-2 focus:ring-primary-500 outline-none" placeholder="e.g. Eid'l Fitr">
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeHolidayModal()" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-primary text-primary-foreground font-bold rounded-md hover:bg-primary-600">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openHolidayModal(date, type, name) {
            document.getElementById('holidayModal').classList.remove('hidden');
            document.getElementById('modalDate').value = date;
            document.getElementById('displayDate').textContent = date;
            
            if(type) document.getElementById('modalType').value = type;
            else document.getElementById('modalType').value = 'REGULAR';
            
            document.getElementById('modalName').value = name;
        }

        function closeHolidayModal() {
            document.getElementById('holidayModal').classList.add('hidden');
        }
    </script>
    <?php endif; ?>

    <script>
        function toggleDropdown() {
            document.getElementById('navDropdown').classList.toggle('show');
        }
        
        function toggleComments(postId) {
            const el = document.getElementById('comments-' + postId);
            el.classList.toggle('hidden');
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreviewContainer').classList.remove('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function previewProfile(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function clearImage() {
            document.getElementById('postImageInput').value = "";
            document.getElementById('imagePreviewContainer').classList.add('hidden');
            document.getElementById('imagePreview').src = "";
        }

        function openMyProfile() {
            document.getElementById('myProfileModal').classList.remove('hidden');
        }

        function closeMyProfile() {
            document.getElementById('myProfileModal').classList.add('hidden');
        }

        window.onclick = function(event) {
            if (!event.target.closest('.relative')) {
                document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.remove('show'));
            }
            if(event.target == document.getElementById('holidayModal')) {
                closeHolidayModal();
            }
            if(event.target == document.getElementById('myProfileModal')) {
                closeMyProfile();
            }
        }
    </script>
</body>
</html>