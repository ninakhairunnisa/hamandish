<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssemblyMembership extends Model
{
    protected $fillable = ['user_id', 'roles', 'description', 'status', 'admin_note'];

    protected $casts = ['roles' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
