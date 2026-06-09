<?php

declare(strict_types=1);

namespace App\Services\Messenger;

/**
 * Eitaa follows the same mini-app contract closely enough to reuse the
 * Telegram-style HMAC validation. If Eitaa's signing scheme is confirmed to
 * differ, override validateInitData()/parseContactUpdate() here only.
 */
class EitaaProvider extends TelegramStyleProvider
{
    public function name(): string
    {
        return 'eitaa';
    }

    public function botDeepLink(): string
    {
        return "https://eitaa.com/{$this->botUsername}";
    }
}
