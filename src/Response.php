<?php

declare(strict_types=1);

namespace ServerSMTP;

final class Response
{
    /**
     * @param array<string, mixed> $data decoded JSON body
     * @param string|null $idempotencyKey the key sent with the request; reuse it to retry safely
     */
    public function __construct(
        public readonly array $data,
        public readonly int $statusCode,
        public readonly ?string $idempotencyKey = null,
    ) {
    }
}
