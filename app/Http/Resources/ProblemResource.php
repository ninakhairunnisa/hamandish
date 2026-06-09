<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProblemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'image_url'       => $this->image_path ? Storage::url($this->image_path) : null,
            'status'          => $this->status,
            'is_featured'     => (bool) $this->is_featured,
            'supports_count'  => (int) $this->supports_count,
            'solutions_count' => $this->whenCounted('solutions'),
            'comments_count'  => $this->whenCounted('comments'),
            'category'        => new CategoryResource($this->whenLoaded('category')),
            'user'            => new UserResource($this->whenLoaded('user')),
            'best_solution'   => new SolutionResource($this->whenLoaded('bestSolution')),
            // present only when the request is authenticated and the flag was eager-loaded
            'is_supported'    => $this->when(isset($this->is_supported), fn () => (bool) $this->is_supported),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
