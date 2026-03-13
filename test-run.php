<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    \Illuminate\Support\Facades\Artisan::call('test', ['--filter' => 'SeatingModuleTest']);
    echo \Illuminate\Support\Facades\Artisan::output();
} catch (\Throwable $e) {
    file_put_contents('error_dump.txt', $e->getMessage() . "\n" . $e->getTraceAsString());
}
