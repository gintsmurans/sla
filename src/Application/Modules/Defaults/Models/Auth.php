<?php

namespace Defaults\Models;

use \Core\Models\Router;
use \Core\Models\Db;

class Auth
{
    public static function checkAuth(bool $redirect = false): bool
    {
        if (empty($_SESSION['user']) || empty($_SESSION['user_timestamp'])) {
            if ($redirect === true) {
                self::redirectToAuth();
            }
            return false;
        }

        $now = time();
        if ($now - $_SESSION['user_timestamp'] > 3600) {
            if ($redirect === true) {
                self::redirectToAuth();
            }
            return false;
        }

        $_SESSION['user_timestamp'] = $now;
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['user_timestamp']);
    }

    public static function redirectToAuth(): void
    {
        Router::redirect('defaults/auth');
    }

    public static function userById(int $userId = null, int $refUserId = null): mixed
    {
        $user = null;
        if (!empty($userId)) {
            $user = Db::fetch('SELECT * FROM users WHERE id = ?', [(int)$userId]);
        } elseif (!empty($refUserId)) {
            $user = Db::fetch('SELECT * FROM users WHERE ref_id = ?', [(int)$refUserId]);
        }

        return empty($user) ? false : $user;
    }

    public static function loadUserSession(int $userId = null, int $refUserId = null): bool
    {
        $user = self::userById($userId, $refUserId);
        if (!empty($user)) {
            $_SESSION['user'] = $user;
            $_SESSION['user_timestamp'] = time();

            return true;
        }

        return false;
    }
}
