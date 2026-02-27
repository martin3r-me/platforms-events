<?php

/**
 * Events Module Configuration
 *
 * @see Platform\Core\PlatformCore::registerModule() für Details zur Modul-Registrierung
 */

return [
    'routing' => [
        'mode' => env('EVENTS_MODE', 'path'),
        'prefix' => 'events',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'events.dashboard',
        'icon'  => 'heroicon-o-calendar-days',
        'order' => 100,
    ],

    'sidebar' => [
        [
            'group' => 'Allgemein',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'events.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
                [
                    'label' => 'Test',
                    'route' => 'events.test',
                    'icon'  => 'heroicon-o-beaker',
                ],
            ],
        ],
    ],
];
