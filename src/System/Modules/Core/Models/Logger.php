<?php

namespace Core\Models;

use \Core\Models\Timers;

/**
 * Core logger class.
 */
class Logger
{
    const errorLevels = [
        'none' => 1000,
        'emergency' => 800,
        'alert' => 700,
        'critical' => 600,
        'error' => 500,
        'warning' => 400,
        'notice' => 300,
        'info' => 200,
        'debug' => 100,
    ];

    const NONE = 'none';
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    /**
     * Array for log entries.
     *
     * (default value: [])
     *
     * @var array
     * @access protected
     * @static
     */
    protected static $logs = [];

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Helpers
    |-------------------------------------------------------------------------------------------------------------------
    */

    /**
     * Compare string error levels
     *
     * @param  string $errorLevel1 Set error level
     * @param  string $errorLevel2 Error level to compare to
     * @return bool
     */
    public static function contains(string $errorLevel1, string $errorLevel2): bool
    {
        if (empty(self::errorLevels[$errorLevel1])) {
            return null;
        }
        $errorLevelInt1 = self::errorLevels[$errorLevel1];

        if (empty(self::errorLevels[$errorLevel2])) {
            return null;
        }
        $errorLevelInt2 = self::errorLevels[$errorLevel2];

        return ($errorLevelInt1 <= $errorLevelInt2);
    }

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Logger methods
    |-------------------------------------------------------------------------------------------------------------------
    */

    /**
     * System is unusable.
     *
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    public static function emergency(string $message, array $context = array(), bool $skipOutput = false)
    {
        self::log(Logger::EMERGENCY, $message, $context, skipOutput: $skipOutput);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    public static function alert(string $message, array $context = array(), bool $skipOutput = false)
    {
        self::log(Logger::ALERT, $message, $context, skipOutput: $skipOutput);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    public static function critical(string $message, array $context = array(), bool $skipOutput = false)
    {
        self::log(Logger::CRITICAL, $message, $context, skipOutput: $skipOutput);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    public static function error(string $message, array $context = array(), bool $skipOutput = false)
    {
        self::log(Logger::ERROR, $message, $context, skipOutput: $skipOutput);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    public static function warning(string $message, array $context = array(), bool $skipOutput = false)
    {
        self::log(Logger::WARNING, $message, $context, skipOutput: $skipOutput);
    }

    /**
     * Normal but significant events.
     *
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    public static function notice(string $message, array $context = array(), bool $skipOutput = false)
    {
        self::log(Logger::NOTICE, $message, $context, skipOutput: $skipOutput);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    public static function info(string $message, array $context = array(), bool $skipOutput = false)
    {
        self::log(Logger::INFO, $message, $context, skipOutput: $skipOutput);
    }

    /**
     * Detailed debug information.
     *
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    public static function debug(string $message, array $context = array(), bool $skipOutput = false)
    {
        self::log(Logger::DEBUG, $message, $context, skipOutput: $skipOutput);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function log(
        string $level,
        string $message,
        array $context = array(),
        string $formattedMessage = null,
        bool $skipOutput = false
    ) {
        self::$logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
        if ($skipOutput == true) {
            return;
        }

        // Output/Log/Send error message
        $formattedLevel = strtoupper($level);
        if (Logger::contains(Config::$items['logging']['display_level'], $level)) {
            if (!empty($formattedMessage)) {
                echo "{$formattedLevel}: {$formattedMessage}";
            } else {
                echo "{$formattedLevel}: {$message}";
            }
        }

        if (Logger::contains(Config::$items['logging']['log_level'], $level)) {
            error_log($message);
        }

        if (Logger::contains(Config::$items['logging']['report_level'], $level)) {
            sp_send_error_email(!empty($formattedMessage) ? $formattedMessage : $message);
        }
    }

    /*
    |-------------------------------------------------------------------------------------------------------------------
    | Debug Output
    |-------------------------------------------------------------------------------------------------------------------
    */

    /**
     * Generate debug output.
     *
     * @see Load::emergency()
     * @see Load::alert()
     * @see Load::critical()
     * @see Load::error()
     * @see Load::warning()
     * @see Load::notice()
     * @see Load::info()
     * @access public
     * @static
     * @return string Returns formatted html string of debug information, including timers,
     *          but also custom messages logged using logger interface.
     */
    public static function debugOutput()
    {
        // Log execution time
        Timers::logTimers();

        // Generate debug output
        $output = '';
        foreach (self::$logs as $item) {
            $class = '';
            switch ($item['level']) {
                case Logger::EMERGENCY:
                case Logger::ALERT:
                case Logger::CRITICAL:
                    $class = 'danger';
                    break;

                case Logger::ERROR:
                case Logger::WARNING:
                    $class = 'warning';
                    break;

                case Logger::NOTICE:
                case Logger::INFO:
                case Logger::DEBUG:
                    $class = 'info';
                    break;
            }

            $output .= '<span class="text-'.$class.'">'.strtoupper($item['level']).': </span>';
            $output .= $item['message'];
            $output .= (!empty($item['context']) ? " [".implode(',', $item['context'])."]\n" : "\n");
        }

        // Return it
        return $output;
    }
}
