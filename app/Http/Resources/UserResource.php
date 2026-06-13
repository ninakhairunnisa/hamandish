<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fullName = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

        return [
            'id'           => $this->id,
            'phone'        => $this->phone,
            // Public-facing name: chosen name, else masked phone (0911***67).
            'display_name' => ($this->show_name && $fullName !== '')
                ? $fullName
                : self::maskPhone((string) $this->phone),
            'show_name'    => (bool) $this->show_name,
            'label'        => $this->label,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'avatar_url' => $this->avatar_path ? Storage::url($this->avatar_path) : null,
            'role'       => $this->role,
            'is_banned'  => (bool) $this->is_banned,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private static function maskPhone(string $phone): string
    {
        if (strlen($phone) < 6) {
            return 'کاربر';
        }

        return substr($phone, 0, 4) . '*****' . substr($phone, -2);
    }
}
