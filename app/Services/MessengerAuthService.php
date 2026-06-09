<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MessengerContactRequiredException;
use App\Models\MessengerIdentity;
use App\Models\User;
use App\Services\Messenger\MessengerManager;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

class MessengerAuthService
{
    public function __construct(
        private readonly MessengerManager $manager,
    ) {}

    /**
     * Authenticate a mini-app session from its signed init-data.
     *
     * If the messenger account is already linked (the user shared their contact
     * with the bot previously) we return the matching user. Otherwise we throw
     * MessengerContactRequiredException so the client can prompt the share flow.
     */
    public function authenticate(string $providerName, string $initData): User
    {
        $provider = $this->manager->provider($providerName);
        $payload = $provider->validateInitData($initData);

        $identity = MessengerIdentity::with('user')
            ->where('provider', $provider->name())
            ->where('messenger_user_id', $payload['messenger_user_id'])
            ->first();

        if ($identity && $identity->user) {
            return $identity->user;
        }

        throw new MessengerContactRequiredException($provider->botDeepLink());
    }

    /**
     * Handle a bot webhook update. When it carries a shared contact we resolve
     * (or create) the phone-keyed user and link this messenger identity to it.
     *
     * Phone is the single source of truth: the same person sharing their number
     * in Bale and Eitaa resolves to the SAME user — no duplicate accounts.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleContactWebhook(string $providerName, array $payload): ?User
    {
        $provider = $this->manager->provider($providerName);
        $contact = $provider->parseContactUpdate($payload);

        if ($contact === null) {
            return null;
        }

        $phone = PhoneNumber::normalize($contact['phone']);

        return DB::transaction(function () use ($provider, $contact, $phone): User {
            $user = User::firstOrCreate(
                ['phone' => $phone],
                [
                    'first_name' => $contact['first_name'],
                    'last_name'  => $contact['last_name'],
                    'role'       => 'user',
                ],
            );

            // Backfill profile names if they were empty.
            $user->fill(array_filter([
                'first_name' => $user->first_name ?: $contact['first_name'],
                'last_name'  => $user->last_name ?: $contact['last_name'],
            ]));
            if ($user->isDirty()) {
                $user->save();
            }

            MessengerIdentity::updateOrCreate(
                [
                    'provider'          => $provider->name(),
                    'messenger_user_id' => $contact['messenger_user_id'],
                ],
                [
                    'user_id'  => $user->id,
                    'username' => $contact['username'],
                ],
            );

            return $user;
        });
    }
}
