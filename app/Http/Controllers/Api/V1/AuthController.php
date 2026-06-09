<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $key = "otp-send:{$phone}:{$request->ip()}";

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "لطفاً {$seconds} ثانیه دیگر تلاش کنید.",
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($key, 120);

        try {
            $this->authService->sendOtp($phone);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json(['message' => 'کد OTP ارسال شد.'], Response::HTTP_OK);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        ['phone' => $phone, 'token' => $token] = $request->validated();

        try {
            $user = $this->authService->verifyOtp($phone, $token);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $accessToken = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $accessToken,
            'user'  => new UserResource($user),
        ], Response::HTTP_OK);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }
}
