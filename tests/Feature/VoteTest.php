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

    public function test_one_solution_per_user_and_edit_window(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $problem = \App\Models\Problem::create(['user_id' => $owner->id, 'title' => 'solution rules', 'description' => str_repeat('x', 30), 'status' => 'approved']);

        $first = $this->actingAs($user)
            ->postJson("/api/v1/problems/{$problem->id}/solutions", ['content' => 'راه‌حل اول من'])
            ->assertCreated()->json();

        $this->actingAs($user)
            ->postJson("/api/v1/problems/{$problem->id}/solutions", ['content' => 'راه‌حل دوم من'])
            ->assertStatus(422);

        $this->actingAs($user)
            ->patchJson("/api/v1/solutions/{$first['id']}", ['content' => 'راه‌حل ویرایش‌شده من'])
            ->assertOk()
            ->assertJsonPath('content', 'راه‌حل ویرایش‌شده من');

        $this->assertNotNull(\App\Models\Solution::find($first['id'])->edited_at);

        $this->actingAs($owner)
            ->patchJson("/api/v1/solutions/{$first['id']}", ['content' => 'ویرایش غیرمجاز دیگران'])
            ->assertForbidden();
    }

    public function test_global_comments_toggle_blocks_replies(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create();
        $replier = User::factory()->create();
        $problem = \App\Models\Problem::create(['user_id' => $author->id, 'title' => 'toggle test!', 'description' => str_repeat('x', 30), 'status' => 'approved']);
        $solution = \App\Models\Solution::create(['problem_id' => $problem->id, 'user_id' => $author->id, 'content' => 'یک راه‌حل خوب']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/settings', ['comments_enabled' => false])
            ->assertOk()
            ->assertJsonPath('comments_enabled', false);

        $this->actingAs($replier)
            ->postJson("/api/v1/solutions/{$solution->id}/comments", ['content' => 'پاسخ در حالت غیرفعال'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/settings', ['comments_enabled' => true]);

        $this->actingAs($replier)
            ->postJson("/api/v1/solutions/{$solution->id}/comments", ['content' => 'پاسخ در حالت فعال'])
            ->assertCreated();
    }
}
