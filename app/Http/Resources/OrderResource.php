<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'customer_name'  => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'address'        => $this->address,
            'payment_method' => $this->payment_method,
            'receipt_url'    => $this->receipt_path ? url('storage/' . $this->receipt_path) : null,
            'status'         => $this->status,
            'total_amount'   => (int) $this->total_amount,
            'note'           => $this->note,
            'admin_note'     => $this->admin_note,
            'items'          => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'           => $i->id,
                'product_id'   => $i->product_id,
                'product_name' => $i->product_name,
                'unit_price'   => (int) $i->unit_price,
                'quantity'     => (int) $i->quantity,
            ])),
            'user'           => $this->whenLoaded('user', fn () => [
                'id'    => $this->user?->id,
                'phone' => $this->user?->phone,
                'name'  => trim(($this->user?->first_name ?? '') . ' ' . ($this->user?->last_name ?? '')),
            ]),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
