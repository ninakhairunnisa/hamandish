<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Problem;
use App\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function __construct(
        private readonly SupportService $supportService,
    ) {}

    public function toggle(Request $request, Problem $problem): JsonResponse
    {
        $result = $this->supportService->toggle($request->user(), $problem);

        return response()->json($result);
    }
}
