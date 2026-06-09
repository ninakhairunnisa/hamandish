<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\ProblemResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['first_name', 'last_name']);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return response()->json(new UserResource($user->fresh()));
    }

    public function problems(Request $request): AnonymousResourceCollection
    {
        $problems = $request->user()->problems()
            ->with('category')
            ->withCount(['solutions', 'comments'])
            ->latest()
            ->paginate(15);

        return ProblemResource::collection($problems);
    }
}
