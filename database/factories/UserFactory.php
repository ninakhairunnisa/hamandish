<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phone'      => '09' . $this->faker->numerify('#########'),
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'role'       => 'user',
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function shopAdmin(): static
    {
        return $this->state(['role' => 'shop_admin']);
    }

    public function superAdmin(): static
    {
        return $this->state(['role' => 'super_admin']);
    }
}
