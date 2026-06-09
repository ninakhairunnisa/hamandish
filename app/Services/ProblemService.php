<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Problem;
use App\Models\Solution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class ProblemService
{
    public function create(User $user, array $data, ?UploadedFile $image = null): Problem
    {
        return Problem::create([
            'user_id'     => $user->id,
            'category_id' => $data['category_id'] ?? null,
            'title'       => $data['title'],
            'description' => $data['description'],
            'image_path'  => $image?->store('problems', 'public'),
            'status'      => 'pending',
        ]);
    }

    /**
     * Build the public, approved feed query with optional search / category / sort.
     */
    public function feedQuery(array $filters): Builder
    {
        $query = Problem::approved()
            ->with(['user', 'category', 'bestSolution'])
            ->withCount(['solutions', 'comments']);

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $q) use ($term): void {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return match ($filters['sort'] ?? 'latest') {
            'popular' => $query->orderByDesc('supports_count')->orderByDesc('id'),
            default   => $query->latest(),
        };
    }

    public function updateStatus(Problem $problem, string $status): Problem
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Invalid status value.');
        }

        $problem->update(['status' => $status]);
        return $problem;
    }

    public function setFeatured(Problem $problem, bool $featured): Problem
    {
        $problem->update(['is_featured' => $featured]);
        return $problem;
    }

    public function setBestSolution(Problem $problem, int $solutionId): Problem
    {
        $solution = Solution::where('problem_id', $problem->id)->findOrFail($solutionId);

        $problem->update(['best_solution_id' => $solution->id]);
        return $problem->load('bestSolution');
    }

    public function autoSetBestSolution(Problem $problem): Problem
    {
        $best = $problem->solutions()
            ->orderByDesc('votes_count')
            ->first();

        if ($best) {
            $problem->update(['best_solution_id' => $best->id]);
        }

        return $problem->load('bestSolution');
    }
}
