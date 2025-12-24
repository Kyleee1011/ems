<?php
namespace App\Services;

class FileUploadService
{
    public static function handleFileUpload($fileArray, $prefix, $subfolder, $exactName = false) {
        if (isset($fileArray['error']) && $fileArray['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/' . $subfolder . '/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
            
            $fileExt = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'pdf', 'docx']; // Adjust allowed types
            
            if (in_array($fileExt, $allowed)) {
                $newFileName = $exactName ? ($prefix . '.png') : ($prefix . '_' . time() . '_' . rand(100,999) . '.' . $fileExt);
                $destPath = $uploadDir . $newFileName;
                
                if ($exactName && file_exists($destPath)) { unlink($destPath); }
                if (move_uploaded_file($fileArray['tmp_name'], $destPath)) { return $destPath; }
            }
        }
        return null;
    }
}
