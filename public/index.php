<?php

use App\Kernel;

// Dev uploads (photo + video): Symfony profiler + large bodies need more headroom than CLI defaults.
if (($_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: '') === 'dev') {
    ini_set('memory_limit', '512M');
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
