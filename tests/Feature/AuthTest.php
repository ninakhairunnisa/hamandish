<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pretend the IPPanel gateway always accepts the request.
        Http::fake(['*ippanel*' => Http::response(['status' => 'ok'], 200)]);
    }

    public function test_send_otp_validates_iranian_phone(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '12345'])
            ->assertStatus(422);
    }

    public function test_send_otp_stores_code_and_returns_ok(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '09123456789'])
            ->assertOk();

        $this->assertNotNull(Cache::get('otp:09123456789'));
    }

    public function test_verify_otp_creates_user_and_issues_token(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '09123456789'])->assertOk();
        $code = Cache::get('otp:09123456789');

        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '09123456789',
            'token' => $code,
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'phone']]);

        $this->assertDatabaseHas('users', ['phone' => '09123456789']);
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '09123456789'])->assertOk();

        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '09123456789',
            'token' => '00000',
        ])->assertStatus(422);
    }

    public function test_otp_is_burned_after_max_failed_attempts(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '09123456789'])->assertOk();
        $code = Cache::get('otp:09123456789');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/verify-otp', [
                'phone' => '09123456789',
                'token' => '11111',
            ])->assertStatus(422);
        }

        // Even the correct code now fails because the OTP was invalidated.
        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '09123456789',
            'token' => $code,
        ])->assertStatus(422);
    }
}
