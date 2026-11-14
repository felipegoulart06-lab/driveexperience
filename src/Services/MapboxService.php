<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ExperienceRoutes;
use RuntimeException;

final class MapboxService
{
    public function token(): string
    {
        return trim((string) env('MAPBOX_TOKEN', ''));
    }

    public function enabled(): bool
    {
        return $this->token() !== '';
    }

    /**
     * HTML com o mapa estático da rota da modalidade (para PDF).
     */
    public function reservationMapHtml(string $modalitySlug, bool $withMap = true): string
    {
        $route = ExperienceRoutes::forModality($modalitySlug);
        if ($route === null) {
            return '';
        }
        $image = '';
        if ($withMap && $this->enabled()) {
            try {
                $image = $this->staticMapPng($route);
            } catch (\Throwable) {
                $image = '';
            }
        }
        return $this->experienceHtml($route, $image);
    }

    /**
     * @param array{title: string, tagline?: string, time: string, about?: string, stops?: list<array{name: string, text: string}>, waypoints: list<array{name: string, lng: float, lat: float}>} $route
     */
    private function experienceHtml(array $route, string $image): string
    {
        $title = e((string) $route['title']);
        $tagline = e((string) ($route['tagline'] ?? ''));
        $time = e((string) $route['time']);
        $about = e((string) ($route['about'] ?? ''));
        $cell = 'border:1px solid #D5DEE8;padding:8px 10px;text-align:left;vertical-align:top;';
        $label = 'width:108px;background-color:#F3F6FA;font-weight:bold;' . $cell;
        $html = '<h2>A experiência</h2><table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0 14px;">';
        $html .= '<tr><td style="' . $label . '">Experiência</td><td style="' . $cell . '"><strong>' . $title . '</strong>';
        if ($tagline !== '') {
            $html .= '<br>' . $tagline;
        }
        $html .= '</td></tr>';
        $html .= '<tr><td style="' . $label . '">Duração</td><td style="' . $cell . '">' . $time . '</td></tr>';
        if ($about !== '') {
            $html .= '<tr><td style="' . $label . '">O passeio</td><td style="' . $cell . '">' . $about . '</td></tr>';
        }
        $html .= '</table>';
        if ($image !== '') {
            $src = 'data:image/png;base64,' . base64_encode($image);
            $html .= '<h2>Trajeto no mapa</h2><p>Linha do percurso pelas ruas de Balneário Camboriú, na ordem em que o carro passa.</p>';
            $html .= '<p><img src="' . $src . '" alt="Mapa do trajeto ' . $title . '" style="width:100%;max-width:720px;border:1px solid #D5DEE8;"></p>';
        }
        $stops = $route['stops'] ?? [];
        if ($stops !== []) {
            $html .= '<h2>O que acontece em cada ponto</h2><table width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0 14px;">';
            foreach ($stops as $index => $stop) {
                $html .= '<tr><td width="36" bgcolor="#071833" style="width:36px;background-color:#071833;color:#ffffff;text-align:center;font-weight:bold;border:1px solid #071833;padding:8px 0;">' . ($index + 1) . '</td>';
                $html .= '<td style="' . $cell . '"><strong>' . e((string) $stop['name']) . '</strong><br>' . e((string) $stop['text']) . '</td></tr>';
            }
            $html .= '</table>';
        }
        return $html;
    }

    /**
     * @param array{title: string, time: string, waypoints: list<array{name: string, lng: float, lat: float}>} $route
     */
    public function staticMapPng(array $route): string
    {
        $token = $this->token();
        if ($token === '') {
            throw new RuntimeException('MAPBOX_TOKEN ausente.');
        }
        $polyline = $this->drivingPolyline($route['waypoints']);
        $cacheKey = hash('sha256', $polyline . '|' . (string) $route['title']);
        $cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dx-mapbox-' . $cacheKey . '.png';
        if (is_file($cacheFile) && filesize($cacheFile) > 1000) {
            $cached = file_get_contents($cacheFile);
            return is_string($cached) ? $cached : '';
        }

        $overlays = ['path-5+0E6EBE-0.9(' . rawurlencode($polyline) . ')'];
        $seen = [];
        foreach ($route['waypoints'] as $index => $point) {
            $key = round((float) $point['lng'], 5) . ',' . round((float) $point['lat'], 5);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $label = $index === 0 ? 'a' : (string) min($index, 9);
            $overlays[] = sprintf(
                'pin-s-%s+071833(%s,%s)',
                $label,
                $this->coord((float) $point['lng']),
                $this->coord((float) $point['lat'])
            );
        }
        $url = sprintf(
            'https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/%s/auto/900x520@2x?padding=48&access_token=%s',
            implode(',', $overlays),
            rawurlencode($token)
        );
        $png = $this->httpGet($url);
        if ($png === '' || strncmp($png, "\x89PNG", 4) !== 0) {
            throw new RuntimeException('Mapa estático inválido.');
        }
        @file_put_contents($cacheFile, $png);
        return $png;
    }

