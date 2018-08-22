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

if (!defined('DC_RC_PATH')) {return;}

$this->registerModule(
    "Last Spams Dashboard Module",     // Name
    "Display last spams on dashboard", // Description
    "Franck Paul",                     // Author
    '0.1',                             // Version
    array(
        'requires'    => array(array('core', '2.15')),
        'permissions' => 'admin',
        'support'     => 'https://open-time.net/?q=dmlastspams', // Support URL
        'type'        => 'plugin'
    )
);
