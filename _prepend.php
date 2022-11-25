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
if (!defined('DC_RC_PATH')) {
    return;
}

// Public and Admin mode

if (!defined('DC_CONTEXT_ADMIN')) {
    return false;
}

// Admin mode

Clearbricks::lib()->autoload(['dmLastSpamsRest' => __DIR__ . '/_services.php']);

// Register REST methods
dcCore::app()->rest->addFunction('dmLastSpamsCheck', [dmLastSpamsRest::class, 'checkNewSpams']);
dcCore::app()->rest->addFunction('dmLastSpamsRows', [dmLastSpamsRest::class, 'getLastSpamsRows']);
dcCore::app()->rest->addFunction('dmLastSpamsCount', [dmLastSpamsRest::class, 'getSpamsCount']);
