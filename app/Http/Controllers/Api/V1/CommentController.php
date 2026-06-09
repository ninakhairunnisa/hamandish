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
    /** Days during which the author may still edit a comment. */
    private const EDIT_WINDOW_DAYS = 7;

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

    /**
     * Edit own comment/reply within the 7-day window; stamps edited_at.
     */
    public function update(StoreCommentRequest $request, Comment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'فقط نویسنده می‌تواند نظر را ویرایش کند.'], Response::HTTP_FORBIDDEN);
        }

        if ($comment->created_at->diffInDays(now()) >= self::EDIT_WINDOW_DAYS) {
            return response()->json(
                ['message' => 'مهلت ویرایش نظر (' . self::EDIT_WINDOW_DAYS . ' روز) به پایان رسیده است.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $comment->update([
            'content'   => $request->validated('content'),
            'edited_at' => now(),
        ]);

        return response()->json(new CommentResource($comment->load('user')));
    }

    /**
     * Reply to a top-level comment — one reply per user per comment.
     */
    public function storeReply(StoreCommentRequest $request, Comment $comment): JsonResponse
    {
        if (! \App\Models\Setting::getBool('comments_enabled')) {
            return response()->json(['message' => 'ثبت نظر موقتاً غیرفعال است.'], Response::HTTP_FORBIDDEN);
        }

        if ($comment->parent_id !== null) {
            return response()->json(['message' => 'پاسخ به پاسخ امکان‌پذیر نیست.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = Comment::where('parent_id', $comment->id)
            ->where('user_id', $request->user()->id)
            ->exists();
        if ($existing) {
            return response()->json(
                ['message' => 'شما قبلاً به این نظر پاسخ داده‌اید؛ می‌توانید پاسخ خود را ویرایش کنید.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $reply = Comment::create([
            'commentable_id'   => $comment->commentable_id,
            'commentable_type' => $comment->commentable_type,
            'parent_id'        => $comment->id,
            'user_id'          => $request->user()->id,
            'content'          => $request->validated('content'),
        ]);

        return response()->json(new CommentResource($reply->load('user')), Response::HTTP_CREATED);
    }

    /**
     * Top-level comments: pinned first, then newest. Replies nested.
     */
    private function index(Model $commentable): AnonymousResourceCollection
    {
        $comments = $commentable->comments()
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(15);

        return CommentResource::collection($comments);
    }

    /**
     * One top-level comment per user per problem/solution.
     */
    private function store(StoreCommentRequest $request, Model $commentable): JsonResponse
    {
        if (! \App\Models\Setting::getBool('comments_enabled')) {
            return response()->json(['message' => 'ثبت نظر موقتاً غیرفعال است.'], Response::HTTP_FORBIDDEN);
        }

        $existing = $commentable->comments()
            ->whereNull('parent_id')
            ->where('user_id', $request->user()->id)
            ->exists();
        if ($existing) {
            return response()->json(
                ['message' => 'شما قبلاً نظر خود را ثبت کرده‌اید؛ می‌توانید همان را ویرایش کنید.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

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
