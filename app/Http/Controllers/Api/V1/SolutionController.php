<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solution\StoreSolutionRequest;
use App\Http\Resources\SolutionResource;
use App\Models\Problem;
use App\Models\Solution;
use App\Notifications\NewSolutionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SolutionController extends Controller
{
    public function index(Problem $problem): AnonymousResourceCollection
    {
        $solutions = $problem->solutions()
            ->with('user')
            ->orderByDesc('votes_count')
            ->paginate(15);

        return SolutionResource::collection($solutions);
    }

    public function store(StoreSolutionRequest $request, Problem $problem): JsonResponse
    {
        if ($problem->status !== 'approved') {
            return response()->json(['message' => 'این مشکل در وضعیت تایید شده نیست.'], Response::HTTP_FORBIDDEN);
        }

        $solution = Solution::create([
            'problem_id' => $problem->id,
            'user_id'    => $request->user()->id,
            'content'    => $request->validated('content'),
        ]);

        // Notify the problem owner (unless they answered their own problem).
        if ($problem->user_id !== $request->user()->id) {
            $problem->user?->notify(new NewSolutionNotification($solution));
        }

        return response()->json(
            new SolutionResource($solution->load('user')),
            Response::HTTP_CREATED,
        );
    }
}
