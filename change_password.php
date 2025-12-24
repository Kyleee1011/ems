<?php
require_once 'config.php';

use App\Utils\AppHelpers;
use App\Services\FileUploadService;

$message = '';
$msgType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ac_no = trim($_POST['ac_no']);
    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($ac_no) || empty($old_password) || empty($new_password)) {
        $message = "All fields are required.";
        $msgType = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $msgType = "error";
    } else {
        try {
            $conn = $pdo;
            
            // Verify User
            $stmt = $conn->prepare("SELECT emp_id, ac_no, password_hash FROM Employees WHERE ac_no = :ac_no");
            $stmt->bindParam(':ac_no', $ac_no);
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user) {
                // Verify Old Password (Hash or Default)
                $verified = false;
                if (!empty($user['password_hash'])) {
                    if (password_verify($old_password, $user['password_hash'])) $verified = true;
                } else {
                    if ($old_password === $user['ac_no']) $verified = true;
                }

                if ($verified) {
                    // Update to New Password
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE Employees SET password_hash = :hash, updated_at = GETDATE() WHERE emp_id = :id");
                    $update->execute([':hash' => $new_hash, ':id' => $user['emp_id']]);
                    
                    header("Location: login.php?changed=1");
                    exit;
                } else {
                    $message = "Incorrect old password.";
                    $msgType = "error";
                }
            } else {
                $message = "Account not found.";
                $msgType = "error";
            }
        } catch(PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $msgType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | EMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-100 h-screen flex items-center justify-center">

    <div class="max-w-md w-full bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="bg-slate-800 p-6 text-center">
            <h1 class="text-xl font-bold text-white">Change Password</h1>
            <p class="text-slate-300 text-xs mt-1">Set a new secure password for your account</p>
        </div>
        
        <div class="p-8">
            <?php if($message): ?>
                <div class="<?php echo $msgType == 'success' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?> text-sm p-3 rounded-lg mb-4 border <?php echo $msgType == 'success' ? 'border-green-100' : 'border-red-100'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">AC Number</label>
                    <input type="text" name="ac_no" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Old Password</label>
                    <input type="password" name="old_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Default is AC No." required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" name="new_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                </div>

                <div class="flex gap-3 pt-2">
                    <a href="login.php" class="w-1/3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 rounded-lg transition text-center">Cancel</a>
                    <button type="submit" class="w-2/3 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition shadow-sm">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>