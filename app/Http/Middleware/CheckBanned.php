<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_banned) {
            return response()->json(['message' => 'حساب کاربری شما مسدود شده است.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
