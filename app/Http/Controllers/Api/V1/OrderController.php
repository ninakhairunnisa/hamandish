<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /** Checkout — create an order from the cart. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name'      => ['required', 'string', 'max:120'],
            'customer_phone'     => ['required', 'string', 'regex:/^09\d{9}$/'],
            'address'            => ['required', 'string', 'max:500'],
            'payment_method'     => ['required', 'in:cod,transfer'],
            'note'               => ['nullable', 'string', 'max:500'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:999'],
            'receipt'            => ['nullable', 'image', 'max:4096'],
        ]);

        try {
            $order = $this->orders->checkout(
                $request->user(),
                $data,
                $request->file('receipt'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(new OrderResource($order->load('items')), Response::HTTP_CREATED);
    }

    /** The authenticated user's own orders. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items')
            ->latest()
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return response()->json(new OrderResource($order->load('items')));
    }

    /** Upload / replace the bank-transfer receipt for an own pending order. */
    public function uploadReceipt(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $request->validate(['receipt' => ['required', 'image', 'max:4096']]);

        if ($order->receipt_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($order->receipt_path);
        }

        $order->update(['receipt_path' => $request->file('receipt')->store('receipts', 'public')]);

        return response()->json(new OrderResource($order->load('items')));
    }
}
