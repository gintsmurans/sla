<?php

namespace Defaults\Controllers;

use \Core\Controllers\Controller;
use \Core\Models\Timers;
use \Core\Models\Config;
use \Core\Models\Router;
use \Core\Models\Db;

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

    public static function logout()
    {
        AuthModel::logout();
        Router::redirect();
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
                $data = json_decode($token->getBody()->getContents(), true);

                // Your bearer token
                $twitch_access_token = $data['access_token'] ?? '';
                $twitch_refresh_token = $data['refresh_token'] ?? '';

                // Make the API call. A ResponseInterface object is returned.
                $response = $twitchApi->getUsersApi()->getUserByAccessToken($twitch_access_token);

                // Get and decode the actual content sent by Twitch.
                $responseContent = json_decode($response->getBody()->getContents(), true);
                if (empty($responseContent['data'][0])) {
                    throw new \Exception('There was no user data in Twitch response');
                }
                $twitchUserData = $responseContent['data'][0];

                // Find out if we have the user
                $user = AuthModel::userById(refUserId: $twitchUserData['id']);
                if (!empty($user)) {
                    $userData = [
                        'ref_access_token' => $twitch_access_token,
                        'ref_refresh_token' => $twitch_refresh_token,
                    ];
                    Db::update('users', $userData, ['id' => $user['id']]);
                } else {
                    // If not, lets add new one into db
                    $userData = [
                        'ref_id' => $twitchUserData['id'],
                        'ref_username' => $twitchUserData['login'],
                        'email' => $twitchUserData['email'],
                        'profile_image_url' => $twitchUserData['profile_image_url'],
                        'ref_access_token' => $twitch_access_token,
                        'ref_refresh_token' => $twitch_access_token,
                    ];
                    $user = Db::insert('users', $userData, returning: 'RETURNING id');
                }

                AuthModel::loadUserSession($user['id']);
                Router::redirect('defaults/welcome/refresh');
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
