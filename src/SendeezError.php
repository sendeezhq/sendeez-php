<?php

declare(strict_types=1);

namespace Sendeez;

final class SendeezError extends \RuntimeException
{
    public readonly string $type;
    /** @var string */
    public $code;
    public readonly ?string $param;
    public readonly ?string $requestId;
    public readonly mixed $details;
    public readonly ?array $action;

    /** @param array<string,mixed> $error */
    public function __construct(
        public readonly int $status,
        array $error,
        public readonly ?float $retryAfter = null,
    ) {
        parent::__construct($error['message'] ?? sprintf('Sendeez request failed with HTTP %d', $status));

        $this->type = $error['type'] ?? 'api_error';
        $this->code = $error['code'] ?? 'unknown_error';
        $this->param = $error['param'] ?? null;
        $this->requestId = $error['request_id'] ?? null;
        $this->details = $error['details'] ?? null;
        $this->action = $error['action'] ?? null;
    }
}
