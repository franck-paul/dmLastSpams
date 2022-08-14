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

// dead but useful code, in order to have translations
__('Last Spams Dashboard Module') . __('Display last spams on dashboard');

// Dashboard behaviours
dcCore::app()->addBehavior('adminDashboardHeaders', ['dmLastSpamsBehaviors', 'adminDashboardHeaders']);
dcCore::app()->addBehavior('adminDashboardContents', ['dmLastSpamsBehaviors', 'adminDashboardContents']);

dcCore::app()->addBehavior('adminAfterDashboardOptionsUpdate', ['dmLastSpamsBehaviors', 'adminAfterDashboardOptionsUpdate']);
dcCore::app()->addBehavior('adminDashboardOptionsForm', ['dmLastSpamsBehaviors', 'adminDashboardOptionsForm']);

# BEHAVIORS
class dmLastSpamsBehaviors
{
    public static function adminDashboardHeaders()
    {
        $sqlp = [
            'limit'      => 1,                 // only the last one
            'no_content' => true,              // content is not required
            'order'      => 'comment_id DESC', // get last first
        ];

        $rs = dcCore::app()->blog->getComments($sqlp);

        if ($rs->count()) {
            $rs->fetch();
            $last_spam_id = $rs->comment_id;
        } else {
            $last_spam_id = -1;
        }

        dcCore::app()->auth->user_prefs->addWorkspace('dmlastspams');

        return
        dcPage::jsJson('dm_lastspams', [
            'dmLastSpams_LastSpamId'  => $last_spam_id,
            'dmLastSpams_AutoRefresh' => dcCore::app()->auth->user_prefs->dmlastspams->last_spams_autorefresh,
            'dmLastSpams_Badge'       => dcCore::app()->auth->user_prefs->dmlastspams->last_spams_badge,
            'dmLastSpams_LastCounter' => 0,
            'dmLastSpams_SpamCount'   => -1,
        ]) .
        dcPage::jsModuleLoad('dmLastSpams/js/service.js', dcCore::app()->getVersion('dmLastSpams')) .
        dcPage::cssModuleLoad('dmLastSpams/css/style.css', 'screen', dcCore::app()->getVersion('dmLastSpams'));
    }

    private static function composeSQLSince($core, $nb, $unit = 'HOUR')
    {
        switch (dcCore::app()->con->syntax()) {
            case 'sqlite':
                $ret = 'datetime(\'' .
                    dcCore::app()->con->db_escape_string('now') . '\', \'' .
                    dcCore::app()->con->db_escape_string('-' . sprintf($nb) . ' ' . $unit) .
                    '\')';

                break;
            case 'postgresql':
                $ret = '(NOW() - \'' . dcCore::app()->con->db_escape_string(sprintf($nb) . ' ' . $unit) . '\'::INTERVAL)';

                break;
            case 'mysql':
            default:
                $ret = '(NOW() - INTERVAL ' . sprintf($nb) . ' ' . $unit . ')';

                break;
        }

        return $ret;
    }

