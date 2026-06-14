<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ShopProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::with('category:id,title')
            ->when($request->filled('search'), function ($q) use ($request): void {
                $q->where('name', 'like', '%' . $request->string('search') . '%');
            })
            ->latest()
            ->paginate(20);

        return ProductResource::collection($products);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['image_path'] = $request->hasFile('image')
            ? $request->file('image')->store('products', 'public')
            : null;

        $product = Product::create($data);

        return response()->json(new ProductResource($product->load('category:id,title')), Response::HTTP_CREATED);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $this->validateData($request, partial: true);

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return response()->json(new ProductResource($product->fresh('category')));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete(); // soft delete — keeps order history intact
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'name'        => [$required, 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price'       => [$required, 'integer', 'min:0', 'max:2000000000'],
            'stock'       => [$required, 'integer', 'min:0', 'max:1000000'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'is_active'   => ['sometimes', 'boolean'],
            'image'       => ['nullable', 'image', 'max:4096'],
        ]);

        // The image file is handled separately via Storage; never mass-assign it.
        unset($data['image']);

        return $data;
    }
}
