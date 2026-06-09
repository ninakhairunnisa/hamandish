<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Links a messenger account (Bale / Eitaa) to a single local user.
        // Because users are keyed by a unique phone, the same person sharing
        // their contact in both messengers maps to ONE user — no duplicates.
        Schema::create('messenger_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['bale', 'eitaa']);
            $table->string('messenger_user_id', 64);
            $table->string('username')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'messenger_user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_identities');
    }
};
