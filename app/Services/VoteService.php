<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Solution;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VoteService
{
    public function vote(User $user, Solution $solution, int $type): Vote
    {
        if (!in_array($type, [1, -1], true)) {
            throw new RuntimeException('Vote type must be +1 or -1.');
        }

        $vote = DB::transaction(function () use ($user, $solution, $type): Vote {
            $vote = Vote::updateOrCreate(
                [
                    'user_id'     => $user->id,
                    'votable_id'  => $solution->id,
                    'votable_type' => Solution::class,
                ],
                ['type' => $type],
            );

            $this->recalculateVotesCount($solution);

            return $vote;
        });

        return $vote;
    }

    public function removeVote(User $user, Solution $solution): void
    {
        DB::transaction(function () use ($user, $solution): void {
            Vote::where([
                'user_id'     => $user->id,
                'votable_id'  => $solution->id,
                'votable_type' => Solution::class,
            ])->delete();

            $this->recalculateVotesCount($solution);
        });
    }

    private function recalculateVotesCount(Solution $solution): void
    {
        $netScore = Vote::where('votable_id', $solution->id)
            ->where('votable_type', Solution::class)
            ->sum('type');

        $solution->updateQuietly(['votes_count' => (int) $netScore]);
    }
}
