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
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'gender' => 'male',
            'birth_date' => '1990-01-01',
            'height_cm' => 175,
            'activity_level' => 'sedentary',
            'goal' => 'maintain',
            'goal_rate_kg_per_week' => null,
            'bmr_override' => null,
            'timezone' => 'Asia/Taipei',
            'onboarded_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * 尚未完成首次設定的使用者（剛註冊的狀態）。
     */
    public function notOnboarded(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => null,
            'birth_date' => null,
            'height_cm' => null,
            'activity_level' => null,
            'goal' => null,
            'onboarded_at' => null,
        ]);
    }
}
