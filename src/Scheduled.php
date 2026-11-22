<?php

declare(strict_types=1);

namespace ServerSMTP;

final class Scheduled
{
    public function __construct(private readonly Client $client)
    {
    }

    public function list(?int $limit = null, ?int $offset = null): Response
    {
        $query = http_build_query(array_filter(['limit' => $limit, 'offset' => $offset], static fn ($value) => $value !== null));
        return $this->client->request('GET', '/v1/send/scheduled' . ($query === '' ? '' : '?' . $query));
    }

    public function get(string $id): Response
    {
        return $this->client->request('GET', '/v1/send/scheduled/' . rawurlencode($id));
    }

    public function cancel(string $id): Response
    {
        return $this->client->request('DELETE', '/v1/send/scheduled/' . rawurlencode($id));
    }
}
