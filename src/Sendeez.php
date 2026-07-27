<?php

declare(strict_types=1);

namespace Sendeez;

use Sendeez\Resources\Admin;
use Sendeez\Resources\ApiKeys;
use Sendeez\Resources\Domains;
use Sendeez\Resources\Emails;
use Sendeez\Resources\Suppressions;
use Sendeez\Resources\Webhooks;

/**
 * Client for the Sendeez transactional email API.
 *
 * Zero Composer dependencies: HTTP is done with `ext-curl`. Pass a
 * `transport` callable to substitute a fake transport in tests (same role
 * as injecting `fetch`/`opener` in the Node and Python SDKs).
 *
 * @phpstan-type Transport callable(string $method, string $url, array<string,string> $headers, ?string $body, float $timeout): array{status:int,headers:array<string,string>,body:string}
 */
final class Sendeez
{
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $maxRetries;
    private readonly float $timeout;

    /** @var callable(string,string,array<string,string>,?string,float):array{status:int,headers:array<string,string>,body:string} */
    private $transport;

    public readonly Emails $emails;
    public readonly Domains $domains;
    public readonly Suppressions $suppressions;
    public readonly Webhooks $webhooks;
    public readonly ApiKeys $apiKeys;
    public readonly Admin $admin;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.sendeez.com',
        int $maxRetries = 2,
        float $timeout = 30.0,
        ?callable $transport = null,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Sendeez apiKey is required');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->maxRetries = $maxRetries;
        $this->timeout = $timeout;
        $this->transport = $transport ?? $this->curlTransport(...);

        $this->emails = new Emails($this);
        $this->domains = new Domains($this);
        $this->suppressions = new Suppressions($this);
        $this->webhooks = new Webhooks($this);
        $this->apiKeys = new ApiKeys($this);
        $this->admin = new Admin($this);
    }

    public static function newIdempotencyKey(): string
    {
        return 'sdk_' . self::uuidV4();
    }

    /**
     * @internal used by Sendeez\Resources\* classes; not part of the public API.
     * @param array<string,mixed>|null $body
     * @param array<string,mixed> $query
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
        ?string $idempotencyKey = null,
        ?float $timeout = null,
    ): mixed {
        $url = $this->baseUrl . $path;
        $query = array_filter($query, static fn (mixed $value): bool => $value !== null);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ];
        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_THROW_ON_ERROR);
            $headers['Content-Type'] = 'application/json';
        }
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $effectiveTimeout = $timeout ?? $this->timeout;
        $attempt = 0;

        while (true) {
            try {
                $response = ($this->transport)($method, $url, $headers, $payload, $effectiveTimeout);
            } catch (\Throwable $error) {
                if ($attempt >= $this->maxRetries || ($method !== 'GET' && $idempotencyKey === null)) {
                    throw $error;
                }
                $this->sleep(2 ** $attempt);
                $attempt++;
                continue;
            }

            $status = $response['status'];
            if ($status >= 200 && $status < 300) {
                if ($status === 204 || $response['body'] === '') {
                    return null;
                }
                return json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
            }

            $decoded = [];
            if ($response['body'] !== '') {
                try {
                    $decoded = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $decoded = [];
                }
            }
            $retryAfter = $this->retryAfter($response['headers']);

            if ($attempt < $this->maxRetries && $this->retryable($method, $idempotencyKey, $status)) {
                $this->sleep($retryAfter ?? (float) (2 ** $attempt));
                $attempt++;
                continue;
            }

            throw new SendeezError($status, $decoded['error'] ?? [], $retryAfter);
        }
    }

    private static function retryable(string $method, ?string $idempotencyKey, int $status): bool
    {
        return ($method === 'GET' || $idempotencyKey !== null) && ($status === 429 || $status >= 500);
    }

    /** @param array<string,string> $headers */
    private static function retryAfter(array $headers): ?float
    {
        $value = $headers['retry-after'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return min((float) $value, 60.0);
    }

    private function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) ($seconds * 1_000_000));
        }
    }

    /**
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private function curlTransport(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Sendeez request failed: unable to initialize curl');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_POSTFIELDS => $body,
        ]);

        $raw = curl_exec($handle);
        if ($raw === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new \RuntimeException("Sendeez request failed: {$error}");
        }

        $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        $rawHeaders = substr((string) $raw, 0, $headerSize);
        $responseBody = substr((string) $raw, $headerSize);

        return ['status' => $status, 'headers' => self::parseHeaders($rawHeaders), 'body' => $responseBody];
    }

    /** @return array<string,string> */
    private static function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
        return $headers;
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
