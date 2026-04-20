<?php

/**
 * Events Module Configuration
 *
 * @see Platform\Core\PlatformCore::registerModule() für Details zur Modul-Registrierung
 */

return [
    'routing' => [
        'mode'   => env('EVENTS_MODE', 'path'),
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
            ],
        ],
        [
            'group' => 'Planung',
            'items' => [
                [
                    'label' => 'Veranstaltungen',
                    'route' => 'events.manage',
                    'icon'  => 'heroicon-o-calendar-days',
                ],
            ],
        ],
        [
            'group' => 'Stammdaten',
            'items' => [
                [
                    'label' => 'Artikel',
                    'route' => 'events.articles',
                    'icon'  => 'heroicon-o-squares-2x2',
                ],
            ],
        ],
    ],
];
