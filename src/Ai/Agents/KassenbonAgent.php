<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class KassenbonAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
Du liest den Text eines deutschen Kassenbons und lieferst die strukturierten Felder.

- supplier_name: Name des Händlers, der den Bon ausgestellt hat. null, wenn er fehlt.
- supplier_street, supplier_zip, supplier_city, vat_id: Anschrift und USt-IdNr., sonst null.
- receipt_number: Beleg- oder Bonnummer. null, wenn keine Nummer erkennbar ist. Niemals eine TSE-Prüfsumme, Genehmigungsnummer oder Kartennummer verwenden.
- receipt_date: Kaufdatum im Format JJJJ-MM-TT. null, wenn keines erkennbar ist.
- net_amount, tax_amount, amount: Netto, Steuer und Brutto-Endsumme als JSON-Zahl mit Punkt. null, wenn nicht erkennbar. amount ist der zu zahlende Betrag.
- payment_method: bar, girocard oder kreditkarte. null, wenn unklar.
- vat: Aufstellung je Steuersatz (rate 7 oder 19, net, tax, gross). Leeres Array, wenn keine Aufstellung da ist.
- line_items: Warenpositionen. Storno- und Rabattzeilen als negative line_total bzw. negative quantity. Pfand nur aufnehmen, wenn es eine eigene Position ist. TSE, Kartenzeilen, Unterschriften und Werbetext weglassen.
- Beträge im Bon mit nachgestelltem Minus (1,50-) sind negativ.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'supplier_name' => $schema->string()->nullable()->required()
                ->description('Name des Händlers, null wenn nicht erkennbar'),
            'supplier_street' => $schema->string()->nullable()->required(),
            'supplier_zip' => $schema->string()->nullable()->required(),
            'supplier_city' => $schema->string()->nullable()->required(),
            'vat_id' => $schema->string()->nullable()->required(),
            'receipt_number' => $schema->string()->nullable()->required()
                ->description('Belegnummer, null wenn nicht erkennbar'),
            'receipt_date' => $schema->string()->nullable()->required()
                ->description('Kaufdatum als JJJJ-MM-TT, null wenn nicht erkennbar'),
            'net_amount' => $schema->number()->nullable()->required(),
            'tax_amount' => $schema->number()->nullable()->required(),
            'amount' => $schema->number()->nullable()->required()
                ->description('Brutto-Endsumme'),
            'payment_method' => $schema->string()->nullable()->required()
                ->description('bar, girocard oder kreditkarte'),
            'vat' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'rate' => $schema->number()->required(),
                    'net' => $schema->number()->required(),
                    'tax' => $schema->number()->required(),
                    'gross' => $schema->number()->required(),
                ])
            )->required(),
            'line_items' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'description' => $schema->string()->nullable()->required(),
                    'quantity' => $schema->number()->nullable()->required(),
                    'unit' => $schema->string()->nullable()->required(),
                    'unit_price' => $schema->number()->nullable()->required(),
                    'line_total' => $schema->number()->nullable()->required(),
                    'article_number' => $schema->string()->nullable()->required(),
                ])
            )->required(),
        ];
    }
}
