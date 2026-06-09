<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solution\VoteRequest;
use App\Http\Resources\VoteResource;
use App\Models\Solution;
use App\Services\VoteService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VoteController extends Controller
{
    public function __construct(
        private readonly VoteService $voteService,
    ) {}

    public function vote(VoteRequest $request, Solution $solution): JsonResponse
    {
        try {
            $vote = $this->voteService->vote(
                $request->user(),
                $solution,
                (int) $request->validated('type'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $solution->refresh();

        return response()->json([
            'vote'        => new VoteResource($vote),
            'votes_count' => $solution->votes_count,
        ]);
    }
}
