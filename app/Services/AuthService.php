<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\SMS\IPPanelSmsService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AuthService
{
    private const OTP_TTL = 120; // seconds
    private const OTP_LENGTH = 5;

    public function __construct(
        private readonly IPPanelSmsService $smsService,
    ) {}

    public function sendOtp(string $phone): void
    {
        $otp = $this->generateOtp();
        $key = $this->otpCacheKey($phone);

        Cache::put($key, $otp, self::OTP_TTL);

        $sent = $this->smsService->sendOtp($phone, $otp);

        if (!$sent) {
            Cache::forget($key);
            throw new RuntimeException('Failed to send OTP via SMS gateway.');
        }
    }

    public function verifyOtp(string $phone, string $token): User
    {
        $key = $this->otpCacheKey($phone);
        $cached = Cache::get($key);

        if ($cached === null || (string) $cached !== $token) {
            throw new RuntimeException('Invalid or expired OTP.');
        }

        Cache::forget($key);

        return User::firstOrCreate(
            ['phone' => $phone],
            ['role' => 'user'],
        );
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(10000, 99999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    private function otpCacheKey(string $phone): string
    {
        return "otp:{$phone}";
    }
}
