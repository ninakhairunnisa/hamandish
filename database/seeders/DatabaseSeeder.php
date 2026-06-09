<?php

declare(strict_types=1);

namespace Database\Seeders;

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
    }
}
