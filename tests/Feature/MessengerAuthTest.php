<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MessengerIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessengerAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $baleToken = 'bale-test-token';
    private string $eitaaToken = 'eitaa-test-token';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.bale.bot_token', $this->baleToken);
        config()->set('services.bale.bot_username', 'hamandish_bot');
        config()->set('services.bale.webhook_secret', 'bale-secret');
        config()->set('services.eitaa.bot_token', $this->eitaaToken);
        config()->set('services.eitaa.bot_username', 'hamandish_bot');
        config()->set('services.eitaa.webhook_secret', 'eitaa-secret');
    }

    /** Build a validly-signed Telegram/Bale-style init-data string. */
    private function signedInitData(string $token, string $userId): string
    {
        $params = [
            'auth_date' => (string) time(),
            'user'      => json_encode(['id' => $userId, 'first_name' => 'Ali', 'username' => 'ali']),
        ];
        ksort($params);
        $dcs = implode("\n", array_map(fn ($k, $v) => "$k=$v", array_keys($params), $params));
        $secret = hash_hmac('sha256', $token, 'WebAppData', true);
        $params['hash'] = hash_hmac('sha256', $dcs, $secret);

        return http_build_query($params);
    }

    public function test_authenticate_requires_contact_when_identity_unknown(): void
    {
        $initData = $this->signedInitData($this->baleToken, '555');

        $this->postJson('/api/v1/auth/messenger', ['provider' => 'bale', 'init_data' => $initData])
            ->assertStatus(409)
            ->assertJsonPath('need_contact', true)
            ->assertJsonPath('bot_deep_link', 'https://ble.ir/hamandish_bot');
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $tampered = $this->signedInitData('wrong-token', '555');

        $this->postJson('/api/v1/auth/messenger', ['provider' => 'bale', 'init_data' => $tampered])
            ->assertStatus(401);
    }

    public function test_contact_share_links_identity_and_authenticates(): void
    {
        // Bot receives a shared contact for messenger user 555.
        $this->postJson('/api/v1/integrations/bale/webhook?secret=bale-secret', [
            'message' => [
                'from'    => ['id' => 555, 'username' => 'ali'],
                'contact' => ['phone_number' => '+989121234567', 'user_id' => 555, 'first_name' => 'Ali'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('users', ['phone' => '09121234567']);

        // Now the mini-app login succeeds and issues a token.
        $initData = $this->signedInitData($this->baleToken, '555');
        $this->postJson('/api/v1/auth/messenger', ['provider' => 'bale', 'init_data' => $initData])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'phone']]);
    }

    public function test_same_phone_across_bale_and_eitaa_maps_to_one_user(): void
    {
        // Same person shares contact in Bale...
        $this->postJson('/api/v1/integrations/bale/webhook?secret=bale-secret', [
            'message' => [
                'from'    => ['id' => 111],
                'contact' => ['phone_number' => '989121234567', 'user_id' => 111],
            ],
        ])->assertOk();

        // ...and in Eitaa, with a different phone formatting and different messenger id.
        $this->postJson('/api/v1/integrations/eitaa/webhook?secret=eitaa-secret', [
            'message' => [
                'from'    => ['id' => 222],
                'contact' => ['phone_number' => '09121234567', 'user_id' => 222],
            ],
        ])->assertOk();

        $this->assertSame(1, User::count());
        $this->assertSame(2, MessengerIdentity::count());

        $user = User::first();
        $this->assertSame(2, $user->messengerIdentities()->count());
    }

    /** Build a validly-signed contact response (Eitaa requestContact result). */
    private function signedContactResponse(string $token, string $phone): string
    {
        $params = [
            'auth_date' => (string) time(),
            'contact'   => json_encode(['phone_number' => $phone, 'first_name' => 'Ali', 'user_id' => 777]),
        ];
        ksort($params);
        $dcs = implode("\n", array_map(fn ($k, $v) => "$k=$v", array_keys($params), $params));
        $secret = hash_hmac('sha256', $token, 'WebAppData', true);
        $params['hash'] = hash_hmac('sha256', $dcs, $secret);

        return http_build_query($params);
    }

    public function test_contact_response_completes_login_and_links_identity(): void
    {
        $initData = $this->signedInitData($this->eitaaToken, '777');
        $contactResponse = $this->signedContactResponse($this->eitaaToken, '+989121234567');

        $res = $this->postJson('/api/v1/auth/messenger/contact', [
            'provider'         => 'eitaa',
            'init_data'        => $initData,
            'contact_response' => $contactResponse,
        ])->assertOk()->assertJsonStructure(['token', 'user']);

        $user = User::where('phone', '09121234567')->firstOrFail();
        $this->assertDatabaseHas('messenger_identities', [
            'provider'          => 'eitaa',
            'messenger_user_id' => '777',
            'user_id'           => $user->id,
        ]);

        // Subsequent plain messenger login now succeeds without contact.
        $this->postJson('/api/v1/auth/messenger', ['provider' => 'eitaa', 'init_data' => $initData])
            ->assertOk();
    }

    public function test_contact_response_with_bad_signature_is_rejected(): void
    {
        $initData = $this->signedInitData($this->eitaaToken, '777');
        $forged = $this->signedContactResponse('wrong-token', '+989121234567');

        $this->postJson('/api/v1/auth/messenger/contact', [
            'provider'         => 'eitaa',
            'init_data'        => $initData,
            'contact_response' => $forged,
        ])->assertStatus(401);
    }

    public function test_webhook_rejects_bad_secret(): void
    {
        $this->postJson('/api/v1/integrations/bale/webhook?secret=nope', [
            'message' => ['from' => ['id' => 1], 'contact' => ['phone_number' => '09121234567', 'user_id' => 1]],
        ])->assertStatus(401);
    }
}
