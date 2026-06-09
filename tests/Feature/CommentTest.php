<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Problem;
use App\Models\Solution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_comments_work_polymorphically_on_problems_and_solutions(): void
    {
        $user = User::factory()->create();
        $problem = Problem::create(['user_id' => $user->id, 'title' => 'commentable problem', 'description' => str_repeat('x', 30), 'status' => 'approved']);
        $solution = Solution::create(['problem_id' => $problem->id, 'user_id' => $user->id, 'content' => 'a solution body']);

        $this->actingAs($user)
            ->postJson("/api/v1/problems/{$problem->id}/comments", ['content' => 'on problem'])
            ->assertCreated()
            ->assertJsonPath('commentable_type', 'Problem');

        $this->actingAs($user)
            ->postJson("/api/v1/solutions/{$solution->id}/comments", ['content' => 'on solution'])
            ->assertCreated()
            ->assertJsonPath('commentable_type', 'Solution');

        $this->getJson("/api/v1/problems/{$problem->id}/comments")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/solutions/{$solution->id}/comments")->assertOk()->assertJsonCount(1, 'data');
    }
}
