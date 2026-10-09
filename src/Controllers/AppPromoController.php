<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Http\Request;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class AppPromoController
{
    public function index(Request $request): void
    {
        view('promos/index', [
            'title' => 'Promoções do aplicativo',
            'items' => Connection::all('SELECT * FROM app_promos ORDER BY sort_order, id'),
        ]);
    }

    public function store(Request $request): void
    {
        $count = (int) Connection::value('SELECT COUNT(*) FROM app_promos');
        if ($count >= 3) {
            throw new ValidationException(['title' => 'O aplicativo mostra no máximo 3 promoções do mês.']);
        }
        $fields = $this->fields($request, true);
        $order = (int) Connection::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM app_promos');
        $stamp = now();
        Connection::insert(
            'INSERT INTO app_promos (occasion, title, summary, starts_on, ends_on, image_file_id, href, active, sort_order, is_demo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [...$fields, $order, $stamp, $stamp]
        );
        redirect_with('/promocoes-app', 'ok', 'Promoção do mês publicada no aplicativo.');
    }

    public function update(Request $request, string $id): void
    {
        $row = $this->find((int) $id);
        $fields = $this->fields($request, false, $row['image_file_id'] !== null ? (int) $row['image_file_id'] : null);
        Connection::run(
            'UPDATE app_promos SET occasion = ?, title = ?, summary = ?, starts_on = ?, ends_on = ?, image_file_id = ?, href = ?, active = ?, updated_at = ? WHERE id = ?',
            [...$fields, now(), (int) $id]
        );
        redirect_with('/promocoes-app', 'ok', 'Promoção atualizada.');
    }

    public function destroy(Request $request, string $id): void
    {
        $this->find((int) $id);
        Connection::run('DELETE FROM app_promos WHERE id = ?', [(int) $id]);
        redirect_with('/promocoes-app', 'ok', 'Promoção retirada do aplicativo.');
    }

    public function image(Request $request, string $id): void
    {
        $row = $this->find((int) $id);
        if ($row['image_file_id'] === null) {
            throw new NotFoundException();
        }
        $file = Connection::one('SELECT mime, content FROM stored_files WHERE id = ?', [(int) $row['image_file_id']]);
        if (!$file) {
            throw new NotFoundException();
        }
        header('Content-Type: ' . (string) $file['mime']);
        header('Cache-Control: private, max-age=3600');
        echo $file['content'];
        exit;
    }

    private function find(int $id): array
    {
        $row = Connection::one('SELECT * FROM app_promos WHERE id = ?', [$id]);
        if (!$row) {
            throw new NotFoundException();
        }
        return $row;
    }

    private function fields(Request $request, bool $creating, ?int $currentImage = null): array
    {
        $occasion = trim($request->str('occasion'));
        $title = trim($request->str('title'));
        $summary = trim($request->str('summary'));
        $starts = parse_date($request->str('starts_on'));
        $ends = parse_date($request->str('ends_on'));
        $href = trim($request->str('href'));
        if ($href === '') {
            $href = '/experiencias';
        }
        $errors = [];
        if (mb_strlen($occasion) < 3 || mb_strlen($occasion) > 80) {
            $errors['occasion'] = 'Informe a data comemorativa, com até 80 caracteres.';
        }
        if (mb_strlen($title) < 3 || mb_strlen($title) > 80) {
            $errors['title'] = 'Informe o título, com até 80 caracteres.';
        }
        if (mb_strlen($summary) < 8 || mb_strlen($summary) > 240) {
            $errors['summary'] = 'O texto da promoção precisa ter entre 8 e 240 caracteres.';
        }
        if (!$starts || !$ends || $ends < $starts) {
            $errors['starts_on'] = 'Informe o início e o fim, com o fim depois do início.';
        }
        if (!preg_match('#^/[a-z0-9/_-]{0,160}$#', $href)) {
            $errors['href'] = 'O link precisa ser um caminho interno, como /experiencias.';
        }
        $imageId = $currentImage;
        $upload = $_FILES['image'] ?? null;
        if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
                $errors['image'] = 'Não foi possível receber a imagem.';
            } elseif ((int) $upload['size'] > 4 * 1024 * 1024) {
                $errors['image'] = 'A imagem pode ter no máximo 4 MB.';
            } else {
                $binary = file_get_contents((string) $upload['tmp_name']);
                $mime = is_string($binary) ? $this->imageMime($binary) : null;
                if ($mime === null) {
                    $errors['image'] = 'Envie a imagem em JPG, PNG ou WebP.';
                } else {
                    $imageId = Connection::insert(
                        'INSERT INTO stored_files (mime, byte_size, content, created_at) VALUES (?, ?, ?, ?)',
                        [$mime, strlen($binary), $binary, now()]
                    );
                }
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return [$occasion, $title, $summary, $starts, $ends, $imageId, $href, $request->str('active') === '1' ? 1 : 0];
    }

    private function imageMime(string $binary): ?string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (strlen($binary) >= 12 && str_starts_with($binary, 'RIFF') && substr($binary, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        return null;
    }
}
