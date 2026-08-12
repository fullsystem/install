<?php

declare(strict_types=1);

return [
    'name' => 'fullsystem/starter',
    'version' => '1.0.0',
    'phases' => [
        'pre-install' => [
            ['remove', ['routes/web.php']],
            ['composer', [
                ['laravel/ai'],
                ['baconfy/factory-payload'],
            ]],
        ],
        'post-install' => [
            'artisan' => [
                'wayfinder:generate --with-form',
            ],
        ],
    ],
];
