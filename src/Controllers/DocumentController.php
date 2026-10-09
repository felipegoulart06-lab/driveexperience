<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Database\Connection;
use App\Http\Request;
use App\Services\DocumentService;
use App\Support\AuthException;
use App\Support\NotFoundException;

final class DocumentController
{
    public function index(Request $request): void
    {
        view('documents/index', [
            'title' => 'Documentos',
            'documents' => Connection::all('SELECT d.public_id, d.type, d.title, d.version, d.issued_at, d.redacted_at, r.id AS reservation_id, r.code FROM documents d LEFT JOIN reservations r ON r.id = d.reservation_id ORDER BY d.id DESC LIMIT 100'),
        ]);
    }

    public function download(Request $request, string $key): void
    {
        $documents = new DocumentService();
        $document = $documents->findByPublicId($key);
        if (!$document) {
            throw new NotFoundException();
        }
        if (!$documents->canView($document)) {
            throw new AuthException('Seu perfil não pode abrir este documento.');
        }
        if ($document['redacted_at']) {
            throw new AuthException('Documento removido por solicitação de privacidade.');
        }
        $binary = $documents->binary($document);
        audit('download', 'document', (int) $document['id'], ['type' => $document['type']]);
        $disposition = $request->query('download') ? 'attachment' : 'inline';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $document['filename'] . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $binary;
        exit;
    }
}
