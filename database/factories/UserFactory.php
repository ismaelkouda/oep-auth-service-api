<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => UserType::Citizen,
            'name' => fake()->name(),
            'phone' => '+225'.fake()->unique()->numerify('##########'),
            'phone_verified_at' => now(),
            'profile_id' => Profile::factory(),
            'status' => UserStatus::Active,
        ];
    }

    public function staff(UserRole $role = UserRole::Manager): static
    {
        return $this->state(fn (): array => [
            'type' => UserType::Staff,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => $role,
            'organization_id' => (string) Str::uuid(),
            'territory_id' => (string) Str::uuid(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Pending,
            'phone_verified_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Suspended]);
    }

    public function locked(): static
    {
        return $this->state(fn (): array => ['locked_until' => now()->addMinutes(15)]);
    }
}
