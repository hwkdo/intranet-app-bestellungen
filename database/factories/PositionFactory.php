<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Database\Factories;

use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'bestellung_id' => Bestellung::factory(),
            'nr' => 1,
            'menge' => fake()->randomFloat(2, 1, 10),
            'einheit' => 'Stk',
            'bezeichnung' => fake()->sentence(3),
            'preis' => fake()->randomFloat(2, 5, 500),
        ];
    }
}
