<?php

use \Core\Models\Config;
use \Core\Models\Logger;
use \Core\Models\Router;
use \Core\Models\RouterException;
use \Core\Models\SpErrorException;

/**
 * StaticPHP's error handler. Turns errors into exceptions and passes on to sp_exception_handler().
 *
 * Stops on @ suppressed errors.
 *
 * @see sp_exception_handler()
 * @access public
 * @param mixed $errno
 * @param mixed $errstr
 * @param mixed $errfile
 * @param mixed $errline
 * @return bool Returns whether the error was handled or not.
 */
function sp_error_handler($errno, $errstr, $errfile, $errline)
{
    // @ used
    if (error_reporting() === 0) {
        return false;
    }

    // Throw all the errors as exceptions, so they can be handled as they should
    throw new SpErrorException($errstr, 0, $errno, $errfile, $errline);
}

/**
 * StaticPHP's exception handler.
 *
 * If debug mode is on, sends formatted error to browser, otherwise sends error email, if debug email is provided in <i>Config/Config.php</i> file.
 *
 * @access public
 * @param Exception|ErrorException|mixed $exception
 * @return void
 */
function sp_exception_handler($exception)
{
    // RouterException is a special case
    if ($exception instanceof RouterException) {
        if (!empty(Config::$items['debug'])) {
            Router::error('500', 'Internal Server Error', $exception->getMessage());
        } else {
            Router::error('404', 'Not Found');
        }
    }

    if (function_exists('http_response_code') && headers_sent() === false) {
        http_response_code(500);
    }

    $errorMsgPlain = sp_format_exception($exception, false, false);
    $errorMsgRich = sp_format_exception($exception, false, true);
    Logger::log('error', $errorMsgPlain, $exception->getTrace(), $errorMsgRich);

    exit(10);
}

/**
 * Sends error messages.
 *
 * @see sp_format_exception()
 * @access public
 * @param String $e
 * @return void
 */
function sp_send_error_email($message)
{
    static $last_error = ['time' => 0];

    $debug_email = Config::$items['logging']['report_email'];
    $email_func = Config::$items['logging']['report_email_func'];
    if (!empty($debug_email) && is_callable($email_func) && (time() - $last_error['time'] >= 30 || $last_error['exception'] != $message)) {
        $email_func(
            $debug_email, // To
            'PHP ERROR: "'.$_SERVER['HTTP_HOST'].'"', // Subject
            $message, // Message
            "Content-Type: text/html; charset=utf-8", // Headers
            'error'
        );
        $last_error['time'] = time();
        $last_error['exception'] = $message;
    }
}

/**
 * Format exception and add session, server and post information for easier debugging.
 *
 * If $full is set to false, only string containing formatted message is returned.
 *
 * @access public
 * @param Exception|ErrorException|mixed $e
 * @param bool $full (default: false)
 * @return string Returns formatted string of the $e exception
 */
function sp_format_exception($e, $full = false, $markup = true)
{
    // Current time
    $datetime = date('d.m.Y H:i:s');

    // Current url
    $url  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://');
    $url .= (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '[unknown host name]');
    $url .= (!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '[unknown url]');

    // Message
    $message = '';
    if ($markup === true) {
        $message  = str_replace("\n", "<br />", $e->getMessage());
        $message .= '<br /><br /><strong>Trace:</strong><br /><table border="0" cellspacing="0" cellpadding="5" style="border: 1px #DADADA solid;"><tr><td style="border-bottom: 1px #DADADA solid;">';
        $message .= str_replace("\n", '</td></tr><tr><td style="border-bottom: 1px #DADADA solid;">', $e->getTraceAsString()).'</td></tr></table>';
    } else {
        $message = $e->getMessage();
        $message .= "\n\nTrace:";
        $message .= $e->getTraceAsString();
    }

    // Session
    $session = [];
    if (is_callable('formatSession')) {
        $session = formatSession();
    } elseif (isset($_SESSION)) {
        $session = $_SESSION;
    }
    $session = json_encode($session, (defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : null));

    // Server
    $server = json_encode($_SERVER, (defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : null));

    // Post
    $post = !empty($_POST) ? json_encode($_POST, (defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : null)) : '{}';

    // Format message
    if ($markup === true) {
        $session = str_replace(array(" ", "\n"), array('&nbsp;', '<br />'), $session);
        $server = str_replace(array(" ", "\n"), array('&nbsp;', '<br />'), $server);
        $post = str_replace(array(" ", "\n"), array('&nbsp;', '<br />'), $post);
    }

    $msg = '';
    if ($full === true) {
        if ($markup === true) {
            $msg = "<pre><strong>META</strong><br />{$datetime}<br /><br /><strong>URL</strong><br />{$url}<br /><br /><strong>Error</strong><br />{$message}<br /><br /><strong>Sesssion Info</strong><br />{$session}<br /><br /><strong>Post Info</strong><br />{$post}<br /><br /><strong>Server</strong><br />{$server}</pre>";
        } else {
            $msg = "META\n{$datetime}\n\nURL\n{$url}\n\nError\n{$message}\n\nSesssion Info\n{$session}\n\nPost Info\n{$post}\n\nServer\n{$server}";
        }
    } else {
        if ($markup === true) {
            $msg = "<pre><strong>Error:</strong><br />{$message}<br /></pre>";
        } else {
            $msg = "Error:\n{$message}\n";
        }
    }

    return $msg;
}
