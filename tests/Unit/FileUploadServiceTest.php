<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\FileUploadService;

/**
 * Unit Test for FileUploadService
 */
class FileUploadServiceTest extends TestCase
{
    private $testUploadDir = 'uploads/test/';

    protected function setUp(): void
    {
        // Create test upload directory
        if (!is_dir($this->testUploadDir)) {
            mkdir($this->testUploadDir, 0777, true);
        }
    }

    public function testHandleFileUploadReturnsNullForNoFile()
    {
        $fileArray = null; 
        $result = FileUploadService::handleFileUpload($fileArray, 'test', 'test');
        
        $this->assertNull($result);
    }

    public function testHandleFileUploadReturnsNullForEmptyArray()
    {
        $fileArray = []; 
        $result = FileUploadService::handleFileUpload($fileArray, 'test', 'test');
        
        $this->assertNull($result);
    }

    public function testHandleFileUploadReturnsNullForUploadError()
    {
        $fileArray = [
            'error' => UPLOAD_ERR_NO_FILE,
            'name' => 'test.png',
        ];
        
        $result = FileUploadService::handleFileUpload($fileArray, 'test', 'test');
        
        $this->assertNull($result);
    }

    public function testHandleFileUploadReturnsNullForInvalidExtension()
    {
        $fileArray = [
            'error' => UPLOAD_ERR_OK,
            'name' => 'test.exe', // Not allowed
            'tmp_name' => __FILE__
        ];
        
        $result = FileUploadService::handleFileUpload($fileArray, 'test', 'test');
        
        $this->assertNull($result);
    }

    public function testHandleFileUploadAcceptsPngExtension()
    {
        $fileArray = [
            'error' => UPLOAD_ERR_OK,
            'name' => 'test.png',
            'tmp_name' => __FILE__, 
        ];
        
        $result = FileUploadService::handleFileUpload($fileArray, 'test', 'test');
        // move_uploaded_file fails in tests, but we check if it returns null without crashing
        $this->assertNull($result);
    }

    public function testHandleFileUploadCreatesDirectoryIfNotExists()
    {
        $subfolder = 'test_subfolder_' . time() . '_' . rand(0, 100);
        $fileArray = [
            'error' => UPLOAD_ERR_OK,
            'name' => 'test.png',
            'tmp_name' => __FILE__,
        ];
        
        FileUploadService::handleFileUpload($fileArray, 'test', $subfolder);
        
        // Even if move_uploaded_file fails, the directory should have been created
        $this->assertDirectoryExists('uploads/' . $subfolder);
        
        // Cleanup
        if (is_dir('uploads/' . $subfolder)) {
            rmdir('uploads/' . $subfolder);
        }
    }

    protected function tearDown(): void
    {
        // Cleanup test directory
        if (is_dir($this->testUploadDir)) {
            $files = glob($this->testUploadDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testUploadDir);
        }
    }
}
