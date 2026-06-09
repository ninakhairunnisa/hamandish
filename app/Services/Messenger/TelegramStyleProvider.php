<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\Exceptions\InvalidMessengerInitDataException;

/**
 * Base implementation for messengers that follow the Telegram Web App
 * contract (Bale does; Eitaa is close). Init-data is a signed query string
 * validated with HMAC-SHA256 against the bot token.
 */
abstract class TelegramStyleProvider implements MessengerProvider
{
    public function __construct(
        protected readonly string $botToken,
        protected readonly string $botUsername,
    ) {}

    public function validateInitData(string $initData): array
    {
        parse_str($initData, $params);

        if (empty($params['hash']) || !is_string($params['hash'])) {
            throw new InvalidMessengerInitDataException('Missing init-data hash.');
        }

        $providedHash = $params['hash'];
        unset($params['hash']);

        // Build the data-check-string: "key=value" pairs sorted by key, joined by \n.
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $pairs);

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $expectedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($expectedHash, $providedHash)) {
            throw new InvalidMessengerInitDataException('Init-data signature mismatch.');
        }

        $user = [];
        if (!empty($params['user']) && is_string($params['user'])) {
            $user = json_decode($params['user'], true) ?: [];
        }

        if (empty($user['id'])) {
            throw new InvalidMessengerInitDataException('Init-data has no user id.');
        }

        return [
            'messenger_user_id' => (string) $user['id'],
            'username'          => $user['username'] ?? null,
            'first_name'        => $user['first_name'] ?? null,
            'last_name'         => $user['last_name'] ?? null,
        ];
    }

    public function parseContactUpdate(array $payload): ?array
    {
        $message = $payload['message'] ?? null;
        $contact = $message['contact'] ?? null;

        if (!$message || !$contact || empty($contact['phone_number'])) {
            return null;
        }

        // Only trust a contact the user shared about *themselves*.
        $fromId = (string) ($message['from']['id'] ?? '');
        $contactUserId = isset($contact['user_id']) ? (string) $contact['user_id'] : $fromId;
        if ($fromId === '' || $contactUserId !== $fromId) {
            return null;
        }

        return [
            'messenger_user_id' => $fromId,
            'phone'             => (string) $contact['phone_number'],
            'username'          => $message['from']['username'] ?? null,
            'first_name'        => $contact['first_name'] ?? ($message['from']['first_name'] ?? null),
            'last_name'         => $contact['last_name'] ?? ($message['from']['last_name'] ?? null),
        ];
    }
}
