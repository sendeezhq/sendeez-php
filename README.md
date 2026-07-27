# sendeez/sendeez

Zero-dependency PHP SDK for the Sendeez transactional email API. Requires
only `ext-curl` and `ext-json`, both bundled with virtually every PHP build.

```php
use Sendeez\Sendeez;

$sendeez = new Sendeez(getenv('SENDEEZ_API_KEY'));

$email = $sendeez->emails->send(
    [
        'from' => ['email' => 'hello@example.com', 'name' => 'Example'],
        'to' => [['email' => 'developer@example.com']],
        'subject' => 'Welcome',
        'text' => 'Your account is ready.',
    ],
    // Optional. The SDK generates one when omitted.
    idempotencyKey: 'welcome-user-123',
);

echo $email['id'], ' ', $email['status'];
```

## Errors and agent actions

```php
use Sendeez\SendeezError;

try {
    $sendeez->emails->send($message);
} catch (SendeezError $error) {
    error_log(sprintf(
        '%s param=%s request_id=%s retry_after=%s',
        $error->code,
        $error->param,
        $error->requestId,
        $error->retryAfter,
    ));
}
```

The SDK retries GET requests and mutations carrying an idempotency key after
network errors, `429`, or `5xx`. It never automatically retries an unsafe
mutation.

Every method accepts a `timeout` (seconds). This lets applications and AI
agents enforce their own execution deadline:

```php
$sendeez->emails->list(timeout: 5.0);
```

## Testing

Pass `transport` to substitute a fake HTTP layer, the same role `fetch`/
`opener` injection plays in the Node and Python SDKs:

```php
$sendeez = new Sendeez(
    'sendeez_example_secret',
    transport: function (string $method, string $url, array $headers, ?string $body, float $timeout): array {
        return ['status' => 200, 'headers' => [], 'body' => '{}'];
    },
);
```
