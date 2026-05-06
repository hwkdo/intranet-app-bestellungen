<?php

declare(strict_types=1);

return [
    'roles' => [
        'admin' => [
            'name' => 'App-Bestellungen-Admin',
            'permissions' => [
                'see-app-bestellungen',
                'manage-app-bestellungen',
            ],
        ],
        'user' => [
            'name' => 'Benutzer',
            'permissions' => [
                'see-app-bestellungen',
            ],
            'add_to_existing' => true,
        ],
        'role_0_500' => [
            'name' => 'App-Bestellungen-0-Bis-500',
            'permissions' => [],
        ],
        'role_500_5000' => [
            'name' => 'App-Bestellungen-500-Bis-5000',
            'permissions' => [],
        ],
        'role_5000_25000' => [
            'name' => 'App-Bestellungen-5000-Bis-25000',
            'permissions' => [],
        ],
        'role_in_auftrag' => [
            'name' => 'App-Bestellungen-In-Auftrag',
            'permissions' => [],
        ],
    ],
];
