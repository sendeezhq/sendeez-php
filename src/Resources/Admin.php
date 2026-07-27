<?php

declare(strict_types=1);

namespace Sendeez\Resources;

use Sendeez\Sendeez;

final class Admin
{
    public function __construct(private readonly Sendeez $client)
    {
    }

    public function diagnostics(?float $timeout = null): mixed
    {
        return $this->client->request('GET', '/v1/admin/diagnostics', timeout: $timeout);
    }
}
