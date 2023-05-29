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
use dcNsProcess;

class Backend extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init = My::checkContext(My::BACKEND);

        // dead but useful code, in order to have translations
        __('Last Spams Dashboard Module') . __('Display last spams on dashboard');

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        dcCore::app()->addBehaviors([
            // Dashboard behaviours
            'adminDashboardHeaders'    => [BackendBehaviors::class, 'adminDashboardHeaders'],
            'adminDashboardContentsV2' => [BackendBehaviors::class, 'adminDashboardContents'],

            'adminAfterDashboardOptionsUpdate' => [BackendBehaviors::class, 'adminAfterDashboardOptionsUpdate'],
            'adminDashboardOptionsFormV2'      => [BackendBehaviors::class, 'adminDashboardOptionsForm'],
        ]);

        dcCore::app()->rest->addFunction('dmLastSpamsCheck', [BackendRest::class, 'checkNewSpams']);
        dcCore::app()->rest->addFunction('dmLastSpamsRows', [BackendRest::class, 'getLastSpamsRows']);
        dcCore::app()->rest->addFunction('dmLastSpamsCount', [BackendRest::class, 'getSpamsCount']);

        return true;
    }
}
