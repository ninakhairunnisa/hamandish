<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use InvalidArgumentException;

class MessengerManager
{
    /**
     * @param  array<string, MessengerProvider>  $providers
     */
    public function __construct(
        private readonly array $providers,
    ) {}

    public function provider(string $name): MessengerProvider
    {
        return $this->providers[$name]
            ?? throw new InvalidArgumentException("Unsupported messenger provider: {$name}");
    }

    /**
     * @return array<int, string>
     */
    public function supported(): array
    {
        return array_keys($this->providers);
    }
}
