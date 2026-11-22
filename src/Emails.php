<?php

declare(strict_types=1);

namespace ServerSMTP;

final class Emails
{
    private const KEY_PATTERN = '/^[a-zA-Z0-9_.:-]{8,200}$/';

    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Sends one email. Reuse $idempotencyKey when retrying so the email is never sent twice.
     *
     * @param array<string, mixed> $message from, to, subject, html, text, replyTo, or templateId + variables
     */
    public function send(array $message, ?string $idempotencyKey = null): Response
    {
        return $this->client->request('POST', '/v1/send', $message, self::key($idempotencyKey));
    }

    /**
     * Sends up to 8 emails in one call.
     *
     * @param list<array<string, mixed>> $messages
     */
    public function sendBatch(array $messages, ?string $idempotencyKey = null): Response
    {
        return $this->client->request('POST', '/v1/send/batch', ['messages' => $messages], self::key($idempotencyKey));
    }

    /**
     * Schedules an email up to 30 days ahead.
     *
     * @param array<string, mixed> $message same fields as send(), plus sendAt (DateTimeInterface or ISO 8601 string)
     */
    public function schedule(array $message, ?string $idempotencyKey = null): Response
    {
        if (($message['sendAt'] ?? null) instanceof \DateTimeInterface) {
            $message['sendAt'] = \DateTimeImmutable::createFromInterface($message['sendAt'])
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.v\Z');
        }
        return $this->client->request('POST', '/v1/send/scheduled', $message, self::key($idempotencyKey));
    }

    private static function key(?string $key): string
    {
        $key ??= 'sdk-' . bin2hex(random_bytes(16));
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new \InvalidArgumentException('idempotencyKey must be 8-200 characters: letters, digits, _ . : -');
        }
        return $key;
    }
}
