<?php

/**
 * @brief dmLastSpams, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\dmLastSpams;

use Dotclear\App;

class BackendRest
{
    /**
     * Gets the spams count.
     *
     * @return     array<string, mixed>   The payload.
     */
    public static function getSpamsCount(): array
    {
        return [
            'ret' => true,
            'nb'  => App::blog()->getComments(['comment_status' => App::blog()::COMMENT_JUNK], true)->f(0),
        ];
    }

    /**
     * Serve method to check new spams for current blog.
     *
     * @param      array<string, string>   $get    The get
     *
     * @return     array<string, mixed>   The payload.
     */
    public static function checkNewSpams($get): array
    {
        $last_id      = empty($get['last_id']) ? -1 : $get['last_id'];
        $last_spam_id = -1;

        $sqlp = [
            'no_content'     => true, // content is not required
            'order'          => 'comment_id ASC',
            'sql'            => 'AND comment_id > ' . $last_id, // only new ones
            'comment_status' => App::blog()::COMMENT_JUNK,
        ];

        $rs    = App::blog()->getComments($sqlp);
        $count = $rs->count();

        if ($count) {
            while ($rs->fetch()) {
                $last_spam_id = $rs->comment_id;
            }
        }

        return [
            'ret'     => true,
            'nb'      => $count,
            'last_id' => $last_spam_id,
        ];
    }

    /**
     * Gets the last spams rows.
     *
     * @param      array<string, string>   $get    The get
     *
     * @return     array<string, mixed>   The payload.
     */
    public static function getLastSpamsRows($get): array
    {
        $stored_id = empty($get['stored_id']) ? -1 : (int) $get['stored_id'];
        $last_id   = empty($get['last_id']) ? -1 : (int) $get['last_id'];
        $counter   = empty($get['counter']) ? 0 : (int) $get['counter'];

        $payload = [
            'ret'       => true,
            'counter'   => 0,
            'stored_id' => $stored_id,
            'last_id'   => $last_id,
        ];

        if ($stored_id == -1) {
            return $payload;
        }

        $preferences = My::prefs();

        $list = BackendBehaviors::getLastSpams(
            $preferences->nb,
            $preferences->large,
            $preferences->author,
            $preferences->date,
            $preferences->time,
            $preferences->recents,
            $stored_id,
            $counter
        );

        return [...$payload, 'list' => $list, 'counter' => $counter];
    }
}
