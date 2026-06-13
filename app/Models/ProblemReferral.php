<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemReferral extends Model
{
    protected $fillable = ['problem_id', 'official_id', 'message', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    public function official(): BelongsTo
    {
        return $this->belongsTo(Official::class);
    }
}
