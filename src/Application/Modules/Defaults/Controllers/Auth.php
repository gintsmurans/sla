<?php

namespace Defaults\Controllers;

use \Core\Controllers\Controller;
use \Core\Models\Timers;
use \Core\Models\Config;
use \Core\Models\Router;

use \Defaults\Models\Auth as AuthModel;

use \TwitchApi\HelixGuzzleClient;
use \TwitchApi\TwitchApi;

class Auth extends Controller
{
    public static function __callStatic($name, $arguments)
    {
        Timers::markTime('Before views');

        self::render('login.html');
    }

    public static function redirect()
    {
        $helixGuzzleClient = new HelixGuzzleClient(Config::$items['services']['twitch']['client_id']);
        $twitchApi = new TwitchApi(
            $helixGuzzleClient,
            Config::$items['services']['twitch']['client_id'],
            Config::$items['services']['twitch']['secret']
        );
        $oauth = $twitchApi->getOauthApi();
        $callbackUri = Router::siteUrl('defaults/auth/callback');

        Timers::startTimer();
        $oauthUri = $oauth->getAuthUrl($callbackUri, 'code', Config::$items['services']['twitch']['scopes']);
        Timers::stopTimer('Get twitch auth url');
        Timers::logTimers();

        Router::redirect($oauthUri, false);
    }

    public static function callback()
    {
        if (empty($_GET['code'])) {
            AuthModel::redirectToAuth();
            return;
        }

        $helixGuzzleClient = new HelixGuzzleClient(Config::$items['services']['twitch']['client_id']);
        $twitchApi = new TwitchApi(
            $helixGuzzleClient,
            Config::$items['services']['twitch']['client_id'],
            Config::$items['services']['twitch']['secret']
        );
        $oauth = $twitchApi->getOauthApi();
        $code = $_GET['code'];
        $callbackUri = Router::siteUrl('defaults/auth/callback');

        Timers::startTimer();
        try {
            $token = $oauth->getUserAccessToken($code, $callbackUri);
            if ($token->getStatusCode() == 200) {
                // Below is the returned token data
                $data = json_decode($token->getBody()->getContents());

                // Your bearer token
                $twitch_access_token = $data->access_token ?? null;

                print_r($data);
            } else {
                // TODO: Handle Error
                echo 'Error getting data from Twitch';
            }
        } catch (\Exception $e) {
            // TODO: Handle Error
            echo 'Error getting data from Twitch';
            sp_exception_handler($e);
        }
        Timers::stopTimer('Get twitch user data');
        Timers::logTimers();
    }
}
