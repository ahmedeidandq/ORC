<?php

class Logger
{
    private static $logFile = '/tmp/orc/orc.log';

    public static function log($message)
    {
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents(self::$logFile, $line, FILE_APPEND);
    }
}
