<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    /**
     * Create an order from a cart payload.
     *
     * @param  array  $data   Validated checkout data:
     *                        customer_name, customer_phone, address,
     *                        payment_method, note (nullable),
     *                        items => [['product_id' => int, 'quantity' => int], ...]
     */
    public function checkout(User $user, array $data, ?UploadedFile $receipt = null): Order
    {
        return DB::transaction(function () use ($user, $data, $receipt): Order {
            $total = 0;
            $lines = [];

            foreach ($data['items'] as $line) {
                // Lock the row to prevent overselling under concurrency.
                $product = Product::where('id', $line['product_id'])->lockForUpdate()->first();

                if (!$product || !$product->is_active) {
                    throw new RuntimeException('یکی از محصولات دیگر در دسترس نیست.');
                }

                $qty = (int) $line['quantity'];
                if ($qty < 1) {
                    throw new RuntimeException('تعداد نامعتبر است.');
                }
                if ($product->stock < $qty) {
                    throw new RuntimeException("موجودی «{$product->name}» کافی نیست.");
                }

                $total += $product->price * $qty;
                $lines[] = [$product, $qty];
            }

            if (empty($lines)) {
                throw new RuntimeException('سبد خرید خالی است.');
            }

            $order = Order::create([
                'user_id'        => $user->id,
                'customer_name'  => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'address'        => $data['address'],
                'payment_method' => $data['payment_method'],
                'receipt_path'   => $receipt?->store('receipts', 'public'),
                'status'         => 'pending',
                'total_amount'   => $total,
                'note'           => $data['note'] ?? null,
            ]);

            foreach ($lines as [$product, $qty]) {
                $order->items()->create([
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'unit_price'   => $product->price,
                    'quantity'     => $qty,
                ]);

                $product->decrement('stock', $qty);
            }

            return $order->load('items');
        });
    }

    public function updateStatus(Order $order, string $status, ?string $adminNote = null): Order
    {
        if (!in_array($status, Order::STATUSES, true)) {
            throw new RuntimeException('وضعیت نامعتبر است.');
        }

        // Returning stock when an order is canceled (only if it wasn't already).
        if ($status === 'canceled' && $order->status !== 'canceled') {
            DB::transaction(function () use ($order): void {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                    }
                }
            });
        }

        $order->update([
            'status'     => $status,
            'admin_note' => $adminNote ?? $order->admin_note,
        ]);

        return $order;
    }
}
