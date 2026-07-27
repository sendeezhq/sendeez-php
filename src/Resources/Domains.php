<?php

declare(strict_types=1);

namespace Sendeez\Resources;

use Sendeez\Sendeez;

final class Domains
{
    public function __construct(private readonly Sendeez $client)
    {
    }

    public function create(string $name, ?float $timeout = null): mixed
    {
        return $this->client->request('POST', '/v1/domains', body: ['name' => $name], timeout: $timeout);
    }

    public function list(?float $timeout = null): mixed
    {
        return $this->client->request('GET', '/v1/domains', timeout: $timeout);
    }

    public function retrieve(string $id, ?float $timeout = null): mixed
    {
        return $this->client->request('GET', '/v1/domains/' . rawurlencode($id), timeout: $timeout);
    }

    public function verify(string $id, ?float $timeout = null): mixed
    {
        return $this->client->request('POST', '/v1/domains/' . rawurlencode($id) . '/verify', timeout: $timeout);
    }

    public function remove(string $id, ?float $timeout = null): void
    {
        $this->client->request('DELETE', '/v1/domains/' . rawurlencode($id), timeout: $timeout);
    }
}
