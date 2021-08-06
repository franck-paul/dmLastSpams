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

$new_version = $core->plugins->moduleInfo('dmLastSpams', 'version');
$old_version = $core->getVersion('dmLastSpams');

if (version_compare($old_version, $new_version, '>=')) {
    return;
}

try {
    $core->auth->user_prefs->addWorkspace('dmlastspams');

    // Default prefs for last spams
    $core->auth->user_prefs->dmlastspams->put('last_spams', false, 'boolean', 'Display last spams', false, true);
    $core->auth->user_prefs->dmlastspams->put('last_spams_nb', 5, 'integer', 'Number of last spams displayed', false, true);
    $core->auth->user_prefs->dmlastspams->put('last_spams_large', true, 'boolean', 'Large display', false, true);
    $core->auth->user_prefs->dmlastspams->put('last_spams_author', true, 'boolean', 'Show authors', false, true);
    $core->auth->user_prefs->dmlastspams->put('last_spams_date', true, 'boolean', 'Show dates', false, true);
    $core->auth->user_prefs->dmlastspams->put('last_spams_time', true, 'boolean', 'Show times', false, true);
    $core->auth->user_prefs->dmlastspams->put('last_spams_recents', 0, 'integer', 'Max age of spams (in hours)', false, true);
    $core->auth->user_prefs->dmlastspams->put('last_spams_autorefresh', false, 'boolean', 'Auto refresh', false, true);
    $core->auth->user_prefs->dmlastspams->put('last_spams_badge', true, 'boolean', 'Display counter (Auto refresh only)', false, true);

    $core->setVersion('dmLastSpams', $new_version);

    return true;
} catch (Exception $e) {
    $core->error->add($e->getMessage());
}

return false;
