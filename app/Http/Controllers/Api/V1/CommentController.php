<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solution\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Problem;
use App\Models\Solution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CommentController extends Controller
{
    public function indexForSolution(Solution $solution): AnonymousResourceCollection
    {
        return $this->index($solution);
    }

    public function storeForSolution(StoreCommentRequest $request, Solution $solution): JsonResponse
    {
        return $this->store($request, $solution);
    }

    public function indexForProblem(Problem $problem): AnonymousResourceCollection
    {
        return $this->index($problem);
    }

    public function storeForProblem(StoreCommentRequest $request, Problem $problem): JsonResponse
    {
        return $this->store($request, $problem);
    }

    private function index(Model $commentable): AnonymousResourceCollection
    {
        $comments = $commentable->comments()
            ->with('user')
            ->latest()
            ->paginate(15);

        return CommentResource::collection($comments);
    }

    private function store(StoreCommentRequest $request, Model $commentable): JsonResponse
    {
        /** @var Comment $comment */
        $comment = $commentable->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        return response()->json(
            new CommentResource($comment->load('user')),
            Response::HTTP_CREATED,
        );
    }
}
