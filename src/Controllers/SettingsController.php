<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\NotificationService;
use App\Services\Settings;

final class SettingsController
{
    public function index(Request $request): void
    {
        view('settings/index', [
            'title' => 'Configurações',
            'settings' => Settings::all(),
            'hours' => Connection::all('SELECT * FROM operating_hours ORDER BY weekday'),
            'templates' => Connection::all('SELECT id, type, name, version FROM document_templates ORDER BY name'),
            'template' => Connection::one('SELECT * FROM document_templates WHERE type = ?', [(string) $request->query('modelo', 'termo_responsabilidade')]),
            'email' => (new NotificationService())->configured('email'),
            'whatsapp' => (new NotificationService())->configured('whatsapp'),
        ]);
    }

    public function save(Request $request): void
    {
        $keys = ['company_name', 'company_document', 'company_phone', 'company_email', 'company_address', 'company_city', 'company_state', 'company_zip', 'company_website', 'buffer_minutes', 'alert_hours', 'session_hours', 'document_retention_months', 'cancellation_policy', 'reschedule_policy', 'quote_conditions'];
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $request->str($key);
        }
        Settings::setMany($values, Auth::id());
        foreach ((array) $request->input('hours', []) as $weekday => $hour) {
            Connection::run('UPDATE operating_hours SET opens_at = ?, closes_at = ?, active = ? WHERE weekday = ?', [
                $hour['opens'] ?? '09:00', $hour['closes'] ?? '18:00', !empty($hour['active']) ? 1 : 0, (int) $weekday,
            ]);
        }
        redirect_with('/configuracoes', 'ok', 'Configurações salvas.');
    }

    public function template(Request $request): void
    {
        $type = $request->str('type');
        Connection::run('UPDATE document_templates SET body = ?, version = version + 1, updated_at = ?, updated_by = ? WHERE type = ?', [
            $request->str('body'), now(), Auth::id(), $type,
        ]);
        redirect_with('/configuracoes?modelo=' . urlencode($type), 'ok', 'Modelo atualizado. Documentos já emitidos não são reescritos.');
    }
}
