<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'content'          => $this->content,
            'commentable_type' => class_basename($this->commentable_type),
            'commentable_id'   => $this->commentable_id,
            'user'             => new UserResource($this->whenLoaded('user')),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
