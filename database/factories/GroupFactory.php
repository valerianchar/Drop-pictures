<?php

namespace Database\Factories;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->randomElement(['Famille', 'Mariage L&T', 'Week-end à Annecy', 'Club photo']),
            'invite_token' => Str::random(32),
        ];
    }

    /**
     * Le propriétaire est toujours membre de son groupe, comme le fait l'action
     * de création.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Group $group): void {
            $group->addMember($group->owner, GroupRole::Proprietaire);
        });
    }

    public function named(string $name): static
    {
        return $this->state(fn () => ['name' => $name]);
    }
}
