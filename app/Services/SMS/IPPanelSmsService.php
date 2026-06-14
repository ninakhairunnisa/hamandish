<?php

declare(strict_types=1);

namespace App\Services\SMS;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IPPanelSmsService
{
    private const API_URL         = 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send';
    private const DIRECT_API_URL  = 'https://api2.ippanel.com/api/v1/sms/send/webservice/single';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $patternCode,
        private readonly string $sender,
        private readonly string $patternVariable = 'code',
    ) {}

    public function sendOtp(string $recipient, string $otp): bool
    {
        if ($this->patternCode === '') {
            Log::warning('IPPanel OTP skipped: ippanel_otp_pattern_code not configured.');
            return false;
        }
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

    /** Send a pattern SMS with a custom pattern code and arbitrary values map. */
    public function sendPattern(string $recipient, string $patternCode, array $values): bool
    {
        if (!$patternCode) {
            return false;
        }
        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post(self::API_URL, [
                'pattern_code' => $patternCode,
                'originator'   => $this->sender,
                'recipient'    => $recipient,
                'values'       => $values,
            ]);

            if ($response->failed()) {
                Log::error('IPPanel pattern SMS failed', [
                    'status'       => $response->status(),
                    'body'         => $response->body(),
                    'recipient'    => $recipient,
                    'pattern_code' => $patternCode,
                ]);
                return false;
            }

            return true;
        } catch (RequestException $e) {
            Log::error('IPPanel pattern SMS exception', [
                'message'   => $e->getMessage(),
                'recipient' => $recipient,
            ]);
            return false;
        }
    }

    public function sendDirect(string $recipient, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post(self::DIRECT_API_URL, [
                'sender'    => $this->sender,
                'recipient' => $recipient,
                'message'   => $message,
            ]);

            if ($response->failed()) {
                Log::error('IPPanel direct SMS failed', [
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                    'recipient' => $recipient,
                ]);
                return false;
            }

            return true;
        } catch (RequestException $e) {
            Log::error('IPPanel direct SMS exception', [
                'message'   => $e->getMessage(),
                'recipient' => $recipient,
            ]);
            return false;
        }
    }
}
