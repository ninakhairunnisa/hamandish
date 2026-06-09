<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Problem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_is_forbidden_from_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->getJson('/api/v1/admin/problems/pending')
            ->assertForbidden();
    }

    public function test_admin_approves_problem_and_owner_is_notified(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $problem = Problem::create(['user_id' => $owner->id, 'title' => 'to approve here', 'description' => str_repeat('x', 30), 'status' => 'pending']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/problems/{$problem->id}/status", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_admin_can_feature_problem(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $problem = Problem::create(['user_id' => $owner->id, 'title' => 'to feature here', 'description' => str_repeat('x', 30), 'status' => 'approved']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/problems/{$problem->id}/featured", ['is_featured' => true])
            ->assertOk()
            ->assertJsonPath('is_featured', true);
    }

    public function test_admin_stats_and_users_endpoints(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/stats')
            ->assertOk()
            ->assertJsonPath('users', 3);

        $this->actingAs($admin)->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_promote_user_but_not_demote_self(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}/role", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('role', 'admin');

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$admin->id}/role", ['role' => 'user'])
            ->assertStatus(422);
    }

    public function test_admin_can_list_and_delete_problems(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $problem = Problem::create(['user_id' => $owner->id, 'title' => 'to be deleted', 'description' => str_repeat('x', 30), 'status' => 'approved']);

        $this->actingAs($admin)->getJson('/api/v1/admin/problems?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin)->deleteJson("/api/v1/admin/problems/{$problem->id}")
            ->assertOk();

        $this->assertSoftDeleted('problems', ['id' => $problem->id]);
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/admin/stats')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/admin/users')->assertForbidden();
    }
}
