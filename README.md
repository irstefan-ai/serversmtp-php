# serversmtp/serversmtp-php

Official PHP SDK for the [ServerSMTP](https://serversmtp.ro) transactional email API. PHP 8.1+, ext-curl, no other dependencies. Works in plain PHP, Laravel, Symfony and WooCommerce plugins.

```sh
composer require serversmtp/serversmtp-php
```

```php
$serversmtp = new ServerSMTP\Client(getenv('SERVERSMTP_API_KEY'));

$response = $serversmtp->emails->send([
    'from' => 'Shop <orders@your-domain.ro>',
    'to' => 'customer@example.com',
    'subject' => 'Order #1001 confirmed',
    'html' => '<p>Thank you!</p>',
], 'order-1001-confirmation');

echo $response->data['status'];   // "sent" or "unknown"
echo $response->idempotencyKey;   // reuse it to retry safely
```

Attachments, CC and BCC (up to 10 files, 10 MB in total; each address counts toward the quota):

```php
$serversmtp->emails->send([
    'from' => 'Shop <orders@your-domain.ro>',
    'to' => 'customer@example.com',
    'cc' => 'accounting@your-domain.ro',
    'bcc' => ['archive@your-domain.ro'],
    'subject' => 'Invoice #1001',
    'text' => 'Your invoice is attached.',
    'attachments' => [ServerSMTP\Attachment::fromPath(__DIR__ . '/invoice-1001.pdf')],
]);
```

- `$client->emails->send()`, `sendBatch()`, `schedule()` (accepts `DateTimeInterface` for `sendAt`)
- `$client->scheduled->list()`, `get()`, `cancel()`, `$client->verifyApiKey()`
- `$client->events->trigger($event, $email, $data, $phone, $audienceId)` starts the automations for an event (email and SMS steps)
- Errors throw `ServerSMTP\ServerSMTPException` with `statusCode`, `fields`, `requestId` and `type` (`api_error` or `network_error`).

Laravel: add `'serversmtp' => ['key' => env('SERVERSMTP_API_KEY')]` to `config/services.php` and create the client with `config('services.serversmtp.key')`. For Laravel Mail, install [`serversmtp/serversmtp-laravel`](https://serversmtp.ro/docs#laravel) (`MAIL_MAILER=serversmtp`) or use SMTP (smtp.serversmtp.ro, port 587).

Docs: https://serversmtp.ro/docs#biblioteci

## Tests

```sh
node tests/contract-server.mjs 4010 &
php tests/run.php http://127.0.0.1:4010
```

`tests/contract-server.mjs` mirrors the public API responses without sending email.

## License

MIT © Think AI SRL
