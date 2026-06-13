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
        // Extend users: super_admin role + ban flag
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_banned')->default(false)->after('show_name');
        });

        // Reports (polymorphic — solutions or comments)
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reportable');   // reportable_type, reportable_id
            $table->string('reason', 300)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'reportable_type', 'reportable_id']);
        });

        // Add reports_count + is_hidden to solutions and comments
        Schema::table('solutions', function (Blueprint $table): void {
            $table->unsignedInteger('reports_count')->default(0)->after('is_pinned');
            $table->boolean('is_hidden')->default(false)->after('reports_count');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->unsignedInteger('reports_count')->default(0)->after('is_pinned');
            $table->boolean('is_hidden')->default(false)->after('reports_count');
        });

        // Seed default super_admin settings
        $defaults = [
            // SMS
            'ippanel_api_key'              => '',
            'ippanel_sender'               => '',
            'ippanel_otp_pattern_code'     => '',
            'ippanel_otp_pattern_variable' => 'code',
            // Assembly section customisation
            'assembly_section_title'       => 'مشارکت در مجمع مردم مبعوث شده',
            'assembly_nav_label'           => 'مشارکت',
            // Guest / public access
            'guest_can_view'               => '1',
            // Report threshold before auto-hide
            'report_threshold'             => '3',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => $value]);
        }

        // Make the seeded phone a super_admin if a user with that phone exists
        DB::table('users')
            ->where('phone', '09352396988')
            ->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn(['reports_count', 'is_hidden']);
        });
        Schema::table('solutions', function (Blueprint $table): void {
            $table->dropColumn(['reports_count', 'is_hidden']);
        });
        Schema::dropIfExists('reports');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_banned');
        });
    }
};
