<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Database\Schema;
use App\Http\Request;

final class HealthController
{
    public function show(Request $request): void
    {
        $database = false;
        try {
            $database = Connection::value('SELECT 1') == 1;
        } catch (\Throwable) {
        }
        json_out([
            'ok' => $database,
            'php' => PHP_VERSION,
            'database' => $database,
            'driver' => $database ? Connection::driver() : null,
            'installed' => $database && Schema::installed(),
            'pdf' => class_exists(\Dompdf\Dompdf::class),
            'storage' => (string) env('STORAGE_DRIVER', 'database'),
        ]);
    }
}
