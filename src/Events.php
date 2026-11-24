<?php

declare(strict_types=1);

namespace ServerSMTP;

final class Events
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Starts the active automations listening for $event (POST /v1/events), e.g. an email or SMS when an order ships.
     *
     * @param string $event lower-case name such as "comanda.expediata" (letters, digits, . _ -)
     * @param array<string, string|int|float|bool|null> $data variables for {{...}} in the automation steps
     * @param string|null $phone Romanian mobile number for SMS steps (07..., +407... or 00407...)
     * @param string|null $audienceId the contact is added to this audience when missing
     * @return Response data['started'] is the number of automation runs started
     */
    public function trigger(string $event, string $email, array $data = [], ?string $phone = null, ?string $audienceId = null): Response
    {
        $body = ['event' => $event, 'email' => $email];
        if ($audienceId !== null) {
            $body['audience_id'] = $audienceId;
        }
        if ($phone !== null && trim($phone) !== '') {
            $body['phone'] = $phone;
        }
        if ($data !== []) {
            $body['data'] = $data;
        }
        return $this->client->request('POST', '/v1/events', $body);
    }
}
