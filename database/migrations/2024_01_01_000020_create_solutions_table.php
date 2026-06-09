<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->integer('votes_count')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('problem_id');
            $table->index('user_id');
        });

        // Add FK for problems.best_solution_id now that solutions exists
        Schema::table('problems', function (Blueprint $table) {
            $table->foreign('best_solution_id')
                ->references('id')->on('solutions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropForeign(['best_solution_id']);
        });
        Schema::dropIfExists('solutions');
    }
};
