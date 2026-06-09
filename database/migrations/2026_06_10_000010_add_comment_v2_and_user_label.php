<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'label')) {
                // Admin-assigned badge, e.g. "مسئول اداره برق".
                $table->string('label', 100)->nullable()->after('role');
            }
        });

        Schema::table('comments', function (Blueprint $table) {
            if (!Schema::hasColumn('comments', 'parent_id')) {
                // Replies: a comment on another comment (one level deep).
                $table->foreignId('parent_id')->nullable()->after('user_id')
                    ->constrained('comments')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('comments', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('content');
            }
            if (!Schema::hasColumn('comments', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('edited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('label');
        });
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['edited_at', 'is_pinned']);
        });
    }
};
