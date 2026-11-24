<?php

// Contract tests: php tests/run.php <baseUrl>, with sdks/contract-server.mjs running.
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'ServerSMTP\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/../src/' . substr($class, strlen($prefix)) . '.php';
    }
});

use ServerSMTP\Client;
use ServerSMTP\ServerSMTPException;

$baseUrl = $argv[1] ?? 'http://127.0.0.1:4010';
$key = 'em_live_' . str_repeat('a', 48);
$client = new Client($key, ['baseUrl' => $baseUrl, 'timeout' => 5]);
$failures = 0;
$passed = 0;

function check(bool $condition, string $label): void
{
    global $failures, $passed;
    if ($condition) {
        $passed++;
    } else {
        $failures++;
        fwrite(STDERR, "FAIL: $label\n");
    }
}

function last(string $baseUrl): array
{
    return json_decode((string) file_get_contents($baseUrl . '/__last'), true);
}

function expectError(callable $call): ?ServerSMTPException
{
    try {
        $call();
    } catch (ServerSMTPException $error) {
        return $error;
    }
    return null;
}

$message = ['from' => 'Magazin <comenzi@magazin.ro>', 'to' => 'client@example.invalid', 'subject' => 'Comanda 1', 'html' => '<p>Mulțumim</p>'];

$sent = $client->emails->send($message);
$request = last($baseUrl);
check($sent->data['status'] === 'sent', 'send returns sent');
check(preg_match('/^sdk-[0-9a-f]{32}$/', (string) $sent->idempotencyKey) === 1, 'generated idempotency key');
check($request['path'] === '/v1/send' && $request['headers']['idempotency-key'] === $sent->idempotencyKey, 'key sent in header');
check($request['headers']['authorization'] === 'Bearer ' . $key, 'bearer auth');
check(str_starts_with((string) $request['headers']['user-agent'], 'serversmtp-php/'), 'user agent');
check($request['body'] === $message, 'body is the message, UTF-8 intact');

$unknown = $client->emails->send(array_merge($message, ['to' => 'unknown@example.invalid']), 'order-1001');
check($unknown->statusCode === 202 && $unknown->data['status'] === 'unknown', 'unknown is returned, not thrown');
check($unknown->idempotencyKey === 'order-1001' && last($baseUrl)['headers']['idempotency-key'] === 'order-1001', 'caller key reused');

try {
    $client->emails->send($message, 'bad key!');
    check(false, 'invalid key rejected');
} catch (InvalidArgumentException) {
    check(true, 'invalid key rejected');
}

$invalid = expectError(fn () => $client->emails->send(array_merge($message, ['subject' => 'invalid'])));
check($invalid?->statusCode === 400 && $invalid->fields === ['subject'], 'validation error with fields');
$rejected = expectError(fn () => $client->emails->send(array_merge($message, ['to' => 'rejected@example.invalid'])));
check($rejected?->statusCode === 502 && $rejected->requestId === 'req-3', 'rejection carries requestId');
$unauthorized = expectError(fn () => (new Client('em_live_' . str_repeat('b', 48), ['baseUrl' => $baseUrl]))->verifyApiKey());
check($unauthorized?->statusCode === 401, 'unauthorized key');

$batch = $client->emails->sendBatch([$message, array_merge($message, ['to' => 'alt@example.invalid'])]);
check(count($batch->data['results']) === 2 && count(last($baseUrl)['body']['messages']) === 2, 'batch');

$scheduled = $client->emails->schedule(array_merge($message, ['sendAt' => new DateTimeImmutable('2030-01-01 11:00:00', new DateTimeZone('Europe/Bucharest'))]));
check($scheduled->statusCode === 201 && last($baseUrl)['body']['sendAt'] === '2030-01-01T09:00:00.000Z', 'schedule converts DateTime to UTC ISO');

$list = $client->scheduled->list(10, 20);
check(count($list->data['items']) === 1 && last($baseUrl)['query'] === ['limit' => '10', 'offset' => '20'], 'list with paging');
check($client->scheduled->get('job-1')->data['id'] === 'job-1', 'get scheduled');
check($client->scheduled->cancel('job-1')->data['status'] === 'cancelled' && last($baseUrl)['method'] === 'DELETE', 'cancel scheduled');
check(expectError(fn () => $client->scheduled->get('missing'))?->statusCode === 404, 'missing scheduled is 404');
check($client->verifyApiKey()->data === ['ok' => true], 'verify api key');

$offline = expectError(fn () => (new Client($key, ['baseUrl' => 'http://127.0.0.1:9', 'timeout' => 3]))->emails->send($message));
check($offline?->type === 'network_error', 'network error type');

$tmp = tempnam(sys_get_temp_dir(), 'ssmtp');
file_put_contents($tmp, '%PDF-1.4 fixture');
$fromPath = ServerSMTP\Attachment::fromPath($tmp, 'factura-1001.pdf');
unlink($tmp);
check($fromPath === ['filename' => 'factura-1001.pdf', 'content' => base64_encode('%PDF-1.4 fixture')], 'attachment from path');
$client->emails->send(array_merge($message, [
    'cc' => 'contabil@example.invalid',
    'bcc' => ['arhiva@example.invalid'],
    'attachments' => [$fromPath, ServerSMTP\Attachment::fromString('Bună ziua', 'nota.txt', 'text/plain')],
]));
$body = last($baseUrl)['body'];
check($body['cc'] === 'contabil@example.invalid' && $body['bcc'] === ['arhiva@example.invalid'], 'cc and bcc sent');
check(base64_decode($body['attachments'][1]['content']) === 'Bună ziua' && $body['attachments'][1]['contentType'] === 'text/plain', 'attachment from string');
try {
    ServerSMTP\Attachment::fromPath('/nonexistent/file.pdf');
    check(false, 'missing attachment file rejected');
} catch (InvalidArgumentException) {
    check(true, 'missing attachment file rejected');
}

$started = $client->events->trigger('comanda.expediata', 'ana@example.invalid', ['order_reference' => 'INV-1001', 'order_total' => '169.90'], '0722 123 456', '00000000-0000-4000-8000-000000000001');
$request = last($baseUrl);
check($started->data === ['started' => 1], 'event starts automations');
check($request['path'] === '/v1/events' && $request['headers']['idempotency-key'] === null, 'event request without idempotency key');
check($request['body'] === ['event' => 'comanda.expediata', 'email' => 'ana@example.invalid', 'audience_id' => '00000000-0000-4000-8000-000000000001', 'phone' => '0722 123 456', 'data' => ['order_reference' => 'INV-1001', 'order_total' => '169.90']], 'event body');
$client->events->trigger('comanda.platita', 'ana@example.invalid', [], '  ');
check(last($baseUrl)['body'] === ['event' => 'comanda.platita', 'email' => 'ana@example.invalid'], 'empty phone and data are left out');
check(expectError(fn () => $client->events->trigger('comanda.expediata', 'ana@example.invalid', [], '123'))?->fields === ['phone'], 'invalid phone reported in fields');

putenv('SERVERSMTP_API_KEY');
try {
    new Client();
    check(false, 'missing key rejected');
} catch (InvalidArgumentException) {
    check(true, 'missing key rejected');
}

echo "passed $passed, failed $failures\n";
exit($failures === 0 ? 0 : 1);
