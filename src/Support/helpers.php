<?php

declare(strict_types=1);

require_once __DIR__ . '/Exceptions.php';

use App\Http\Session;

function base_path(string $path = ''): string
{
    $root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function env_load(string $file): void
{
    if (!is_file($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        $name = trim($parts[0]);
        $value = trim($parts[1] ?? '', " \t\"'");
        if ($name === '') {
            continue;
        }
        $current = getenv($name);
        if ($current !== false && $current !== '') {
            continue;
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function parse_date(mixed $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) && checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
        return $value;
    }
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) && checkdate((int) $matches[2], (int) $matches[1], (int) $matches[3])) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    return null;
}

function parse_time(mixed $value): ?string
{
    $value = trim((string) $value);
    if (!preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
        return null;
    }
    $hour = (int) $matches[1];
    $minute = (int) $matches[2];
    if ($hour > 23 || $minute > 59) {
        return null;
    }
    return sprintf('%02d:%02d', $hour, $minute);
}

function money(int|float|string|null $cents): string
{
    return 'R$ ' . number_format(((int) $cents) / 100, 2, ',', '.');
}

function parse_money(mixed $value): ?int
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return 0;
    }
    $brazilian = '/^\d{1,3}(\.\d{3})*,\d{2}$/';
    $plain = '/^\d+([.,]\d{1,2})?$/';
    if (!preg_match($brazilian, $raw) && !preg_match($plain, $raw)) {
        return null;
    }
    if (str_contains($raw, ',')) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    }
    if (!is_numeric($raw)) {
        return null;
    }
    return (int) round(((float) $raw) * 100);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function date_br(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }
    $time = strtotime($value);
    if ($time === false) {
        return '—';
    }
    return strlen(trim($value)) > 10 ? date('d/m/Y H:i', $time) : date('d/m/Y', $time);
}

function add_minutes(string $dateTime, int $minutes): string
{
    return (new DateTimeImmutable($dateTime))->modify(($minutes >= 0 ? '+' : '') . $minutes . ' minutes')->format('Y-m-d H:i:s');
}

function only_digits(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

function phone_digits(?string $value): string
{
    $digits = only_digits($value);
    if (str_starts_with($digits, '55') && strlen($digits) > 11) {
        $digits = substr($digits, 2);
    }
    return $digits;
}

function whatsapp_url(?string $phone, ?string $whatsapp): ?string
{
    $number = whatsapp_number($phone, $whatsapp);
    return $number === null ? null : 'https://wa.me/' . $number;
}

function whatsapp_number(?string $phone, ?string $whatsapp): ?string
{
    $source = trim((string) (($whatsapp !== null && $whatsapp !== '') ? $whatsapp : $phone));
    $digits = only_digits($source);
    if ($digits === '') {
        return null;
    }
    if (!str_starts_with($source, '+') && (strlen($digits) === 10 || strlen($digits) === 11)) {
        $digits = '55' . $digits;
    }
    if (strlen($digits) < 12 || strlen($digits) > 15) {
        return null;
    }
    return $digits;
}

function message_variables(array $row): array
{
    $name = trim((string) ($row['client_name'] ?? $row['full_name'] ?? ''));
    $vehicle = trim((string) ($row['brand'] ?? '') . ' ' . (string) ($row['model'] ?? ''));
    return [
        ['token' => '{nome}', 'label' => 'Nome', 'value' => $name],
        ['token' => '{codigo}', 'label' => 'Nº da reserva', 'value' => (string) ($row['code'] ?? '')],
        ['token' => '{experiencia}', 'label' => 'Experiência', 'value' => (string) ($row['experience'] ?? '')],
        ['token' => '{veiculo}', 'label' => 'Veículo', 'value' => $vehicle],
        ['token' => '{data}', 'label' => 'Data', 'value' => date_br((string) ($row['scheduled_date'] ?? ''))],
        ['token' => '{horario}', 'label' => 'Horário', 'value' => substr((string) ($row['start_time'] ?? ''), 0, 5)],
        ['token' => '{status}', 'label' => 'Status', 'value' => \App\Domain\Statuses::label('operational', (string) ($row['operational_status'] ?? ''))],
        ['token' => '{participantes}', 'label' => 'Participantes', 'value' => (string) ($row['participants'] ?? '')],
        ['token' => '{valor}', 'label' => 'Valor', 'value' => money((int) ($row['final_cents'] ?? 0))],
    ];
}

function booking_actions(array $row): void
{
    $id = (int) ($row['id'] ?? 0);
    $name = trim((string) ($row['client_name'] ?? $row['full_name'] ?? ''));
    $when = trim(date_br((string) ($row['scheduled_date'] ?? '')) . ' ' . substr((string) ($row['start_time'] ?? ''), 0, 5));
    $text = 'Olá' . ($name !== '' ? ', ' . $name : '') . '. Sua reserva ' . (string) ($row['code'] ?? '') . ($when !== '' ? ' está marcada para ' . $when : '') . '.';
    $number = whatsapp_number($row['phone'] ?? null, $row['whatsapp'] ?? null);
    $email = trim((string) ($row['email'] ?? ''));
    $mail = $email !== '' ? 'mailto:' . $email . '?subject=' . rawurlencode('Reserva ' . (string) ($row['code'] ?? '')) . '&body=' . rawurlencode($text) : '';
    echo '<div class="booking-actions">';
    echo '<a class="ghost icon-detail" href="/reservas/' . $id . '" aria-label="Ver detalhes" title="Ver detalhes">';
    echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    echo '</a>';
    echo '<details class="communicate"><summary>Comunicar</summary><div class="communicate-menu">';
    if ($number) {
        $pack = [
            'id' => $id,
            'name' => $name,
            'phone' => phone_display($number),
            'configured' => (new \App\Services\NotificationService())->configured('whatsapp'),
            'draft' => 'Olá, {nome}. Sua reserva {codigo} de {experiencia} no {veiculo} está marcada para {data} às {horario}.',
            'vars' => message_variables($row),
        ];
        echo '<button type="button" data-whatsapp="' . e(json_encode($pack, JSON_UNESCAPED_UNICODE)) . '">WhatsApp</button>';
    }
    if ($mail !== '') {
        echo '<a href="' . e($mail) . '">E-mail</a>';
    }
    if (!$number && $mail === '') {
        echo '<p>Este cliente não tem telefone nem e-mail.</p>';
    }
    echo '</div></details>';
    if (\App\Auth\Auth::can('reservations.manage')) {
        echo '<a class="ghost" href="/reservas/' . $id . '?editar=1">Editar</a>';
    }
    echo '</div>';
}

function phone_display(?string $value): string
{
    $digits = phone_digits($value);
    if (strlen($digits) === 11) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
    }
    if (strlen($digits) === 10) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
    }
    $value = trim((string) $value);
    return $value;
}

