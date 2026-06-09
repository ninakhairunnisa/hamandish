<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solution\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Solution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CommentController extends Controller
{
    public function index(Solution $solution): AnonymousResourceCollection
    {
        $comments = $solution->comments()
            ->with('user')
            ->latest()
            ->paginate(15);

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, Solution $solution): JsonResponse
    {
        $comment = Comment::create([
            'solution_id' => $solution->id,
            'user_id'     => $request->user()->id,
            'content'     => $request->validated('content'),
        ]);

        return response()->json(
            new CommentResource($comment->load('user')),
            Response::HTTP_CREATED,
        );
    }
}
