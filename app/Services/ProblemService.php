<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Problem;
use App\Models\Solution;
use App\Models\User;
use RuntimeException;

class ProblemService
{
    public function create(User $user, array $data): Problem
    {
        return Problem::create([
            'user_id'     => $user->id,
            'title'       => $data['title'],
            'description' => $data['description'],
            'status'      => 'pending',
        ]);
    }

    public function updateStatus(Problem $problem, string $status): Problem
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Invalid status value.');
        }

        $problem->update(['status' => $status]);
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
