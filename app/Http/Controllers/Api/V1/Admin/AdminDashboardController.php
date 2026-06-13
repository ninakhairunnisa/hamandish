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
