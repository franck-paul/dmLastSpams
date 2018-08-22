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

if (!defined('DC_CONTEXT_ADMIN')) {return;}

// dead but useful code, in order to have translations
__('Last Spams Dashboard Module') . __('Display last spams on dashboard');

// Dashboard behaviours
$core->addBehavior('adminDashboardHeaders', array('dmLastSpamsBehaviors', 'adminDashboardHeaders'));
$core->addBehavior('adminDashboardContents', array('dmLastSpamsBehaviors', 'adminDashboardContents'));

$core->addBehavior('adminAfterDashboardOptionsUpdate', array('dmLastSpamsBehaviors', 'adminAfterDashboardOptionsUpdate'));
$core->addBehavior('adminDashboardOptionsForm', array('dmLastSpamsBehaviors', 'adminDashboardOptionsForm'));

# BEHAVIORS
class dmLastSpamsBehaviors
{
    public static function adminDashboardHeaders()
    {
        global $core;

        $sqlp = array(
            'limit'      => 1,                 // only the last one
            'no_content' => true,              // content is not required
            'order'      => 'comment_id DESC' // get last first
        );

        $rs = $core->blog->getComments($sqlp);

        if ($rs->count()) {
            $rs->fetch();
            $last_spam_id = $rs->comment_id;
        } else {
            $last_spam_id = -1;
        }

        $core->auth->user_prefs->addWorkspace('dmlastspams');

        return
        '<script type="text/javascript">' . "\n" .
        dcPage::jsVar('dotclear.dmLastSpams_LastSpamId', $last_spam_id) .
        dcPage::jsVar('dotclear.dmLastSpams_AutoRefresh', $core->auth->user_prefs->dmlastspams->last_spams_autorefresh) .
        dcPage::jsVar('dotclear.dmLastSpams_Badge', $core->auth->user_prefs->dmlastspams->last_spams_badge) .
        dcPage::jsVar('dotclear.dmLastSpams_LastCounter', 0) .
        dcPage::jsVar('dotclear.dmLastSpams_SpamCount', -1) .
        "</script>\n" .
        dcPage::jsLoad(urldecode(dcPage::getPF('dmLastSpams/js/service.js')), $core->getVersion('dmLastSpams')) .
        dcPage::cssLoad(urldecode(dcPage::getPF('dmLastSpams/css/style.css')), 'screen', $core->getVersion('dmLastSpams'));
    }

    public static function getLastSpams($core, $nb, $large, $author, $date, $time, $recents = 0,
        $last_id = -1, &$last_counter = 0) {
        $recents = (integer) $recents;
        $nb      = (integer) $nb;

        // Get last $nb comments
        $params = array();
        if ($nb > 0) {
            $params['limit'] = $nb;
        } else {
            $params['limit'] = 30; // As in first page of comments' list
        }
        $params['comment_status'] = -2;
        if ($recents > 0) {
            $params['sql'] = ' AND comment_dt >= (NOW() - INTERVAL ' . sprintf($recents) . ' HOUR) ';
        }
        $rs = $core->blog->getComments($params, false);
        if (!$rs->isEmpty()) {
            $ret = '<ul>';
            while ($rs->fetch()) {
                $ret .= '<li class="line';
                if ($last_id != -1 && $rs->comment_id > $last_id) {
                    $ret .= ($last_id != -1 && $rs->comment_id > $last_id ? ' dmls-new' : '');
                    $last_counter++;
                }
                $ret .= '" id="dmls' . $rs->comment_id . '">';
                $ret .= '<a href="comment.php?id=' . $rs->comment_id . '">' . $rs->post_title . '</a>';
                $info = array();
                if ($large) {
                    if ($author) {
                        $info[] = __('by') . ' ' . $rs->comment_author;
                    }
                    if ($date) {
                        $info[] = __('on') . ' ' . dt::dt2str($core->blog->settings->system->date_format, $rs->comment_dt);
                    }
                    if ($time) {
                        $info[] = __('at') . ' ' . dt::dt2str($core->blog->settings->system->time_format, $rs->comment_dt);
                    }
                } else {
                    if ($author) {
                        $info[] = $rs->comment_author;
                    }
                    if ($date) {
                        $info[] = dt::dt2str(__('%Y-%m-%d'), $rs->comment_dt);
                    }
                    if ($time) {
                        $info[] = dt::dt2str(__('%H:%M'), $rs->comment_dt);
                    }
                }
                if (count($info)) {
                    $ret .= ' (' . implode(' ', $info) . ')';
                }
                $ret .= '</li>';
            }
            $ret .= '</ul>';
            $ret .= '<p><a href="comments.php?status=-2">' . __('See all spams') . '</a></p>';

            return $ret;
        } else {
            return '<p>' . __('No spams') .
                ($recents > 0 ? ' ' . sprintf(__('since %d hour', 'since %d hours', $recents), $recents) : '') . '</p>';
        }
    }

