<?php

declare(strict_types=1);

/**
 * Committed default configuration for SaaS Lab.
 * Copy values into config.local.php for deployment-specific overrides.
 */
return [
    'app_name' => 'SaaS Lab',
    'base_url' => 'https://lab.example.com',
    'app_timezone' => 'America/Los_Angeles',
    'environment' => 'production',
    'debug' => false,
    'session' => [
        'name' => 'saas_lab_session',
        'lifetime' => 86400,
        // null => derive from base_url path (covers subdirectory installs)
        'path' => null,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    'reserved_project_slugs' => [
        'account',
        'admin',
        'api',
        'assets',
        'founder',
        'install',
        'login',
        'logout',
        'p',
        'profile',
        'projects',
        'public',
        'register',
        'storage',
        'templates',
    ],
    'password_min_length' => 8,
    'approved_templates' => [
        'logged-in-prototype',
    ],
];
