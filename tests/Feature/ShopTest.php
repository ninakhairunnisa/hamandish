<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_active_products(): void
    {
        Product::factory()->count(3)->create();
        Product::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/shop/products')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_checkout_and_stock_decreases(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5, 'price' => 20_000]);

        $this->actingAs($user)
            ->postJson('/api/v1/orders', [
                'customer_name'  => 'علی رضایی',
                'customer_phone' => '09120000000',
                'address'        => 'سیاهکل، خیابان اصلی، پلاک ۱',
                'payment_method' => 'cod',
                'items'          => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertCreated()
            ->assertJsonPath('total_amount', 40_000)
            ->assertJsonPath('status', 'pending');

        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_checkout_rejects_insufficient_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 1]);

        $this->actingAs($user)
            ->postJson('/api/v1/orders', [
                'customer_name'  => 'تست',
                'customer_phone' => '09120000000',
                'address'        => 'آدرس تستی برای سفارش',
                'payment_method' => 'cod',
                'items'          => [['product_id' => $product->id, 'quantity' => 5]],
            ])
            ->assertStatus(422);

        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_transfer_checkout_stores_receipt(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 3]);

        $this->actingAs($user)
            ->postJson('/api/v1/orders', [
                'customer_name'  => 'تست',
                'customer_phone' => '09120000000',
                'address'        => 'آدرس تستی برای سفارش',
                'payment_method' => 'transfer',
                'items'          => [['product_id' => $product->id, 'quantity' => 1]],
                'receipt'        => UploadedFile::fake()->image('receipt.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('payment_method', 'transfer');
    }

    public function test_regular_user_cannot_access_shop_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/shop-admin/products')->assertForbidden();
    }

    public function test_shop_admin_can_create_product(): void
    {
        $admin = User::factory()->shopAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/shop-admin/products', [
                'name'  => 'محصول تستی',
                'price' => 50_000,
                'stock' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'محصول تستی');

        $this->assertDatabaseHas('products', ['name' => 'محصول تستی', 'stock' => 10]);
    }

    public function test_shop_admin_cannot_access_main_admin_panel(): void
    {
        $admin = User::factory()->shopAdmin()->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/problems/pending')->assertForbidden();
    }

    public function test_shop_admin_updates_order_status_and_cancel_restores_stock(): void
    {
        $shopAdmin = User::factory()->shopAdmin()->create();
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'customer_name'  => 'تست',
            'customer_phone' => '09120000000',
            'address'        => 'آدرس تستی برای سفارش',
            'payment_method' => 'cod',
            'items'          => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->assertSame(3, $product->fresh()->stock);
        $orderId = \App\Models\Order::first()->id;

        $this->actingAs($shopAdmin)
            ->patchJson("/api/v1/shop-admin/orders/{$orderId}/status", ['status' => 'canceled'])
            ->assertOk()
            ->assertJsonPath('status', 'canceled');

        // Stock returned on cancel
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_super_admin_can_assign_shop_admin_role(): void
    {
        $super = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($super)
            ->patchJson("/api/v1/super-admin/users/{$target->id}/role", ['role' => 'shop_admin'])
            ->assertOk();

        $this->assertSame('shop_admin', $target->fresh()->role);
    }
}
