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

    public function test_one_comment_per_user_and_edit_window(): void
    {
        $user = \App\Models\User::factory()->create();
        $owner = \App\Models\User::factory()->create();
        $problem = \App\Models\Problem::create(['user_id' => $owner->id, 'title' => 'comment rules', 'description' => str_repeat('x', 30), 'status' => 'approved']);

        $first = $this->actingAs($user)
            ->postJson("/api/v1/problems/{$problem->id}/comments", ['content' => 'نظر اول من'])
            ->assertCreated()->json();

        // Second top-level comment is rejected.
        $this->actingAs($user)
            ->postJson("/api/v1/problems/{$problem->id}/comments", ['content' => 'نظر دوم'])
            ->assertStatus(422);

        // Author can edit within 7 days; edited_at is stamped.
        $this->actingAs($user)
            ->patchJson("/api/v1/comments/{$first['id']}", ['content' => 'نظر ویرایش‌شده'])
            ->assertOk()
            ->assertJsonPath('content', 'نظر ویرایش‌شده');

        $this->assertNotNull(\App\Models\Comment::find($first['id'])->edited_at);

        // Someone else cannot edit it.
        $this->actingAs($owner)
            ->patchJson("/api/v1/comments/{$first['id']}", ['content' => 'تلاش برای ویرایش غیرمجاز'])
            ->assertForbidden();
    }

    public function test_one_reply_per_user_per_comment(): void
    {
        $author = \App\Models\User::factory()->create();
        $replier = \App\Models\User::factory()->create();
        $owner = \App\Models\User::factory()->create();
        $problem = \App\Models\Problem::create(['user_id' => $owner->id, 'title' => 'reply rules!', 'description' => str_repeat('x', 30), 'status' => 'approved']);

        $comment = $this->actingAs($author)
            ->postJson("/api/v1/problems/{$problem->id}/comments", ['content' => 'نظر اصلی'])
            ->json();

        $this->actingAs($replier)
            ->postJson("/api/v1/comments/{$comment['id']}/replies", ['content' => 'پاسخ من'])
            ->assertCreated();

        $this->actingAs($replier)
            ->postJson("/api/v1/comments/{$comment['id']}/replies", ['content' => 'پاسخ دوم'])
            ->assertStatus(422);
    }

    public function test_display_name_masks_phone_when_no_name(): void
    {
        $user = \App\Models\User::factory()->create(['phone' => '09111234567', 'first_name' => null, 'last_name' => null]);

        $this->actingAs($user)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('display_name', '0911*****67');
    }
}