    /**
     * @param list<array{name: string, lng: float, lat: float}> $waypoints
     */
    public function drivingPolyline(array $waypoints): string
    {
        if (count($waypoints) < 2) {
            throw new RuntimeException('Trajeto sem pontos suficientes.');
        }
        $coords = [];
        foreach ($waypoints as $point) {
            $coords[] = $this->coord((float) $point['lng']) . ',' . $this->coord((float) $point['lat']);
        }
        // Mapbox Directions aceita no máximo 25 coordenadas por requisição.
        $chunks = array_chunk($coords, 25);
        $merged = [];
        foreach ($chunks as $chunk) {
            if (count($chunk) < 2) {
                continue;
            }
            $url = sprintf(
                'https://api.mapbox.com/directions/v5/mapbox/driving/%s?geometries=polyline&overview=full&access_token=%s',
                implode(';', $chunk),
                rawurlencode($this->token())
            );
            $json = $this->httpGet($url);
            $data = json_decode($json, true);
            if (!is_array($data) || empty($data['routes'][0]['geometry'])) {
                throw new RuntimeException('Não foi possível traçar a rota no Mapbox.');
            }
            $decoded = $this->decodePolyline((string) $data['routes'][0]['geometry']);
            if ($merged === []) {
                $merged = $decoded;
            } else {
                $merged = array_merge($merged, array_slice($decoded, 1));
            }
        }
        if ($merged === []) {
            throw new RuntimeException('Geometria da rota vazia.');
        }
        return $this->encodePolyline($merged);
    }

    private function coord(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6f', $value), '0'), '.') ?: '0';
    }

    private function httpGet(string $url): string
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                throw new RuntimeException('Falha ao iniciar HTTP.');
            }
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_HTTPHEADER => ['Accept: */*', 'User-Agent: DriveXperience/1.0'],
            ];
            $ca = (string) (env('SSL_CERT_FILE', '') ?: ini_get('curl.cainfo') ?: ini_get('openssl.cafile') ?: '');
            if ($ca !== '' && is_file($ca)) {
                $options[CURLOPT_CAINFO] = $ca;
            } elseif ((string) env('APP_ENV', 'local') === 'local') {
                // PHP no Windows costuma chegar sem CA bundle; em produção o certificado é validado.
                $options[CURLOPT_SSL_VERIFYPEER] = false;
                $options[CURLOPT_SSL_VERIFYHOST] = 0;
            }
            curl_setopt_array($curl, $options);
            $body = curl_exec($curl);
            $errno = curl_errno($curl);
            $error = curl_error($curl);
            $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if (!is_string($body) || $body === '' || $code >= 400) {
                throw new RuntimeException('Falha HTTP Mapbox (' . $code . ($errno ? ', ' . $error : '') . ').');
            }
            return $body;
        }
        $context = stream_context_create([
            'http' => [
                'timeout' => 25,
                'header' => "Accept: */*\r\nUser-Agent: DriveXperience/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (!is_string($body) || $body === '') {
            throw new RuntimeException('Falha ao consultar o Mapbox.');
        }
        return $body;
    }

    /** @return list<array{0: float, 1: float}> lat,lng */
    private function decodePolyline(string $polyline): array
    {
        $coordinates = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $length = strlen($polyline);
        while ($index < $length) {
            $shift = 0;
            $result = 0;
            do {
                $b = ord($polyline[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $lat += ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $shift = 0;
            $result = 0;
            do {
                $b = ord($polyline[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $lng += ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $coordinates[] = [$lat * 1e-5, $lng * 1e-5];
        }
        return $coordinates;
    }

    /** @param list<array{0: float, 1: float}> $points lat,lng */
    private function encodePolyline(array $points): string
    {
        $lastLat = 0;
        $lastLng = 0;
        $result = '';
        foreach ($points as [$lat, $lng]) {
            $ilat = (int) round($lat * 1e5);
            $ilng = (int) round($lng * 1e5);
            $result .= $this->encodeSigned($ilat - $lastLat);
            $result .= $this->encodeSigned($ilng - $lastLng);
            $lastLat = $ilat;
            $lastLng = $ilng;
        }
        return $result;
    }

    private function encodeSigned(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $output = '';
        while ($value >= 0x20) {
            $output .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }
        $output .= chr($value + 63);
        return $output;
    }
}
