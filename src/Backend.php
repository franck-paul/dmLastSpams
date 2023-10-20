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

class Backend extends Process
{
    public static function init(): bool
    {
        // dead but useful code, in order to have translations
        __('Last Spams Dashboard Module') . __('Display last spams on dashboard');

        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        App::behavior()->addBehaviors([
            // Dashboard behaviours
            'adminDashboardHeaders'    => BackendBehaviors::adminDashboardHeaders(...),
            'adminDashboardContentsV2' => BackendBehaviors::adminDashboardContents(...),

            'adminAfterDashboardOptionsUpdate' => BackendBehaviors::adminAfterDashboardOptionsUpdate(...),
            'adminDashboardOptionsFormV2'      => BackendBehaviors::adminDashboardOptionsForm(...),
        ]);

        App::rest()->addFunction('dmLastSpamsCheck', BackendRest::checkNewSpams(...));
        App::rest()->addFunction('dmLastSpamsRows', BackendRest::getLastSpamsRows(...));
        App::rest()->addFunction('dmLastSpamsCount', BackendRest::getSpamsCount(...));

        return true;
    }
}
