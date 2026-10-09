<?php

declare(strict_types=1);

return [
    'api' => [
        'max_upload_kb' => (int) env('INTRANET_APP_BESTELLUNGEN_API_MAX_UPLOAD_KB', 10240),
        'allowed_mimes' => ['application/pdf'],
        'allowed_extensions' => ['pdf'],
        'extract_queue' => env('INTRANET_APP_BESTELLUNGEN_API_EXTRACT_QUEUE'),
    ],

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
        'interne_besteller' => [
            'name' => 'App-Bestellungen-InterneBesteller',
            'permissions' => [],
        ],
    ],
];
