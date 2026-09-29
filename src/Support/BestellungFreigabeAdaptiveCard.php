<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Support;

use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\Position;

class BestellungFreigabeAdaptiveCard
{
    public const VERB_APPROVE = 'bestellungen.approve';

    public const VERB_REJECT = 'bestellungen.reject';

    public const VERB_REFRESH = 'bestellungen.refresh';

    private const MAX_POSITIONS = 10;

    /**
     * Freigabe-Card mit Refresh.
     *
     * refresh.userIds muss gesetzt sein (sonst ignoriert Teams refresh oft ganz).
     * - echte MRI (`29:…`): Auto-Refresh
     * - Azure-GUID / leer: manueller Refresh-Menüpunkt (kein zuverlässiges Auto)
     *
     * @return array<string, mixed>
     */
    public static function forBestellung(Bestellung $bestellung, ?string $recipientAzureUserIdOrMri = null): array
    {
        $bestellung->loadMissing(['user', 'positionen']);

        $detailUrl = route('apps.bestellungen.detail', [
            'bestellung' => $bestellung,
            'aktion' => 'freigeben',
        ]);

        $positionen = $bestellung->positionen;
        $shown = $positionen->take(self::MAX_POSITIONS);
        $remaining = max(0, $positionen->count() - $shown->count());

        $facts = [
            ['title' => 'Nummer', 'value' => (string) $bestellung->nummer],
            ['title' => 'Anforderer', 'value' => (string) ($bestellung->user?->name ?? '—')],
            ['title' => 'Lieferant', 'value' => (string) ($bestellung->lieferantenname ?: '—')],
            ['title' => 'Kostenstelle', 'value' => (string) ($bestellung->kostenstelle ?: '—')],
            ['title' => 'Summe', 'value' => self::formatMoney((float) $bestellung->gesamtbetrag)],
        ];

        $body = [
            [
                'type' => 'TextBlock',
                'size' => 'Medium',
                'weight' => 'Bolder',
                'text' => 'Bestellung zur Freigabe',
                'wrap' => true,
            ],
            [
                'type' => 'TextBlock',
                'text' => (string) ($bestellung->betreff ?: 'Ohne Betreff'),
                'wrap' => true,
                'spacing' => 'Small',
            ],
            [
                'type' => 'FactSet',
                'facts' => $facts,
            ],
        ];

        $begruendung = trim((string) ($bestellung->begruendung ?? ''));

        if ($begruendung !== '') {
            $body[] = [
                'type' => 'TextBlock',
                'weight' => 'Bolder',
                'text' => 'Begründung',
                'spacing' => 'Medium',
            ];
            $body[] = [
                'type' => 'TextBlock',
                'text' => self::truncate($begruendung, 500),
                'wrap' => true,
            ];
        }

        if ($shown->isNotEmpty()) {
            $body[] = [
                'type' => 'TextBlock',
                'weight' => 'Bolder',
                'text' => 'Positionen',
                'spacing' => 'Medium',
            ];

            /** @var Position $position */
            foreach ($shown as $position) {
                $line = sprintf(
                    '%s × %s %s — %s',
                    self::formatNumber((float) $position->menge),
                    (string) ($position->einheit ?: 'Stk'),
                    (string) ($position->bezeichnung ?: 'Position'),
                    self::formatMoney($position->gesamt()),
                );

                $body[] = [
                    'type' => 'TextBlock',
                    'text' => '• '.self::truncate($line, 160),
                    'wrap' => true,
                    'spacing' => 'None',
                ];
            }

            if ($remaining > 0) {
                $body[] = [
                    'type' => 'TextBlock',
                    'text' => '… und '.$remaining.' weitere',
                    'isSubtle' => true,
                    'wrap' => true,
                ];
            }
        }

        $body[] = [
            'type' => 'Input.Text',
            'id' => 'ablehnenGrund',
            'label' => 'Ablehnungsgrund (Pflicht bei Ablehnung)',
            'isMultiline' => true,
            'placeholder' => 'Grund angeben…',
        ];

        $card = [
            'type' => 'AdaptiveCard',
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'version' => '1.5',
            'body' => $body,
            'actions' => [
                [
                    'type' => 'Action.Execute',
                    'title' => 'Freigeben',
                    'verb' => self::VERB_APPROVE,
                    'data' => [
                        'bestellung_id' => $bestellung->id,
                    ],
                    'style' => 'positive',
                ],
                [
                    'type' => 'Action.Execute',
                    'title' => 'Ablehnen',
                    'verb' => self::VERB_REJECT,
                    'data' => [
                        'bestellung_id' => $bestellung->id,
                    ],
                    'style' => 'destructive',
                ],
                [
                    'type' => 'Action.OpenUrl',
                    'title' => 'Im Intranet öffnen',
                    'url' => $detailUrl,
                ],
            ],
        ];

        $card['refresh'] = [
            'action' => [
                'type' => 'Action.Execute',
                'title' => 'Aktualisieren',
                'verb' => self::VERB_REFRESH,
                'data' => [
                    'bestellung_id' => $bestellung->id,
                ],
            ],
            'userIds' => self::refreshUserIds($recipientAzureUserIdOrMri),
        ];

        return $card;
    }

    /**
     * @return list<string>
     */
    private static function refreshUserIds(?string $recipientAzureUserIdOrMri): array
    {
        if (! is_string($recipientAzureUserIdOrMri) || trim($recipientAzureUserIdOrMri) === '') {
            // Leeres Array = laut MS-Docs manueller Refresh-Menüpunkt, kein Auto.
            return [];
        }

        $value = trim($recipientAzureUserIdOrMri);

        // Echte Teams-MRI → Auto-Refresh für diesen User.
        if (str_starts_with($value, '29:')) {
            return [$value];
        }

        // AAD-Object-ID (socialite_id): kein gültiges MRI-Match → Teams zeigt
        // den manuellen Refresh (wie zuvor bestätigt). NICHT zu 29:1{guid}
        // umschreiben — das kann fälschlich als Auto-Ziel gelten und den
        // Menüpunkt entfernen, ohne dass Auto zuverlässig feuert.
        if (preg_match('/^[0-9a-f-]{36}$/i', $value) === 1) {
            return [strtolower($value)];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function statusCard(Bestellung $bestellung, string $headline, string $detail): array
    {
        $detailUrl = route('apps.bestellungen.detail', [
            'bestellung' => $bestellung,
        ]);

        return [
            'type' => 'AdaptiveCard',
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'version' => '1.5',
            'body' => [
                [
                    'type' => 'TextBlock',
                    'size' => 'Medium',
                    'weight' => 'Bolder',
                    'text' => $headline,
                    'wrap' => true,
                ],
                [
                    'type' => 'TextBlock',
                    'text' => $detail,
                    'wrap' => true,
                ],
                [
                    'type' => 'FactSet',
                    'facts' => [
                        ['title' => 'Nummer', 'value' => (string) $bestellung->nummer],
                        ['title' => 'Status', 'value' => (string) ($bestellung->status?->label() ?? $bestellung->status)],
                        ['title' => 'Summe', 'value' => self::formatMoney((float) $bestellung->gesamtbetrag)],
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'Action.OpenUrl',
                    'title' => 'Im Intranet öffnen',
                    'url' => $detailUrl,
                ],
            ],
        ];
    }

    private static function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private static function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1).'…';
    }
}
