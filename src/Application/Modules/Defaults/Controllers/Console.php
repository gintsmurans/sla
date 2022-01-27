<?php

namespace Defaults\Controllers;

use \Core\Controllers\Controller;
use \Core\Models\Router;
use \Core\Models\Timers;

use \Defaults\Models\Twitch;

/**
 * Welcome page controller.
 */

class Console extends Controller
{
    public static function construct($class = null, $method = null)
    {
        // Check if user is authenticated
        if (php_sapi_name() !== 'cli') {
            Router::redirect();
        }
    }

    public static function refresh()
    {
        Timers::startTimer();
        Twitch::refreshTopStreams();
        Timers::stopTimer('Top Streams');

        Timers::startTimer();
        Twitch::syncTags();
        Timers::stopTimer('Sync missing tags');

        // Log timers
        Timers::logTimers();
    }
}
