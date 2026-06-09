<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetBestSolutionRequest;
use App\Http\Requests\Admin\UpdateProblemStatusRequest;
use App\Http\Resources\ProblemResource;
use App\Models\Problem;
use App\Services\ProblemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AdminProblemController extends Controller
{
    public function __construct(
        private readonly ProblemService $problemService,
    ) {}

    public function pending(): AnonymousResourceCollection
    {
        $problems = Problem::pending()
            ->with('user')
            ->withCount('solutions')
            ->latest()
            ->paginate(15);

        return ProblemResource::collection($problems);
    }

    public function updateStatus(UpdateProblemStatusRequest $request, Problem $problem): JsonResponse
    {
        $updated = $this->problemService->updateStatus($problem, $request->validated('status'));

        return response()->json(new ProblemResource($updated->load('user')));
    }

    public function setBestSolution(SetBestSolutionRequest $request, Problem $problem): JsonResponse
    {
        try {
            $updated = $this->problemService->setBestSolution($problem, (int) $request->validated('solution_id'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(new ProblemResource($updated));
    }
}
