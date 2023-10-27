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
use Dotclear\Core\Process;
use Dotclear\Interface\Core\UserWorkspaceInterface;
use Exception;

class Install extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        try {
            // Update
            $old_version = App::version()->getVersion(My::id());
            if (version_compare((string) $old_version, '2.0', '<')) {
                // Rename settings workspace
                if (App::auth()->prefs()->exists('dmlastspams')) {
                    App::auth()->prefs()->delWorkspace(My::id());
                    App::auth()->prefs()->renWorkspace('dmlastspams', My::id());
                }

                // Change settings names (remove last_spams_ prefix in them)
                $rename = static function (string $name, UserWorkspaceInterface $preferences) : void {
                    if ($preferences->prefExists('last_spams_' . $name, true)) {
                        $preferences->rename('last_spams_' . $name, $name);
                    }
                };

                $preferences = My::prefs();
                if ($preferences) {
                    foreach (['nb', 'large', 'author', 'date', 'time', 'recents', 'autorefresh', 'badge'] as $pref) {
                        $rename($pref, $preferences);
                    }

                    $preferences->rename('last_spams', 'active');
                }
            }

            // Default prefs for last spams
            $preferences = My::prefs();
            if ($preferences) {
                $preferences->put('active', false, App::userWorkspace()::WS_BOOL, 'Display last spams', false, true);
                $preferences->put('nb', 5, App::userWorkspace()::WS_INT, 'Number of last spams displayed', false, true);
                $preferences->put('large', true, App::userWorkspace()::WS_BOOL, 'Large display', false, true);
                $preferences->put('author', true, App::userWorkspace()::WS_BOOL, 'Show authors', false, true);
                $preferences->put('date', true, App::userWorkspace()::WS_BOOL, 'Show dates', false, true);
                $preferences->put('time', true, App::userWorkspace()::WS_BOOL, 'Show times', false, true);
                $preferences->put('recents', 0, App::userWorkspace()::WS_INT, 'Max age of spams (in hours)', false, true);
                $preferences->put('autorefresh', false, App::userWorkspace()::WS_BOOL, 'Auto refresh', false, true);
                $preferences->put('interval', 30, App::userWorkspace()::WS_INT, 'Interval between two refreshes', false, true);
                $preferences->put('badge', true, App::userWorkspace()::WS_BOOL, 'Display counter (Auto refresh only)', false, true);
            }
        } catch (Exception $exception) {
            App::error()->add($exception->getMessage());
        }

        return true;
    }
}
