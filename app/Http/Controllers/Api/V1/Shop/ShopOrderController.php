<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ShopOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::with(['items', 'user:id,phone,first_name,last_name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(new OrderResource($order->load(['items', 'user:id,phone,first_name,last_name'])));
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status'     => ['required', 'in:' . implode(',', Order::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->orders->updateStatus($order, $data['status'], $data['admin_note'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(new OrderResource($order->fresh(['items', 'user'])));
    }

    /** Aggregate counters for the shop dashboard. */
    public function stats(): JsonResponse
    {
        $counts = Order::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        return response()->json([
            'total'     => (int) $counts->sum(),
            'pending'   => (int) ($counts['pending'] ?? 0),
            'confirmed' => (int) ($counts['confirmed'] ?? 0),
            'shipping'  => (int) ($counts['shipping'] ?? 0),
            'delivered' => (int) ($counts['delivered'] ?? 0),
            'canceled'  => (int) ($counts['canceled'] ?? 0),
            'revenue'   => (int) Order::where('status', 'delivered')->sum('total_amount'),
        ]);
    }
}
