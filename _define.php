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
$this->registerModule(
    'Last Spams Dashboard Module',
    'Display last spams on dashboard',
    'Franck Paul',
    '7.0',
    [
        'date'     => '2025-06-03T17:39:44+0200',
        'requires' => [
            ['core', '2.34'],
            ['dmHelper', '5.0'],
        ],
        'permissions' => 'My',
        'type'        => 'plugin',
        'settings'    => [
            'pref' => '#user-favorites.dmlastspams',
        ],

        'details'    => 'https://open-time.net/?q=dmlastspams',
        'support'    => 'https://github.com/franck-paul/dmlastspams',
        'repository' => 'https://raw.githubusercontent.com/franck-paul/dmlastspams/main/dcstore.xml',
        'license'    => 'gpl2',
    ]
);