function blank_to_null(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function person_name(string $value): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }
    $particles = ['da', 'de', 'do', 'das', 'dos', 'e'];
    $parts = preg_split('/\s/u', mb_strtolower($value)) ?: [];
    foreach ($parts as $index => $part) {
        if ($part === '') {
            continue;
        }
        if ($index > 0 && in_array($part, $particles, true)) {
            continue;
        }
        $parts[$index] = mb_strtoupper(mb_substr($part, 0, 1)) . mb_substr($part, 1);
    }
    return implode(' ', $parts);
}

function fold(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = strtr($value, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
        'é' => 'e', 'ê' => 'e', 'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
    ]);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
    return trim($value);
}

function valid_cpf(?string $cpf): bool
{
    $digits = only_digits($cpf);
    if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
        return false;
    }
    for ($size = 9; $size < 11; $size++) {
        $sum = 0;
        for ($i = 0; $i < $size; $i++) {
            $sum += (int) $digits[$i] * (($size + 1) - $i);
        }
        $digit = ((10 * $sum) % 11) % 10;
        if ((int) $digits[$size] !== $digit) {
            return false;
        }
    }
    return true;
}

function format_cpf(?string $cpf): string
{
    $digits = only_digits($cpf);
    if (strlen($digits) !== 11) {
        return '';
    }
    return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
}

function mask_cpf(?string $cpf, bool $full): string
{
    $digits = only_digits($cpf);
    if ($digits === '') {
        return '—';
    }
    if ($full) {
        return format_cpf($digits) ?: '—';
    }
    if (strlen($digits) !== 11) {
        return '***';
    }
    return substr($digits, 0, 3) . '.***.***-' . substr($digits, 9, 2);
}

function mask_plate(?string $plate, bool $full): string
{
    $plate = strtoupper(trim((string) $plate));
    if ($plate === '') {
        return '—';
    }
    if ($full) {
        return $plate;
    }
    if (strlen($plate) < 3) {
        return '***';
    }
    return substr($plate, 0, 3) . '-****';
}

function password_is_strong(string $password): bool
{
    return strlen($password) >= 12
        && preg_match('/[A-Za-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1;
}

function like_term(string $value): string
{
    $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value));
    return '%' . $value . '%';
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 302);
    exit;
}

function safe_referer(string $fallback): string
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $parts = parse_url($referer);
    if (!is_array($parts) || ($parts['host'] ?? '') !== $host) {
        return $fallback;
    }
    $path = (string) ($parts['path'] ?? '/');
    if (!str_starts_with($path, '/')) {
        return $fallback;
    }
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return $path . $query;
}

