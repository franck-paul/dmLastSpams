<?php

/**
 * @brief dmLastSpams, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul contact@open-time.net
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\dmLastSpams;

use ArrayObject;
use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Img;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Li;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Number;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Set;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Timestamp;
use Dotclear\Helper\Html\Form\Ul;
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
        App::backend()->page()->jsJson('dm_lastspams', [
            'lastSpamId'  => $last_spam_id,
            'autoRefresh' => $preferences->autorefresh,
            'badge'       => $preferences->badge,
            'lastCounter' => 0,
            'spamCount'   => -1,
            'interval'    => ($preferences->interval ?? 30),
        ]) .
        My::jsLoad('service.js') .
        My::cssLoad('style.css');
    }

    private static function composeSQLSince(int $nb, string $unit = 'HOUR'): string
    {
        return match (App::db()->con()->syntax()) {
            'sqlite' => 'datetime(\'' .
                App::db()->con()->escapeStr('now') . '\', \'' .
                App::db()->con()->escapeStr('-' . $nb . ' ' . $unit) .
                '\')',

            'postgresql' => '(NOW() - \'' . App::db()->con()->escapeStr($nb . ' ' . $unit) . '\'::INTERVAL)',

            // default also stands for MySQL
            default => '(NOW() - INTERVAL ' . $nb . ' ' . $unit . ')',
        };
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
        // Get last $nb comments
        $params = [];
        if ($nb > 0) {
            $params['limit'] = $nb;
        } else {
            $params['limit'] = 30; // As in first page of comments' list
        }

        $params['comment_status'] = App::status()->comment()::JUNK;
        if ($recents > 0) {
            $params['sql'] = ' AND comment_dt >= ' . self::composeSQLSince($recents) . ' ';
        }

        $rs = App::blog()->getComments($params);
        if (!$rs->isEmpty()) {
            $lines = function (MetaRecord $rs, bool $large) use ($author, $date, $time, $last_id, &$last_counter) {
                $date_format = is_string($date_format = App::blog()->settings()->system->date_format) ? $date_format : '%F';
                $time_format = is_string($time_format = App::blog()->settings()->system->time_format) ? $time_format : '%T';
                $user_tz     = is_string($user_tz = App::auth()->getInfo('user_tz')) ? $user_tz : 'UTC';

                while ($rs->fetch()) {
                    $comment_status = is_numeric($comment_status = $rs->comment_status) ? (int) $comment_status : App::status()->comment()::PENDING;
                    $comment_id     = is_numeric($comment_id = $rs->comment_id) ? (int) $comment_id : 0;
                    $comment_dt     = is_string($comment_dt = $rs->comment_dt) ? $comment_dt : '';
                    $comment_author = is_string($comment_author = $rs->comment_author) ? $comment_author : '';
                    $post_title     = is_string($post_title = $rs->post_title) ? $post_title : '';

                    $status = match ($comment_status) {
                        App::status()->comment()::JUNK        => 'sts-junk',
                        App::status()->comment()::PENDING     => 'sts-pending',
                        App::status()->comment()::PUBLISHED   => 'sts-published',
                        App::status()->comment()::UNPUBLISHED => 'sts-unpublished',
                        default                               => 'sts-unknown',
                    };
                    $title = match ($comment_status) {
                        App::status()->comment()::JUNK        => __('Junk'),
                        App::status()->comment()::PENDING     => __('Pending'),
                        App::status()->comment()::PUBLISHED   => __('Published'),
                        App::status()->comment()::UNPUBLISHED => __('Unpublished'),
                        default                               => '',
                    };
                    $new = '';
                    if ($last_id !== -1 && $comment_id > $last_id) {
                        $new = 'dmls-new';
                        ++$last_counter;
                    }
                    $infos = [];
                    if ($large) {
                        if ($author) {
                            $infos[] = (new Text(null, __('by') . ' ' . $comment_author));
                        }
                        if ($date) {
                            $details = __('on') . ' ' . Date::dt2str($date_format, $comment_dt);
                            $infos[] = (new Timestamp($details))
                                ->datetime(Date::iso8601((int) strtotime($comment_dt), $user_tz));
                        }
                        if ($time) {
                            $details = __('at') . ' ' . Date::dt2str($time_format, $comment_dt);
                            $infos[] = (new Timestamp($details))
                                ->datetime(Date::iso8601((int) strtotime($comment_dt), $user_tz));
                        }
                    } else {
                        if ($author) {
                            $infos[] = (new Text(null, $comment_author));
                        }
                        if ($date) {
                            $infos[] = (new Timestamp(Date::dt2str(__('%Y-%m-%d'), $comment_dt)))
                                ->datetime(Date::iso8601((int) strtotime($comment_dt), $user_tz));
                        }
                        if ($time) {
                            $infos[] = (new Timestamp(Date::dt2str(__('%H:%M'), $comment_dt)))
                                ->datetime(Date::iso8601((int) strtotime($comment_dt), $user_tz));
                        }
                    }
                    yield (new Li('dmls' . $comment_id))
                        ->class(['line', $status, $new])
                        ->separator(' ')
                        ->items([
                            (new Link())
                                ->href(App::backend()->url()->get('admin.comment', ['id' => $comment_id]))
                                ->title($title)
                                ->text($post_title),
                            ... $infos,
                        ]);
                }
            };

            return (new Set())
                 ->items([
                     (new Ul())
                         ->items([
                             ... $lines($rs, $large),
                         ]),
                     (new Para())
                         ->items([
                             (new Link())
                                 ->href(App::backend()->url()->get('admin.comments', ['status' => App::status()->comment()::JUNK]))
                                 ->text(__('See all spams')),
                         ]),
                 ])
             ->render();
        }

        return (new Note())
            ->text(__('No spams') . ($recents > 0 ? ' ' . sprintf(__('since %d hour', 'since %d hours', $recents), $recents) : ''))
        ->render();
    }

    /**
     * @param      ArrayObject<int, ArrayObject<int, string>>  $contents  The contents
     */
    public static function adminDashboardContents(ArrayObject $contents): string
    {
        // Variable data helpers
        $_Bool = fn (mixed $var): bool => (bool) $var;
        $_Int  = fn (mixed $var, int $default = 0): int => $var !== null && is_numeric($val = $var) ? (int) $val : $default;

        // Add modules to the contents stack
        $preferences = My::prefs();

        if ($_Bool($preferences->active)) {
            $class = ($preferences->large ? 'medium' : 'small');

            $ret = (new Div('last-spams'))
                ->class(['box', $class])
                ->items([
                    (new Text(
                        'h3',
                        (new Img(urldecode((string) App::backend()->page()->getPF(My::id() . '/icon.svg'))))
                            ->alt('')
                            ->class(['icon-small', 'light-only'])
                        ->render() .
                        (new Img(urldecode((string) App::backend()->page()->getPF(My::id() . '/icon-dark.svg'))))
                            ->alt('')
                            ->class(['icon-small', 'dark-only'])
                        ->render() .
                        ' ' . __('Last spams')
                    )),
                    (new Text(null, self::getLastSpams(
                        $_Int($preferences->nb),
                        $_Bool($preferences->large),
                        $_Bool($preferences->author),
                        $_Bool($preferences->date),
                        $_Bool($preferences->time),
                        $_Int($preferences->recents),
                    ))),
                ])
            ->render();

            $contents->append(new ArrayObject([$ret]));
        }

        return '';
    }

    public static function adminAfterDashboardOptionsUpdate(): string
    {
        // Get and store user's prefs for plugin options
        try {
            // Post data helpers
            $_Bool = fn (string $name): bool => !empty($_POST[$name]);
            $_Int  = fn (string $name, int $default = 0): int => isset($_POST[$name]) && is_numeric($val = $_POST[$name]) ? (int) $val : $default;

            $preferences = My::prefs();

            $preferences->put('active', $_Bool('dmlast_spams'), App::userWorkspace()::WS_BOOL);
            $preferences->put('nb', $_Int('dmlast_spams_nb', 5), App::userWorkspace()::WS_INT);
            $preferences->put('large', !$_Bool('dmlast_spams_small'), App::userWorkspace()::WS_BOOL);
            $preferences->put('author', $_Bool('dmlast_spams_author'), App::userWorkspace()::WS_BOOL);
            $preferences->put('date', $_Bool('dmlast_spams_date'), App::userWorkspace()::WS_BOOL);
            $preferences->put('time', $_Bool('dmlast_spams_time'), App::userWorkspace()::WS_BOOL);
            $preferences->put('recents', $_Int('dmlast_spams_recents'), App::userWorkspace()::WS_INT);
            $preferences->put('autorefresh', $_Bool('dmlast_spams_autorefresh'), App::userWorkspace()::WS_BOOL);
            $preferences->put('interval', $_Int('dmlast_spams_interval'), App::userWorkspace()::WS_INT);
            $preferences->put('badge', $_Bool('dmlast_spams_badge'), App::userWorkspace()::WS_BOOL);
        } catch (Exception $exception) {
            App::error()->add($exception->getMessage());
        }

        return '';
    }

    public static function adminDashboardOptionsForm(): string
    {
        // Variable data helpers
        $_Bool = fn (mixed $var): bool => (bool) $var;
        $_Int  = fn (mixed $var, int $default = 0): int => $var !== null && is_numeric($val = $var) ? (int) $val : $default;

        $preferences = My::prefs();

        // Add fieldset for plugin options

        echo
        (new Fieldset('dmlastspams'))
        ->legend((new Legend(__('Last spams on dashboard'))))
        ->fields([
            (new Para())->items([
                (new Checkbox('dmlast_spams', $_Bool($preferences->active)))
                    ->value(1)
                    ->label((new Label(__('Display last spams'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmlast_spams_nb', 1, 999, $_Int($preferences->nb, 5)))
                    ->label((new Label(__('Number of last spams to display:'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_author', $_Bool($preferences->author)))
                    ->value(1)
                    ->label((new Label(__('Show authors'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_date', $_Bool($preferences->date)))
                    ->value(1)
                    ->label((new Label(__('Show dates'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_time', $_Bool($preferences->time)))
                    ->value(1)
                    ->label((new Label(__('Show times'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmlast_spams_recents', 0, 96, $_Int($preferences->recents)))
                    ->label((new Label(__('Max age of spams to display (in hours):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->class('form-note')->items([
                (new Text(null, __('Leave empty to ignore age of spams'))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_small', !$_Bool($preferences->large)))
                    ->value(1)
                    ->label((new Label(__('Small screen'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_autorefresh', $_Bool($preferences->autorefresh)))
                    ->value(1)
                    ->label((new Label(__('Auto refresh'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmlast_spams_interval', 0, 9_999_999, $_Int($preferences->interval)))
                    ->label((new Label(__('Interval in seconds between two refreshes:'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->items([
                (new Checkbox('dmlast_spams_badge', $_Bool($preferences->badge)))
                    ->value(1)
                    ->label((new Label(__('Display badges (only if Auto refresh is enabled)'), Label::INSIDE_TEXT_AFTER))),
            ]),
        ])
        ->render();

        return '';
    }
}
