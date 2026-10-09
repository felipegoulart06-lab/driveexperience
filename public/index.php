<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Auth\Auth;
use App\Database\Schema;
use App\Http\Request;
use App\Http\Router;
use App\Http\Session;
use App\Support\AuthException;
use App\Support\NotFoundException;
use App\Support\ValidationException;

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if (str_starts_with($path, '/assets/')) {
    $file = base_path('public' . $path);
    if (is_file($file)) {
        $types = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff2' => 'font/woff2',
        ];
        header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        exit;
    }
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://tile.openstreetmap.org https://a.tile.openstreetmap.org https://b.tile.openstreetmap.org https://c.tile.openstreetmap.org; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'");
header('X-Robots-Tag: noindex, nofollow');

$request = new Request();
try {
    $installed = Schema::installed();
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Não foi possível conectar ao banco de dados.';
    exit;
}

if (!$installed && !in_array($request->path(), ['/instalar', '/health'], true)) {
    view('errors/setup', ['title' => 'Instalação', '_layout' => 'layouts/guest.php']);
    exit;
}
if ($installed) {
    Schema::ensure();
    Session::start();
}

try {
    $router = new Router();
    require base_path('src/Http/routes.php');
    register_routes($router);
    $router->dispatch($request);
} catch (NotFoundException) {
    http_response_code(404);
    view('errors/status', [
        'title' => 'Página não encontrada',
        'message' => 'O endereço não existe neste sistema.',
        '_layout' => Auth::check() ? 'layouts/app.php' : 'layouts/guest.php',
    ]);
} catch (AuthException $exception) {
    http_response_code(403);
    view('errors/status', ['title' => 'Acesso negado', 'message' => $exception->getMessage()]);
} catch (ValidationException $exception) {
    if (str_starts_with($request->path(), '/api/')) {
        json_out(['error' => $exception->getMessage(), 'fields' => $exception->errors], 422);
    }
    back_with_errors($exception->errors, '/');
} catch (Throwable $exception) {
    error_log($exception::class);
    http_response_code(500);
    $layout = $installed && Auth::check() ? 'layouts/app.php' : 'layouts/guest.php';
    view('errors/status', [
        'title' => 'Erro interno',
        'message' => app_debug() ? $exception->getMessage() : 'Não foi possível concluir a operação.',
        '_layout' => $layout,
    ]);
}
