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

use dcCore;
use dcWorkspace;
use Dotclear\Core\Process;
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
            $old_version = dcCore::app()->getVersion(My::id());
            if (version_compare((string) $old_version, '2.0', '<')) {
                // Rename settings workspace
                if (dcCore::app()->auth->user_prefs->exists('dmlastspams')) {
                    dcCore::app()->auth->user_prefs->delWorkspace(My::id());
                    dcCore::app()->auth->user_prefs->renWorkspace('dmlastspams', My::id());
                }
                // Change settings names (remove last_spams_ prefix in them)
                $rename = function (string $name, dcWorkspace $preferences): void {
                    if ($preferences->prefExists('last_spams_' . $name, true)) {
                        $preferences->rename('last_spams_' . $name, $name);
                    }
                };

                $preferences = dcCore::app()->auth->user_prefs->get(My::id());
                foreach (['nb', 'large', 'author', 'date', 'time', 'recents', 'autorefresh', 'badge'] as $pref) {
                    $rename($pref, $preferences);
                }
                $preferences->rename('last_spams', 'active');
            }

            // Default prefs for last spams
            $preferences = dcCore::app()->auth->user_prefs->get(My::id());
            $preferences->put('active', false, dcWorkspace::WS_BOOL, 'Display last spams', false, true);
            $preferences->put('nb', 5, dcWorkspace::WS_INT, 'Number of last spams displayed', false, true);
            $preferences->put('large', true, dcWorkspace::WS_BOOL, 'Large display', false, true);
            $preferences->put('author', true, dcWorkspace::WS_BOOL, 'Show authors', false, true);
            $preferences->put('date', true, dcWorkspace::WS_BOOL, 'Show dates', false, true);
            $preferences->put('time', true, dcWorkspace::WS_BOOL, 'Show times', false, true);
            $preferences->put('recents', 0, dcWorkspace::WS_INT, 'Max age of spams (in hours)', false, true);
            $preferences->put('autorefresh', false, dcWorkspace::WS_BOOL, 'Auto refresh', false, true);
            $preferences->put('interval', 30, dcWorkspace::WS_INT, 'Interval between two refreshes', false, true);
            $preferences->put('badge', true, dcWorkspace::WS_BOOL, 'Display counter (Auto refresh only)', false, true);
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        return true;
    }
}
