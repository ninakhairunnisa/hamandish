<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShopController extends Controller
{
    /** Public product listing — active products, optional search/category. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with('category:id,title')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%' . $request->string('search') . '%';
                $q->where('name', 'like', $term);
            })
            ->latest()
            ->paginate(20);

        return ProductResource::collection($products);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);

        return response()->json(new ProductResource($product->load('category:id,title')));
    }

    /** Active product categories for the storefront filter. */
    public function categories(): JsonResponse
    {
        $categories = ProductCategory::ordered()
            ->where('is_active', true)
            ->get(['id', 'title']);

        return response()->json($categories);
    }
}
