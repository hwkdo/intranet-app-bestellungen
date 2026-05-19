<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Database\Factories;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Projekt>
 */
class ProjektFactory extends Factory
{
    protected $model = Projekt::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'beschreibung' => fake()->optional()->sentence(),
            'user_id' => User::factory(),
        ];
    }
}
