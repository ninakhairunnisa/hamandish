<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Problem\StoreProblemRequest;
use App\Http\Resources\ProblemResource;
use App\Models\Problem;
use App\Services\ProblemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProblemController extends Controller
{
    public function __construct(
        private readonly ProblemService $problemService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $problems = Problem::approved()
            ->with(['user', 'bestSolution'])
            ->withCount('solutions')
            ->latest()
            ->paginate(15);

        return ProblemResource::collection($problems);
    }

    public function store(StoreProblemRequest $request): JsonResponse
    {
        $problem = $this->problemService->create($request->user(), $request->validated());

        return response()->json(new ProblemResource($problem->load('user')), Response::HTTP_CREATED);
    }

    public function show(Problem $problem): JsonResponse
    {
        if ($problem->status !== 'approved') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $problem->load(['user', 'bestSolution.user']);

        return response()->json(new ProblemResource($problem));
    }
}
