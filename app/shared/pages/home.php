<?php

declare(strict_types=1);

require_installed();

view('shared/home', [
    'title' => config('app_name', 'SaaS Lab'),
    'user' => auth()->user(),
    'flashes' => flash_messages(),
]);
