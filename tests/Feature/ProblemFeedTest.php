<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Problem;
use App\Models\Support;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProblemFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_only_returns_approved_problems(): void
    {
        $user = User::factory()->create();
        Problem::create(['user_id' => $user->id, 'title' => 'approved one here', 'description' => str_repeat('x', 30), 'status' => 'approved']);
        Problem::create(['user_id' => $user->id, 'title' => 'pending one here', 'description' => str_repeat('x', 30), 'status' => 'pending']);

        $this->getJson('/api/v1/problems')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_featured_endpoint_returns_only_featured_approved(): void
    {
        $user = User::factory()->create();
        Problem::create(['user_id' => $user->id, 'title' => 'featured one here', 'description' => str_repeat('x', 30), 'status' => 'approved', 'is_featured' => true]);
        Problem::create(['user_id' => $user->id, 'title' => 'normal one here', 'description' => str_repeat('x', 30), 'status' => 'approved', 'is_featured' => false]);

        $this->getJson('/api/v1/problems/featured')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_featured', true);
    }

    public function test_support_toggle_increments_and_decrements_count(): void
    {
        $owner = User::factory()->create();
        $supporter = User::factory()->create();
        $problem = Problem::create(['user_id' => $owner->id, 'title' => 'supportable problem', 'description' => str_repeat('x', 30), 'status' => 'approved']);

        $this->actingAs($supporter)
            ->postJson("/api/v1/problems/{$problem->id}/support")
            ->assertOk()->assertJsonPath('supported', true)->assertJsonPath('supports_count', 1);

        $this->actingAs($supporter)
            ->postJson("/api/v1/problems/{$problem->id}/support")
            ->assertOk()->assertJsonPath('supported', false)->assertJsonPath('supports_count', 0);

        $this->assertSame(0, Support::where('problem_id', $problem->id)->count());
    }

    public function test_owner_can_view_own_pending_problem_but_others_cannot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $problem = Problem::create(['user_id' => $owner->id, 'title' => 'hidden pending', 'description' => str_repeat('x', 30), 'status' => 'pending']);

        $this->actingAs($owner)->getJson("/api/v1/problems/{$problem->id}")->assertOk();
        $this->actingAs($other)->getJson("/api/v1/problems/{$problem->id}")->assertNotFound();
        $this->getJson("/api/v1/problems/{$problem->id}")->assertNotFound();
    }
}
