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

if (!dcCore::app()->newVersion(basename(__DIR__), dcCore::app()->plugins->moduleInfo(basename(__DIR__), 'version'))) {
    return;
}

try {
    // Default prefs for last spams
    dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams', false, 'boolean', 'Display last spams', false, true);
    dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_nb', 5, 'integer', 'Number of last spams displayed', false, true);
    dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_large', true, 'boolean', 'Large display', false, true);
    dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_author', true, 'boolean', 'Show authors', false, true);
    dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_date', true, 'boolean', 'Show dates', false, true);
    dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_time', true, 'boolean', 'Show times', false, true);
    dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_recents', 0, 'integer', 'Max age of spams (in hours)', false, true);
    dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_autorefresh', false, 'boolean', 'Auto refresh', false, true);
    dcCore::app()->auth->user_prefs->dmlastspams->put('last_spams_badge', true, 'boolean', 'Display counter (Auto refresh only)', false, true);

    return true;
} catch (Exception $e) {
    dcCore::app()->error->add($e->getMessage());
}

return false;
