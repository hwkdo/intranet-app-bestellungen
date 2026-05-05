<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Database\Factories;

use Hwkdo\IntranetAppBestellungen\Models\Art;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Art>
 */
class ArtFactory extends Factory
{
    protected $model = Art::class;

    public function definition(): array
    {
        return [
            'bezeichnung' => fake()->word(),
            'icon' => null,
            'aktiv' => true,
            'sortierung' => 0,
        ];
    }
}
