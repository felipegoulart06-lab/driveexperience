<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\NotificationService;
use App\Services\Settings;
use App\Services\TwoFactor;
use App\Services\WhatsAppLink;
use App\Support\AuthException;
use App\Support\ValidationException;

final class SettingsController
{
    public function index(Request $request): void
    {
        $this->onlyAdmin();
        view('settings/index', [
            'title' => 'Configurações',
            'settings' => Settings::all(),
            'hours' => Connection::all('SELECT * FROM operating_hours ORDER BY weekday'),
            'templates' => Connection::all('SELECT id, type, name, version FROM document_templates ORDER BY name'),
            'template' => Connection::one('SELECT * FROM document_templates WHERE type = ?', [(string) $request->query('modelo', 'termo_responsabilidade')]),
            'email' => (new NotificationService())->configured('email'),
            'whatsapp' => (new NotificationService())->configured('whatsapp'),
            'wa' => (new WhatsAppLink())->view(),
        ]);
    }

    public function whatsappTwoFactor(Request $request): void
    {
        $this->onlyAdmin();
        try {
            json_out(TwoFactor::begin());
        } catch (\RuntimeException $exception) {
            json_out(['error' => $exception->getMessage()], 422);
        }
    }

    public function whatsappTwoFactorActivate(Request $request): void
    {
        $this->onlyAdmin();
        try {
            TwoFactor::activate($request->str('code'));
        } catch (\RuntimeException $exception) {
            json_out(['error' => $exception->getMessage()], 422);
        }
        $state = (new WhatsAppLink())->view();
        $state['message'] = '2FA ativo. Informe o código de novo para gerar o QR Code do WhatsApp.';
        json_out($state);
    }

    public function whatsapp(Request $request): void
    {
        $this->onlyAdmin();
        json_out((new WhatsAppLink())->refresh());
    }

    public function whatsappConnect(Request $request): void
    {
        $this->onlyAdmin();
        try {
            json_out((new WhatsAppLink())->beginQr($request->str('code')));
        } catch (\RuntimeException $exception) {
            json_out(['error' => $exception->getMessage()], 422);
        }
    }

    public function whatsappDisconnect(Request $request): void
    {
        $this->onlyAdmin();
        if ($request->str('confirm') !== 'sim' || $request->str('again') !== 'sim') {
            json_out(['error' => 'Confirme a desconexão duas vezes.'], 422);
        }
        try {
            json_out((new WhatsAppLink())->disconnect($request->str('code')));
        } catch (\RuntimeException $exception) {
            json_out(['error' => $exception->getMessage()], 422);
        }
    }

    public function save(Request $request): void
    {
        $this->onlyAdmin();
        $section = $request->str('section', 'empresa');
        if ($section === 'empresa') {
            $values = [];
            foreach (['company_name', 'company_document', 'company_phone', 'company_email', 'company_address', 'company_city', 'company_state', 'company_zip', 'company_website', 'google_review_url', 'play_store_url'] as $key) {
                $values[$key] = $request->str($key);
            }
            if (trim($values['company_name']) === '') {
                throw new ValidationException(['company_name' => 'Informe o nome da empresa.']);
            }
            foreach (['google_review_url', 'play_store_url'] as $link) {
                if ($values[$link] !== '' && !preg_match('#^https://#', $values[$link])) {
                    throw new ValidationException([$link => 'O link precisa começar com https://.']);
                }
            }
            $logoId = $this->storeImage('company_logo', (int) Settings::get('company_logo_file_id', 0));
            $signId = $this->storeImage('company_signature', (int) Settings::get('company_signature_file_id', 0));
            if ($logoId > 0) {
                $values['company_logo_file_id'] = (string) $logoId;
            }
            if ($signId > 0) {
                $values['company_signature_file_id'] = (string) $signId;
            }
            Settings::setMany($values, Auth::id());
        } elseif ($section === 'operacao') {
            $values = [];
            foreach (['buffer_minutes', 'alert_hours', 'session_hours', 'document_retention_months'] as $key) {
                $values[$key] = $request->str($key);
            }
            Settings::setMany($values, Auth::id());
        } elseif ($section === 'politicas') {
            Settings::setMany([
                'cancellation_policy' => $request->str('cancellation_policy'),
                'reschedule_policy' => $request->str('reschedule_policy'),
                'quote_conditions' => $request->str('quote_conditions'),
            ], Auth::id());
        } elseif ($section === 'whatsapp') {
            Settings::set('wa_send_receipt', $request->str('wa_send_receipt') === '1' ? '1' : '0', Auth::id());
        } elseif ($section === 'horarios') {
            foreach ((array) $request->input('hours', []) as $weekday => $hour) {
                Connection::run('UPDATE operating_hours SET opens_at = ?, closes_at = ?, active = ? WHERE weekday = ?', [
                    $hour['opens'] ?? '09:00', $hour['closes'] ?? '18:00', !empty($hour['active']) ? 1 : 0, (int) $weekday,
                ]);
            }
        }
        $tabs = ['empresa', 'operacao', 'politicas', 'horarios', 'modelos', 'whatsapp'];
        $back = in_array($section, $tabs, true) ? $section : 'empresa';
        redirect_with('/configuracoes?aba=' . $back, 'ok', 'Configurações salvas.');
    }

    public function template(Request $request): void
    {
        $this->onlyAdmin();
        $type = $request->str('type');
        Connection::run('UPDATE document_templates SET body = ?, version = version + 1, updated_at = ?, updated_by = ? WHERE type = ?', [
            $request->str('body'), now(), Auth::id(), $type,
        ]);
        redirect_with('/configuracoes', 'ok', 'Modelo atualizado. Documentos já emitidos não são reescritos.');
    }

    public function logo(Request $request): void
    {
        $this->onlyAdmin();
        $this->serveFile((int) Settings::get('company_logo_file_id', 0));
    }

    public function signature(Request $request): void
    {
        $this->onlyAdmin();
        $this->serveFile((int) Settings::get('company_signature_file_id', 0));
    }

    private function onlyAdmin(): void
    {
        if ((Auth::user()['role_slug'] ?? '') !== 'administrador') {
            throw new AuthException('Somente o administrador altera os dados da empresa.');
        }
    }

    private function storeImage(string $field, int $currentId): int
    {
        $upload = $_FILES[$field] ?? null;
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $currentId;
        }
        if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException([$field => 'Não foi possível receber o arquivo.']);
        }
        if ((int) $upload['size'] > 4 * 1024 * 1024) {
            throw new ValidationException([$field => 'A imagem pode ter no máximo 4 MB.']);
        }
        $binary = file_get_contents((string) $upload['tmp_name']);
        if (!is_string($binary) || $binary === '') {
            throw new ValidationException([$field => 'Arquivo inválido.']);
        }
        $mime = $this->jpegOrPng($binary);
        if ($mime === null) {
            throw new ValidationException([$field => 'Envie apenas JPG ou PNG.']);
        }
        return Connection::insert(
            'INSERT INTO stored_files (mime, byte_size, content, created_at) VALUES (?, ?, ?, ?)',
            [$mime, strlen($binary), $binary, now()]
        );
    }

    private function jpegOrPng(string $binary): ?string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        return null;
    }

    private function serveFile(int $id): void
    {
        if ($id <= 0) {
            http_response_code(404);
            exit;
        }
        $file = Connection::one('SELECT mime, content FROM stored_files WHERE id = ?', [$id]);
        if (!$file) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . (string) $file['mime']);
        header('Cache-Control: private, max-age=3600');
        echo $file['content'];
        exit;
    }
}
