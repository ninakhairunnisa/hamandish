<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
            $table->boolean('is_featured')->default(false)->after('status')->index();
            $table->string('image_path')->nullable()->after('description');
            $table->unsignedInteger('supports_count')->default(0)->after('is_featured')->index();
        });
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'is_featured', 'image_path', 'supports_count']);
        });
    }
};
