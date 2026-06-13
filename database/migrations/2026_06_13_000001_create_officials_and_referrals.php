<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('officials', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('position');
            $table->string('phone', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('problem_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('problem_id')->constrained()->cascadeOnDelete();
            $table->foreignId('official_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_referrals');
        Schema::dropIfExists('officials');
    }
};
