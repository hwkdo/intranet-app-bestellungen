<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\D3;

use App\Models\User;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\D3RestLaravel\Facades\D3RestLaravel;
use Hwkdo\D3RestLaravel\models\Bestellschein;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Hwkdo\IntranetAppBestellungen\Models\Notiz;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\BestellscheinPdfService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BestellscheinD3Service
{
    public function __construct(
        private readonly BestellscheinPdfService $pdfService,
    ) {}

    /**
     * Pusht einen frisch erzeugten Bestellschein nach D3.
     */
    public function push(Bestellung $bestellung, ?User $actor = null): ?string
    {
        $bestellung->loadMissing(['positionen', 'user', 'notizen']);

        $pdfPath = $this->pdfService->buildFile($bestellung);

        $dokument = new Bestellschein([
            'nummer' => (int) $bestellung->nummer,
            'lieferantenName' => (string) $bestellung->lieferantenname,
            'lieferantenSuchfeld' => (string) $bestellung->lieferantennummer,
            'lieferantenPlz' => (string) ($bestellung->lieferanschrift['plz'] ?? ''),
            'lieferantenOrt' => (string) ($bestellung->lieferanschrift['ort'] ?? ''),
            'kostenstelle' => (int) $bestellung->kostenstelle,
            'haushaltsjahr' => (int) $bestellung->haushaltsjahr,
            'erfassungsdatum' => optional($bestellung->created_at)->format('Y-m-d'),
            'bueBelegnummer' => null,
            'betreff' => $bestellung->betreff ?? $bestellung->nummer,
            'benutzer' => $this->resolveBenutzer($bestellung),
            'belegdatum' => optional($bestellung->created_at)->format('Y-m-d'),
            'abteilung' => $this->resolveAbteilung($bestellung),
            'doc_type' => DocTypeEnum::Bestellschein,
            'filename' => basename($pdfPath),
        ]);

        $this->log('push.start', $bestellung, ['pdf_path' => $pdfPath]);

        $response = $dokument->save(filepath: $pdfPath);

        if (! $response->success) {
            $this->log('push.failed', $bestellung, ['response_message' => $response->message]);

            return null;
        }

        $bestellung->d3id = $response->id;
        $bestellung->d3_pushed_at = now();
        $bestellung->save();

        $bestellung->aktionen()->create([
            'user_id' => $actor?->getKey(),
            'typ' => AktionTyp::D3Push->value,
            'von_status' => $bestellung->status?->value,
            'nach_status' => $bestellung->status?->value,
            'payload' => ['d3id' => $response->id],
        ]);

        $this->syncNotesToD3IfNeeded($bestellung);

        $this->log('push.success', $bestellung, ['d3id' => $response->id]);

        return $response->id;
    }

    /**
     * Re-Push: alten Eintrag „quasi-löschen", neu pushen.
     */
    public function rePush(Bestellung $bestellung, ?User $actor = null): ?string
    {
        $oldD3Id = $bestellung->d3id;

        if ($oldD3Id) {
            try {
                D3RestLaravel::quasiDeleteDoc($oldD3Id);
                $this->log('re_push.quasi_delete', $bestellung, ['old_d3id' => $oldD3Id]);
            } catch (\Throwable $e) {
                $this->log('re_push.quasi_delete_failed', $bestellung, [
                    'old_d3id' => $oldD3Id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $newId = $this->push($bestellung, $actor);

        if ($newId) {
            $bestellung->aktionen()->create([
                'user_id' => $actor?->getKey(),
                'typ' => AktionTyp::D3RePush->value,
                'von_status' => $bestellung->status?->value,
                'nach_status' => $bestellung->status?->value,
                'payload' => ['old_d3id' => $oldD3Id, 'new_d3id' => $newId],
            ]);
        }

        return $newId;
    }

    /**
     * D3-Suche nach Bestellschein anhand der BEN-Nummer.
     */
    public function search(Bestellung $bestellung): Collection
    {
        $result = D3RestLaravel::SearchResult(
            fulltext: $bestellung->nummer,
            doc_type: DocTypeEnum::Bestellschein,
        );

        return $result instanceof Collection ? $result : collect($result ?? []);
    }

    public function syncNotesToD3IfNeeded(Bestellung $bestellung): void
    {
        if (! $bestellung->d3id) {
            return;
        }

        if (! IntranetAppBestellungenSettings::resolvedAppSettings()->d3NotizenSyncAktiv) {
            return;
        }

        $bestellung->loadMissing('notizen');

        $bestellung->notizen
            ->where('an_d3_gesendet', false)
            ->each(function (Notiz $notiz) use ($bestellung): void {
                try {
                    D3RestLaravel::sendNote(
                        von: optional($notiz->user)->name ?? 'System',
                        message: $notiz->text,
                        id: $bestellung->d3id,
                    );
                    $notiz->forceFill(['an_d3_gesendet' => true])->save();
                } catch (\Throwable $e) {
                    Log::warning('bestellungen.d3_note_sync_failed', [
                        'notiz_id' => $notiz->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function resolveBenutzer(Bestellung $bestellung): array
    {
        $bestellung->loadMissing(['user', 'besteller', 'freigeber', 'aktionen.user']);

        $werte = collect([
            $this->d3BenutzerWert($bestellung->user),
            $this->d3BenutzerWert($bestellung->besteller),
            $this->d3BenutzerWert($bestellung->freigeber),
        ]);

        if (! $bestellung->freigeber_id) {
            $freigegebenDurch = $bestellung->aktionen
                ->firstWhere('typ', AktionTyp::Freigegeben->value)?->user;

            $werte->push($this->d3BenutzerWert($freigegebenDurch));
        }

        return $werte
            ->map(fn ($wert) => is_string($wert) ? trim($wert) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function d3BenutzerWert(?\App\Models\User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $ldapDisplayName = trim((string) ($user->ldap_displayname ?? ''));
        if ($ldapDisplayName !== '') {
            return $ldapDisplayName;
        }

        $vorname = trim((string) ($user->vorname ?? ''));
        $nachname = trim((string) ($user->nachname ?? ''));
        if ($nachname !== '' && $vorname !== '') {
            return $nachname.', '.$vorname;
        }

        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $username = trim((string) ($user->username ?? ''));

        return $username !== '' ? $username : null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveAbteilung(Bestellung $bestellung): array
    {
        $gruppen = collect($bestellung->gruppen ?? [])
            ->map(fn ($gruppe) => trim((string) $gruppe))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($gruppen !== []) {
            return $gruppen;
        }

        $user = $bestellung->user;
        if (! $user) {
            return [];
        }

        try {
            $ttlSeconds = $this->soapUserGroupsCacheTtlSeconds();
            $gruppen = D3RestLaravel::getUserInGroupsSoapCached((string) $user->username, $ttlSeconds);
            if (is_array($gruppen) && $gruppen !== []) {
                return collect($gruppen)
                    ->map(fn ($gruppe) => (string) $gruppe)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        } catch (\Throwable $e) {
            Log::warning('bestellungen.d3_groups_soap.failed', [
                'bestellung_id' => $bestellung->getKey(),
                'user_id' => $user->getKey(),
                'username' => $user->username,
                'error' => $e->getMessage(),
            ]);
        }

        $abteilung = $user->abteilung ?? null;

        return $abteilung ? [(string) $abteilung] : [];
    }

    private function soapUserGroupsCacheTtlSeconds(): int
    {
        $stunden = IntranetAppBestellungenSettings::resolvedAppSettings()->d3SoapUserGroupsCacheTtlStunden;

        return max(1, (int) $stunden) * 3600;
    }

    private function log(string $phase, Bestellung $bestellung, array $context = []): void
    {
        Log::info('bestellungen.d3_'.$phase, array_merge([
            'bestellung_id' => $bestellung->getKey(),
            'nummer' => $bestellung->nummer,
            'd3id' => $bestellung->d3id,
        ], $context));
    }
}
