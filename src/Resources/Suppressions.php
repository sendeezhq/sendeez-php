<?php

declare(strict_types=1);

namespace Sendeez\Resources;

use Sendeez\Sendeez;

final class Suppressions
{
    public function __construct(private readonly Sendeez $client)
    {
    }

    public function create(
        string $recipient,
        ?string $reason = null,
        ?string $expiresAt = null,
        ?float $timeout = null,
    ): mixed {
        $body = ['recipient' => $recipient];
        if ($reason !== null) {
            $body['reason'] = $reason;
        }
        if ($expiresAt !== null) {
            $body['expires_at'] = $expiresAt;
        }
        return $this->client->request('POST', '/v1/suppressions', body: $body, timeout: $timeout);
    }

    public function list(?float $timeout = null): mixed
    {
        return $this->client->request('GET', '/v1/suppressions', timeout: $timeout);
    }

    public function remove(string $id, ?float $timeout = null): void
    {
        $this->client->request('DELETE', '/v1/suppressions/' . rawurlencode($id), timeout: $timeout);
    }
}
