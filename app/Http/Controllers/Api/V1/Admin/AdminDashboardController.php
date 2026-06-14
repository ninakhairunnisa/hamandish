<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Comment;
use App\Models\Problem;
use App\Models\Solution;
use App\Models\Support;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AdminDashboardController extends Controller
{
    /**
     * Aggregate counters for the admin dashboard.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'users'             => User::count(),
            'problems_total'    => Problem::count(),
            'problems_pending'  => Problem::where('status', 'pending')->count(),
            'problems_approved' => Problem::where('status', 'approved')->count(),
            'problems_rejected' => Problem::where('status', 'rejected')->count(),
            'problems_featured' => Problem::where('is_featured', true)->count(),
            'solutions'         => Solution::count(),
            'comments'          => Comment::count(),
            'supports'          => Support::count(),
        ]);
    }

    /**
     * Paginated user list with optional phone/name search.
     */
    public function users(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%' . $request->string('search') . '%';
                $q->where(fn ($w) => $w
                    ->where('phone', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term));
            })
            ->withCount('problems')
            ->latest()
            ->paginate(15);

        return UserResource::collection($users);
    }

    /**
     * Promote or demote a user. Admins cannot demote themselves so the
     * panel can never be locked out by accident.
     */
    public function setRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:user,admin'],  // regular admin cannot set super_admin
        ]);

        // Protect super_admin accounts from being demoted by regular admins.
        if ($user->isSuperAdmin()) {
            return response()->json(
                ['message' => 'تغییر نقش ادمین کل توسط ادمین عادی مجاز نیست.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        if ($user->id === $request->user()->id && $validated['role'] !== 'admin') {
            return response()->json(
                ['message' => 'نمی‌توانید نقش ادمین را از خودتان بگیرید.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->update(['role' => $validated['role']]);

        return response()->json(new UserResource($user));
    }

    /**
     * Set or clear a user's public label (e.g. "مسئول اداره برق").
     */
    public function setLabel(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $user->update(['label' => $validated['label'] ?? null]);

        return response()->json(new UserResource($user));
    }

    /**
     * Pin/unpin a comment so it stays at the top of its thread.
     */
    public function pinComment(Request $request, \App\Models\Comment $comment): JsonResponse
    {
        $validated = $request->validate([
            'is_pinned' => ['required', 'boolean'],
        ]);

        $comment->update(['is_pinned' => $validated['is_pinned']]);

        return response()->json(new \App\Http\Resources\CommentResource($comment->load('user')));
    }

    /**
     * Pin/unpin a solution so it stays at the top of the list.
     */
    public function pinSolution(Request $request, \App\Models\Solution $solution): JsonResponse
    {
        $validated = $request->validate(['is_pinned' => ['required', 'boolean']]);

        $solution->update(['is_pinned' => $validated['is_pinned']]);

        return response()->json(new \App\Http\Resources\SolutionResource($solution->load('user')));
    }

    /**
     * Ban or unban a user (available to all admins). Revokes all tokens
     * on ban so the account is logged out everywhere immediately.
     */
    public function banUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate(['is_banned' => ['required', 'boolean']]);

        // Regular admins cannot ban a super_admin account.
        if ($user->isSuperAdmin() && !$request->user()->isSuperAdmin()) {
            return response()->json(
                ['message' => 'مسدودسازی ادمین کل مجاز نیست.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        $user->update(['is_banned' => $validated['is_banned']]);

        if ($validated['is_banned']) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message'   => $validated['is_banned'] ? 'کاربر مسدود شد.' : 'مسدودیت برداشته شد.',
            'is_banned' => $user->is_banned,
        ]);
    }

    /**
     * Moderation list: solutions and comments with their visibility state,
     * for direct hide/show/delete management (independent of user reports).
     */
    public function moderation(Request $request): JsonResponse
    {
        $solutions = Solution::with('user:id,phone,first_name,last_name', 'problem:id,title')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Solution $s) => [
                'id'            => $s->id,
                'type'          => 'solution',
                'body'          => $s->body,
                'is_hidden'     => (bool) $s->is_hidden,
                'reports_count' => $s->reports_count,
                'user'          => ['id' => $s->user?->id, 'name' => trim("{$s->user?->first_name} {$s->user?->last_name}"), 'phone' => $s->user?->phone, 'is_banned' => (bool) $s->user?->is_banned],
                'problem'       => ['id' => $s->problem?->id, 'title' => $s->problem?->title],
            ]);

        $comments = Comment::with('user:id,phone,first_name,last_name')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Comment $c) => [
                'id'            => $c->id,
                'type'          => 'comment',
                'body'          => $c->body,
                'is_hidden'     => (bool) $c->is_hidden,
                'reports_count' => $c->reports_count,
                'user'          => ['id' => $c->user?->id, 'name' => trim("{$c->user?->first_name} {$c->user?->last_name}"), 'phone' => $c->user?->phone, 'is_banned' => (bool) $c->user?->is_banned],
            ]);

        return response()->json(['solutions' => $solutions, 'comments' => $comments]);
    }

    /** Hide or show a solution. */
    public function setSolutionVisibility(Request $request, Solution $solution): JsonResponse
    {
        $validated = $request->validate(['is_hidden' => ['required', 'boolean']]);
        $solution->update(['is_hidden' => $validated['is_hidden']]);

        return response()->json(['id' => $solution->id, 'is_hidden' => $solution->is_hidden]);
    }

    /** Permanently delete a solution. */
    public function deleteSolution(Solution $solution): JsonResponse
    {
        $solution->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /** Hide or show a comment. */
    public function setCommentVisibility(Request $request, Comment $comment): JsonResponse
    {
        $validated = $request->validate(['is_hidden' => ['required', 'boolean']]);
        $comment->update(['is_hidden' => $validated['is_hidden']]);

        return response()->json(['id' => $comment->id, 'is_hidden' => $comment->is_hidden]);
    }

    /** Permanently delete a comment. */
    public function deleteComment(Comment $comment): JsonResponse
    {
        $comment->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Global app settings (currently: comments/replies on or off).
     */
    public function getSettings(): JsonResponse
    {
        return response()->json([
            'comments_enabled' => \App\Models\Setting::getBool('comments_enabled'),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate(['comments_enabled' => ['required', 'boolean']]);

        \App\Models\Setting::setBool('comments_enabled', $validated['comments_enabled']);

        return $this->getSettings();
    }
}
