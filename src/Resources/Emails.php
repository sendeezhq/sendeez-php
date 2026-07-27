<?php

declare(strict_types=1);

namespace Sendeez\Resources;

use Sendeez\Sendeez;

final class Emails
{
    public function __construct(private readonly Sendeez $client)
    {
    }

    /** @param array<string,mixed> $input */
    public function send(array $input, ?string $idempotencyKey = null, ?float $timeout = null): mixed
    {
        return $this->client->request(
            'POST',
            '/v1/emails',
            body: $input,
            idempotencyKey: $idempotencyKey ?? Sendeez::newIdempotencyKey(),
            timeout: $timeout,
        );
    }

    public function retrieve(string $id, ?float $timeout = null): mixed
    {
        return $this->client->request('GET', '/v1/emails/' . rawurlencode($id), timeout: $timeout);
    }

    public function list(?int $limit = null, ?string $after = null, ?float $timeout = null): mixed
    {
        return $this->client->request(
            'GET',
            '/v1/emails',
            query: ['limit' => $limit, 'after' => $after],
            timeout: $timeout,
        );
    }

    /** @param array<string,mixed> $input */
    public function simulateEvent(
        string $emailId,
        array $input,
        ?string $idempotencyKey = null,
        ?float $timeout = null,
    ): mixed {
        return $this->client->request(
            'POST',
            '/v1/emails/' . rawurlencode($emailId) . '/events',
            body: $input,
            idempotencyKey: $idempotencyKey ?? Sendeez::newIdempotencyKey(),
            timeout: $timeout,
        );
    }
}
