<?php

namespace Defaults\Models;

use \Core\Models\Router;

class Auth
{
    public static function checkAuth($redirect = false)
    {
        if (empty($_SESSION['user']) || empty($_SESSION['user_timestamp'])) {
            if ($redirect === true) {
                self::redirectToAuth();
            }
            return false;
        }

        if (time() - $_SESSION['user_timestamp'] > 3600) {
            if ($redirect === true) {
                self::redirectToAuth();
            }
            return false;
        }

        return true;
    }

    public static function redirectToAuth()
    {
        Router::redirect('defaults/auth');
    }
}