    public static function getLastSpams(
        $core,
        $nb,
        $large,
        $author,
        $date,
        $time,
        $recents = 0,
        $last_id = -1,
        &$last_counter = 0
    ) {
        $recents = (int) $recents;
        $nb      = (int) $nb;

        // Get last $nb comments
        $params = [];
        if ($nb > 0) {
            $params['limit'] = $nb;
        } else {
            $params['limit'] = 30; // As in first page of comments' list
        }
        $params['comment_status'] = -2;
        if ($recents > 0) {
            $params['sql'] = ' AND comment_dt >= ' . dmLastSpamsBehaviors::composeSQLSince(dcCore::app(), $recents) . ' ';
        }
        $rs = dcCore::app()->blog->getComments($params, false);
        if (!$rs->isEmpty()) {
            $ret = '<ul>';
            while ($rs->fetch()) {
                $ret .= '<li class="line';
                if ($last_id != -1 && $rs->comment_id > $last_id) {
                    $ret .= ($last_id != -1 && $rs->comment_id > $last_id ? ' dmls-new' : '');
                    $last_counter++;
                }
                if ($rs->comment_status == -2) {
                    $ret .= ' sts-junk';
                }
                $ret .= '" id="dmls' . $rs->comment_id . '">';
                $ret .= '<a href="comment.php?id=' . $rs->comment_id . '">' . $rs->post_title . '</a>';
                $info = [];
                if ($large) {
                    if ($author) {
                        $info[] = __('by') . ' ' . $rs->comment_author;
                    }
                    if ($date) {
                        $info[] = __('on') . ' ' . dt::dt2str(dcCore::app()->blog->settings->system->date_format, $rs->comment_dt);
                    }
                    if ($time) {
                        $info[] = __('at') . ' ' . dt::dt2str(dcCore::app()->blog->settings->system->time_format, $rs->comment_dt);
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
        }

        return '<p>' . __('No spams') .
                ($recents > 0 ? ' ' . sprintf(__('since %d hour', 'since %d hours', $recents), $recents) : '') . '</p>';
    }

    public static function adminDashboardContents($core, $contents)
    {
        // Add modules to the contents stack
        dcCore::app()->auth->user_prefs->addWorkspace('dmlastspams');
        if (dcCore::app()->auth->user_prefs->dmlastspams->last_spams) {
            $class = (dcCore::app()->auth->user_prefs->dmlastspams->last_spams_large ? 'medium' : 'small');
            $ret   = '<div id="last-spams" class="box ' . $class . '">' .
            '<h3>' . '<img src="' . urldecode(dcPage::getPF('dmLastSpams/icon.png')) . '" alt="" />' . ' ' . __('Last spams') . '</h3>';
            $ret .= dmLastSpamsBehaviors::getLastSpams(
                dcCore::app(),
                dcCore::app()->auth->user_prefs->dmlastspams->last_spams_nb,
                dcCore::app()->auth->user_prefs->dmlastspams->last_spams_large,
                dcCore::app()->auth->user_prefs->dmlastspams->last_spams_author,
                dcCore::app()->auth->user_prefs->dmlastspams->last_spams_date,
                dcCore::app()->auth->user_prefs->dmlastspams->last_spams_time,
                dcCore::app()->auth->user_prefs->dmlastspams->last_spams_recents
            );
            $ret .= '</div>';
            $contents[] = new ArrayObject([$ret]);
        }
    }

    public static function adminAfterDashboardOptionsUpdate($userID)
    {
        // Get and store user's prefs for plugin options
        dcCore::app()->auth->user_prefs->addWorkspace('dmlastspams');

        try {
            dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams', !empty($_POST['dmlast_spams']), 'boolean');
            dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_nb', (int) $_POST['dmlast_spams_nb'], 'integer');
            dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_large', empty($_POST['dmlast_spams_small']), 'boolean');
            dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_author', !empty($_POST['dmlast_spams_author']), 'boolean');
            dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_date', !empty($_POST['dmlast_spams_date']), 'boolean');
            dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_time', !empty($_POST['dmlast_spams_time']), 'boolean');
            dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_recents', (int) $_POST['dmlast_spams_recents'], 'integer');
            dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_autorefresh', !empty($_POST['dmlast_spams_autorefresh']), 'boolean');
            dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_badge', !empty($_POST['dmlast_spams_badge']), 'boolean');
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }
    }

    public static function adminDashboardOptionsForm($core)
    {
        // Add fieldset for plugin options
        dcCore::app()->auth->user_prefs->addWorkspace('dmlastspams');

        echo '<div class="fieldset" id="dmlastspams"><h4>' . __('Last spams on dashboard') . '</h4>' .

        '<p>' .
        form::checkbox('dmlast_spams', 1, dcCore::app()->auth->user_prefs->dmlastspams->last_spams) . ' ' .
        '<label for="dmlast_spams" class="classic">' . __('Display last spams') . '</label></p>' .

        '<p><label for="dmlast_spams_nb" class="classic">' . __('Number of last spams to display:') . '</label> ' .
        form::number('dmlast_spams_nb', 1, 999, dcCore::app()->auth->user_prefs->dmlastspams->last_spams_nb) .
        '</p>' .

        '<p>' .
        form::checkbox('dmlast_spams_author', 1, dcCore::app()->auth->user_prefs->dmlastspams->last_spams_author) . ' ' .
        '<label for="dmlast_spams_author" class="classic">' . __('Show authors') . '</label></p>' .

        '<p>' .
        form::checkbox('dmlast_spams_date', 1, dcCore::app()->auth->user_prefs->dmlastspams->last_spams_date) . ' ' .
        '<label for="dmlast_spams_date" class="classic">' . __('Show dates') . '</label></p>' .

        '<p>' .
        form::checkbox('dmlast_spams_time', 1, dcCore::app()->auth->user_prefs->dmlastspams->last_spams_time) . ' ' .
        '<label for="dmlast_spams_time" class="classic">' . __('Show times') . '</label></p>' .

        '<p><label for="dmlast_spams_recents" class="classic">' . __('Max age of spams to display (in hours):') . '</label> ' .
        form::number('dmlast_spams_recents', 1, 96, dcCore::app()->auth->user_prefs->dmlastspams->last_spams_recents) .
        '</p>' .
        '<p class="form-note">' . __('Leave empty to ignore age of spams') . '</p>' .

        '<p>' .
        form::checkbox('dmlast_spams_small', 1, !dcCore::app()->auth->user_prefs->dmlastspams->last_spams_large) . ' ' .
        '<label for="dmlast_spams_small" class="classic">' . __('Small screen') . '</label></p>' .

        '<p>' .
        form::checkbox('dmlast_spams_autorefresh', 1, dcCore::app()->auth->user_prefs->dmlastspams->last_spams_autorefresh) . ' ' .
        '<label for="dmlast_spams_autorefresh" class="classic">' . __('Auto refresh') . '</label></p>' .

        '<p>' .
        form::checkbox('dmlast_spams_badge', 1, dcCore::app()->auth->user_prefs->dmlastspams->last_spams_badge) . ' ' .
        '<label for="dmlast_spams_badge" class="classic">' . __('Display badges (only if Auto refresh is enabled)') . '</label></p>' .

            '</div>';
    }
}
