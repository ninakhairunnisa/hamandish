<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssemblyMembership;
use App\Models\AssemblyRole;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssemblyMembershipController extends Controller
{
    /** Return the form data: intro message + available roles. */
    public function form(): JsonResponse
    {
        return response()->json([
            'intro_message' => Setting::find('assembly_intro_message')?->value ?? '',
            'roles'         => AssemblyRole::ordered()->get(['id', 'title']),
        ]);
    }

    /** Return the current user's membership (if any). */
    public function show(Request $request): JsonResponse
    {
        $membership = AssemblyMembership::where('user_id', $request->user()->id)->first();
        if (!$membership) {
            return response()->json(null);
        }
        return response()->json([
            'id'          => $membership->id,
            'roles'       => $membership->roles,
            'description' => $membership->description,
            'status'      => $membership->status,
            'created_at'  => $membership->created_at,
        ]);
    }

    /** Submit or update the current user's membership application. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'roles'       => ['required', 'array', 'min:1'],
            'roles.*'     => ['integer', 'exists:assembly_roles,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $existing = AssemblyMembership::where('user_id', $request->user()->id)->first();

        if ($existing && in_array($existing->status, ['approved', 'recorded'], true)) {
            return response()->json(
                ['message' => 'عضویت شما قبلاً تأیید شده است و قابل ویرایش نیست.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $membership = AssemblyMembership::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'roles'       => $data['roles'],
                'description' => $data['description'] ?? null,
                'status'      => 'pending',
                'admin_note'  => null,
            ]
        );

        return response()->json([
            'id'          => $membership->id,
            'roles'       => $membership->roles,
            'description' => $membership->description,
            'status'      => $membership->status,
            'created_at'  => $membership->created_at,
        ], $membership->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
