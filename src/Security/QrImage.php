<?php

declare(strict_types=1);

namespace App\Security;

final class QrImage
{
    public static function dataUri(string $text): string
    {
        $reporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        try {
            require_once __DIR__ . '/phpqrcode.php';
            $path = tempnam(sys_get_temp_dir(), 'dxqr');
            if ($path === false) {
                throw new \RuntimeException('Não foi possível gerar o QR Code do 2FA.');
            }
            \QRcode::png($text, $path, QR_ECLEVEL_M, 6, 2);
            $png = (string) file_get_contents($path);
            unlink($path);
        } finally {
            error_reporting($reporting);
        }
        if ($png === '') {
            throw new \RuntimeException('Não foi possível gerar o QR Code do 2FA.');
        }
        return 'data:image/png;base64,' . base64_encode($png);
    }
}
