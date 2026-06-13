<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assembly_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('assembly_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('roles')->default('[]');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'recorded'])
                  ->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_memberships');
        Schema::dropIfExists('assembly_roles');
    }
};
