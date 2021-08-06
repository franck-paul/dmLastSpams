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
     * Serve method to get number of spams for current blog.
     *
     * @param     core     <b>dcCore</b>     dcCore instance
     * @param     get     <b>array</b>     cleaned $_GET
     */
    public static function getSpamsCount($core, $get)
    {
        $count = $core->blog->getComments(['comment_status' => -2], true)->f(0);

        $rsp      = new xmlTag('check');
        $rsp->ret = $count;

        return $rsp;
    }

    /**
     * Serve method to check new spams for current blog.
     *
     * @param    core    <b>dcCore</b>    dcCore instance
     * @param    get        <b>array</b>    cleaned $_GET
     *
     * @return    <b>xmlTag</b>    XML representation of response
     */
    public static function checkNewSpams($core, $get)
    {
        $last_id = !empty($get['last_id']) ? $get['last_id'] : -1;

        $sqlp = [
            'no_content'     => true, // content is not required
            'order'          => 'comment_id ASC',
            'sql'            => 'AND comment_id > ' . $last_id, // only new ones
            'comment_status' => -2
        ];
        $core->auth->user_prefs->addWorkspace('dmlastspams');

        $rs    = $core->blog->getComments($sqlp);
        $count = $rs->count();

        if ($count) {
            while ($rs->fetch()) {
                $last_spam_id = $rs->comment_id;
            }
        }
        $rsp      = new xmlTag('check');
        $rsp->ret = $count;
        if ($count) {
            $rsp->last_id = $last_spam_id;
        }

        return $rsp;
    }

    /**
     * Serve method to get new spams rows for current blog.
     *
     * @param    core    <b>dcCore</b>    dcCore instance
     * @param    get        <b>array</b>    cleaned $_GET
     *
     * @return    <b>xmlTag</b>    XML representation of response
     */
    public static function getLastSpamsRows($core, $get)
    {
        $rsp      = new xmlTag('rows');
        $rsp->ret = 0;

        $stored_id = !empty($get['stored_id']) ? $get['stored_id'] : -1;
        $last_id   = !empty($get['last_id']) ? $get['last_id'] : -1;
        $counter   = !empty($get['counter']) ? $get['counter'] : 0;

        $rsp->stored_id = $stored_id;
        $rsp->last_id   = $last_id;

        if ($stored_id == -1) {
            return $rsp;
        }

        $core->auth->user_prefs->addWorkspace('dmlastspams');
        $ret = dmLastSpamsBehaviors::getLastSpams($core,
            $core->auth->user_prefs->dmlastspams->last_spams_nb,
            $core->auth->user_prefs->dmlastspams->last_spams_large,
            $core->auth->user_prefs->dmlastspams->last_spams_author,
            $core->auth->user_prefs->dmlastspams->last_spams_date,
            $core->auth->user_prefs->dmlastspams->last_spams_time,
            $core->auth->user_prefs->dmlastspams->last_spams_recents,
            $stored_id, $counter);

        $rsp->list    = $ret;
        $rsp->counter = $counter;
        $rsp->ret     = 1;

        return $rsp;
    }
}
