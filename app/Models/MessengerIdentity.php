<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerIdentity extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'messenger_user_id',
        'username',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
