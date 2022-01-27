<?php

namespace Defaults\Controllers;

use \Core\Controllers\Controller;
use \Core\Models\Router;
use \Core\Models\Timers;
use \Core\Models\Db;

use \Defaults\Models\Auth;
use \Defaults\Models\Twitch;

/**
 * Welcome page controller.
 */

class Welcome extends Controller
{
    public static function construct($class = null, $method = null)
    {
        // Check if user is authenticated
        Auth::checkAuth(true);
    }

    public static function index($param1 = null, $param2 = null)
    {
        // Do something heavy and add timer mark
        Timers::markTime('Before views');

        $viewData = [];

        // Top streams
        $viewData['top_streams'] = Db::fetchAll(
            "
                SELECT game_name, COUNT(id) AS count
                FROM top_streams
                GROUP BY game_name
                ORDER BY count DESC
            "
        );

        // Top games
        $viewData['top_games'] = Db::fetchAll(
            "
                SELECT game_name, SUM(viewer_count) AS viewer_count
                FROM top_streams
                GROUP BY game_name
                ORDER BY SUM(viewer_count) DESC
            "
        );

        // Median
        $stream = $viewData['top_games'][ceil(count($viewData['top_games']) / 2)];
        $viewData['median_number'] = $stream['viewer_count'];

        // Top 100 streams
        $viewData['top_100_streams'] = Db::fetchAll(
            "
                SELECT channel_name, title, viewer_count
                FROM top_streams
                ORDER BY viewer_count DESC
                LIMIT 100
            "
        );

        // Streams by start time
        $viewData['streams_start_time'] = Db::fetchAll(
            "
                SELECT
                    date_trunc('hour', started_at) AS start_time,
                    COUNT(id) AS count
                FROM top_streams
                GROUP BY start_time
                ORDER BY start_time DESC
            "
        );

        // Following streams
        $tmp = Db::fetchAll(
            "
                SELECT stream_id
                FROM followed_streams
                WHERE user_id = ?
            ",
            [$_SESSION['user']['id']]
        );
        $viewData['following_streams'] = [];
        if (!empty($tmp)) {
            $ids = array_column($tmp, 'stream_id');
            $ids = implode(',', $ids);
            $viewData['following_streams'] = Db::fetchAll(
                "
                    SELECT channel_name, title, viewer_count
                    FROM top_streams
                    WHERE stream_id IN ({$ids})
                    ORDER BY viewer_count DESC
                "
            );
        }

        // Lowest viewer
        $allStreams = Db::fetchAll(
            "
                SELECT viewer_count, tags
                FROM top_streams
                ORDER BY viewer_count ASC
            "
        );
        $lowestStream = reset($allStreams);
        $allFollowedStream = Db::fetchAll(
            "
                SELECT viewer_count, tags
                FROM followed_streams
                WHERE user_id = ?
                ORDER BY viewer_count ASC
            ",
            [$_SESSION['user']['id']]
        );
        $lowestFollowedStream = reset($allFollowedStream);
        $viewData['lowest_streamer_needs'] = 0;
        if (!empty($lowestFollowedStream)) {
            $viewData['lowest_streamer_needs'] = $lowestStream['viewer_count'] - $lowestFollowedStream['viewer_count'];
        }

        // Tags
        $allTags = Twitch::getAllTags(true);
        $allStreamerTags = [];
        foreach ($allStreams as $stream) {
            if (!empty($stream['tags'])) {
                $stream['tags'] = json_decode($stream['tags'], true);
                $allStreamerTags += $stream['tags'];
            }
        }
        $followedTags = [];
        foreach ($allFollowedStream as $stream) {
            if (!empty($stream['tags'])) {
                $stream['tags'] = json_decode($stream['tags'], true);
                $followedTags += $stream['tags'];
            }
        }
        $viewData['all_tags'] = $allTags;
        // $viewData['shared_tags'] = array_intersect($allStreamerTags, $followedTags);
        $viewData['shared_tags'] = $allStreamerTags;

        // Load view
        self::render('index.html', $viewData);
    }

    public static function refresh()
    {
        // TODO: Check timestamp
        $now = time();
        if (!empty($_SESSION['last_data_refresh']) && $now - $_SESSION['last_data_refresh'] < 300) {
            Router::redirect();
        }

        // Refresh data
        Timers::startTimer();
        Twitch::refreshToken($_SESSION['user']['id']);
        Timers::stopTimer('Refresh access token');

        Timers::startTimer();
        Twitch::refreshFollowedStreams($_SESSION['user']['id']);
        Timers::stopTimer('Followed Streams');

        Timers::startTimer();
        Twitch::syncTags();
        Timers::stopTimer('Sync missing tags');

        // Log timers
        Timers::logTimers();

        // Add timestamp and move back
        $_SESSION['last_data_refresh'] = $now;
        Router::redirect();
    }
}
