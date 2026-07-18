<?php

declare(strict_types=1);

/**
 * Copy this file to config.local.php and adjust for your host.
 * config.local.php is gitignored and must never be committed.
 */
return [
    'base_url' => 'https://lab.example.com',
    'app_timezone' => 'America/Los_Angeles',
    'environment' => 'production',
    'debug' => false,
    'session' => [
        // Leave null to derive from base_url path, or set explicitly:
        // 'path' => '/saas-lab/',
        'path' => null,
        'secure' => true,
    ],
];
