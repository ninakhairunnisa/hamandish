<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "supporters" of a problem (the 👥 counter on each problem card)
        Schema::create('supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('problem_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'problem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supports');
    }
};
