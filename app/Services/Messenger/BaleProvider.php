<?php

declare(strict_types=1);

namespace App\Services\Messenger;

class BaleProvider extends TelegramStyleProvider
{
    public function name(): string
    {
        return 'bale';
    }

    public function botDeepLink(): string
    {
        return "https://ble.ir/{$this->botUsername}";
    }
}
