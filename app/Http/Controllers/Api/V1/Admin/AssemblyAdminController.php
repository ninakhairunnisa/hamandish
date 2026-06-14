<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssemblyMembership;
use App\Models\AssemblyRole;
use App\Models\Setting;
use App\Services\SMS\IPPanelSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssemblyAdminController extends Controller
{
    // ── Roles ─────────────────────────────────────────────────────────────

    public function roles(): JsonResponse
    {
        return response()->json(AssemblyRole::ordered()->get(['id', 'title', 'sort_order']));
    }

    public function storeRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $role = AssemblyRole::create($data);
        return response()->json($role, Response::HTTP_CREATED);
    }

    public function updateRole(Request $request, AssemblyRole $role): JsonResponse
    {
        $data = $request->validate([
            'title'      => ['sometimes', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $role->update($data);
        return response()->json($role);
    }

    public function destroyRole(AssemblyRole $role): JsonResponse
    {
        $role->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Settings (intro message) ───────────────────────────────────────────

    public function getSettings(): JsonResponse
    {
        return response()->json([
            'intro_message' => Setting::find('assembly_intro_message')?->value ?? '',
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'intro_message' => ['required', 'string', 'max:2000'],
        ]);
        Setting::updateOrCreate(
            ['key' => 'assembly_intro_message'],
            ['value' => $data['intro_message']]
        );
        return response()->json(['message' => 'ذخیره شد.']);
    }

    // ── Memberships ────────────────────────────────────────────────────────

    public function memberships(Request $request): JsonResponse
    {
        $query = AssemblyMembership::with('user:id,phone,first_name,last_name')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $memberships = $query->paginate(50)->through(fn ($m) => $this->formatMembership($m));

        return response()->json($memberships);
    }

    public function updateMembership(Request $request, AssemblyMembership $membership, IPPanelSmsService $sms): JsonResponse
    {
        $data = $request->validate([
            'status'     => ['required', 'in:pending,approved,rejected,recorded'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $wasApproved = $membership->status === 'approved';
        $membership->update($data);

        // Notify the member by SMS the first time they are approved, when a
        // membership pattern is configured in super-admin settings.
        if ($data['status'] === 'approved' && !$wasApproved) {
            $patternCode = Setting::get('ippanel_membership_pattern_code', '');
            $phone = $membership->fresh('user')->user?->phone;
            if ($patternCode !== '' && $phone) {
                $variable = Setting::get('ippanel_membership_pattern_variable', '') ?: 'name';
                $name = trim(($membership->user?->first_name ?? '') . ' ' . ($membership->user?->last_name ?? ''));
                $sms->sendPattern($phone, $patternCode, [$variable => $name ?: 'عضو گرامی']);
            }
        }

        return response()->json($this->formatMembership($membership->fresh('user')));
    }

    /** Export memberships as CSV. */
    public function exportCsv(Request $request)
    {
        $roles = AssemblyRole::ordered()->pluck('title', 'id');
        $query = AssemblyMembership::with('user:id,phone,first_name,last_name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $memberships = $query->orderByDesc('created_at')->get();

        $rows   = [];
        $rows[] = implode(',', ['نام', 'نام خانوادگی', 'شماره موبایل', 'مسئولیت‌های انتخاب‌شده', 'توضیحات', 'وضعیت', 'تاریخ ثبت']);

        foreach ($memberships as $m) {
            $selectedRoles = collect($m->roles)->map(fn ($id) => $roles[$id] ?? $id)->implode(' | ');
            $status = match ($m->status) {
                'pending'  => 'در انتظار',
                'approved' => 'تأیید شده',
                'rejected' => 'رد شده',
                'recorded' => 'ثبت شده',
                default    => $m->status,
            };
            $rows[] = implode(',', [
                '"' . ($m->user?->first_name ?? '') . '"',
                '"' . ($m->user?->last_name ?? '') . '"',
                '"' . ($m->user?->phone ?? '') . '"',
                '"' . $selectedRoles . '"',
                '"' . str_replace('"', '""', $m->description ?? '') . '"',
                '"' . $status . '"',
                '"' . $m->created_at->toDateTimeString() . '"',
            ]);
        }

        $csv = "\xEF\xBB\xBF" . implode("\n", $rows); // UTF-8 BOM for Excel

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="assembly-memberships.csv"',
        ]);
    }

    /** Summary counts per status. */
    public function stats(): JsonResponse
    {
        $counts = AssemblyMembership::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'total'    => $counts->sum(),
            'pending'  => $counts['pending']  ?? 0,
            'approved' => $counts['approved'] ?? 0,
            'rejected' => $counts['rejected'] ?? 0,
            'recorded' => $counts['recorded'] ?? 0,
        ]);
    }

    private function formatMembership(AssemblyMembership $m): array
    {
        return [
            'id'          => $m->id,
            'user'        => [
                'id'    => $m->user?->id,
                'phone' => $m->user?->phone,
                'name'  => trim(($m->user?->first_name ?? '') . ' ' . ($m->user?->last_name ?? '')),
            ],
            'roles'       => $m->roles,
            'description' => $m->description,
            'status'      => $m->status,
            'admin_note'  => $m->admin_note,
            'created_at'  => $m->created_at,
        ];
    }
}
