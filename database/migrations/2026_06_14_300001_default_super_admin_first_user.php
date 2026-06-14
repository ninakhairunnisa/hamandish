<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The default super admin is now the first registered user (id = 1)
        // instead of a hard-coded phone number. Promote id = 1 if it exists
        // and no super admin has been designated yet.
        $firstUser = DB::table('users')->where('id', 1)->first();

        if ($firstUser && $firstUser->role !== 'super_admin') {
            DB::table('users')->where('id', 1)->update(['role' => 'super_admin']);
        }
    }

    public function down(): void
    {
        // No-op: we don't want to strip super_admin on rollback.
    }
};
