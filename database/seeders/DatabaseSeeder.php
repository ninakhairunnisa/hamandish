<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['phone' => '09000000000'],
            [
                'first_name' => 'Super',
                'last_name'  => 'Admin',
                'role'       => 'admin',
            ],
        );

        $categories = [
            ['title' => 'ترافیک',       'slug' => 'traffic',    'icon' => 'car',   'color' => '#F97316'],
            ['title' => 'فضای سبز',     'slug' => 'green-space', 'icon' => 'tree',  'color' => '#22C55E'],
            ['title' => 'پسماند شهری', 'slug' => 'waste',      'icon' => 'trash', 'color' => '#8B5CF6'],
            ['title' => 'روشنایی',      'slug' => 'lighting',   'icon' => 'bulb',  'color' => '#FACC15'],
            ['title' => 'آب',           'slug' => 'water',      'icon' => 'drop',  'color' => '#3B82F6'],
            ['title' => 'حمل و نقل',    'slug' => 'transport',  'icon' => 'bus',   'color' => '#EF4444'],
            ['title' => 'پیاده‌رو',     'slug' => 'sidewalk',   'icon' => 'road',  'color' => '#A855F7'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
