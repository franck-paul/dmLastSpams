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
use Dotclear\App;
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
    public static function adminDashboardHeaders(): string
    {
        $preferences = My::prefs();
        $sqlp        = [
            'limit'      => 1,                 // only the last one
            'no_content' => true,              // content is not required
            'order'      => 'comment_id DESC', // get last first
        ];

        $rs = App::blog()->getComments($sqlp);

        if ($rs->count()) {
            $rs->fetch();
            $last_spam_id = $rs->comment_id;
        } else {
            $last_spam_id = -1;
        }

        return
        Page::jsJson('dm_lastspams', [
            'dmLastSpams_LastSpamId'  => $last_spam_id,
            'dmLastSpams_AutoRefresh' => $preferences?->autorefresh,
            'dmLastSpams_Badge'       => $preferences?->badge,
            'dmLastSpams_LastCounter' => 0,
            'dmLastSpams_SpamCount'   => -1,
            'dmLastSpams_Interval'    => ($preferences->interval ?? 30),
        ]) .
        My::jsLoad('service.js') .
        My::cssLoad('style.css');
    }

    private static function composeSQLSince(int $nb, string $unit = 'HOUR'): string
    {
        switch (App::con()->syntax()) {
            case 'sqlite':
                $ret = 'datetime(\'' .
                    App::con()->escapeStr('now') . '\', \'' .
                    App::con()->escapeStr('-' . sprintf((string) $nb) . ' ' . $unit) .
                    '\')';

                break;
            case 'postgresql':
                $ret = '(NOW() - \'' . App::con()->escapeStr(sprintf((string) $nb) . ' ' . $unit) . '\'::INTERVAL)';

                break;
            case 'mysql':
            default:
                $ret = '(NOW() - INTERVAL ' . sprintf((string) $nb) . ' ' . $unit . ')';

                break;
        }

        return $ret;
    }

    public static function getLastSpams(
        int $nb,
        bool $large,
        bool $author,
        bool $date,
        bool $time,
        int $recents = 0,
        int $last_id = -1,
        int &$last_counter = 0
    ): string {
        $recents = (int) $recents;
        $nb      = (int) $nb;

        // Get last $nb comments
        $params = [];
        if ($nb > 0) {
            $params['limit'] = $nb;
        } else {
            $params['limit'] = 30; // As in first page of comments' list
        }
        $params['comment_status'] = App::blog()::COMMENT_JUNK;
        if ($recents > 0) {
            $params['sql'] = ' AND comment_dt >= ' . BackendBehaviors::composeSQLSince($recents) . ' ';
        }
        $rs = App::blog()->getComments($params);
        if (!$rs->isEmpty()) {
            $ret = '<ul>';
            while ($rs->fetch()) {
                $ret .= '<li class="line';
                if ($last_id !== -1 && $rs->comment_id > $last_id) {
                    $ret .= ' dmls-new';
                    $last_counter++;
                }
                if ($rs->comment_status == App::blog()::COMMENT_JUNK) {
                    $ret .= ' sts-junk';
                }
                $ret .= '" id="dmls' . $rs->comment_id . '">';
                $ret .= '<a href="' . App::backend()->url()->get('admin.comment', ['id' => $rs->comment_id]) . '">' . $rs->post_title . '</a>';
                $dt   = '<time datetime="' . Date::iso8601((int) strtotime($rs->comment_dt), App::auth()->getInfo('user_tz')) . '">%s</time>';
                $info = [];
                if ($large) {
                    if ($author) {
                        $info[] = __('by') . ' ' . $rs->comment_author;
                    }
                    if ($date) {
                        $info[] = sprintf($dt, __('on') . ' ' . Date::dt2str(App::blog()->settings()->system->date_format, $rs->comment_dt));
                    }
                    if ($time) {
                        $info[] = sprintf($dt, __('at') . ' ' . Date::dt2str(App::blog()->settings()->system->time_format, $rs->comment_dt));
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
            $ret .= '<p><a href="' . App::backend()->url()->get('admin.comments', ['status' => App::blog()::COMMENT_JUNK]) . '">' . __('See all spams') . '</a></p>';

            return $ret;
        }

        return '<p>' . __('No spams') .
                ($recents > 0 ? ' ' . sprintf(__('since %d hour', 'since %d hours', $recents), $recents) : '') . '</p>';
    }

    /**
     * @param      ArrayObject<int, ArrayObject<int, string>>  $contents  The contents
     *
     * @return     string
     */
    public static function adminDashboardContents(ArrayObject $contents): string
    {
        // Add modules to the contents stack
        $preferences = My::prefs();
        if ($preferences?->active) {
            $class = ($preferences->large ? 'medium' : 'small');
            $ret   = '<div id="last-spams" class="box ' . $class . '">' .
            '<h3>' .
            '<img src="' . urldecode(Page::getPF('dmLastSpams/icon.svg')) . '" alt="" class="light-only icon-small" />' .
            '<img src="' . urldecode(Page::getPF('dmLastSpams/icon-dark.svg')) . '" alt="" class="dark-only icon-small" />' .
            ' ' . __('Last spams') . '</h3>';
            $ret .= BackendBehaviors::getLastSpams(
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

        return '';
    }

    public static function adminAfterDashboardOptionsUpdate(): string
    {
        // Get and store user's prefs for plugin options
        try {
            $preferences = My::prefs();
            if ($preferences) {
                $preferences->put('active', !empty($_POST['dmlast_spams']), App::userWorkspace()::WS_BOOL);
                $preferences->put('nb', (int) $_POST['dmlast_spams_nb'], App::userWorkspace()::WS_INT);
                $preferences->put('large', empty($_POST['dmlast_spams_small']), App::userWorkspace()::WS_BOOL);
                $preferences->put('author', !empty($_POST['dmlast_spams_author']), App::userWorkspace()::WS_BOOL);
                $preferences->put('date', !empty($_POST['dmlast_spams_date']), App::userWorkspace()::WS_BOOL);
                $preferences->put('time', !empty($_POST['dmlast_spams_time']), App::userWorkspace()::WS_BOOL);
                $preferences->put('recents', (int) $_POST['dmlast_spams_recents'], App::userWorkspace()::WS_INT);
                $preferences->put('autorefresh', !empty($_POST['dmlast_spams_autorefresh']), App::userWorkspace()::WS_BOOL);
                $preferences->put('interval', (int) $_POST['dmlast_spams_interval'], App::userWorkspace()::WS_INT);
                $preferences->put('badge', !empty($_POST['dmlast_spams_badge']), App::userWorkspace()::WS_BOOL);
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return '';
    }

    public static function adminDashboardOptionsForm(): string
    {
        $preferences = My::prefs();

        // Add fieldset for plugin options

        echo
        (new Fieldset('dmlastspams'))
        ->legend((new Legend(__('Last spams on dashboard'))))
        ->fields([
            (new Para())->items([
                (new Checkbox('dmlast_spams', $preferences?->active))
                    ->value(1)
                    ->label((new Label(__('Display last spams'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmlast_spams_nb', 1, 999, $preferences?->nb))
                    ->label((new Label(__('Number of last spams to display:'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_author', $preferences?->author))
                    ->value(1)
                    ->label((new Label(__('Show authors'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_date', $preferences?->date))
                    ->value(1)
                    ->label((new Label(__('Show dates'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_time', $preferences?->time))
                    ->value(1)
                    ->label((new Label(__('Show times'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmlast_spams_recents', 0, 96, $preferences?->recents))
                    ->label((new Label(__('Max age of spams to display (in hours):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->class('form-note')->items([
                (new Text(null, __('Leave empty to ignore age of spams'))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_small', !$preferences?->large))
                    ->value(1)
                    ->label((new Label(__('Small screen'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_autorefresh', $preferences?->autorefresh))
                    ->value(1)
                    ->label((new Label(__('Auto refresh'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmlast_spams_interval', 0, 9_999_999, $preferences?->interval))
                    ->label((new Label(__('Interval in seconds between two refreshes:'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_badge', $preferences?->badge))
                    ->value(1)
                    ->label((new Label(__('Display badges (only if Auto refresh is enabled)'), Label::INSIDE_TEXT_AFTER))),
            ]),
        ])
        ->render();

        return '';
    }
}
