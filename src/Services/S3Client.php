<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class S3Client
{
    public function put(string $key, string $body, string $mime): void
    {
        $this->request('PUT', $key, $body, $mime);
    }

    public function get(string $key): string
    {
        return $this->request('GET', $key, '', '');
    }

    private function request(string $method, string $key, string $body, string $mime): string
    {
        $endpoint = rtrim((string) env('S3_ENDPOINT', ''), '/');
        $bucket = (string) env('S3_BUCKET', '');
        $region = (string) env('S3_REGION', 'us-east-1');
        $access = (string) env('S3_ACCESS_KEY', '');
        $secret = (string) env('S3_SECRET_KEY', '');
        if ($endpoint === '' || $bucket === '' || $access === '' || $secret === '') {
            throw new RuntimeException('O armazenamento externo foi selecionado, mas o endpoint ou as credenciais não estão configurados.');
        }
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $encodedKey = str_replace('%2F', '/', rawurlencode($key));
        $path = '/' . rawurlencode($bucket) . '/' . $encodedKey;
        $amzDate = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payload = hash('sha256', $body);
        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $payload,
            'x-amz-date' => $amzDate,
        ];
        if ($method === 'PUT') {
            $headers['content-type'] = $mime;
        }
        ksort($headers);
        $canonicalHeaders = '';
        $names = [];
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim($value) . "\n";
            $names[] = $name;
        }
        $signed = implode(';', $names);
        $canonical = $method . "\n" . $path . "\n\n" . $canonicalHeaders . "\n" . $signed . "\n" . $payload;
        $scope = $date . '/' . $region . '/s3/aws4_request';
        $string = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonical);
        $signing = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $region, hash_hmac('sha256', $date, 'AWS4' . $secret, true), true), true), true);
        $signature = hash_hmac('sha256', $string, $signing);
        $curlHeaders = ['Authorization: AWS4-HMAC-SHA256 Credential=' . $access . '/' . $scope . ', SignedHeaders=' . $signed . ', Signature=' . $signature];
        foreach ($headers as $name => $value) {
            if ($name !== 'host') {
                $curlHeaders[] = $name . ': ' . $value;
            }
        }
        $channel = curl_init($endpoint . $path);
        curl_setopt_array($channel, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($method === 'PUT') {
            curl_setopt($channel, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($channel);
        $status = (int) curl_getinfo($channel, CURLINFO_RESPONSE_CODE);
        curl_close($channel);
        if ($response === false || $status >= 300) {
            throw new RuntimeException('O armazenamento externo recusou a operação. Código ' . $status . '.');
        }
        return (string) $response;
    }
}
