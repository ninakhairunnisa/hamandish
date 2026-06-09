<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'problem_id'  => $this->problem_id,
            'content'     => $this->content,
            'votes_count' => $this->votes_count,
            'edited_at'   => $this->edited_at?->toIso8601String(),
            'is_pinned'   => $this->is_pinned,
            'replies'     => CommentResource::collection($this->whenLoaded('comments')),
            'user'        => new UserResource($this->whenLoaded('user')),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
