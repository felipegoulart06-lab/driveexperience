<?php

declare(strict_types=1);

namespace Cliente\Services;

use App\Database\Connection;

final class Catalog
{
    public function vehicles(): array
    {
        return Connection::all(
            "SELECT v.id, v.brand, v.model, v.slug, v.description, v.capacity, v.image_file_id, v.is_demo,
                    (SELECT MIN(price_cents) FROM vehicle_prices p JOIN modalities m ON m.id = p.modality_id
                     WHERE p.vehicle_id = v.id AND m.type = 'experiencia' AND m.active = 1 AND p.price_cents > 0) AS from_cents
             FROM vehicles v
             WHERE v.deleted_at IS NULL AND v.active = 1
             ORDER BY v.brand, v.model"
        );
    }

    public function vehicle(string $slug): ?array
    {
        $vehicle = Connection::one(
            'SELECT * FROM vehicles WHERE slug = ? AND deleted_at IS NULL AND active = 1',
            [$slug]
        );
        if (!$vehicle) {
            return null;
        }
        $vehicle['prices'] = Connection::all(
            "SELECT m.id, m.name, m.slug, m.description, m.duration_minutes, p.price_cents
             FROM vehicle_prices p JOIN modalities m ON m.id = p.modality_id
             WHERE p.vehicle_id = ? AND m.type = 'experiencia' AND m.active = 1 AND p.price_cents > 0
             ORDER BY m.sort_order",
            [(int) $vehicle['id']]
        );
        $vehicle['photos'] = Connection::all(
            'SELECT file_id FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_cover DESC, sort_order, id',
            [(int) $vehicle['id']]
        );
        return $vehicle;
    }

    public function photos(): array
    {
        return Connection::all(
            "SELECT id, name, slug, description, base_price_cents
             FROM modalities
             WHERE type = 'foto' AND active = 1 AND base_price_cents > 0
             ORDER BY sort_order, name"
        );
    }

    public function photo(int $id): ?array
    {
        return Connection::one(
            "SELECT id, name, slug, description, base_price_cents
             FROM modalities
             WHERE id = ? AND type = 'foto' AND active = 1 AND base_price_cents > 0",
            [$id]
        );
    }

    public function products(): array
    {
        return Connection::all('SELECT * FROM shop_products WHERE active = 1 ORDER BY name');
    }

    public function product(string $slug): ?array
    {
        $product = Connection::one('SELECT * FROM shop_products WHERE slug = ? AND active = 1', [$slug]);
        if (!$product) {
            return null;
        }
        $product['variants'] = Connection::all('SELECT * FROM shop_variants WHERE product_id = ? ORDER BY id', [(int) $product['id']]);
        return $product;
    }

    public function price(int $vehicleId, int $modalityId): ?array
    {
        return Connection::one(
            "SELECT m.id, m.name, m.duration_minutes, m.type, p.price_cents, v.brand, v.model, v.capacity, v.is_demo
             FROM vehicle_prices p
             JOIN modalities m ON m.id = p.modality_id
             JOIN vehicles v ON v.id = p.vehicle_id
             WHERE p.vehicle_id = ? AND p.modality_id = ? AND m.active = 1 AND v.deleted_at IS NULL AND v.active = 1 AND p.price_cents > 0",
            [$vehicleId, $modalityId]
        );
    }
}
