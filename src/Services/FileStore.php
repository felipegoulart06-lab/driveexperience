<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class FileStore
{
    public function put(string $binary, string $filename, string $mime): array
    {
        $driver = (string) Settings::get('storage_driver', env('STORAGE_DRIVER', 'database'));
        if ($driver === 's3') {
            $key = date('Y/m/') . bin2hex(random_bytes(8)) . '-' . $filename;
            (new S3Client())->put($key, $binary, $mime);
            return ['driver' => 's3', 'key' => $key, 'content' => null];
        }
        if (getenv('VERCEL') && $driver === 'local') {
            throw new RuntimeException('Armazenamento local não persiste na Vercel. Use database ou s3.');
        }
        return ['driver' => 'database', 'key' => null, 'content' => $binary];
    }

    public function get(string $driver, ?string $key, mixed $content): string
    {
        if ($driver === 's3') {
            return (new S3Client())->get((string) $key);
        }
        if (!is_string($content) || $content === '') {
            throw new RuntimeException('O arquivo não está mais disponível.');
        }
        return $content;
    }
}
