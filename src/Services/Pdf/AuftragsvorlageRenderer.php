<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Pdf;

use Hwkdo\IntranetAppBestellungen\Models\Auftragsvorlage;
use Illuminate\Support\Str;

class AuftragsvorlageRenderer
{
    /**
     * Stehen in jeder Vorlage zur Verfügung, unabhängig von den gewählten Variablen.
     *
     * @var list<string>
     */
    public const SYSTEM_PLATZHALTER = [
        'anrede',
        'betreff',
        'angebotswert',
        'angebot_bezug',
    ];

    /**
     * @param  array<string, string>  $werte
     */
    public function html(Auftragsvorlage $vorlage, array $werte): string
    {
        return $this->htmlAusInhalt((string) $vorlage->inhalt, $werte);
    }

    /**
     * @param  array<string, string>  $werte
     */
    public function htmlAusInhalt(string $inhalt, array $werte): string
    {
        $text = $inhalt;

        foreach ($werte as $key => $value) {
            $ersatz = $key === 'freitext'
                ? (string) $value
                : e((string) $value);

            $text = str_replace('{'.$key.'}', $ersatz, $text);
        }

        return (string) Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function beispielWerte(): array
    {
        return [
            'anrede' => 'Sehr geehrter Herr Meier,',
            'betreff' => 'Dachsanierung',
            'angebotswert' => '1.190,00 EURO',
            'angebot_bezug' => 'Ihrem oben genannten Angebot',
            'frist' => '15.04.2026',
            'freitext' => "Sehr geehrte Damen und Herren,\n\nhier mein Text.",
        ];
    }
}
