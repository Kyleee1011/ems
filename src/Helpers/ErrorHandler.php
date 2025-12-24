<?php
namespace App\Helpers;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Throwable;

class ErrorHandler
{
    private $logger;

    public function __construct()
    {
        // Initialize Monolog
        $this->logger = new Logger('ems');
        $this->logger->pushHandler(new RotatingFileHandler(__DIR__ . '/../../logs/app.log', 30, Logger::DEBUG));
    }

    public function register()
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleException(Throwable $exception)
    {
        $this->logger->error('Uncaught Exception: ' . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->renderError($exception);
    }

    public function handleError($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $this->logger->error("Error ($errno): $errstr", ['file' => $errfile, 'line' => $errline]);
        return true; 
    }

    public function handleShutdown()
    {
        $error = error_get_last();
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
             $this->logger->critical("Fatal Error: " . $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
             if (!headers_sent()) {
                 http_response_code(500);
                 echo "<h1>System Error</h1><p>A fatal error occurred. Please contact support.</p>";
             }
        }
    }

    private function renderError(Throwable $e)
    {
        if (php_sapi_name() === 'cli') {
            echo "Error: " . $e->getMessage() . PHP_EOL;
            return;
        }

        $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) || 
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if ($isApi) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Internal Server Error',
                'message' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'An error occurred'
            ]);
        } else {
            http_response_code(500);
            $msg = htmlspecialchars($e->getMessage());
            echo <<<HTML
<!DOCTYPE html>
<html>
<head><title>Error</title><link href="https://cdn.tailwindcss.com" rel="stylesheet"></head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-lg w-full text-center">
        <div class="text-red-500 text-5xl mb-4"><i class="fa fa-exclamation-triangle"></i></div>
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Something went wrong</h1>
        <p class="text-gray-600 mb-4">We logged the error and will check it.</p>
        <div class="bg-gray-50 p-3 rounded text-left text-xs font-mono text-red-600 overflow-auto max-h-32">
            $msg
        </div>
        <a href="/ems/dashboard" class="mt-6 inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Back to Dashboard</a>
    </div>
</body>
</html>
HTML;
        }
        exit;
    }
}
