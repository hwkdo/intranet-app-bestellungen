<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Database\Factories;

use Hwkdo\IntranetAppBestellungen\Enums\AuftragsvorlageVariable;
use Hwkdo\IntranetAppBestellungen\Models\Auftragsvorlage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auftragsvorlage>
 */
class AuftragsvorlageFactory extends Factory
{
    protected $model = Auftragsvorlage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'schluessel' => fake()->unique()->slug(2),
            'beschreibung' => fake()->optional()->sentence(),
            'inhalt' => 'Auftrag zu **{betreff}**.',
            'variablen' => [AuftragsvorlageVariable::Ansprechpartner->value],
            'anrede_automatisch' => true,
            'aktiv' => true,
            'sort' => 100,
        ];
    }
}
