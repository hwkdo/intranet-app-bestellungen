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
            'name' => 'App-Bestellungen-Benutzer',
            'permissions' => [
                'see-app-bestellungen',
            ],
        ],
    ],
];
