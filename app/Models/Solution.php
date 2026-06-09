<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Solution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'problem_id',
        'user_id',
        'content',
        'edited_at',
        'is_pinned',
        'votes_count',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'is_pinned' => 'boolean',
            'votes_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // If a solution is removed, detach it from any problem that featured it
        // as the "best solution" so we never point at a hidden/trashed row.
        static::deleting(function (Solution $solution): void {
            Problem::where('best_solution_id', $solution->id)
                ->update(['best_solution_id' => null]);
        });
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(Vote::class, 'votable');
    }
}
