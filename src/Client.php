<?php

declare(strict_types=1);

namespace ServerSMTP;

final class Client
{
    public const VERSION = '0.3.1';
    private const DEFAULT_BASE_URL = 'https://api.serversmtp.ro';

    public readonly Emails $emails;
    public readonly Scheduled $scheduled;
    public readonly Events $events;
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    /**
     * @param string|null $apiKey defaults to the SERVERSMTP_API_KEY environment variable
     * @param array{baseUrl?: string, timeout?: int} $options timeout in seconds
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $key = $apiKey ?? (getenv('SERVERSMTP_API_KEY') ?: null);
        if ($key === null || $key === '') {
            throw new \InvalidArgumentException('Missing API key. Pass it to new Client() or set SERVERSMTP_API_KEY.');
        }
        $this->apiKey = $key;
        $this->baseUrl = rtrim($options['baseUrl'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = $options['timeout'] ?? 30;
        $this->emails = new Emails($this);
        $this->scheduled = new Scheduled($this);
        $this->events = new Events($this);
    }

    /** Checks that the API key is active. */
    public function verifyApiKey(): Response
    {
        return $this->request('POST', '/v1/auth/check');
    }

    /**
     * @internal used by Emails, Scheduled and Events
     * @param array<string, mixed>|null $body
     */
    public function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): Response
    {
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'User-Agent: serversmtp-php/' . self::VERSION,
            'Accept: application/json',
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        $curl = curl_init($this->baseUrl . $path);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $networkError = curl_error($curl);
        curl_close($curl);
        if ($raw === false) {
            throw new ServerSMTPException($networkError ?: 'Network error', null, [], null, 'network_error');
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = [];
        }
        if ($status < 200 || $status >= 300) {
            throw new ServerSMTPException(
                is_string($data['message'] ?? null) ? $data['message'] : 'HTTP ' . $status,
                $status,
                is_array($data['fields'] ?? null) ? $data['fields'] : [],
                is_string($data['requestId'] ?? null) ? $data['requestId'] : null,
            );
        }
        return new Response($data, $status, $idempotencyKey);
    }
}
