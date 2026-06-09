<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Problem;
use App\Models\Solution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoteTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSolution(User $author): Solution
    {
        $problem = Problem::create([
            'user_id'     => $author->id,
            'title'       => 'a valid problem title',
            'description' => str_repeat('x', 30),
            'status'      => 'approved',
        ]);

        return Solution::create([
            'problem_id' => $problem->id,
            'user_id'    => $author->id,
            'content'    => 'a solution content',
        ]);
    }

    public function test_user_cannot_vote_on_own_solution(): void
    {
        $author = User::factory()->create();
        $solution = $this->approvedSolution($author);

        $this->actingAs($author)
            ->postJson("/api/v1/solutions/{$solution->id}/vote", ['type' => 1])
            ->assertStatus(422);
    }

    public function test_vote_updates_cached_count_and_is_idempotent_per_user(): void
    {
        $author = User::factory()->create();
        $solution = $this->approvedSolution($author);
        $voter = User::factory()->create();

        $this->actingAs($voter)
            ->postJson("/api/v1/solutions/{$solution->id}/vote", ['type' => 1])
            ->assertOk()->assertJsonPath('votes_count', 1);

        // Same user flips to downvote — one row, score becomes -1.
        $this->actingAs($voter)
            ->postJson("/api/v1/solutions/{$solution->id}/vote", ['type' => -1])
            ->assertOk()->assertJsonPath('votes_count', -1);

        $this->assertSame(1, $solution->votes()->count());
    }

    public function test_user_can_remove_vote(): void
    {
        $author = User::factory()->create();
        $solution = $this->approvedSolution($author);
        $voter = User::factory()->create();

        $this->actingAs($voter)->postJson("/api/v1/solutions/{$solution->id}/vote", ['type' => 1]);
        $this->actingAs($voter)->deleteJson("/api/v1/solutions/{$solution->id}/vote")
            ->assertOk()->assertJsonPath('votes_count', 0);

        $this->assertSame(0, $solution->votes()->count());
    }
}
