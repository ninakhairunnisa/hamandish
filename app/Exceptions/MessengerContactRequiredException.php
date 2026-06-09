<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a verified messenger user has not yet shared their phone number,
 * so we cannot resolve (or create) the phone-keyed local account.
 */
class MessengerContactRequiredException extends RuntimeException
{
    public function __construct(
        public readonly string $botDeepLink,
    ) {
        parent::__construct('Contact sharing required.');
    }
}
