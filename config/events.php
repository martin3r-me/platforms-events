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
            'group' => 'Vorlagen',
            'items' => [
                [
                    'label' => 'Pakete',
                    'route' => 'events.articles',
                    'icon'  => 'heroicon-o-archive-box',
                ],
            ],
        ],
        [
            'group' => 'Verwaltung',
            'items' => [
                [
                    'label' => 'Einstellungen',
                    'route' => 'events.settings',
                    'icon'  => 'heroicon-o-cog-6-tooth',
                ],
            ],
        ],
    ],
];
