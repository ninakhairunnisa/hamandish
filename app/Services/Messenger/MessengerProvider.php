<?php

declare(strict_types=1);

namespace App\Services\Messenger;

interface MessengerProvider
{
    /**
     * Provider key, e.g. "bale" or "eitaa".
     */
    public function name(): string;

    /**
     * Validate the signed mini-app init data and return the decoded payload.
     *
     * @return array{messenger_user_id: string, username: ?string, first_name: ?string, last_name: ?string}
     *
     * @throws \App\Exceptions\InvalidMessengerInitDataException
     */
    public function validateInitData(string $initData): array;

    /**
     * Parse a bot webhook update that carries a shared contact.
     * Returns null when the update is not a contact-share event.
     *
     * @param  array<string, mixed>  $payload
     * @return array{messenger_user_id: string, phone: string, username: ?string, first_name: ?string, last_name: ?string}|null
     */
    public function parseContactUpdate(array $payload): ?array;

    /**
     * Validate the signed contact response the host hands the web app after
     * requestContact() (same HMAC scheme as init-data) and return the contact.
     *
     * @return array{phone: string, first_name: ?string, last_name: ?string}
     *
     * @throws \App\Exceptions\InvalidMessengerInitDataException
     */
    public function validateContactResponse(string $response): array;

    /**
     * Deep link the user can tap to open the bot and share their contact.
     */
    public function botDeepLink(): string;
}
