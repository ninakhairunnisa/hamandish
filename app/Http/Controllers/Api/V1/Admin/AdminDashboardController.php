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
            'role' => ['required', 'in:user,admin'],
        ]);

        if ($user->id === $request->user()->id && $validated['role'] !== 'admin') {
            return response()->json(
                ['message' => 'نمی‌توانید نقش ادمین را از خودتان بگیرید.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->update(['role' => $validated['role']]);

        return response()->json(new UserResource($user));
    }
}
