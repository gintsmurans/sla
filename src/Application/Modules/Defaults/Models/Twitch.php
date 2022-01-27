<?php

namespace Defaults\Models;

use \Core\Models\Config;
use \Core\Models\Db;
use \Core\Models\Logger;
use \Core\Models\Timers;

use \TwitchApi\HelixGuzzleClient;
use \TwitchApi\TwitchApi;

class Twitch
{
    public static function getAllTags($returnKeyed = false): array
    {
        $tmp = Db::fetchAll(
            "
                SELECT tag_id, title, description
                FROM tags
                ORDER BY tag_id ASC
            "
        );

        if (empty($tmp)) {
            return [];
        }

        if ($returnKeyed === false) {
            return $tmp;
        }

        $newData = [];
        foreach ($tmp as $tagItem) {
            $newData[$tagItem['tag_id']] = $tagItem;
        }

        return $newData;
    }

    public static function getAppToken(): ?string
    {
        // Init client
        $helixGuzzleClient = new HelixGuzzleClient(Config::$items['services']['twitch']['client_id']);
        $twitchApi = new TwitchApi(
            $helixGuzzleClient,
            Config::$items['services']['twitch']['client_id'],
            Config::$items['services']['twitch']['secret']
        );
        $authApi = $twitchApi->getOauthApi();

        $response = $authApi->getAppAccessToken();
        if ($response->getStatusCode() == 200) {
            // Below is the returned token data
            $responseData = json_decode($response->getBody()->getContents(), true);
            return $responseData['access_token'];
        } else {
            // TODO: Handle Error
            echo 'Error getting data from Twitch';
            return null;
        }
    }

    public static function refreshToken($userId): bool
    {
        // Find user access token
        $user = Auth::userById($userId);

        // Init client
        $helixGuzzleClient = new HelixGuzzleClient(Config::$items['services']['twitch']['client_id']);
        $twitchApi = new TwitchApi(
            $helixGuzzleClient,
            Config::$items['services']['twitch']['client_id'],
            Config::$items['services']['twitch']['secret']
        );
        $authApi = $twitchApi->getOauthApi();

        try {
            $response = $authApi->refreshToken($user['ref_refresh_token']);
            $responseData = json_decode($response->getBody()->getContents(), true);

            // Your bearer token
            $twitch_access_token = $responseData['access_token'] ?? '';
            $twitch_refresh_token = $responseData['refresh_token'] ?? '';
            $userData = [
                'ref_access_token' => $twitch_access_token,
                'ref_refresh_token' => $twitch_refresh_token,
            ];
            Db::update('users', $userData, ['id' => $user['id']]);

            return true;
        } catch (\Exception $e) {
            $msg = "Failed to refresh access token: ".$e->getMessage();
            Logger::log(Logger::ERROR, $msg);
            return false;
        }
    }

    public static function refreshTopStreams()
    {
        // Get token
        $accessToken = self::getAppToken();

        // Init client
        $helixGuzzleClient = new HelixGuzzleClient(Config::$items['services']['twitch']['client_id']);
        $twitchApi = new TwitchApi(
            $helixGuzzleClient,
            Config::$items['services']['twitch']['client_id'],
            Config::$items['services']['twitch']['secret']
        );
        $streamsApi = $twitchApi->getStreamsApi();

        // Get all streams
        $count = 0;
        $safetyCheck = 0;
        $pagination = null;
        $allTags = self::getAllTags();
        $allTags = array_column($allTags, 'tag_id');
        $newTags = [];

        Db::query("TRUNCATE top_streams RESTART IDENTITY");

        while ($count < 1000) {
            $response = $streamsApi->getStreams($accessToken, after: $pagination);
            if ($response->getStatusCode() == 200) {
                $responseData = json_decode($response->getBody()->getContents(), true);
                $allStreams = $responseData['data'];

                foreach ($allStreams as $stream) {
                    if (!empty($stream['tag_ids'])) {
                        $newTags = array_merge($newTags, $stream['tag_ids']);
                    }
                    $streamData = [
                        'stream_id' => $stream['id'],
                        'title' => $stream['title'],
                        'game_id' => empty($stream['game_id']) ? null : $stream['game_id'],
                        'game_name' => $stream['game_name'],
                        'started_at' => $stream['started_at'],
                        'viewer_count' => $stream['viewer_count'],
                        'channel_name' => $stream['user_name'],
                        'tags' => empty($stream['tag_ids']) ? '[]' : json_encode($stream['tag_ids']),
                        'thumbnail_url' => $stream['thumbnail_url'],
                    ];
                    try {
                        Db::insert('top_streams', $streamData);
                        $count += 1;

                        if ($count == 1000) {
                            break;
                        }
                    } catch (\Exception $e) {
                        // Do nothing as it should fail because
                        // twitch api can return same stream multiple times
                        $msg = "Failed to insert data with error: ".$e->getMessage();
                        Logger::log(Logger::INFO, $msg);
                    }
                }

                if (empty($responseData['pagination']['cursor'])) {
                    $safetyCheck = 1000;
                } else {
                    $pagination = $responseData['pagination']['cursor'];
                }
            } else {
                break;
            }

            // Just in case
            $safetyCheck += 1;
            if ($safetyCheck > 60) {
                break;
            }

            usleep(100000);
        }

        // Insert new tags
        $newTags = array_unique($newTags);
        $newTags = array_diff($newTags, $allTags);
        foreach ($newTags as $oneTag) {
            $tagData = [
                'tag_id' => $oneTag
            ];
            Db::insert('tags', $tagData);
        }
    }

