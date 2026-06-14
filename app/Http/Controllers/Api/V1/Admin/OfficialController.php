<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Official;
use App\Models\Problem;
use App\Models\ProblemReferral;
use App\Models\Setting;
use App\Models\User;
use App\Services\SMS\IPPanelSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OfficialController extends Controller
{
    public function index(): JsonResponse
    {
        $officials = Official::with('user:id,phone,first_name,last_name')
            ->orderBy('name')
            ->get()
            ->map(fn (Official $o) => $this->format($o));

        return response()->json($officials);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:150'],
            'phone'    => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'notes'    => ['nullable', 'string', 'max:1000'],
            'user_id'  => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Auto-link user if phone matches a registered user
        if (empty($data['user_id']) && !empty($data['phone'])) {
            $user = User::where('phone', $data['phone'])->first();
            if ($user) {
                $data['user_id'] = $user->id;
            }
        }

        $official = Official::create($data);
        $official->load('user:id,phone,first_name,last_name');

        return response()->json($this->format($official), Response::HTTP_CREATED);
    }

    public function update(Request $request, Official $official): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:100'],
            'position' => ['sometimes', 'string', 'max:150'],
            'phone'    => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'notes'    => ['nullable', 'string', 'max:1000'],
            'user_id'  => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (isset($data['phone']) && empty($data['user_id'])) {
            $user = User::where('phone', $data['phone'])->first();
            $data['user_id'] = $user?->id;
        }

        $official->update($data);
        $official->load('user:id,phone,first_name,last_name');

        return response()->json($this->format($official));
    }

    public function destroy(Official $official): JsonResponse
    {
        $official->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /** Search users by phone for linking an existing account. */
    public function searchUser(Request $request): JsonResponse
    {
        $phone = $request->validate(['phone' => ['required', 'string']])['phone'];
        $user = User::where('phone', $phone)->first();

        return response()->json($user
            ? ['id' => $user->id, 'phone' => $user->phone, 'name' => trim("{$user->first_name} {$user->last_name}")]
            : null
        );
    }

    /** Send a problem referral SMS to an official. */
    public function sendReferral(Request $request, Problem $problem, IPPanelSmsService $sms): JsonResponse
    {
        $data = $request->validate([
            'official_id' => ['required', 'integer', 'exists:officials,id'],
            'message'     => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $official = Official::findOrFail($data['official_id']);

        $phone = $official->phone ?? $official->user?->phone;

        if (!$phone) {
            return response()->json(['message' => 'شماره تماس مسئول ثبت نشده است.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prefer a registered IPPanel pattern when configured; otherwise fall
        // back to a direct free-text SMS using the editable message.
        $patternCode = Setting::get('ippanel_referral_pattern_code', '');
        if ($patternCode !== '') {
            $variable = Setting::get('ippanel_referral_pattern_variable', '') ?: 'message';
            $sent = $sms->sendPattern($phone, $patternCode, [$variable => $data['message']]);
        } else {
            $sent = $sms->sendDirect($phone, $data['message']);
        }

        $referral = ProblemReferral::create([
            'problem_id'  => $problem->id,
            'official_id' => $official->id,
            'message'     => $data['message'],
            'sent_at'     => $sent ? now() : null,
        ]);

        if (!$sent) {
            return response()->json(['message' => 'ارجاع ثبت شد اما ارسال پیامک ناموفق بود.'], Response::HTTP_ACCEPTED);
        }

        return response()->json([
            'message'   => 'ارجاع با موفقیت ارسال شد.',
            'referral'  => [
                'id'         => $referral->id,
                'official'   => $official->name,
                'sent_at'    => $referral->sent_at,
            ],
        ]);
    }

    /** List referrals for a problem (for the admin detail view). */
    public function referrals(Problem $problem): JsonResponse
    {
        $referrals = ProblemReferral::with('official:id,name,position')
            ->where('problem_id', $problem->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ProblemReferral $r) => [
                'id'       => $r->id,
                'official' => ['id' => $r->official->id, 'name' => $r->official->name, 'position' => $r->official->position],
                'message'  => $r->message,
                'sent_at'  => $r->sent_at,
            ]);

        return response()->json($referrals);
    }

    private function format(Official $o): array
    {
        return [
            'id'       => $o->id,
            'name'     => $o->name,
            'position' => $o->position,
            'phone'    => $o->phone,
            'notes'    => $o->notes,
            'user'     => $o->user ? [
                'id'    => $o->user->id,
                'phone' => $o->user->phone,
                'name'  => trim("{$o->user->first_name} {$o->user->last_name}"),
            ] : null,
        ];
    }
}
