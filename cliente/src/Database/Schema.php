<?php

declare(strict_types=1);

namespace Cliente\Database;

use App\Database\Connection;

final class Schema
{
    public static function ensure(): void
    {
        $id = match (Connection::driver()) {
            'mysql' => 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'id BIGSERIAL PRIMARY KEY',
            default => 'id INTEGER PRIMARY KEY AUTOINCREMENT',
        };
        $dt = match (Connection::driver()) {
            'mysql' => 'DATETIME',
            'pgsql' => 'TIMESTAMP',
            default => 'TEXT',
        };
        $tables = [
            "CREATE TABLE IF NOT EXISTS client_accounts (
                {$id}, client_id INTEGER NOT NULL UNIQUE, email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL, marketing_opt_in INTEGER NOT NULL DEFAULT 0,
                terms_accepted_at {$dt} NOT NULL, email_verified_at {$dt} NULL,
                deletion_requested_at {$dt} NULL, deleted_at {$dt} NULL,
                created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS client_login_attempts (
                {$id}, email VARCHAR(190) NOT NULL, ip VARCHAR(64) NOT NULL, created_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS client_orders (
                {$id}, public_id VARCHAR(32) NOT NULL UNIQUE, code VARCHAR(24) NOT NULL UNIQUE,
                client_id INTEGER NOT NULL, kind VARCHAR(20) NOT NULL, status VARCHAR(40) NOT NULL,
                subtotal_cents INTEGER NOT NULL, discount_cents INTEGER NOT NULL DEFAULT 0,
                freight_cents INTEGER NOT NULL DEFAULT 0, total_cents INTEGER NOT NULL,
                coupon_code VARCHAR(40) NULL, reservation_id INTEGER NULL, notes TEXT NULL,
                is_demo INTEGER NOT NULL DEFAULT 0, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS client_order_items (
                {$id}, order_id INTEGER NOT NULL, description VARCHAR(180) NOT NULL,
                quantity INTEGER NOT NULL, unit_cents INTEGER NOT NULL, total_cents INTEGER NOT NULL,
                vehicle_id INTEGER NULL, modality_id INTEGER NULL, variant_id INTEGER NULL, meta_json TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS shop_products (
                {$id}, name VARCHAR(160) NOT NULL, slug VARCHAR(80) NOT NULL UNIQUE,
                description TEXT NULL, price_cents INTEGER NOT NULL, promo_cents INTEGER NULL,
                collection VARCHAR(80) NULL, stock INTEGER NOT NULL DEFAULT 0, preorder INTEGER NOT NULL DEFAULT 0,
                active INTEGER NOT NULL DEFAULT 1, is_demo INTEGER NOT NULL DEFAULT 0,
                created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS shop_variants (
                {$id}, product_id INTEGER NOT NULL, size VARCHAR(20) NULL, color VARCHAR(40) NULL,
                stock INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS gift_vouchers (
                {$id}, public_id VARCHAR(32) NOT NULL UNIQUE, code VARCHAR(24) NOT NULL UNIQUE,
                order_id INTEGER NULL, buyer_client_id INTEGER NOT NULL, recipient_client_id INTEGER NULL,
                recipient_name VARCHAR(180) NOT NULL, giver_name VARCHAR(180) NOT NULL, message TEXT NULL,
                vehicle_id INTEGER NULL, modality_id INTEGER NULL, amount_cents INTEGER NOT NULL,
                status VARCHAR(30) NOT NULL, expires_at {$dt} NULL, redeemed_at {$dt} NULL,
                created_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS point_entries (
                {$id}, client_id INTEGER NOT NULL, delta INTEGER NOT NULL, balance_after INTEGER NOT NULL,
                reason VARCHAR(180) NOT NULL, source VARCHAR(40) NOT NULL, source_id VARCHAR(64) NULL,
                created_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS rewards (
                {$id}, name VARCHAR(160) NOT NULL, description TEXT NULL, cost_points INTEGER NOT NULL,
                stock INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, is_demo INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS referrals (
                {$id}, referrer_client_id INTEGER NOT NULL UNIQUE, code VARCHAR(40) NOT NULL UNIQUE,
                created_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS referral_events (
                {$id}, referrer_client_id INTEGER NOT NULL, referred_client_id INTEGER NOT NULL UNIQUE,
                status VARCHAR(30) NOT NULL, created_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS campaigns (
                {$id}, name VARCHAR(160) NOT NULL, description TEXT NULL, starts_on TEXT NULL, ends_on TEXT NULL,
                active INTEGER NOT NULL DEFAULT 1, is_public INTEGER NOT NULL DEFAULT 1, is_demo INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS app_promos (
                {$id}, occasion VARCHAR(80) NOT NULL, title VARCHAR(80) NOT NULL, summary VARCHAR(240) NOT NULL,
                starts_on TEXT NOT NULL, ends_on TEXT NOT NULL, image_file_id INTEGER NULL,
                href VARCHAR(180) NOT NULL, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0,
                is_demo INTEGER NOT NULL DEFAULT 0, created_at {$dt} NOT NULL, updated_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS coupons (
                {$id}, code VARCHAR(40) NOT NULL UNIQUE, description VARCHAR(180) NOT NULL,
                kind VARCHAR(20) NOT NULL, amount INTEGER NOT NULL, expires_on TEXT NULL,
                max_uses INTEGER NULL, min_cents INTEGER NOT NULL DEFAULT 0,
                active INTEGER NOT NULL DEFAULT 1, is_demo INTEGER NOT NULL DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS coupon_uses (
                {$id}, coupon_id INTEGER NOT NULL, order_id INTEGER NOT NULL, client_id INTEGER NOT NULL, created_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS client_notifications (
                {$id}, client_id INTEGER NOT NULL, title VARCHAR(160) NOT NULL, body TEXT NOT NULL,
                channel VARCHAR(20) NOT NULL, read_at {$dt} NULL, created_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS client_settings (
                setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT NOT NULL, updated_at {$dt} NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS client_audit (
                {$id}, client_id INTEGER NULL, action VARCHAR(60) NOT NULL, detail TEXT NULL, created_at {$dt} NOT NULL
            )",
        ];
        foreach ($tables as $sql) {
            try {
                Connection::run($sql);
            } catch (\Throwable $exception) {
                throw new \RuntimeException($exception->getMessage() . "\n" . $sql, 0, $exception);
            }
        }
        try {
            Connection::run('CREATE UNIQUE INDEX idx_point_source ON point_entries (client_id, source, source_id)');
        } catch (\Throwable) {
        }
        $stamp = now();
        $defaults = [
            'points_per_visit' => '100',
            'visit_interval_hours' => '12',
            'referral_points' => '200',
        ];
        foreach ($defaults as $key => $value) {
            $exists = Connection::value('SELECT setting_key FROM client_settings WHERE setting_key = ?', [$key]);
            if (!$exists) {
                Connection::run('INSERT INTO client_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)', [$key, $value, $stamp]);
            }
        }
        if (!Connection::value("SELECT id FROM client_origins WHERE slug = 'aplicativo'")) {
            $order = (int) Connection::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM client_origins');
            Connection::insert(
                'INSERT INTO client_origins (name, slug, requires_platform, active, sort_order) VALUES (?, ?, 0, 1, ?)',
                ['Aplicativo', 'aplicativo', $order]
            );
        }
        self::demoCatalog($stamp);
    }

    private static function demoCatalog(string $stamp): void
    {
        if (!Connection::value('SELECT id FROM shop_products LIMIT 1')) {
            $shirt = Connection::insert(
                'INSERT INTO shop_products (name, slug, description, price_cents, promo_cents, collection, stock, preorder, active, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, 1, ?, ?)',
                ['Camiseta Drive', 'camiseta-drive', 'Peça de demonstração da loja oficial. Não é um produto real à venda.', 18900, 14900, 'Demonstração', 8, $stamp, $stamp]
            );
            foreach (['P', 'M', 'G'] as $size) {
                Connection::insert('INSERT INTO shop_variants (product_id, size, color, stock) VALUES (?, ?, ?, ?)', [$shirt, $size, 'Marinho', 2]);
            }
            Connection::insert(
                'INSERT INTO shop_products (name, slug, description, price_cents, promo_cents, collection, stock, preorder, active, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, ?, ?, 0, 1, 1, ?, ?)',
                ['Boné Drive', 'bone-drive', 'Peça de demonstração. Estoque e preço podem ser alterados no banco.', 9900, 'Demonstração', 5, $stamp, $stamp]
            );
        }
        if (!Connection::value('SELECT id FROM coupons LIMIT 1')) {
            Connection::insert(
                'INSERT INTO coupons (code, description, kind, amount, expires_on, max_uses, min_cents, active, is_demo) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)',
                ['DEMO10', 'Cupom de demonstração: 10% na loja.', 'percent', 1000, date('Y-m-d', strtotime('+1 year')), 100, 0]
            );
        }
        if (!Connection::value('SELECT id FROM rewards LIMIT 1')) {
            Connection::insert(
                'INSERT INTO rewards (name, description, cost_points, stock, active, is_demo) VALUES (?, ?, ?, ?, 1, 1)',
                ['Cupom de demonstração', 'Recompensa de exemplo. O custo em pontos é configurável.', 500, 20]
            );
        }
        if (!Connection::value('SELECT id FROM campaigns LIMIT 1')) {
            Connection::insert(
                'INSERT INTO campaigns (name, description, starts_on, ends_on, active, is_public, is_demo) VALUES (?, ?, ?, ?, 1, 1, 1)',
                ['Campanha de demonstração', 'Oferta de exemplo. Não representa uma promoção real.', date('Y-m-d'), date('Y-m-d', strtotime('+30 days'))]
            );
        }
        self::demoProducts($stamp);
        self::demoVisitor($stamp);
        self::demoPromo($stamp);
    }

    private static function demoPromo(string $stamp): void
    {
        if (env('SEED_DEMO') !== '1' || Connection::value('SELECT id FROM app_promos LIMIT 1')) {
            return;
        }
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

    private static function hasColumn(string $table, string $column): bool
    {
        if (Connection::driver() === 'sqlite') {
            foreach (Connection::all('PRAGMA table_info(' . $table . ')') as $row) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }
        if (Connection::driver() === 'pgsql') {
            return (bool) Connection::value(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
                [$table, $column]
            );
        }
        return (bool) Connection::value(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    private static function demoProducts(string $stamp): void
    {
        if (env('SEED_DEMO') !== '1') {
            return;
        }
        if (!self::hasColumn('shop_products', 'image_path')) {
            Connection::run('ALTER TABLE shop_products ADD COLUMN image_path VARCHAR(180) NULL');
        }
        $items = [
            ['Camiseta Drive', 'camiseta-drive', 'Camiseta de malha marinho. Peça fictícia da vitrine, só para demonstração.', 18900, 14900, 'Pista', 12, 'loja/camiseta.jpg', [['P', 'Marinho', 4], ['M', 'Marinho', 4], ['G', 'Marinho', 4]]],
            ['Boné Drive', 'bone-drive', 'Boné marinho. Peça fictícia da vitrine, só para demonstração.', 9900, null, 'Pista', 8, 'loja/bone.jpg', []],
            ['Jaqueta Pista', 'jaqueta-pista', 'Corta-vento marinho. Peça fictícia da vitrine, só para demonstração.', 32900, 27900, 'Pista', 6, 'loja/jaqueta.jpg', [['P', 'Marinho', 2], ['M', 'Marinho', 2], ['G', 'Marinho', 2]]],
            ['Chaveiro Pista', 'chaveiro-pista', 'Chaveiro de metal com alça marinho. Peça fictícia, só para demonstração.', 4900, null, 'Pista', 20, 'loja/chaveiro.jpg', []],
            ['Garrafa Pista', 'garrafa-pista', 'Garrafa térmica marinho. Peça fictícia, só para demonstração.', 8900, null, 'Pista', 10, 'loja/garrafa.jpg', []],
            ['Óculos Pista', 'oculos-pista', 'Óculos escuros. Peça fictícia, só para demonstração.', 15900, null, 'Pista', 7, 'loja/oculos.jpg', []],
        ];
        foreach ($items as [$name, $slug, $description, $price, $promo, $collection, $stock, $image, $variants]) {
            $id = Connection::value('SELECT id FROM shop_products WHERE slug = ?', [$slug]);
            if ($id) {
                Connection::run(
                    "UPDATE shop_products SET image_path = ? WHERE id = ? AND (image_path IS NULL OR image_path = '')",
                    [$image, $id]
                );
                continue;
            }
            $productId = Connection::insert(
                'INSERT INTO shop_products (name, slug, description, price_cents, promo_cents, collection, stock, preorder, active, is_demo, image_path, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, 1, ?, ?, ?)',
                [$name, $slug, $description, $price, $promo, $collection, $stock, $image, $stamp, $stamp]
            );
            foreach ($variants as [$size, $color, $variantStock]) {
                Connection::insert(
                    'INSERT INTO shop_variants (product_id, size, color, stock) VALUES (?, ?, ?, ?)',
                    [$productId, $size, $color, $variantStock]
                );
            }
        }
    }

    private static function demoVisitor(string $stamp): void
    {
        if (env('SEED_DEMO') !== '1') {
            return;
        }
        $email = 'marina.costa@demonstracao.local';
        if (Connection::value('SELECT id FROM client_accounts WHERE email = ?', [$email])) {
            return;
        }
        $origin = (int) Connection::value("SELECT id FROM client_origins WHERE slug = 'aplicativo'");
        $phone = '47988880000';
        Connection::transaction(function () use ($stamp, $email, $origin, $phone): void {
            $clientId = Connection::insert(
                'INSERT INTO clients (full_name, phone, phone_digits, whatsapp, whatsapp_digits, email, city, state, origin_id, notes, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
                ['Marina Costa', $phone, $phone, $phone, $phone, $email, 'Balneário Camboriú', 'SC', $origin ?: null, 'Conta fictícia de quem baixou o aplicativo. Não é um cliente real.', $stamp, $stamp]
            );
            Connection::insert(
                'INSERT INTO client_accounts (client_id, email, password_hash, marketing_opt_in, terms_accepted_at, email_verified_at, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?, ?, ?)',
                [$clientId, $email, password_hash('DriveApp2026', PASSWORD_DEFAULT), $stamp, $stamp, $stamp, $stamp]
            );
            Connection::insert(
                'INSERT INTO point_entries (client_id, delta, balance_after, reason, source, source_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$clientId, 350, 350, 'Saldo inicial de demonstração', 'demonstracao', 'conta-demo', $stamp]
            );
            Connection::insert(
                'INSERT INTO client_notifications (client_id, title, body, channel, created_at) VALUES (?, ?, ?, ?, ?)',
                [$clientId, 'Bem-vinda ao aplicativo', 'Esta conta é de demonstração. Pedidos e pontos daqui não são reais.', 'app', $stamp]
            );
            Connection::insert(
                'INSERT INTO client_audit (client_id, action, detail, created_at) VALUES (?, ?, ?, ?)',
                [$clientId, 'conta_demonstracao', 'Conta fictícia criada para experimentar o aplicativo.', $stamp]
            );
        });
    }
}
