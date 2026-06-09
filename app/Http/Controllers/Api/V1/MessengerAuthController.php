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
            \Illuminate\Support\Facades\Log::warning('Messenger init-data validation failed', [
                'provider' => $request->validated('provider'),
                'reason'   => $e->getMessage(),
                'init_data_keys' => array_keys(self::parseKeys($request->validated('init_data'))),
            ]);

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

    /**
     * Complete login with the signed contact response from requestContact().
     * Used when the host returns the shared phone directly to the web app
     * (Eitaa does) instead of delivering it via the bot webhook.
     */
    public function contact(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider'         => ['required', 'in:bale,eitaa'],
            'init_data'        => ['required', 'string'],
            'contact_response' => ['required', 'string'],
        ]);

        try {
            $user = $this->messengerAuth->authenticateWithContact(
                $validated['provider'],
                $validated['init_data'],
                $validated['contact_response'],
            );
        } catch (InvalidMessengerInitDataException $e) {
            \Illuminate\Support\Facades\Log::warning('Messenger contact-response validation failed', [
                'provider' => $validated['provider'],
                'reason'   => $e->getMessage(),
            ]);

            return response()->json(['message' => 'اعتبارسنجی شماره تماس ناموفق بود.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $user->createToken('messenger')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }

    /**
     * Parameter names only (never values) for safe diagnostic logging.
     *
     * @return array<string, mixed>
     */
    private static function parseKeys(string $initData): array
    {
        parse_str($initData, $params);

        return $params;
    }
}
