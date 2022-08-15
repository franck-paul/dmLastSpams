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

$this->registerModule(
    'Last Spams Dashboard Module',     // Name
    'Display last spams on dashboard', // Description
    'Franck Paul',                     // Author
    '0.4.1',
    [
        'requires'    => [['core', '2.23']],
        'permissions' => 'admin',
        'type'        => 'plugin',
        'settings'    => [                                             // Settings
            'pref' => '#user-favorites.dmlastspams',
        ],

        'details'    => 'https://open-time.net/?q=dmlastspams',       // Details URL
        'support'    => 'https://github.com/franck-paul/dmlastspams', // Support URL
        'repository' => 'https://raw.githubusercontent.com/franck-paul/dmlastspams/master/dcstore.xml',
    ]
);
