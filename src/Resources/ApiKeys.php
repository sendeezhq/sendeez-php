<?php

declare(strict_types=1);

namespace Sendeez\Resources;

use Sendeez\Sendeez;

final class ApiKeys
{
    public function __construct(private readonly Sendeez $client)
    {
    }

    /** @param list<string> $scopes */
    public function create(
        string $name,
        array $scopes,
        ?string $expiresAt = null,
        ?float $timeout = null,
    ): mixed {
        $body = ['name' => $name, 'scopes' => $scopes];
        if ($expiresAt !== null) {
            $body['expires_at'] = $expiresAt;
        }
        return $this->client->request('POST', '/v1/api-keys', body: $body, timeout: $timeout);
    }

    public function list(?float $timeout = null): mixed
    {
        return $this->client->request('GET', '/v1/api-keys', timeout: $timeout);
    }

    public function rotate(string $id, ?float $timeout = null): mixed
    {
        return $this->client->request('POST', '/v1/api-keys/' . rawurlencode($id) . '/rotate', timeout: $timeout);
    }

    public function remove(string $id, ?float $timeout = null): void
    {
        $this->client->request('DELETE', '/v1/api-keys/' . rawurlencode($id), timeout: $timeout);
    }
}
