<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solutions', function (Blueprint $table) {
            if (!Schema::hasColumn('solutions', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('content');
            }
            if (!Schema::hasColumn('solutions', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('edited_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'show_name')) {
                // Opt-in: display the chosen name instead of the masked phone.
                $table->boolean('show_name')->default(false)->after('label');
            }
        });

        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('value');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('solutions', fn (Blueprint $t) => $t->dropColumn(['edited_at', 'is_pinned']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('show_name'));
        Schema::dropIfExists('settings');
    }
};
