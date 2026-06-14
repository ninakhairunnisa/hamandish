<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => (int) $this->price,
            'stock'       => (int) $this->stock,
            'in_stock'    => $this->stock > 0,
            'is_active'   => (bool) $this->is_active,
            'image_url'   => $this->image_path ? url('storage/' . $this->image_path) : null,
            'category'    => $this->whenLoaded('category', fn () => [
                'id'    => $this->category?->id,
                'title' => $this->category?->title,
            ]),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
