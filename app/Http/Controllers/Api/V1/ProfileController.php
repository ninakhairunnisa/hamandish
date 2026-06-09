<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\ProblemResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['first_name', 'last_name', 'show_name']);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return response()->json(new UserResource($user->fresh()));
    }

    public function problems(Request $request): AnonymousResourceCollection
    {
        $problems = $request->user()->problems()
            ->with('category')
            ->withCount(['solutions', 'comments'])
            ->latest()
            ->paginate(15);

        return ProblemResource::collection($problems);
    }

    /**
     * The current user's comments, with enough context to deep-link back to
     * the problem (and scroll to the comment).
     */
    public function comments(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $comments = \App\Models\Comment::where('user_id', $request->user()->id)
            ->with('commentable')
            ->latest()
            ->paginate(15);

        $data = $comments->getCollection()->map(function (\App\Models\Comment $c): array {
            $commentable = $c->commentable;
            $problemId = $commentable instanceof \App\Models\Problem
                ? $commentable->id
                : $commentable?->problem_id;
            $title = $commentable instanceof \App\Models\Problem
                ? $commentable->title
                : $commentable?->problem?->title;

            return [
                'id'         => $c->id,
                'content'    => $c->content,
                'edited_at'  => $c->edited_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
                'problem_id' => $problemId,
                'problem_title' => $title,
            ];
        })->filter(fn (array $row) => $row['problem_id'] !== null)->values();

        return response()->json([
            'data' => $data,
            'meta' => ['current_page' => $comments->currentPage(), 'last_page' => $comments->lastPage()],
        ]);
    }
}
