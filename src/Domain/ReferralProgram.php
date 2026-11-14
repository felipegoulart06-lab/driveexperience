<?php

declare(strict_types=1);

namespace App\Domain;

use App\Database\Connection;

final class ReferralProgram
{
    public static function rates(): array
    {
        return [
            'super-drive' => ['kind' => 'Experiência de direção', 'bps' => 1000],
            'drive-xperience' => ['kind' => 'Experiência de direção', 'bps' => 1000],
            'rota-da-fama' => ['kind' => 'Experiência de direção', 'bps' => 1000],
            'carona' => ['kind' => 'Carona', 'bps' => 800],
            'carona-da-fama' => ['kind' => 'Carona', 'bps' => 800],
            'fotos-prata' => ['kind' => 'Produção de fotos', 'bps' => 1200],
            'fotos-ouro' => ['kind' => 'Produção de fotos', 'bps' => 1200],
            'fotos-diamante' => ['kind' => 'Produção de fotos', 'bps' => 1500],
        ];
    }

    public function catalog(): array
    {
        $rates = self::rates();
        $rows = Connection::all('SELECT id, name, slug, description, type, base_price_cents FROM modalities WHERE active = 1 ORDER BY sort_order, name');
        $offers = [];
        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            $rate = $rates[$slug] ?? [
                'kind' => (string) $row['type'] === 'foto' ? 'Produção de fotos' : 'Experiência',
                'bps' => 1000,
            ];
            $min = (int) Connection::value('SELECT MIN(price_cents) FROM vehicle_prices WHERE modality_id = ? AND price_cents > 0', [(int) $row['id']]);
            $base = $min > 0 ? $min : (int) $row['base_price_cents'];
            $offers[] = [
                'name' => (string) $row['name'],
                'slug' => $slug,
                'kind' => $rate['kind'],
                'bps' => (int) $rate['bps'],
                'percent' => $this->percent((int) $rate['bps']),
                'base_cents' => $base,
                'example_cents' => $base > 0 ? (int) round($base * (int) $rate['bps'] / 10000) : 0,
                'description' => (string) $row['description'],
            ];
        }
        return $offers;
    }

    private function percent(int $bps): string
    {
        $value = $bps / 100;
        $decimals = $bps % 100 === 0 ? 0 : 1;
        return number_format($value, $decimals, ',', '');
    }
}
