<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Database\Factories;

use Hwkdo\IntranetAppBestellungen\Models\Auftragszeile;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auftragszeile>
 */
class AuftragszeileFactory extends Factory
{
    protected $model = Auftragszeile::class;

    public function definition(): array
    {
        return [
            'bestellung_id' => Bestellung::factory()->state([
                'typ' => 'auftragsvergabe',
            ]),
            'angebot_id' => null,
            'nr' => 1,
            'angebotsdatum' => fake()->date(),
            'angebotsnummer' => fake()->numerify('ANG-####'),
            'betrag_netto' => fake()->randomFloat(2, 100, 5000),
            'betrag_brutto' => fake()->randomFloat(2, 100, 6000),
            'freitext' => fake()->optional()->sentence(),
        ];
    }
}
