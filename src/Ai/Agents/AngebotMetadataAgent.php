<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class AngebotMetadataAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
Du liest den Text eines Angebots und füllst drei Felder.

- supplier_name: Name des anbietenden Unternehmens. null, wenn er nicht im Text steht.
- reference_number: Angebots-, Referenz- oder Vorgangsnummer. null, wenn keine Nummer im Text steht.
- amount: Endsumme, die zu zahlen ist (Gesamtbetrag, Summe, Endsumme, Bruttobetrag). JSON-Zahl mit Punkt als Dezimaltrennzeichen. null, wenn nur Einzelpreise oder keine Summe erkennbar sind.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'supplier_name' => $schema->string()->nullable()->required()
                ->description('Name des Anbieters, null wenn nicht erkennbar'),
            'reference_number' => $schema->string()->nullable()->required()
                ->description('Angebots- oder Referenznummer, null wenn nicht erkennbar'),
            'amount' => $schema->number()->nullable()->required()
                ->description('Endsumme als Zahl, null wenn nicht erkennbar'),
        ];
    }
}
