<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Problem;
use App\Models\Support;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupportService
{
    /**
     * Toggle a user's support for a problem.
     *
     * @return array{supported: bool, supports_count: int}
     */
    public function toggle(User $user, Problem $problem): array
    {
        return DB::transaction(function () use ($user, $problem): array {
            $existing = Support::where('user_id', $user->id)
                ->where('problem_id', $problem->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $supported = false;
            } else {
                Support::create([
                    'user_id'    => $user->id,
                    'problem_id' => $problem->id,
                ]);
                $supported = true;
            }

            $count = Support::where('problem_id', $problem->id)->count();
            $problem->updateQuietly(['supports_count' => $count]);

            return ['supported' => $supported, 'supports_count' => $count];
        });
    }
}
