<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\NotFoundException;
use App\Support\ValidationException;

final class ImportService
{
    public function __construct(private ReservationService $reservations = new ReservationService())
    {
    }

    public function fields(): array
    {
        return [
            'external_id' => 'Identificador da venda',
            'customer_name' => 'Nome do cliente',
            'customer_email' => 'E-mail',
            'customer_phone' => 'Telefone',
            'customer_document' => 'CPF',
            'customer_city' => 'Cidade',
            'customer_state' => 'Estado',
            'product_name' => 'Produto ou experiência',
            'vehicle_name' => 'Veículo',
            'scheduled_date' => 'Data',
            'start_time' => 'Horário',
            'participants' => 'Participantes',
            'gross_amount' => 'Valor bruto',
            'fee_amount' => 'Taxa da plataforma',
            'net_amount' => 'Valor líquido',
            'notes' => 'Observações',
        ];
    }

    public function upload(string $filename, string $content, int $platformId, int $userId): int
    {
        if (trim($content) === '') {
            throw new ValidationException(['file' => 'O arquivo está vazio.']);
        }
        $parsed = $this->parse($filename, $content);
        if ($parsed['rows'] === []) {
            throw new ValidationException(['file' => 'Nenhuma linha de dados foi encontrada.']);
        }
        return Connection::insert(
            'INSERT INTO import_batches (filename, platform_id, origin_id, status, raw_text, report_json, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $filename,
                $platformId ?: null,
                (int) Connection::value("SELECT id FROM client_origins WHERE slug = 'plataforma'"),
                'mapeamento',
                $content,
                json_encode(['headers' => $parsed['headers']], JSON_UNESCAPED_UNICODE),
                $userId,
                now(),
            ]
        );
    }

    public function batch(int $id): array
    {
        $batch = Connection::one('SELECT * FROM import_batches WHERE id = ?', [$id]);
        if (!$batch) {
            throw new NotFoundException('Importação não encontrada.');
        }
        $meta = json_decode((string) $batch['report_json'], true) ?: [];
        $batch['headers'] = $meta['headers'] ?? [];
        $batch['mapping'] = json_decode((string) ($batch['mapping_json'] ?? ''), true) ?: [];
        return $batch;
    }

    public function preview(int $id, array $mapping, int $userId): array
    {
        $batch = $this->batch($id);
        if (!in_array($batch['status'], ['mapeamento', 'previa'], true)) {
            throw new ValidationException(['mapping' => 'Esta importação já foi concluída.']);
        }
        $parsed = $this->parse((string) $batch['filename'], (string) $batch['raw_text']);
        Connection::run('DELETE FROM import_rows WHERE batch_id = ?', [$id]);
        $summary = ['ready' => 0, 'duplicate' => 0, 'error' => 0];
        foreach ($parsed['rows'] as $index => $row) {
            $draft = $this->draft($row, $mapping, (int) $batch['platform_id']);
            $result = $draft['error'] ?? $this->reservations->recordExternal($draft, $userId, true);
            $status = $result['status'] === 'ready' ? 'previsto' : ($result['status'] === 'duplicate' ? 'duplicado' : 'erro');
            $summary[$status === 'previsto' ? 'ready' : ($status === 'duplicado' ? 'duplicate' : 'error')]++;
            Connection::insert(
                'INSERT INTO import_rows (batch_id, row_number, external_key, payload_json, status, message) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $index + 2, $draft['external_id'] ?? null, json_encode($draft, JSON_UNESCAPED_UNICODE), $status, $result['message'] ?? '']
            );
        }
        Connection::run(
            'UPDATE import_batches SET mapping_json = ?, status = ?, report_json = ? WHERE id = ?',
            [json_encode($mapping, JSON_UNESCAPED_UNICODE), 'previa', json_encode(['headers' => $parsed['headers'], 'summary' => $summary], JSON_UNESCAPED_UNICODE), $id]
        );
        return $summary;
    }

    public function confirm(int $id, int $userId): array
    {
        $batch = $this->batch($id);
        if ($batch['status'] !== 'previa') {
            throw new ValidationException(['file' => 'Revise o mapeamento antes de importar.']);
        }
        $counts = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        foreach (Connection::all('SELECT * FROM import_rows WHERE batch_id = ? ORDER BY row_number', [$id]) as $row) {
            $draft = json_decode((string) $row['payload_json'], true) ?: [];
            if ($row['status'] === 'duplicado') {
                $counts['skipped']++;
                continue;
            }
            if ($row['status'] === 'erro') {
                $counts['errors']++;
                continue;
            }
            $result = $this->reservations->recordExternal($draft, $userId, false);
            $status = match ($result['status']) {
                'created' => 'importado',
                'duplicate' => 'duplicado',
                default => 'erro',
            };
            if ($status === 'importado') {
                $counts['imported']++;
            } elseif ($status === 'duplicado') {
                $counts['skipped']++;
            } else {
                $counts['errors']++;
            }
            Connection::run(
                'UPDATE import_rows SET status = ?, message = ?, reservation_id = ? WHERE id = ?',
                [$status, $result['message'] ?? ($result['code'] ?? ''), $result['id'] ?? null, (int) $row['id']]
            );
        }
        Connection::run(
            'UPDATE import_batches SET status = ?, imported_count = ?, skipped_count = ?, error_count = ?, confirmed_at = ? WHERE id = ?',
            ['concluida', $counts['imported'], $counts['skipped'], $counts['errors'], now(), $id]
        );
        activity('importacao', 'Importação concluída com ' . $counts['imported'] . ' venda(s)', 'import', $id);
        return $counts;
    }

    public function parse(string $filename, string $content): array
    {
        if (preg_match('/\.xlsx$/i', $filename)) {
            return $this->parseXlsx($content);
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $first = strtok($content, "\r\n");
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\') ?: [];
        $headers = array_map(static fn ($header): string => trim((string) $header), $headers);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count(array_filter($row, static fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $assoc = [];
            foreach ($headers as $index => $header) {
                $assoc[$header] = trim((string) ($row[$index] ?? ''));
            }
            $rows[] = $assoc;
        }
        fclose($handle);
        return ['headers' => $headers, 'rows' => $rows];
    }

    private function draft(array $row, array $mapping, int $platformId): array
    {
        $value = function (string $field) use ($row, $mapping): string {
            $column = $mapping[$field] ?? '';
            return $column !== '' ? trim((string) ($row[$column] ?? '')) : '';
        };
        $external = $value('external_id');
        if ($external === '') {
            $external = substr(hash('sha256', json_encode([$platformId, $value('customer_name'), $value('scheduled_date'), $value('start_time'), $value('product_name'), $value('gross_amount')])), 0, 24);
        }
        $gross = parse_money($value('gross_amount'));
        if ($value('customer_name') === '') {
            return ['error' => ['status' => 'error', 'message' => 'Nome do cliente ausente.'], 'external_id' => $external];
        }
        return [
            'platform_id' => $platformId,
            'external_id' => $external,
            'customer' => [
                'name' => $value('customer_name'),
                'email' => $value('customer_email'),
                'phone' => $value('customer_phone'),
                'cpf' => $value('customer_document'),
                'city' => $value('customer_city'),
                'state' => $value('customer_state'),
            ],
            'product' => $value('product_name'),
            'vehicle' => $value('vehicle_name'),
            'scheduled_date' => $value('scheduled_date'),
            'start_time' => $value('start_time'),
            'participants' => (int) ($value('participants') ?: 1),
            'gross_cents' => (int) ($gross ?? 0),
            'fee_cents' => (int) (parse_money($value('fee_amount')) ?? 0),
            'net_cents' => (int) (parse_money($value('net_amount')) ?? 0),
            'reported_paid_cents' => 0,
            'notes' => $value('notes'),
        ];
    }

    private function parseXlsx(string $content): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new ValidationException(['file' => 'A extensão ZIP do PHP não está habilitada. Salve a planilha como CSV e importe novamente.']);
        }
        $path = tempnam(sys_get_temp_dir(), 'dx');
        file_put_contents($path, $content);
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            unlink($path);
            throw new ValidationException(['file' => 'Não foi possível ler o XLSX.']);
        }
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $xml = simplexml_load_string($sharedXml);
            foreach ($xml->si ?? [] as $item) {
                $shared[] = trim((string) $item->t);
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        if (!$sheet) {
            throw new ValidationException(['file' => 'A planilha não possui a primeira aba.']);
        }
        $xml = simplexml_load_string($sheet);
        $rows = [];
        foreach ($xml->sheetData->row ?? [] as $row) {
            $values = [];
            foreach ($row->c ?? [] as $cell) {
                $type = (string) $cell['t'];
                $value = (string) $cell->v;
                $values[] = $type === 's' ? ($shared[(int) $value] ?? '') : $value;
            }
            $rows[] = $values;
        }
        if ($rows === []) {
            return ['headers' => [], 'rows' => []];
        }
        $headers = array_map(static fn ($value): string => trim((string) $value), array_shift($rows));
        $assocRows = [];
        foreach ($rows as $row) {
            $assoc = [];
            foreach ($headers as $index => $header) {
                $assoc[$header] = trim((string) ($row[$index] ?? ''));
            }
            $assocRows[] = $assoc;
        }
        return ['headers' => $headers, 'rows' => $assocRows];
    }
}