function back_with_errors(array $errors, string $fallback = '/'): never
{
    $old = $_POST;
    foreach (['password', 'password_confirmation', '_csrf', 'secret', 'webhook_secret'] as $secret) {
        unset($old[$secret]);
    }
    Session::putErrors($errors, $old);
    redirect(safe_referer($fallback));
}

function redirect_with(string $path, string $type, string $message): never
{
    Session::flash($type, $message);
    redirect($path);
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function request_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function view(string $template, array $data = []): void
{
    try {
        $errors = array_merge(Session::pull('_errors') ?? [], $data['errors'] ?? []);
        $old = Session::pull('_old') ?? [];
        $flash = Session::pull('_flash');
    } catch (\Throwable) {
        $errors = $data['errors'] ?? [];
        $old = [];
        $flash = null;
    }
    $data['errors'] = $errors;
    $data['old'] = $old;
    $data['flash'] = $flash;
    $title = (string) ($data['title'] ?? 'Drive Xperience');
    $layout = (string) ($data['_layout'] ?? 'layouts/app.php');
    unset($data['_layout']);
    $render = static function (string $templateFile, array $vars): string {
        extract($vars, EXTR_SKIP);
        ob_start();
        require base_path($templateFile);
        return (string) ob_get_clean();
    };
    $content = $render('views/' . $template . '.php', $data);
    echo $render('views/' . $layout, ['title' => $title, 'content' => $content, 'flash' => $data['flash']]);
}

function field(array $old, array $source, string $key, mixed $default = ''): string
{
    if (array_key_exists($key, $old) && $old[$key] !== null && !is_array($old[$key])) {
        return (string) $old[$key];
    }
    if (array_key_exists($key, $source) && $source[$key] !== null && !is_array($source[$key])) {
        return (string) $source[$key];
    }
    return (string) $default;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(\App\Auth\Csrf::token()) . '">';
}

function nav_items(): array
{
    return [
        ['/', 'Painel', 'dashboard.view'],
        ['/clientes', 'Clientes', 'clients.view'],
        ['/reservas', 'Reservas', 'reservations.view'],
        ['/agenda', 'Agenda', 'agenda.view'],
        ['/veiculos', 'Veículos', 'vehicles.view'],
        ['/rastreio', 'Rastreio', 'tracking.view'],
        ['/produtos', 'Produtos e experiências', 'products.view'],
        ['/promocoes-app', 'Promoções do app', 'products.view'],
        ['/fotos', 'Pacotes de fotos', 'photos.view'],
        ['/financeiro', 'Financeiro', 'finance.view'],
        ['/documentos', 'Documentos', 'documents.view'],
        ['/relatorios', 'Relatórios', 'reports.view'],
        ['/orcamentos', 'Orçamentos', 'quotes.view'],
        ['/integracoes', 'Integrações', 'integrations.manage'],
        ['/usuarios', 'Usuários', 'users.manage'],
        ['/configuracoes', 'Configurações', 'settings.manage'],
    ];
}

function selected(bool $condition): string
{
    return $condition ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

function activity(string $type, string $summary, ?string $entity = null, ?int $entityId = null): void
{
    $user = \App\Auth\Auth::id();
    \App\Database\Connection::run(
        'INSERT INTO activities (type, summary, entity_type, entity_id, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$type, mb_substr($summary, 0, 300), $entity, $entityId, $user, now()]
    );
}

function audit(string $action, string $entity, ?int $entityId, array $changes = []): void
{
    $redacted = redact($changes);
    \App\Database\Connection::run(
        'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, changes_json, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            \App\Auth\Auth::id(),
            $action,
            $entity,
            $entityId,
            $redacted === [] ? null : json_encode($redacted, JSON_UNESCAPED_UNICODE),
            request_ip(),
            now(),
        ]
    );
}

function redact(array $data): array
{
    $clean = [];
    foreach ($data as $key => $value) {
        $name = (string) $key;
        if (preg_match('/password|secret|token|cpf|placa|plate|content|authorization|webhook/i', $name)) {
            $clean[$name] = '[restrito]';
            continue;
        }
        if (is_array($value)) {
            $clean[$name] = redact($value);
            continue;
        }
        $clean[$name] = is_scalar($value) || $value === null ? $value : '[omitido]';
    }
    return $clean;
}

function app_debug(): bool
{
    return env('APP_DEBUG', '0') === '1' && env('APP_ENV', 'local') !== 'production';
}

function demo_enabled(): bool
{
    try {
        return \App\Services\Settings::get('demo_loaded', '0') === '1';
    } catch (\Throwable) {
        return false;
    }
}
