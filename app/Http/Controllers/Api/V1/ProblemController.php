<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Problem\StoreProblemRequest;
use App\Http\Resources\ProblemResource;
use App\Models\Problem;
use App\Models\Support;
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

    public function index(Request $request): AnonymousResourceCollection
    {
        $problems = $this->problemService
            ->feedQuery($request->only(['search', 'category_id', 'sort']))
            ->paginate(15);

        $this->markSupported($problems->getCollection(), $request);

        return ProblemResource::collection($problems);
    }

    public function featured(Request $request): AnonymousResourceCollection
    {
        $problems = Problem::approved()
            ->featured()
            ->with(['user', 'category', 'bestSolution'])
            ->withCount(['solutions', 'comments'])
            ->latest()
            ->paginate(15);

        $this->markSupported($problems->getCollection(), $request);

        return ProblemResource::collection($problems);
    }

    public function popular(Request $request): AnonymousResourceCollection
    {
        $problems = Problem::approved()
            ->with(['user', 'category', 'bestSolution'])
            ->withCount(['solutions', 'comments'])
            ->orderByDesc('supports_count')
            ->orderByDesc('id')
            ->paginate(15);

        $this->markSupported($problems->getCollection(), $request);

        return ProblemResource::collection($problems);
    }

    public function store(StoreProblemRequest $request): JsonResponse
    {
        $problem = $this->problemService->create(
            $request->user(),
            $request->validated(),
            $request->file('image'),
        );

        return response()->json(
            new ProblemResource($problem->load(['user', 'category'])),
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, Problem $problem): JsonResponse
    {
        // Only approved problems are public; the owner and admins can view their own.
        if ($problem->status !== 'approved') {
            $user = $request->user();
            if (!$user || (!$user->isAdmin() && $user->id !== $problem->user_id)) {
                abort(Response::HTTP_NOT_FOUND);
            }
        }

        $problem->load(['user', 'category', 'bestSolution.user'])
            ->loadCount(['solutions', 'comments']);

        $this->markSupported(collect([$problem]), $request);

        return response()->json(new ProblemResource($problem));
    }

    /**
     * Annotate each problem with whether the current user supports it.
     */
    private function markSupported(\Illuminate\Support\Collection $problems, Request $request): void
    {
        $user = $request->user();
        if (!$user || $problems->isEmpty()) {
            return;
        }

        $supportedIds = Support::where('user_id', $user->id)
            ->whereIn('problem_id', $problems->pluck('id'))
            ->pluck('problem_id')
            ->flip();

        $problems->each(function (Problem $problem) use ($supportedIds): void {
            $problem->is_supported = $supportedIds->has($problem->id);
        });
    }
}
