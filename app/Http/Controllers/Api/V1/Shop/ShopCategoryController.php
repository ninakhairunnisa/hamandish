<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShopCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            ProductCategory::ordered()->withCount('products')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        return response()->json(ProductCategory::create($data), Response::HTTP_CREATED);
    }

    public function update(Request $request, ProductCategory $category): JsonResponse
    {
        $data = $request->validate([
            'title'      => ['sometimes', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $category->update($data);

        return response()->json($category);
    }

    public function destroy(ProductCategory $category): JsonResponse
    {
        $category->delete(); // products keep category_id = null via nullOnDelete
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
