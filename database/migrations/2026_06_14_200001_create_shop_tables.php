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
        // ── Product categories ────────────────────────────────────────────────
        if (!Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 100);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // ── Products ──────────────────────────────────────────────────────────
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('price')->default(0);     // Tomans
                $table->unsignedInteger('stock')->default(0);
                $table->string('image_path')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['is_active', 'category_id']);
            });
        }

        // ── Orders ────────────────────────────────────────────────────────────
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('customer_name', 120);
                $table->string('customer_phone', 20);
                $table->text('address');                              // single-line free text
                $table->enum('payment_method', ['cod', 'transfer'])->default('cod');
                $table->string('receipt_path')->nullable();          // transfer receipt image
                $table->enum('status', ['pending', 'confirmed', 'shipping', 'delivered', 'canceled'])->default('pending');
                $table->unsignedBigInteger('total_amount')->default(0);
                $table->text('note')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }

        // ── Order items (price/name snapshot at purchase time) ────────────────
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('product_name', 150);
                $table->unsignedBigInteger('unit_price');
                $table->unsignedInteger('quantity');
                $table->timestamps();
            });
        }

        // ── Default shop settings ─────────────────────────────────────────────
        $defaults = [
            'shop_enabled'    => '1',
            'shop_nav_label'  => 'فروشگاه',
            'shop_bank_card'  => '',     // card number for transfer payments
            'shop_bank_holder'=> '',     // account holder name
        ];
        foreach ($defaults as $key => $value) {
            DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => $value]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
