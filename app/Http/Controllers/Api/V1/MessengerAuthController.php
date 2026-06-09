<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidMessengerInitDataException;
use App\Exceptions\MessengerContactRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MessengerAuthRequest;
use App\Http\Resources\UserResource;
use App\Services\MessengerAuthService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MessengerAuthController extends Controller
{
    public function __construct(
        private readonly MessengerAuthService $messengerAuth,
    ) {}

    /**
     * Authenticate a Bale/Eitaa mini-app session from its signed init-data.
     */
    public function authenticate(MessengerAuthRequest $request): JsonResponse
    {
        try {
            $user = $this->messengerAuth->authenticate(
                $request->validated('provider'),
                $request->validated('init_data'),
            );
        } catch (InvalidMessengerInitDataException $e) {
            return response()->json(['message' => 'اعتبارسنجی نشست پیام‌رسان ناموفق بود.'], Response::HTTP_UNAUTHORIZED);
        } catch (MessengerContactRequiredException $e) {
            // The user is verified but hasn't shared their phone yet.
            return response()->json([
                'message'        => 'برای ورود، شماره تماس خود را با ربات به اشتراک بگذارید.',
                'need_contact'   => true,
                'bot_deep_link'  => $e->botDeepLink,
            ], Response::HTTP_CONFLICT);
        }

        $token = $user->createToken('messenger')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }
}
