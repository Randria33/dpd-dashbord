<?php
namespace Utils;

class Logger {
    private $logFile;
    private $logLevel;
    
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    
    public function __construct($logFile = null) {
        $this->logFile = $logFile ?: __DIR__ . '/../logs/app_' . date('Y-m-d') . '.log';
        $this->logLevel = self::LEVEL_INFO;
        
        // Créer le dossier logs s'il n'existe pas
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
    
    private function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function cleanOldLogs($days = 30) {
        $logDir = dirname($this->logFile);
        $files = glob($logDir . '/app_*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < strtotime("-$days days")) {
                unlink($file);
            }
        }
    }
}