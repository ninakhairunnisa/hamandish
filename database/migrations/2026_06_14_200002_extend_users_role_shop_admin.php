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
        // Convert role to a plain string so any role value (user, admin,
        // super_admin, shop_admin, …) is accepted on every driver. On MySQL
        // the previous ENUM is widened; on SQLite the CHECK constraint that
        // backs an enum column is dropped by rebuilding the column as a string.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'");
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 20)->default('user')->change();
            });
        }
    }

    public function down(): void
    {
        // No-op: narrowing back to an enum would risk data loss.
    }
};
