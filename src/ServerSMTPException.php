<?php

declare(strict_types=1);

namespace ServerSMTP;

final class ServerSMTPException extends \RuntimeException
{
    /**
     * @param list<string> $fields invalid request fields reported by the API
     * @param string $type "api_error" or "network_error"
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly array $fields = [],
        public readonly ?string $requestId = null,
        public readonly string $type = 'api_error',
    ) {
        parent::__construct($message, $statusCode ?? 0);
    }
}
