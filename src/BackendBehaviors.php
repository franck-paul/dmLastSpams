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

use ArrayObject;
use dcBlog;
use dcCore;
use dcWorkspace;
use Dotclear\Core\Backend\Page;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Number;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Text;
use Exception;

class BackendBehaviors
{
    public static function adminDashboardHeaders()
    {
        $preferences = dcCore::app()->auth->user_prefs->get(My::id());
        $sqlp        = [
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

        return
        Page::jsJson('dm_lastspams', [
            'dmLastSpams_LastSpamId'  => $last_spam_id,
            'dmLastSpams_AutoRefresh' => $preferences->autorefresh,
            'dmLastSpams_Badge'       => $preferences->badge,
            'dmLastSpams_LastCounter' => 0,
            'dmLastSpams_SpamCount'   => -1,
            'dmLastSpams_Interval'    => ($preferences->interval ?? 30),
        ]) .
        My::jsLoad('service.js') .
        My::cssLoad('style.css');
    }

    private static function composeSQLSince($nb, $unit = 'HOUR')
    {
        switch (dcCore::app()->con->syntax()) {
            case 'sqlite':
                $ret = 'datetime(\'' .
                    dcCore::app()->con->escape('now') . '\', \'' .
                    dcCore::app()->con->escape('-' . sprintf((string) $nb) . ' ' . $unit) .
                    '\')';

                break;
            case 'postgresql':
                $ret = '(NOW() - \'' . dcCore::app()->con->escape(sprintf((string) $nb) . ' ' . $unit) . '\'::INTERVAL)';

                break;
            case 'mysql':
            default:
                $ret = '(NOW() - INTERVAL ' . sprintf((string) $nb) . ' ' . $unit . ')';

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
        $params['comment_status'] = dcBlog::COMMENT_JUNK;
        if ($recents > 0) {
            $params['sql'] = ' AND comment_dt >= ' . BackendBehaviors::composeSQLSince($recents) . ' ';
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
                if ($rs->comment_status == dcBlog::COMMENT_JUNK) {
                    $ret .= ' sts-junk';
                }
                $ret .= '" id="dmls' . $rs->comment_id . '">';
                $ret .= '<a href="' . dcCore::app()->admin->url->get('admin.comment', ['id' => $rs->comment_id]) . '">' . $rs->post_title . '</a>';
                $dt   = '<time datetime="' . Date::iso8601(strtotime($rs->comment_dt), dcCore::app()->auth->getInfo('user_tz')) . '">%s</time>';
                $info = [];
                if ($large) {
                    if ($author) {
                        $info[] = __('by') . ' ' . $rs->comment_author;
                    }
                    if ($date) {
                        $info[] = sprintf($dt, __('on') . ' ' . Date::dt2str(dcCore::app()->blog->settings->system->date_format, $rs->comment_dt));
                    }
                    if ($time) {
                        $info[] = sprintf($dt, __('at') . ' ' . Date::dt2str(dcCore::app()->blog->settings->system->time_format, $rs->comment_dt));
                    }
                } else {
                    if ($author) {
                        $info[] = $rs->comment_author;
                    }
                    if ($date) {
                        $info[] = sprintf($dt, Date::dt2str(__('%Y-%m-%d'), $rs->comment_dt));
                    }
                    if ($time) {
                        $info[] = sprintf($dt, Date::dt2str(__('%H:%M'), $rs->comment_dt));
                    }
                }
                if (count($info)) {
                    $ret .= ' (' . implode(' ', $info) . ')';
                }
                $ret .= '</li>';
            }
            $ret .= '</ul>';
            $ret .= '<p><a href="' . dcCore::app()->admin->url->get('admin.comments', ['status' => dcBlog::COMMENT_JUNK]) . '">' . __('See all spams') . '</a></p>';

            return $ret;
        }

        return '<p>' . __('No spams') .
                ($recents > 0 ? ' ' . sprintf(__('since %d hour', 'since %d hours', $recents), $recents) : '') . '</p>';
    }

    public static function adminDashboardContents($contents)
    {
        // Add modules to the contents stack
        $preferences = dcCore::app()->auth->user_prefs->get(My::id());
        if ($preferences->active) {
            $class = ($preferences->large ? 'medium' : 'small');
            $ret   = '<div id="last-spams" class="box ' . $class . '">' .
            '<h3>' .
            '<img src="' . urldecode(Page::getPF('dmLastSpams/icon.svg')) . '" alt="" class="light-only icon-small" />' .
            '<img src="' . urldecode(Page::getPF('dmLastSpams/icon-dark.svg')) . '" alt="" class="dark-only icon-small" />' .
            ' ' . __('Last spams') . '</h3>';
            $ret .= BackendBehaviors::getLastSpams(
                dcCore::app(),
                $preferences->nb,
                $preferences->large,
                $preferences->author,
                $preferences->date,
                $preferences->time,
                $preferences->recents
            );
            $ret .= '</div>';
            $contents[] = new ArrayObject([$ret]);
        }
    }

    public static function adminAfterDashboardOptionsUpdate()
    {
        // Get and store user's prefs for plugin options
        try {
            $preferences = dcCore::app()->auth->user_prefs->get(My::id());
            $preferences->put('active', !empty($_POST['dmlast_spams']), dcWorkspace::WS_BOOL);
            $preferences->put('nb', (int) $_POST['dmlast_spams_nb'], dcWorkspace::WS_INT);
            $preferences->put('large', empty($_POST['dmlast_spams_small']), dcWorkspace::WS_BOOL);
            $preferences->put('author', !empty($_POST['dmlast_spams_author']), dcWorkspace::WS_BOOL);
            $preferences->put('date', !empty($_POST['dmlast_spams_date']), dcWorkspace::WS_BOOL);
            $preferences->put('time', !empty($_POST['dmlast_spams_time']), dcWorkspace::WS_BOOL);
            $preferences->put('recents', (int) $_POST['dmlast_spams_recents'], dcWorkspace::WS_INT);
            $preferences->put('autorefresh', !empty($_POST['dmlast_spams_autorefresh']), dcWorkspace::WS_BOOL);
            $preferences->put('interval', (int) $_POST['dmlast_spams_interval'], dcWorkspace::WS_INT);
            $preferences->put('badge', !empty($_POST['dmlast_spams_badge']), dcWorkspace::WS_BOOL);
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }
    }

    public static function adminDashboardOptionsForm()
    {
        $preferences = dcCore::app()->auth->user_prefs->get(My::id());

        // Add fieldset for plugin options

        echo
        (new Fieldset('dmlastspams'))
        ->legend((new Legend(__('Last spams on dashboard'))))
        ->fields([
            (new Para())->items([
                (new Checkbox('dmlast_spams', $preferences->active))
                    ->value(1)
                    ->label((new Label(__('Display last spams'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmlast_spams_nb', 1, 999, $preferences->nb))
                    ->label((new Label(__('Number of last spams to display:'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_author', $preferences->author))
                    ->value(1)
                    ->label((new Label(__('Show authors'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_date', $preferences->date))
                    ->value(1)
                    ->label((new Label(__('Show dates'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_time', $preferences->time))
                    ->value(1)
                    ->label((new Label(__('Show times'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmlast_spams_recents', 0, 96, $preferences->recents))
                    ->label((new Label(__('Max age of spams to display (in hours):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->class('form-note')->items([
                (new Text(null, __('Leave empty to ignore age of spams'))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_small', !$preferences->large))
                    ->value(1)
                    ->label((new Label(__('Small screen'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_autorefresh', $preferences->autorefresh))
                    ->value(1)
                    ->label((new Label(__('Auto refresh'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmlast_spams_interval', 0, 9_999_999, $preferences->interval))
                    ->label((new Label(__('Interval in seconds between two refreshes:'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_badge', $preferences->badge))
                    ->value(1)
                    ->label((new Label(__('Display badges (only if Auto refresh is enabled)'), Label::INSIDE_TEXT_AFTER))),
            ]),
        ])
        ->render();
    }
}
