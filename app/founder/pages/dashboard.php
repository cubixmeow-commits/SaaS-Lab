<?php

declare(strict_types=1);

require_installed();
auth()->requireAdmin();

view('founder/dashboard', [
    'title' => 'Founder Dashboard',
    'summary' => FounderMetrics::summary(),
    'rows' => FounderMetrics::projectRows(),
    'flashes' => flash_messages(),
]);