    public static function adminDashboardContents($core, $contents)
    {
        // Add modules to the contents stack
        $core->auth->user_prefs->addWorkspace('dmlastspams');
        if ($core->auth->user_prefs->dmlastspams->last_spams) {
            $class = ($core->auth->user_prefs->dmlastspams->last_spams_large ? 'medium' : 'small');
            $ret   = '<div id="last-spams" class="box ' . $class . '">' .
            '<h3>' . '<img src="' . urldecode(dcPage::getPF('dmLastSpams/icon.png')) . '" alt="" />' . ' ' . __('Last spams') . '</h3>';
            $ret .= dmLastSpamsBehaviors::getLastSpams($core,
                $core->auth->user_prefs->dmlastspams->last_spams_nb,
                $core->auth->user_prefs->dmlastspams->last_spams_large,
                $core->auth->user_prefs->dmlastspams->last_spams_author,
                $core->auth->user_prefs->dmlastspams->last_spams_date,
                $core->auth->user_prefs->dmlastspams->last_spams_time,
                $core->auth->user_prefs->dmlastspams->last_spams_recents);
            $ret .= '</div>';
            $contents[] = new ArrayObject(array($ret));
        }
    }

    public static function adminAfterDashboardOptionsUpdate($userID)
    {
        global $core;

        // Get and store user's prefs for plugin options
        $core->auth->user_prefs->addWorkspace('dmlastspams');
        try {
            $core->auth->user_prefs->dmlastspams->put('last_spams', !empty($_POST['dmlast_spams']), 'boolean');
            $core->auth->user_prefs->dmlastspams->put('last_spams_nb', (integer) $_POST['dmlast_spams_nb'], 'integer');
            $core->auth->user_prefs->dmlastspams->put('last_spams_large', empty($_POST['dmlast_spams_small']), 'boolean');
            $core->auth->user_prefs->dmlastspams->put('last_spams_author', !empty($_POST['dmlast_spams_author']), 'boolean');
            $core->auth->user_prefs->dmlastspams->put('last_spams_date', !empty($_POST['dmlast_spams_date']), 'boolean');
            $core->auth->user_prefs->dmlastspams->put('last_spams_time', !empty($_POST['dmlast_spams_time']), 'boolean');
            $core->auth->user_prefs->dmlastspams->put('last_spams_recents', (integer) $_POST['dmlast_spams_recents'], 'integer');
            $core->auth->user_prefs->dmlastspams->put('last_spams_autorefresh', !empty($_POST['dmlast_spams_autorefresh']), 'boolean');
            $core->auth->user_prefs->dmlastspams->put('last_spams_badge', !empty($_POST['dmlast_spams_badge']), 'boolean');
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }

    public static function adminDashboardOptionsForm($core)
    {
        // Add fieldset for plugin options
        $core->auth->user_prefs->addWorkspace('dmlastspams');

        echo '<div class="fieldset"><h4>' . __('Last spams on dashboard') . '</h4>' .

        '<p>' .
        form::checkbox('dmlast_spams', 1, $core->auth->user_prefs->dmlastspams->last_spams) . ' ' .
        '<label for="dmlast_spams" class="classic">' . __('Display last spams') . '</label></p>' .

        '<p><label for="dmlast_spams_nb" class="classic">' . __('Number of last spams to display:') . '</label> ' .
        form::field('dmlast_spams_nb', 2, 3, (integer) $core->auth->user_prefs->dmlastspams->last_spams_nb) .
        '</p>' .

        '<p>' .
        form::checkbox('dmlast_spams_author', 1, $core->auth->user_prefs->dmlastspams->last_spams_author) . ' ' .
        '<label for="dmlast_spams_author" class="classic">' . __('Show authors') . '</label></p>' .

        '<p>' .
        form::checkbox('dmlast_spams_date', 1, $core->auth->user_prefs->dmlastspams->last_spams_date) . ' ' .
        '<label for="dmlast_spams_date" class="classic">' . __('Show dates') . '</label></p>' .

        '<p>' .
        form::checkbox('dmlast_spams_time', 1, $core->auth->user_prefs->dmlastspams->last_spams_time) . ' ' .
        '<label for="dmlast_spams_time" class="classic">' . __('Show times') . '</label></p>' .

        '<p><label for="dmlast_spams_recents" class="classic">' . __('Max age of spams to display (in hours):') . '</label> ' .
        form::field('dmlast_spams_recents', 2, 3, (integer) $core->auth->user_prefs->dmlastspams->last_spams_recents) .
        '</p>' .
        '<p class="form-note">' . __('Leave empty to ignore age of spams') . '</p>' .

        '<p>' .
        form::checkbox('dmlast_spams_small', 1, !$core->auth->user_prefs->dmlastspams->last_spams_large) . ' ' .
        '<label for="dmlast_spams_small" class="classic">' . __('Small screen') . '</label></p>' .

        '<p>' .
        form::checkbox('dmlast_spams_autorefresh', 1, $core->auth->user_prefs->dmlastspams->last_spams_autorefresh) . ' ' .
        '<label for="dmlast_spams_autorefresh" class="classic">' . __('Auto refresh') . '</label></p>' .

        '<p>' .
        form::checkbox('dmlast_spams_badge', 1, $core->auth->user_prefs->dmlastspams->last_spams_badge) . ' ' .
        '<label for="dmlast_spams_badge" class="classic">' . __('Display badges (only if Auto refresh is enabled)') . '</label></p>' .

            '</div>';
    }
}
