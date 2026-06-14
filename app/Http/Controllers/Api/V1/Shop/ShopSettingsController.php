<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'shop_enabled'     => Setting::getBool('shop_enabled', true),
            'shop_nav_label'   => Setting::get('shop_nav_label', 'فروشگاه'),
            'shop_bank_card'   => Setting::get('shop_bank_card', ''),
            'shop_bank_holder' => Setting::get('shop_bank_holder', ''),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shop_enabled'     => ['sometimes', 'boolean'],
            'shop_nav_label'   => ['sometimes', 'string', 'max:50'],
            'shop_bank_card'   => ['sometimes', 'nullable', 'string', 'max:30'],
            'shop_bank_holder' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                Setting::setBool($key, $value);
            } else {
                Setting::set($key, (string) ($value ?? ''));
            }
        }

        return $this->show();
    }
}
