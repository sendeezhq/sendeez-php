<?php

declare(strict_types=1);

namespace Sendeez\Resources;

use Sendeez\Sendeez;

final class Webhooks
{
    public function __construct(private readonly Sendeez $client)
    {
    }

    /** @param list<string>|null $events */
    public function create(
        string $url,
        ?string $description = null,
        ?array $events = null,
        ?float $timeout = null,
    ): mixed {
        $body = ['url' => $url];
        if ($description !== null) {
            $body['description'] = $description;
        }
        if ($events !== null) {
            $body['events'] = $events;
        }
        return $this->client->request('POST', '/v1/webhooks', body: $body, timeout: $timeout);
    }

    public function list(?float $timeout = null): mixed
    {
        return $this->client->request('GET', '/v1/webhooks', timeout: $timeout);
    }

    public function retrieve(string $id, ?float $timeout = null): mixed
    {
        return $this->client->request('GET', '/v1/webhooks/' . rawurlencode($id), timeout: $timeout);
    }

    public function test(string $id, ?float $timeout = null): mixed
    {
        return $this->client->request('POST', '/v1/webhooks/' . rawurlencode($id) . '/test', timeout: $timeout);
    }

    public function deliveries(string $id, ?float $timeout = null): mixed
    {
        return $this->client->request('GET', '/v1/webhooks/' . rawurlencode($id) . '/deliveries', timeout: $timeout);
    }

    public function remove(string $id, ?float $timeout = null): void
    {
        $this->client->request('DELETE', '/v1/webhooks/' . rawurlencode($id), timeout: $timeout);
    }
}
