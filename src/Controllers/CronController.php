<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Services\NotificationService;

final class CronController
{
    public function alerts(Request $request): void
    {
        $secret = (string) env('CRON_SECRET', '');
        $header = $request->header('Authorization');
        if ($secret === '' || !hash_equals('Bearer ' . $secret, $header)) {
            json_out(['error' => 'Não autorizado.'], 401);
        }
        $notifications = new NotificationService();
        $created = $notifications->alertUpcoming();
        $notifications->cleanup();
        json_out(['ok' => true, 'alertas' => $created]);
    }
}
