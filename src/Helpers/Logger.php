<?php
namespace App\Helpers;

class Logger
{
    private $logFile;

    public function __construct($logFile = __DIR__ . '/../../logs/app.log')
    {
        $this->logFile = $logFile;
        // Ensure log directory exists
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public function log($level, $message, $context = [])
    {
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $formatted = "[$date] [$level] $message $contextStr" . PHP_EOL;
        
        // Append to file
        file_put_contents($this->logFile, $formatted, FILE_APPEND);
    }

    public function info($message, $context = [])
    {
        $this->log('INFO', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->log('WARNING', $message, $context);
    }
    
    public function debug($message, $context = [])
    {
        $this->log('DEBUG', $message, $context);
    }
}
