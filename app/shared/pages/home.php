<?php

declare(strict_types=1);

view('shared/home', [
    'title' => config('app_name', 'SaaS Lab'),
    'flashes' => flash_messages(),
]);
