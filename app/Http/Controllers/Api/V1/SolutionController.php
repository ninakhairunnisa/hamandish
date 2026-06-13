<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solution\StoreSolutionRequest;
use App\Http\Resources\SolutionResource;
use App\Models\Problem;
use App\Models\Solution;
use App\Notifications\NewSolutionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SolutionController extends Controller
{
    public function index(Problem $problem): AnonymousResourceCollection
    {
        $query = $problem->solutions()
            ->with(['user', 'comments' => fn ($q) => $q->with('user')->where('is_hidden', false)->oldest()])
            ->orderByDesc('is_pinned')
            ->orderByDesc('votes_count');

        // Admins see everything; regular users see only visible solutions.
        if (!request()->user()?->isAdmin()) {
            $query->where('is_hidden', false);
        }

        return SolutionResource::collection($query->paginate(15));
    }

    public function store(StoreSolutionRequest $request, Problem $problem): JsonResponse
    {
        if ($problem->status !== 'approved') {
            return response()->json(['message' => 'این مشکل در وضعیت تایید شده نیست.'], Response::HTTP_FORBIDDEN);
        }

        // One solution per user per problem.
        $exists = Solution::where('problem_id', $problem->id)
            ->where('user_id', $request->user()->id)
            ->exists();
        if ($exists) {
            return response()->json(
                ['message' => 'شما قبلاً برای این مشکل راه‌حل ثبت کرده‌اید؛ می‌توانید همان را ویرایش کنید.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $solution = Solution::create([
            'problem_id' => $problem->id,
            'user_id'    => $request->user()->id,
            'content'    => $request->validated('content'),
        ]);

        // Notify the problem owner (unless they answered their own problem).
        if ($problem->user_id !== $request->user()->id) {
            $problem->user?->notify(new NewSolutionNotification($solution));
        }

        return response()->json(
            new SolutionResource($solution->load('user')),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Edit own solution within 7 days; stamps edited_at.
     */
    public function update(StoreSolutionRequest $request, Solution $solution): JsonResponse
    {
        if ($solution->user_id !== $request->user()->id) {
            return response()->json(['message' => 'فقط نویسنده می‌تواند راه‌حل را ویرایش کند.'], Response::HTTP_FORBIDDEN);
        }

        if ($solution->created_at->diffInDays(now()) >= 7) {
            return response()->json(
                ['message' => 'مهلت ویرایش راه‌حل (۷ روز) به پایان رسیده است.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $solution->update([
            'content'   => $request->validated('content'),
            'edited_at' => now(),
        ]);

        return response()->json(new SolutionResource($solution->load('user')));
    }
}
