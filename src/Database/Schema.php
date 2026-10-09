<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

final class Schema
{
    public static function installed(): bool
    {
        try {
            return Connection::value('SELECT version FROM schema_migrations WHERE version = ?', ['001']) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function migrate(): void
    {
        if (self::installed()) {
            self::ensure();
            return;
        }
        $driver = Connection::driver();
        $pdo = Connection::pdo();
        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        } elseif ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        }
        foreach (self::statements($driver) as $sql) {
            self::exec($pdo, $sql);
        }
        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } elseif ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        Connection::run('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)', ['001', now()]);
        self::ensure();
    }

    public static function ensure(): void
    {
        if (!self::installed()) {
            return;
        }
        $types = self::types(Connection::driver());
        $id = $types['id'];
        $ref = $types['ref'];
        $dt = $types['dt'];
        $text = $types['text'];
        $bool = $types['bool'];
        $engine = $types['engine'];
        $real = $types['real'];
        Connection::pdo()->exec("CREATE TABLE IF NOT EXISTS vehicle_images (\n{$id}, vehicle_id {$ref} NOT NULL, file_id {$ref} NOT NULL, filename VARCHAR(180) NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, is_cover {$bool} NOT NULL DEFAULT 0, created_at {$dt} NOT NULL, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE, FOREIGN KEY (file_id) REFERENCES stored_files(id)\n){$engine}");
        Connection::pdo()->exec("CREATE TABLE IF NOT EXISTS partners (\n{$id}, name VARCHAR(160) NOT NULL, phone VARCHAR(40) NULL, email VARCHAR(190) NULL, instagram VARCHAR(80) NULL, commission_bps INTEGER NOT NULL DEFAULT 0, notes {$text} NULL, active {$bool} NOT NULL DEFAULT 1, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL\n){$engine}");
        self::addColumn('clients', 'instagram', 'VARCHAR(80) NULL');
        self::addColumn('clients', 'cnh_file_id', "{$ref} NULL");
        self::addColumn('clients', 'residence_file_id', "{$ref} NULL");
        self::addColumn('reservations', 'partner_id', "{$ref} NULL");
        self::addColumn('reservations', 'commission_bps', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn('reservations', 'commission_cents', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumn('reservations', 'payment_plan', 'VARCHAR(20) NULL');
        Connection::pdo()->exec("CREATE TABLE IF NOT EXISTS trackers (\n{$id}, vehicle_id {$ref} NULL, name VARCHAR(120) NOT NULL, external_id VARCHAR(80) NOT NULL UNIQUE, active {$bool} NOT NULL DEFAULT 1, last_lat {$real} NULL, last_lng {$real} NULL, last_speed {$real} NULL, last_heading {$real} NULL, last_seen_at {$dt} NULL, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)\n){$engine}");
        Connection::pdo()->exec("CREATE TABLE IF NOT EXISTS tracker_positions (\n{$id}, tracker_id {$ref} NOT NULL, latitude {$real} NOT NULL, longitude {$real} NOT NULL, speed_kmh {$real} NULL, heading {$real} NULL, recorded_at {$dt} NOT NULL, FOREIGN KEY (tracker_id) REFERENCES trackers(id) ON DELETE CASCADE\n){$engine}");
        try {
            Connection::pdo()->exec('CREATE INDEX idx_tracker_positions_recent ON tracker_positions (tracker_id, recorded_at)');
        } catch (PDOException $exception) {
            $message = strtolower($exception->getMessage());
            if (!str_contains($message, 'duplicate') && !str_contains($message, 'exists')) {
                throw $exception;
            }
        }
        Connection::pdo()->exec("CREATE TABLE IF NOT EXISTS inbound_webhooks (\n{$id}, platform_id {$ref} NOT NULL, name VARCHAR(120) NOT NULL, token VARCHAR(80) NOT NULL UNIQUE, active {$bool} NOT NULL DEFAULT 0, test_mode {$bool} NOT NULL DEFAULT 1, allowed_domains {$text} NULL, mapping_json {$text} NULL, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL, FOREIGN KEY (platform_id) REFERENCES platforms(id)\n){$engine}");
        Connection::pdo()->exec("CREATE TABLE IF NOT EXISTS webhook_captures (\n{$id}, webhook_id {$ref} NOT NULL, payload_json {$text} NOT NULL, source_host VARCHAR(190) NULL, created_at {$dt} NOT NULL, FOREIGN KEY (webhook_id) REFERENCES inbound_webhooks(id) ON DELETE CASCADE\n){$engine}");
        self::grant('Ver rastreio', 'tracking.view', 'rastreio', ['administrador', 'operacional', 'atendimento']);
        self::grant('Gerir rastreio', 'tracking.manage', 'rastreio', ['administrador', 'operacional']);
        if (Connection::value('SELECT setting_key FROM settings WHERE setting_key = ?', ['tracking_token']) === null) {
            \App\Services\Settings::set('tracking_token', bin2hex(random_bytes(24)));
        }
        Connection::pdo()->exec("CREATE TABLE IF NOT EXISTS app_promos (\n{$id}, occasion VARCHAR(80) NOT NULL, title VARCHAR(80) NOT NULL, summary VARCHAR(240) NOT NULL, starts_on {$dt} NOT NULL, ends_on {$dt} NOT NULL, image_file_id {$ref} NULL, href VARCHAR(180) NOT NULL, active {$bool} NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, is_demo {$bool} NOT NULL DEFAULT 0, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL\n){$engine}");
        if (env('SEED_DEMO') === '1' && Connection::value('SELECT id FROM app_promos LIMIT 1') === null) {
            $stamp = now();
            Connection::insert(
                'INSERT INTO app_promos (occasion, title, summary, starts_on, ends_on, href, active, sort_order, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, 1, 1, ?, ?)',
                [
                    'Dia das Crianças',
                    'Outubro na pista',
                    '12 de outubro. Leve a família para a pista. A carona e a direção seguem a tabela da frota.',
                    date('Y-m-01'),
                    date('Y-m-t'),
                    '/experiencias',
                    $stamp,
                    $stamp,
                ]
            );
        }
    }

    private static function grant(string $name, string $slug, string $module, array $roles): void
    {
        $permissionId = Connection::value('SELECT id FROM permissions WHERE slug = ?', [$slug]);
        if (!$permissionId) {
            $permissionId = Connection::insert('INSERT INTO permissions (name, slug, module) VALUES (?, ?, ?)', [$name, $slug, $module]);
        }
        foreach ($roles as $role) {
            $roleId = Connection::value('SELECT id FROM roles WHERE slug = ?', [$role]);
            if (!$roleId) {
                continue;
            }
            $granted = Connection::value('SELECT role_id FROM role_permissions WHERE role_id = ? AND permission_id = ?', [(int) $roleId, (int) $permissionId]);
            if (!$granted) {
                Connection::run('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [(int) $roleId, (int) $permissionId]);
            }
        }
    }

    private static function addColumn(string $table, string $column, string $definition): void
    {
        try {
            Connection::pdo()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (PDOException $exception) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'duplicate') || str_contains($message, 'exists')) {
                return;
            }
            throw $exception;
        }
    }

    private static function exec(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (PDOException $exception) {
            $message = $exception->getMessage();
            if (str_contains($message, 'already exists') || str_contains($message, 'Duplicate')) {
                return;
            }
            throw $exception;
        }
    }

    /** @return array{id:string,ref:string,dt:string,date:string,blob:string,text:string,bool:string,money:string,real:string,engine:string} */
    private static function types(string $driver): array
    {
        return match ($driver) {
            'mysql' => [
                'id' => 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
                'ref' => 'BIGINT UNSIGNED',
                'dt' => 'DATETIME',
                'date' => 'DATE',
                'blob' => 'LONGBLOB',
                'text' => 'LONGTEXT',
                'bool' => 'TINYINT(1)',
                'money' => 'BIGINT NOT NULL DEFAULT 0',
                'real' => 'DOUBLE',
                'engine' => ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            ],
            'pgsql' => [
                'id' => 'id BIGSERIAL PRIMARY KEY',
                'ref' => 'BIGINT',
                'dt' => 'TIMESTAMP',
                'date' => 'DATE',
                'blob' => 'BYTEA',
                'text' => 'TEXT',
                'bool' => 'SMALLINT',
                'money' => 'BIGINT NOT NULL DEFAULT 0',
                'real' => 'DOUBLE PRECISION',
                'engine' => '',
            ],
            default => [
                'id' => 'id INTEGER PRIMARY KEY AUTOINCREMENT',
                'ref' => 'INTEGER',
                'dt' => 'TEXT',
                'date' => 'TEXT',
                'blob' => 'BLOB',
                'text' => 'TEXT',
                'bool' => 'INTEGER',
                'money' => 'INTEGER NOT NULL DEFAULT 0',
                'real' => 'REAL',
                'engine' => '',
            ],
        };
    }

    private static function statements(string $driver): array
    {
        $types = self::types($driver);
        $id = $types['id'];
        $ref = $types['ref'];
        $dt = $types['dt'];
        $date = $types['date'];
        $blob = $types['blob'];
        $text = $types['text'];
        $bool = $types['bool'];
        $money = $types['money'];
        $engine = $types['engine'];
        $table = static fn (string $name, string $columns): string => "CREATE TABLE IF NOT EXISTS {$name} (\n{$columns}\n){$engine}";

        $statements = [
            $table('schema_migrations', "version VARCHAR(40) NOT NULL PRIMARY KEY, applied_at {$dt} NOT NULL"),
            $table('roles', "{$id}, name VARCHAR(80) NOT NULL, slug VARCHAR(40) NOT NULL UNIQUE, description VARCHAR(255) NOT NULL DEFAULT '', is_system {$bool} NOT NULL DEFAULT 1, created_at {$dt} NOT NULL"),
            $table('permissions', "{$id}, name VARCHAR(120) NOT NULL, slug VARCHAR(80) NOT NULL UNIQUE, module VARCHAR(40) NOT NULL"),
            $table('role_permissions', "role_id {$ref} NOT NULL, permission_id {$ref} NOT NULL, PRIMARY KEY (role_id, permission_id), FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE, FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE"),
            $table('users', "{$id}, role_id {$ref} NOT NULL, name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, active {$bool} NOT NULL DEFAULT 1, failed_logins INTEGER NOT NULL DEFAULT 0, locked_until {$dt} NULL, last_login_at {$dt} NULL, is_demo {$bool} NOT NULL DEFAULT 0, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL, deleted_at {$dt} NULL, FOREIGN KEY (role_id) REFERENCES roles(id)"),
            $table('sessions', "id VARCHAR(64) NOT NULL PRIMARY KEY, user_id {$ref} NULL, payload {$text} NOT NULL, expires_at {$dt} NOT NULL, created_at {$dt} NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"),
            $table('login_attempts', "{$id}, email VARCHAR(190) NOT NULL, ip VARCHAR(64) NOT NULL, created_at {$dt} NOT NULL"),
            $table('settings', "setting_key VARCHAR(80) NOT NULL PRIMARY KEY, setting_value {$text} NOT NULL, updated_at {$dt} NULL, updated_by {$ref} NULL"),
            $table('client_origins', "{$id}, name VARCHAR(80) NOT NULL, slug VARCHAR(40) NOT NULL UNIQUE, requires_platform {$bool} NOT NULL DEFAULT 0, active {$bool} NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0"),
            $table('platforms', "{$id}, name VARCHAR(120) NOT NULL, slug VARCHAR(60) NOT NULL UNIQUE, status VARCHAR(30) NOT NULL DEFAULT 'pendente', connector VARCHAR(40) NOT NULL DEFAULT 'pendente', secret_encrypted {$text} NULL, config_json {$text} NULL, last_sync_at {$dt} NULL, notes {$text} NULL, active {$bool} NOT NULL DEFAULT 1, trust_payment {$bool} NOT NULL DEFAULT 0, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL"),
            $table('clients', "{$id}, full_name VARCHAR(180) NOT NULL, cpf VARCHAR(11) NULL, phone VARCHAR(30) NULL, phone_digits VARCHAR(20) NULL, whatsapp VARCHAR(30) NULL, whatsapp_digits VARCHAR(20) NULL, email VARCHAR(190) NULL, city VARCHAR(120) NULL, state VARCHAR(2) NULL, birth_date {$date} NULL, origin_id {$ref} NULL, platform_id {$ref} NULL, external_id VARCHAR(120) NULL, notes {$text} NULL, is_demo {$bool} NOT NULL DEFAULT 0, anonymized_at {$dt} NULL, created_by {$ref} NULL, updated_by {$ref} NULL, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL, archived_at {$dt} NULL, FOREIGN KEY (origin_id) REFERENCES client_origins(id), FOREIGN KEY (platform_id) REFERENCES platforms(id)"),
            $table('client_contacts', "{$id}, client_id {$ref} NOT NULL, user_id {$ref} NULL, channel VARCHAR(30) NOT NULL, notes {$text} NOT NULL, contacted_at {$dt} NOT NULL, created_at {$dt} NOT NULL, FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE"),
            $table('stored_files', "{$id}, mime VARCHAR(80) NOT NULL, byte_size INTEGER NOT NULL, content {$blob} NOT NULL, created_at {$dt} NOT NULL"),
            $table('vehicles', "{$id}, brand VARCHAR(80) NOT NULL, model VARCHAR(120) NOT NULL, slug VARCHAR(80) NOT NULL UNIQUE, model_year INTEGER NULL, plate VARCHAR(10) NULL, description {$text} NULL, capacity INTEGER NOT NULL DEFAULT 2, base_status VARCHAR(30) NOT NULL DEFAULT 'disponivel', notes {$text} NULL, image_file_id {$ref} NULL, active {$bool} NOT NULL DEFAULT 1, is_demo {$bool} NOT NULL DEFAULT 0, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL, deleted_at {$dt} NULL, FOREIGN KEY (image_file_id) REFERENCES stored_files(id)"),
            $table('vehicle_images', "{$id}, vehicle_id {$ref} NOT NULL, file_id {$ref} NOT NULL, filename VARCHAR(180) NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, is_cover {$bool} NOT NULL DEFAULT 0, created_at {$dt} NOT NULL, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE, FOREIGN KEY (file_id) REFERENCES stored_files(id)"),
            $table('modalities', "{$id}, name VARCHAR(120) NOT NULL, slug VARCHAR(80) NOT NULL UNIQUE, description {$text} NULL, type VARCHAR(20) NOT NULL, duration_minutes INTEGER NOT NULL DEFAULT 0, base_price_cents {$money}, active {$bool} NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL"),
            $table('vehicle_prices', "vehicle_id {$ref} NOT NULL, modality_id {$ref} NOT NULL, price_cents {$money}, PRIMARY KEY (vehicle_id, modality_id), FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE, FOREIGN KEY (modality_id) REFERENCES modalities(id) ON DELETE CASCADE"),
            $table('operating_hours', "{$id}, weekday INTEGER NOT NULL UNIQUE, opens_at VARCHAR(5) NOT NULL, closes_at VARCHAR(5) NOT NULL, active {$bool} NOT NULL DEFAULT 1"),
            $table('schedule_blocks', "{$id}, vehicle_id {$ref} NULL, starts_at {$dt} NOT NULL, ends_at {$dt} NOT NULL, reason VARCHAR(255) NOT NULL, block_type VARCHAR(30) NOT NULL, created_by {$ref} NULL, created_at {$dt} NOT NULL, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)"),
            $table('maintenance_logs', "{$id}, vehicle_id {$ref} NOT NULL, description {$text} NOT NULL, starts_at {$dt} NULL, ends_at {$dt} NULL, cost_cents {$money}, created_by {$ref} NULL, created_at {$dt} NOT NULL, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)"),
            $table('counters', 'name VARCHAR(60) NOT NULL PRIMARY KEY, current_value INTEGER NOT NULL'),
            $table('reservations', "{$id}, public_id VARCHAR(32) NOT NULL UNIQUE, code VARCHAR(20) NOT NULL UNIQUE, client_id {$ref} NOT NULL, origin_id {$ref} NULL, platform_id {$ref} NULL, external_transaction_id VARCHAR(120) NULL, external_reservation_code VARCHAR(120) NULL, platform_gross_cents {$money}, platform_fee_cents {$money}, platform_net_cents {$money}, platform_reported_paid_cents {$money}, operational_status VARCHAR(40) NOT NULL, financial_status VARCHAR(40) NOT NULL, vehicle_id {$ref} NULL, scheduled_date {$date} NULL, start_time VARCHAR(5) NULL, starts_at {$dt} NULL, ends_at {$dt} NULL, duration_minutes INTEGER NOT NULL DEFAULT 0, participants INTEGER NOT NULL DEFAULT 1, assigned_user_id {$ref} NULL, notes {$text} NULL, original_cents {$money}, discount_cents {$money}, fees_cents {$money}, final_cents {$money}, paid_cents {$money}, pending_cents {$money}, cancellation_reason VARCHAR(255) NULL, cancelled_at {$dt} NULL, completed_at {$dt} NULL, is_demo {$bool} NOT NULL DEFAULT 0, created_by {$ref} NULL, updated_by {$ref} NULL, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL, deleted_at {$dt} NULL, FOREIGN KEY (client_id) REFERENCES clients(id), FOREIGN KEY (origin_id) REFERENCES client_origins(id), FOREIGN KEY (platform_id) REFERENCES platforms(id), FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)"),
            $table('reservation_items', "{$id}, reservation_id {$ref} NOT NULL, modality_id {$ref} NULL, description VARCHAR(180) NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, unit_price_cents {$money}, total_cents {$money}, FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE, FOREIGN KEY (modality_id) REFERENCES modalities(id)"),
            $table('reservation_events', "{$id}, reservation_id {$ref} NOT NULL, event_type VARCHAR(40) NOT NULL, from_status VARCHAR(40) NULL, to_status VARCHAR(40) NULL, message VARCHAR(500) NULL, diff_json {$text} NULL, user_id {$ref} NULL, created_at {$dt} NOT NULL, FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE"),
            $table('payment_methods', "{$id}, name VARCHAR(80) NOT NULL, active {$bool} NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0"),
            $table('expense_categories', "{$id}, name VARCHAR(80) NOT NULL, active {$bool} NOT NULL DEFAULT 1"),
            $table('payments', "{$id}, public_id VARCHAR(32) NOT NULL UNIQUE, client_id {$ref} NOT NULL, reservation_id {$ref} NULL, production_id {$ref} NULL, amount_cents {$money}, method_id {$ref} NULL, status VARCHAR(20) NOT NULL, origin VARCHAR(30) NOT NULL, paid_at {$dt} NULL, installments INTEGER NOT NULL DEFAULT 1, external_transaction_id VARCHAR(120) NULL, notes {$text} NULL, created_by {$ref} NULL, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL, FOREIGN KEY (client_id) REFERENCES clients(id), FOREIGN KEY (reservation_id) REFERENCES reservations(id), FOREIGN KEY (method_id) REFERENCES payment_methods(id)"),
            $table('expenses', "{$id}, category_id {$ref} NULL, amount_cents {$money}, description VARCHAR(255) NOT NULL, expense_date {$date} NOT NULL, reservation_id {$ref} NULL, client_id {$ref} NULL, method_id {$ref} NULL, source_key VARCHAR(80) NULL UNIQUE, is_demo {$bool} NOT NULL DEFAULT 0, created_by {$ref} NULL, updated_by {$ref} NULL, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL, deleted_at {$dt} NULL, FOREIGN KEY (category_id) REFERENCES expense_categories(id), FOREIGN KEY (reservation_id) REFERENCES reservations(id)"),
            $table('productions', "{$id}, public_id VARCHAR(32) NOT NULL UNIQUE, client_id {$ref} NOT NULL, reservation_id {$ref} NULL, modality_id {$ref} NULL, amount_cents {$money}, contracted_at {$dt} NOT NULL, producer_user_id {$ref} NULL, status VARCHAR(30) NOT NULL, due_date {$date} NULL, delivered_at {$dt} NULL, private_link VARCHAR(500) NULL, notes {$text} NULL, is_demo {$bool} NOT NULL DEFAULT 0, created_by {$ref} NULL, updated_by {$ref} NULL, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL, FOREIGN KEY (client_id) REFERENCES clients(id), FOREIGN KEY (reservation_id) REFERENCES reservations(id), FOREIGN KEY (modality_id) REFERENCES modalities(id)"),
            $table('quotes', "{$id}, public_id VARCHAR(32) NOT NULL UNIQUE, code VARCHAR(20) NOT NULL UNIQUE, client_id {$ref} NOT NULL, valid_until {$date} NULL, status VARCHAR(20) NOT NULL, notes {$text} NULL, conditions {$text} NULL, total_cents {$money}, vehicle_id {$ref} NULL, scheduled_date {$date} NULL, start_time VARCHAR(5) NULL, participants INTEGER NOT NULL DEFAULT 1, is_demo {$bool} NOT NULL DEFAULT 0, created_by {$ref} NULL, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL, FOREIGN KEY (client_id) REFERENCES clients(id), FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)"),
            $table('quote_items', "{$id}, quote_id {$ref} NOT NULL, modality_id {$ref} NULL, description VARCHAR(180) NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, unit_price_cents {$money}, total_cents {$money}, FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE, FOREIGN KEY (modality_id) REFERENCES modalities(id)"),
            $table('document_templates', "{$id}, type VARCHAR(60) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, body {$text} NOT NULL, version INTEGER NOT NULL DEFAULT 1, updated_at {$dt} NULL, updated_by {$ref} NULL"),
            $table('documents', "{$id}, public_id VARCHAR(32) NOT NULL UNIQUE, type VARCHAR(60) NOT NULL, version INTEGER NOT NULL, template_version INTEGER NOT NULL DEFAULT 1, title VARCHAR(180) NOT NULL, reservation_id {$ref} NULL, client_id {$ref} NULL, payment_id {$ref} NULL, production_id {$ref} NULL, quote_id {$ref} NULL, filename VARCHAR(180) NOT NULL, mime VARCHAR(80) NOT NULL, byte_size INTEGER NOT NULL DEFAULT 0, checksum VARCHAR(64) NULL, content {$blob} NULL, storage_driver VARCHAR(20) NOT NULL DEFAULT 'database', storage_key VARCHAR(180) NULL, issued_at {$dt} NOT NULL, issued_by {$ref} NULL, redacted_at {$dt} NULL, FOREIGN KEY (reservation_id) REFERENCES reservations(id), FOREIGN KEY (client_id) REFERENCES clients(id), FOREIGN KEY (payment_id) REFERENCES payments(id), FOREIGN KEY (production_id) REFERENCES productions(id), FOREIGN KEY (quote_id) REFERENCES quotes(id)"),
            $table('import_batches', "{$id}, filename VARCHAR(180) NOT NULL, platform_id {$ref} NULL, origin_id {$ref} NULL, mapping_json {$text} NULL, status VARCHAR(30) NOT NULL, report_json {$text} NULL, raw_text {$text} NULL, imported_count INTEGER NOT NULL DEFAULT 0, skipped_count INTEGER NOT NULL DEFAULT 0, error_count INTEGER NOT NULL DEFAULT 0, created_by {$ref} NULL, created_at {$dt} NOT NULL, confirmed_at {$dt} NULL"),
            $table('import_rows', "{$id}, batch_id {$ref} NOT NULL, row_number INTEGER NOT NULL, external_key VARCHAR(160) NULL, payload_json {$text} NULL, status VARCHAR(20) NOT NULL, message VARCHAR(500) NULL, reservation_id {$ref} NULL, FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE"),
            $table('sync_logs', "{$id}, platform_id {$ref} NULL, direction VARCHAR(20) NOT NULL, external_id VARCHAR(160) NULL, payload_json {$text} NULL, status VARCHAR(20) NOT NULL, message VARCHAR(500) NULL, attempts INTEGER NOT NULL DEFAULT 0, reservation_id {$ref} NULL, http_status INTEGER NULL, created_at {$dt} NOT NULL, processed_at {$dt} NULL, FOREIGN KEY (platform_id) REFERENCES platforms(id)"),
            $table('activities', "{$id}, type VARCHAR(40) NOT NULL, summary VARCHAR(300) NOT NULL, entity_type VARCHAR(40) NULL, entity_id INTEGER NULL, user_id {$ref} NULL, created_at {$dt} NOT NULL"),
            $table('notifications', "{$id}, channel VARCHAR(20) NOT NULL, recipient VARCHAR(190) NOT NULL, template VARCHAR(60) NOT NULL, payload_json {$text} NULL, status VARCHAR(30) NOT NULL, error VARCHAR(300) NULL, related_type VARCHAR(40) NULL, related_id INTEGER NULL, created_at {$dt} NOT NULL, sent_at {$dt} NULL"),
            $table('audit_logs', "{$id}, user_id {$ref} NULL, action VARCHAR(60) NOT NULL, entity_type VARCHAR(40) NOT NULL, entity_id INTEGER NULL, changes_json {$text} NULL, ip VARCHAR(64) NULL, created_at {$dt} NOT NULL"),
        ];

        $indexes = [
            'CREATE INDEX idx_clients_phone ON clients (phone_digits)',
            'CREATE INDEX idx_clients_email ON clients (email)',
            'CREATE INDEX idx_clients_cpf ON clients (cpf)',
            'CREATE INDEX idx_clients_name ON clients (full_name)',
            'CREATE INDEX idx_res_date ON reservations (scheduled_date)',
            'CREATE INDEX idx_res_vehicle ON reservations (vehicle_id, scheduled_date)',
            'CREATE INDEX idx_res_client ON reservations (client_id)',
            'CREATE INDEX idx_res_status ON reservations (operational_status)',
            'CREATE UNIQUE INDEX idx_res_external ON reservations (platform_id, external_transaction_id)',
            'CREATE INDEX idx_pay_res ON payments (reservation_id)',
            'CREATE INDEX idx_doc_res ON documents (reservation_id)',
            'CREATE INDEX idx_doc_client ON documents (client_id)',
            'CREATE INDEX idx_act_created ON activities (created_at)',
            'CREATE INDEX idx_blocks_vehicle ON schedule_blocks (vehicle_id, starts_at)',
            'CREATE INDEX idx_login_created ON login_attempts (created_at)',
        ];

        return array_merge($statements, $indexes);
    }
}
