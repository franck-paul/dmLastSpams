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
if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

class dmLastSpamsRest
{
    /**
     * Gets the spams count.
     *
     * @return     array   The payload.
     */
    public static function getSpamsCount(): array
    {
        return [
            'ret' => true,
            'nb'  => dcCore::app()->blog->getComments(['comment_status' => dcBlog::COMMENT_JUNK], true)->f(0),
        ];
    }

    /**
     * Serve method to check new spams for current blog.
     *
     * @param      array   $get    The get
     *
     * @return     array   The payload.
     */
    public static function checkNewSpams($get): array
    {
        $last_id      = !empty($get['last_id']) ? $get['last_id'] : -1;
        $last_spam_id = -1;

        $sqlp = [
            'no_content'     => true, // content is not required
            'order'          => 'comment_id ASC',
            'sql'            => 'AND comment_id > ' . $last_id, // only new ones
            'comment_status' => dcBlog::COMMENT_JUNK,
        ];

        $rs    = dcCore::app()->blog->getComments($sqlp);
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
     * @param      array   $get    The get
     *
     * @return     array   The payload.
     */
    public static function getLastSpamsRows($get): array
    {
        $stored_id = !empty($get['stored_id']) ? $get['stored_id'] : -1;
        $last_id   = !empty($get['last_id']) ? $get['last_id'] : -1;
        $counter   = !empty($get['counter']) ? $get['counter'] : 0;

        $payload = [
            'ret'       => true,
            'counter'   => 0,
            'stored_id' => $stored_id,
            'last_id'   => $last_id,
        ];

        if ($stored_id == -1) {
            return $payload;
        }

        $list = dmLastSpamsBehaviors::getLastSpams(
            dcCore::app(),
            dcCore::app()->auth->user_prefs->dmlastspams->last_spams_nb,
            dcCore::app()->auth->user_prefs->dmlastspams->last_spams_large,
            dcCore::app()->auth->user_prefs->dmlastspams->last_spams_author,
            dcCore::app()->auth->user_prefs->dmlastspams->last_spams_date,
            dcCore::app()->auth->user_prefs->dmlastspams->last_spams_time,
            dcCore::app()->auth->user_prefs->dmlastspams->last_spams_recents,
            $stored_id,
            $counter
        );

        return array_merge($payload, ['list' => $list, 'counter' => $counter]);
    }
}
