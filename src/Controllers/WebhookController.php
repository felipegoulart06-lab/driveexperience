<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Services\InboundWebhookService;
use App\Services\IntegrationService;

final class WebhookController
{
    public function receive(Request $request, string $key): void
    {
        $result = (new IntegrationService())->receive($key, $request->rawBody(), $request->header('X-DX-Signature'));
        json_out($result['body'], $result['status']);
    }

    public function inbound(Request $request, string $token): void
    {
        $result = (new InboundWebhookService())->receive($token, $request);
        json_out($result['body'], $result['status']);
    }
}
