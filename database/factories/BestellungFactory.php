<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Database\Factories;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bestellung>
 */
class BestellungFactory extends Factory
{
    protected $model = Bestellung::class;

    public function definition(): array
    {
        return [
            'nummer' => '3'.fake()->unique()->numerify('#########'),
            'status' => BestellungStatus::ZurFreigabe,
            'freigabe_stufe_aktuell' => 1,
            'lieferantennummer' => fake()->numerify('#####'),
            'lieferantenname' => fake()->company(),
            'kostenstelle' => fake()->numerify('####'),
            'haushaltsjahr' => (int) date('Y'),
            'typ' => 'intern',
            'betreff' => fake()->sentence(4),
            'begruendung' => fake()->sentence(),
            'kontierung' => null,
            'gesamtbetrag' => fake()->randomFloat(2, 10, 5000),
            'user_id' => User::factory(),
        ];
    }

    public function status(BestellungStatus $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function bestellt(): self
    {
        return $this->state(fn (): array => [
            'status' => BestellungStatus::Bestellt,
            'bestellt_at' => now(),
        ]);
    }

    public function inD3(): self
    {
        return $this->state(fn (): array => [
            'd3id' => 'd3-'.fake()->uuid(),
            'd3_pushed_at' => now(),
        ]);
    }
}
