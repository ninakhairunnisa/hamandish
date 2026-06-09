<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\SMS\IPPanelSmsService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AuthService
{
    private const OTP_TTL = 120;          // seconds the OTP stays valid
    private const OTP_LENGTH = 5;
    private const MAX_VERIFY_ATTEMPTS = 5; // wrong tries before the OTP is burned

    public function __construct(
        private readonly IPPanelSmsService $smsService,
    ) {}

    /**
     * Generate, persist and dispatch an OTP. Throws if the gateway fails so the
     * caller can avoid penalising the user with a rate-limit lockout.
     */
    public function sendOtp(string $phone): void
    {
        $otp = $this->generateOtp();
        $key = $this->otpCacheKey($phone);

        $sent = $this->smsService->sendOtp($phone, $otp);

        if (!$sent) {
            throw new RuntimeException('Failed to send OTP via SMS gateway.');
        }

        // Persist only after a confirmed send, and reset any prior attempt counter.
        Cache::put($key, $otp, self::OTP_TTL);
        Cache::forget($this->attemptsCacheKey($phone));
    }

    public function verifyOtp(string $phone, string $token): User
    {
        $key = $this->otpCacheKey($phone);
        $cached = Cache::get($key);

        if ($cached === null) {
            throw new RuntimeException('Invalid or expired OTP.');
        }

        // Constant-time comparison to avoid leaking the code via timing.
        if (!hash_equals((string) $cached, $token)) {
            $this->registerFailedAttempt($phone);
            throw new RuntimeException('Invalid or expired OTP.');
        }

        // Success: burn the OTP and the attempt counter immediately.
        Cache::forget($key);
        Cache::forget($this->attemptsCacheKey($phone));

        return User::firstOrCreate(
            ['phone' => $phone],
            ['role' => 'user'],
        );
    }

    /**
     * Count a wrong guess; once the ceiling is hit the OTP is invalidated so an
     * attacker cannot keep brute-forcing the 5-digit code within its TTL.
     */
    private function registerFailedAttempt(string $phone): void
    {
        $attemptsKey = $this->attemptsCacheKey($phone);
        $attempts = (int) Cache::get($attemptsKey, 0) + 1;
        Cache::put($attemptsKey, $attempts, self::OTP_TTL);

        if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget($this->otpCacheKey($phone));
            Cache::forget($attemptsKey);
        }
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 99999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    private function otpCacheKey(string $phone): string
    {
        return "otp:{$phone}";
    }

    private function attemptsCacheKey(string $phone): string
    {
        return "otp-attempts:{$phone}";
    }
}
