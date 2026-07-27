<?php

declare(strict_types=1);

namespace Sendeez\Tests;

use PHPUnit\Framework\TestCase;
use Sendeez\Sendeez;
use Sendeez\SendeezError;

final class SendeezTest extends TestCase
{
    public function testSendSetsAuthHeaderAndGeneratesIdempotencyKey(): void
    {
        $captured = [];
        $client = new Sendeez(
            'sendeez_example_secret',
            maxRetries: 0,
            transport: function (string $method, string $url, array $headers, ?string $body, float $timeout) use (&$captured): array {
                $captured[] = $headers;
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'em_123', 'object' => 'email', 'status' => 'queued']),
                ];
            },
        );

        $client->emails->send([
            'from' => ['email' => 'hello@example.com'],
            'to' => [['email' => 'developer@example.com']],
            'subject' => 'Hello',
            'text' => 'Hello',
        ]);

        $headers = $captured[0];
        self::assertSame('Bearer sendeez_example_secret', $headers['Authorization']);
        self::assertStringStartsWith('sdk_', $headers['Idempotency-Key']);
    }

    public function testSendThrowsStructuredApiError(): void
    {
        $client = new Sendeez(
            'sendeez_example_secret',
            maxRetries: 0,
            transport: function (): array {
                return [
                    'status' => 422,
                    'headers' => [],
                    'body' => json_encode([
                        'error' => [
                            'type' => 'invalid_request_error',
                            'code' => 'recipient_suppressed',
                            'message' => 'Recipient is suppressed.',
                            'request_id' => 'req_123',
                        ],
                    ]),
                ];
            },
        );

        try {
            $client->emails->send([
                'from' => ['email' => 'hello@example.com'],
                'to' => [['email' => 'blocked@example.com']],
                'subject' => 'Hello',
                'text' => 'Hello',
            ]);
            self::fail('Expected SendeezError to be thrown');
        } catch (SendeezError $error) {
            self::assertSame(422, $error->status);
            self::assertSame('recipient_suppressed', $error->code);
            self::assertSame('req_123', $error->requestId);
        }
    }

    public function testRetriesSafeGetRequestsAfterServerFailure(): void
    {
        $calls = 0;
        $client = new Sendeez(
            'sendeez_example_secret',
            maxRetries: 1,
            transport: function () use (&$calls): array {
                $calls++;
                if ($calls === 1) {
                    return [
                        'status' => 503,
                        'headers' => ['retry-after' => '0'],
                        'body' => json_encode(['error' => ['code' => 'internal_error']]),
                    ];
                }
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode([
                        'object' => 'list',
                        'data' => [],
                        'has_more' => false,
                        'next_cursor' => null,
                    ]),
                ];
            },
        );

        $result = $client->emails->list();

        self::assertSame(
            ['object' => 'list', 'data' => [], 'has_more' => false, 'next_cursor' => null],
            $result,
        );
        self::assertSame(2, $calls);
    }

    public function testDoesNotRetryUnsafeMutationWithoutIdempotencyKey(): void
    {
        $calls = 0;
        $client = new Sendeez(
            'sendeez_example_secret',
            maxRetries: 2,
            transport: function () use (&$calls): array {
                $calls++;
                return [
                    'status' => 503,
                    'headers' => [],
                    'body' => json_encode(['error' => ['code' => 'internal_error']]),
                ];
            },
        );

        $this->expectException(SendeezError::class);
        try {
            $client->domains->create('example.com');
        } finally {
            self::assertSame(1, $calls);
        }
    }
}
