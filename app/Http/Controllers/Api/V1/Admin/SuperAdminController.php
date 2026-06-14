<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Comment;
use App\Models\Report;
use App\Models\Setting;
use App\Models\Solution;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminController extends Controller
{
    // ── SMS / System Settings ──────────────────────────────────────────────

    public function getSettings(): JsonResponse
    {
        $keys = [
            'ippanel_api_key',
            'ippanel_sender',
            'ippanel_otp_pattern_code',
            'ippanel_otp_pattern_variable',
            'ippanel_referral_pattern_code',
            'ippanel_referral_pattern_variable',
            'ippanel_membership_pattern_code',
            'ippanel_membership_pattern_variable',
            'assembly_section_title',
            'assembly_nav_label',
            'assembly_intro_message',
            'guest_can_view',
            'report_threshold',
            'comments_enabled',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = Setting::find($key)?->value ?? '';
        }

        return response()->json($settings);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ippanel_api_key'              => ['sometimes', 'string', 'max:200'],
            'ippanel_sender'               => ['sometimes', 'string', 'max:50'],
            'ippanel_otp_pattern_code'           => ['sometimes', 'string', 'max:100'],
            'ippanel_otp_pattern_variable'       => ['sometimes', 'string', 'max:50'],
            'ippanel_referral_pattern_code'      => ['sometimes', 'string', 'max:100'],
            'ippanel_referral_pattern_variable'  => ['sometimes', 'string', 'max:50'],
            'ippanel_membership_pattern_code'    => ['sometimes', 'string', 'max:100'],
            'ippanel_membership_pattern_variable'=> ['sometimes', 'string', 'max:50'],
            'assembly_section_title'       => ['sometimes', 'string', 'max:200'],
            'assembly_nav_label'           => ['sometimes', 'string', 'max:50'],
            'assembly_intro_message'       => ['sometimes', 'string', 'max:2000'],
            'guest_can_view'               => ['sometimes', 'boolean'],
            'report_threshold'             => ['sometimes', 'integer', 'min:1', 'max:100'],
            'comments_enabled'             => ['sometimes', 'boolean'],
        ]);

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                Setting::setBool($key, $value);
            } else {
                Setting::set($key, (string) $value);
            }
        }

        return response()->json(['message' => 'تنظیمات ذخیره شد.']);
    }

    // ── Role management (super_admin only) ────────────────────────────────

    public function setRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:user,admin,super_admin'],
        ]);

        // Prevent removing the last super_admin
        if ($data['role'] !== 'super_admin' && $user->isSuperAdmin()) {
            $count = User::where('role', 'super_admin')->count();
            if ($count <= 1) {
                return response()->json(
                    ['message' => 'باید حداقل یک ادمین کل وجود داشته باشد.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        $user->update(['role' => $data['role']]);

        return response()->json(new UserResource($user->fresh()));
    }

    public function banUser(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'is_banned' => ['required', 'boolean'],
        ]);

        $user->update(['is_banned' => $data['is_banned']]);

        // Revoke all tokens when banning
        if ($data['is_banned']) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message'   => $data['is_banned'] ? 'کاربر مسدود شد.' : 'مسدودیت برداشته شد.',
            'is_banned' => $user->is_banned,
        ]);
    }

    // ── Reported content review ────────────────────────────────────────────

    public function reportedContent(Request $request): JsonResponse
    {
        $type = $request->get('type', 'all'); // all | solution | comment

        $solutionsQ = Solution::with('user:id,phone,first_name,last_name', 'problem:id,title')
            ->where('is_hidden', true)
            ->orderByDesc('reports_count');

        $commentsQ  = Comment::with('user:id,phone,first_name,last_name')
            ->where('is_hidden', true)
            ->orderByDesc('reports_count');

        $solutions = ($type === 'comment') ? collect() : $solutionsQ->get();
        $comments  = ($type === 'solution') ? collect() : $commentsQ->get();

        return response()->json([
            'solutions' => $solutions->map(fn (Solution $s) => [
                'id'            => $s->id,
                'type'          => 'solution',
                'body'          => $s->body,
                'reports_count' => $s->reports_count,
                'user'          => ['id' => $s->user?->id, 'phone' => $s->user?->phone, 'name' => trim("{$s->user?->first_name} {$s->user?->last_name}")],
                'problem'       => ['id' => $s->problem?->id, 'title' => $s->problem?->title],
                'created_at'    => $s->created_at,
            ]),
            'comments' => $comments->map(fn (Comment $c) => [
                'id'            => $c->id,
                'type'          => 'comment',
                'body'          => $c->body,
                'reports_count' => $c->reports_count,
                'user'          => ['id' => $c->user?->id, 'phone' => $c->user?->phone, 'name' => trim("{$c->user?->first_name} {$c->user?->last_name}")],
                'created_at'    => $c->created_at,
            ]),
        ]);
    }

    public function reviewContent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'    => ['required', 'in:solution,comment'],
            'id'      => ['required', 'integer'],
            'action'  => ['required', 'in:restore,remove'],
        ]);

        $model = $data['type'] === 'solution'
            ? Solution::findOrFail($data['id'])
            : Comment::findOrFail($data['id']);

        if ($data['action'] === 'restore') {
            $model->update(['is_hidden' => false, 'reports_count' => 0]);
            Report::where('reportable_type', get_class($model))
                ->where('reportable_id', $model->id)
                ->delete();
            $msg = 'محتوا بازگردانی شد.';
        } else {
            // Keep hidden, just mark as reviewed by keeping is_hidden = true
            $model->update(['is_hidden' => true]);
            $msg = 'محتوا تأیید شد (پنهان باقی ماند).';
        }

        return response()->json(['message' => $msg]);
    }
}
