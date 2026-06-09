<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->nullableMorphs('commentable'); // commentable_id + commentable_type
        });

        // Backfill any existing rows that referenced a solution directly.
        if (Schema::hasColumn('comments', 'solution_id')) {
            DB::table('comments')->whereNotNull('solution_id')->update([
                'commentable_id'   => DB::raw('solution_id'),
                'commentable_type' => \App\Models\Solution::class,
            ]);

            Schema::table('comments', function (Blueprint $table) {
                // Drop the compound index that references solution_id first.
                try {
                    $table->dropIndex(['solution_id', 'created_at']);
                } catch (\Throwable $e) {
                    // index may not exist on this driver
                }
                // Drop the FK only when the driver supports/created it.
                try {
                    $table->dropForeign(['solution_id']);
                } catch (\Throwable $e) {
                    // FK may not exist on this driver
                }
                $table->dropColumn('solution_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('solution_id')->nullable();
            $table->dropMorphs('commentable');
        });
    }
};
