<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->regexify('[a-z]{6}[0-9]{3}'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_USER,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08########'),
            'position' => fake()->jobTitle(),
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function developer(): static
    {
        return $this->state(['role' => User::ROLE_DEVELOPER]);
    }

    public function administrator(): static
    {
        return $this->state(['role' => User::ROLE_ADMINISTRATOR]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(['must_change_password' => true]);
    }
}
