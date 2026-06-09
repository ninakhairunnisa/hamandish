<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('votable'); // votable_id + votable_type + index
            $table->tinyInteger('type')->comment('+1 upvote, -1 downvote');
            $table->timestamps();

            $table->unique(['user_id', 'votable_id', 'votable_type'], 'votes_user_votable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
