<?php

class ErrorHandler
{

    private static $_types = [
        0                   =>  'Exception',
        E_ERROR			    =>	'Error',
        E_WARNING		    =>	'Warning',
        E_PARSE			    =>	'Parsing Error',
        E_NOTICE		    =>	'Notice',
        E_STRICT		    =>	'Runtime Notice',
        E_DEPRECATED		=>	'Deprecated',
        E_CORE_ERROR		=>	'Core Error',
        E_CORE_WARNING		=>	'Core Warning',
        E_COMPILE_ERROR		=>	'Compile Error',
        E_COMPILE_WARNING	=>	'Compile Warning',
        E_USER_ERROR		=>	'User Error',
        E_USER_WARNING		=>	'User Warning',
        E_USER_NOTICE		=>	'User Notice',
    ];

    protected static $_debug;

    public function __construct($debug = false)
    {
        self::$_debug = $debug;
    }

    public function setHandlers()
    {
        ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
        ini_set('display_errors', 0);

        if (self::$_debug) {
            ini_set('error_reporting', -1);
            ini_set('display_errors', 1);
        }

        set_error_handler([$this, 'errorHandler']);
        set_exception_handler([$this, 'exceptionHandler']);
        register_shutdown_function([$this, 'shutdownHandler']);

        ob_start();
    }

    public function shutdownHandler() {
        $error = error_get_last();
        if (isset($error) && $error['type'] === ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING))) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            $this->errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
        } else {
            ob_end_flush();
        }
    }

    public function errorHandler($errorCode, $errorMessage, $errorFile, $errorLine)
    {
        self::logMessage($errorMessage, $errorCode, $errorFile, $errorLine);

        if (self::$_debug || $errorCode === ($errorCode & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR))) {
            self::showMessage($errorMessage, $errorCode, $errorFile, $errorLine);
            exit(1);
        }
    }

    /**
     * @param $e Throwable
     */
    public function exceptionHandler($e)
    {
        self::logMessage($e->getMessage(), $e->getCode(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        self::showMessage($e->getMessage(), $e->getCode(), $e->getFile(), $e->getLine());
    }

    public static function showMessage($error, $code, $file = '', $line = 0)
    {
        $type = isset(self::$_types[$code]) ? self::$_types[$code] : $code;

        if (PHP_SAPI === 'cli') {

            $errorText = "[$type] $error in file $file #$line\n";

        } else {

            if (self::$_debug) {
                $errorText = "<p>[$type] <b>$error</b><br/>$file #$line</p>";
            } else {
                $errorText = "<b>$type:</b> $error<br>";
            }

        }

        print $errorText;
    }

    public static function logMessage($error, $code, $file = '', $line = 0, $backtrace = null)
    {
        $type = isset(self::$_types[$code]) ? self::$_types[$code] : $code;

        $message = $type . ': "' . $error . '"';
        if (!empty($file) && !empty($line)) {
            $message .= ' in file ' . $file . ' #' . $line;
        }
        error_log($message);

        if (self::$_debug) {
            if (!isset($backtrace)) {
                ob_start();
                debug_print_backtrace();
                $backtrace = ob_get_clean();
            }

            self::logTrace($backtrace);
        }
    }

    protected static function logTrace($backtrace)
    {
        error_log('Backtrace:');

        $content = print_r($backtrace,true);
        $content = trim($content, "\n");

        $arrayOfString = explode("\n", $content);
        foreach ($arrayOfString as $currentString) {
            error_log($currentString);
        }
        error_log('----------------------------------');
    }

} 
