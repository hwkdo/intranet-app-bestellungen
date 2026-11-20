<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use Hwkdo\IntranetAppBestellungen\Database\Factories\AuftragsvorlageFactory;
use Hwkdo\IntranetAppBestellungen\Enums\AuftragsvorlageVariable;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\AuftragsvorlageRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auftragsvorlage extends Model
{
    use HasFactory;

    protected $table = 'intranet_app_bestellungen_auftragsvorlagen';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'variablen' => 'array',
            'anrede_automatisch' => 'boolean',
            'aktiv' => 'boolean',
            'sort' => 'integer',
        ];
    }

    protected static function newFactory(): AuftragsvorlageFactory
    {
        return AuftragsvorlageFactory::new();
    }

    public function bestellungen(): HasMany
    {
        return $this->hasMany(Bestellung::class, 'auftragsvorlage_id');
    }

    public function scopeAktiv(Builder $query): Builder
    {
        return $query->where('aktiv', true);
    }

    public function verwendet(AuftragsvorlageVariable $variable): bool
    {
        return in_array($variable->value, $this->variablenWerte(), true);
    }

    /**
     * @return list<AuftragsvorlageVariable>
     */
    public function variablenEnums(): array
    {
        $enums = [];

        foreach ($this->variablenWerte() as $wert) {
            $enum = AuftragsvorlageVariable::tryFrom($wert);
            if ($enum instanceof AuftragsvorlageVariable) {
                $enums[] = $enum;
            }
        }

        return $enums;
    }

    /**
     * @return list<string>
     */
    public function bekanntePlatzhalter(): array
    {
        $platzhalter = AuftragsvorlageRenderer::SYSTEM_PLATZHALTER;

        foreach ($this->variablenEnums() as $variable) {
            $platzhalter = array_merge($platzhalter, $variable->platzhalter());
        }

        return array_values(array_unique($platzhalter));
    }

    /**
     * @return list<string>
     */
    public function verwendetePlatzhalter(): array
    {
        preg_match_all('/\{([a-z_]+)\}/u', (string) $this->inhalt, $treffer);

        return array_values(array_unique($treffer[1] ?? []));
    }

    /**
     * @return list<string>
     */
    public function unbekanntePlatzhalter(): array
    {
        return array_values(array_diff($this->verwendetePlatzhalter(), $this->bekanntePlatzhalter()));
    }

    /**
     * Variablen, deren Platzhalter im Brieftext fehlt.
     *
     * @return list<string>
     */
    public function variablenOhnePlatzhalter(): array
    {
        $fehlend = [];

        foreach ($this->variablenEnums() as $variable) {
            foreach ($variable->platzhalter() as $platzhalter) {
                if (! str_contains((string) $this->inhalt, '{'.$platzhalter.'}')) {
                    $fehlend[] = $variable->label();
                    break;
                }
            }
        }

        return $fehlend;
    }

    /**
     * @return list<string>
     */
    private function variablenWerte(): array
    {
        $werte = is_array($this->variablen) ? $this->variablen : [];

        return array_values(array_map(static fn (mixed $wert): string => (string) $wert, $werte));
    }

    /**
     * @return list<array{name: string, schluessel: string, beschreibung: string, inhalt: string, variablen: list<string>, anrede_automatisch: bool, aktiv: bool, sort: int}>
     */
    public static function standardVorlagen(): array
    {
        return [
            [
                'name' => 'Hausverwaltung',
                'schluessel' => 'hausverwaltung',
                'beschreibung' => 'Auftragsschreiben mit festem Text und Ausführungsfrist.',
                'inhalt' => <<<'TEXT'
Sie erhalten hiermit den Auftrag zu **{betreff}** gemäß {angebot_bezug} (Angebotswert gesamt: **{angebotswert}** inkl. Umsatzsteuer).

Die Ausführungsfrist für diesen Auftrag endet am **{frist}**. Die Nichteinhaltung der Ausführungsfrist ohne unsere vorherige Zustimmung entbindet uns von der Abnahmeverpflichtung und bedeutet die Stornierung des Auftrages.

Lieferschein und Rechnung müssen unbedingt die Verwendungsstelle bzw. den Einrichtungsbereich ausweisen.
TEXT,
                'variablen' => [
                    AuftragsvorlageVariable::Ansprechpartner->value,
                    AuftragsvorlageVariable::Ausfuehrungsfrist->value,
                ],
                'anrede_automatisch' => true,
                'aktiv' => true,
                'sort' => 10,
            ],
            [
                'name' => 'Allgemeine Dienstleistungen',
                'schluessel' => 'allgemeine-dienstleistungen',
                'beschreibung' => 'Auftragsschreiben mit frei formuliertem Brieftext.',
                'inhalt' => '{freitext}',
                'variablen' => [
                    AuftragsvorlageVariable::Ansprechpartner->value,
                    AuftragsvorlageVariable::Freitext->value,
                ],
                'anrede_automatisch' => false,
                'aktiv' => true,
                'sort' => 20,
            ],
        ];
    }
}
