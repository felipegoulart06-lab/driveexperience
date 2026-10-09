<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\ImportService;
use App\Services\InboundWebhookService;
use App\Services\IntegrationService;
use App\Services\NotificationService;

final class IntegrationController
{
    public function index(Request $request): void
    {
        $hooks = new InboundWebhookService();
        view('integrations/index', [
            'title' => 'Integrações',
            'webhooks' => array_map(static function (array $hook) use ($hooks): array {
                $hook['url'] = $hooks->url($hook);
                return $hook;
            }, $hooks->list()),
            'platforms' => Connection::all('SELECT id, name, slug, status, connector, active, trust_payment, last_sync_at, notes FROM platforms WHERE slug NOT LIKE ? ORDER BY name', ['site-%']),
            'logs' => Connection::all('SELECT s.*, p.name AS platform_name FROM sync_logs s LEFT JOIN platforms p ON p.id = s.platform_id ORDER BY s.id DESC LIMIT 30'),
            'batches' => Connection::all('SELECT * FROM import_batches ORDER BY id DESC LIMIT 15'),
            'notifications' => [
                'email' => (new NotificationService())->configured('email'),
                'whatsapp' => (new NotificationService())->configured('whatsapp'),
            ],
        ]);
    }

    public function createWebhook(Request $request): void
    {
        $id = (new InboundWebhookService())->create($request->str('name'), $request->str('allowed_domains'));
        redirect_with('/integracoes/webhooks/' . $id, 'ok', 'Webhook criado. Ative o modo teste, cole o link no site e envie um pedido de teste.');
    }

    public function webhook(Request $request, string $id): void
    {
        $hooks = new InboundWebhookService();
        $hook = $hooks->find((int) $id);
        view('integrations/webhook', [
            'title' => $hook['name'],
            'hook' => $hook,
            'url' => $hooks->url($hook),
            'fields' => $hooks->fields(),
            'mapping' => $hooks->mapping($hook),
            'samples' => $hooks->samples((int) $id),
        ]);
    }

    public function webhookTests(Request $request, string $id): void
    {
        json_out(['samples' => (new InboundWebhookService())->samples((int) $id)]);
    }

    public function updateWebhook(Request $request, string $id): void
    {
        $hooks = new InboundWebhookService();
        $hooks->update((int) $id, $request->all());
        if ($request->str('action') === 'import') {
            $result = $hooks->importCapture((int) $id, (int) $request->input('sample_id'), (int) Auth::id());
            redirect_with('/integracoes/webhooks/' . (int) $id, $result['ok'] ? 'ok' : 'erro', $result['message']);
        }
        redirect_with('/integracoes/webhooks/' . (int) $id, 'ok', 'Webhook atualizado.');
    }

    public function deleteWebhook(Request $request, string $id): void
    {
        (new InboundWebhookService())->delete((int) $id);
        redirect_with('/integracoes', 'ok', 'Webhook removido.');
    }

    public function savePlatform(Request $request): void
    {
        $id = (new IntegrationService())->savePlatform($request->all(), null, (int) Auth::id());
        redirect_with('/integracoes', 'ok', 'Plataforma registrada. Sem segredo, o conector permanece pendente.');
    }

    public function updatePlatform(Request $request, string $id): void
    {
        (new IntegrationService())->savePlatform($request->all(), (int) $id, (int) Auth::id());
        redirect_with('/integracoes', 'ok', 'Plataforma atualizada.');
    }

    public function upload(Request $request): void
    {
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            back_with_errors(['file' => 'Envie um arquivo CSV ou XLSX.'], '/integracoes');
        }
        $content = (string) file_get_contents((string) $file['tmp_name']);
        $id = (new ImportService())->upload((string) $file['name'], $content, $request->int('platform_id'), (int) Auth::id());
        redirect('/integracoes/importar/' . $id);
    }

    public function map(Request $request, string $id): void
    {
        $imports = new ImportService();
        $batch = $imports->batch((int) $id);
        view('integrations/import', [
            'title' => 'Mapear importação',
            'batch' => $batch,
            'fields' => $imports->fields(),
            'rows' => Connection::all('SELECT * FROM import_rows WHERE batch_id = ? ORDER BY row_number', [(int) $id]),
        ]);
    }

    public function preview(Request $request, string $id): void
    {
        (new ImportService())->preview((int) $id, (array) $request->input('map', []), (int) Auth::id());
        redirect_with('/integracoes/importar/' . $id, 'ok', 'Prévia gerada. Confira duplicidades e erros antes de importar.');
    }

    public function confirm(Request $request, string $id): void
    {
        $counts = (new ImportService())->confirm((int) $id, (int) Auth::id());
        redirect_with('/integracoes/importar/' . $id, 'ok', $counts['imported'] . ' importada(s), ' . $counts['skipped'] . ' ignorada(s), ' . $counts['errors'] . ' com erro.');
    }

    public function reprocess(Request $request, string $id): void
    {
        $result = (new IntegrationService())->reprocess((int) $id, (int) Auth::id());
        redirect_with('/integracoes', ($result['status'] ?? '') === 'error' ? 'erro' : 'ok', $result['message'] ?? 'Reprocessamento concluído.');
    }
}