    public static function syncTags()
    {
        // Get token
        $accessToken = self::getAppToken();

        // Create a client
        $helixGuzzleClient = new HelixGuzzleClient(Config::$items['services']['twitch']['client_id']);
        $twitchApi = new TwitchApi(
            $helixGuzzleClient,
            Config::$items['services']['twitch']['client_id'],
            Config::$items['services']['twitch']['secret']
        );
        $tagsApi = $twitchApi->getTagsApi();

        // Tags
        $tmp = Db::fetchAll(
            "
                SELECT tag_id
                FROM tags
                WHERE title IS NULL
                ORDER BY tag_id ASC
            "
        );
        $allTags = array_column($tmp, 'tag_id');

        while (!empty($allTags)) {
            $tags = array_splice($allTags, 0, 100);
            $response = $tagsApi->getAllStreamTags($accessToken, $tags);
            if ($response->getStatusCode() == 200) {
                $allTagsResponse = json_decode($response->getBody()->getContents(), true);
                if (!empty($allTagsResponse['data'])) {
                    foreach ($allTagsResponse['data'] as $tagItem) {
                        $tagData = [
                            'title' => $tagItem['localization_names']['en-us'],
                            'description' => $tagItem['localization_descriptions']['en-us'],
                        ];
                        Db::update('tags', $tagData, ['tag_id' => $tagItem['tag_id']]);
                    }
                }
            }
        }
    }


    public static function refreshFollowedStreams($userId)
    {
        // Find user access token
        $user = Auth::userById($userId);

        // Create a client
        $helixGuzzleClient = new HelixGuzzleClient(Config::$items['services']['twitch']['client_id']);
        $twitchApi = new TwitchApi(
            $helixGuzzleClient,
            Config::$items['services']['twitch']['client_id'],
            Config::$items['services']['twitch']['secret']
        );
        $streamsApi = $twitchApi->getStreamsApi();

        // Tags
        $allTags = self::getAllTags();
        $allTags = array_column($allTags, 'tag_id');
        $newTags = [];

        // Get user followed streams
        $response = $streamsApi->getFollowedStreams($user['ref_access_token'], $user['ref_id']);
        if ($response->getStatusCode() == 200) {
            $followedStreams = json_decode($response->getBody()->getContents(), true);
            if (!empty($followedStreams['data'])) {
                Db::beginTransaction();
                Db::query('DELETE FROM followed_streams WHERE user_id = ?', [$user['id']]);
                foreach ($followedStreams['data'] as $item) {
                    if (!empty($stream['tag_ids'])) {
                        $newTags = array_merge($newTags, $item['tag_ids']);
                    }
                    $fData = [
                        'user_id' => $user['id'],
                        'stream_id' => $item['id'],
                        'channel_name' => $item['user_name'],
                        'viewer_count' => $item['viewer_count'],
                        'tags' => empty($item['tag_ids']) ? '[]' : json_encode($item['tag_ids']),
                    ];
                    Db::insert('followed_streams', $fData);
                }

                // Insert new tags
                $newTags = array_unique($newTags);
                $newTags = array_diff($newTags, $allTags);
                foreach ($newTags as $oneTag) {
                    $tagData = [
                        'tag_id' => $oneTag
                    ];
                    Db::insert('tags', $tagData);
                }

                Db::commit();
            }
        }
    }
}
