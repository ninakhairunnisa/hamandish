<?php

declare(strict_types=1);

namespace App\Services\SMS;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IPPanelSmsService
{
    private const API_URL = 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $patternCode,
        private readonly string $sender,
        private readonly string $patternVariable = 'code',
    ) {}

    public function sendOtp(string $recipient, string $otp): bool
    {
        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post(self::API_URL, [
                'pattern_code' => $this->patternCode,
                'originator' => $this->sender,
                'recipient' => $recipient,
                'values' => [
                    $this->patternVariable => $otp,
                ],
            ]);

            if ($response->failed()) {
                Log::error('IPPanel SMS failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'recipient' => $recipient,
                ]);
                return false;
            }

            return true;
        } catch (RequestException $e) {
            Log::error('IPPanel SMS exception', [
                'message' => $e->getMessage(),
                'recipient' => $recipient,
            ]);
            return false;
        }
    }
}
