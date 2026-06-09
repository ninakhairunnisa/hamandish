<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Services\MessengerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MessengerWebhookController extends Controller
{
    public function __construct(
        private readonly MessengerAuthService $messengerAuth,
    ) {}

    /**
     * Bot webhook endpoint. When the user shares their contact, we link the
     * messenger identity to the phone-keyed user. Secured by a per-provider
     * secret passed in the path so only the bot platform can call it.
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, ['bale', 'eitaa'], true)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $expectedSecret = (string) config("services.{$provider}.webhook_secret");
        if ($expectedSecret === '' || !hash_equals($expectedSecret, (string) $request->query('secret'))) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $this->messengerAuth->handleContactWebhook($provider, $request->all());

        // Always 200 so the bot platform does not retry/back off.
        return response()->json(['ok' => true]);
    }
}
