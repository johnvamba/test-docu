<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'title' => rtrim(fake()->sentence(4), '.'),
            'content' => '<p>'.e(fake()->paragraph()).'</p>',
        ];
    }

    public function sharedWith(User $user, string $role = 'viewer'): static
    {
        return $this->afterCreating(fn (Document $document) => $document->collaborators()->attach($user->id, ['role' => $role]));
    }
}
