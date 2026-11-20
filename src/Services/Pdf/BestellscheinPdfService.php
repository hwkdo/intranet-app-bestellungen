<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Pdf;

use App\Services\PdfService;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Exceptions\AuftragsscheinUnvollstaendigException;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\LieferantCache;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class BestellscheinPdfService
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly AuftragsschreibenDaten $auftragsschreibenDaten,
    ) {}

    public function html(Bestellung $bestellung, BestellungTyp $typ = BestellungTyp::Intern): string
    {
        $bestellung->loadMissing(['positionen.art', 'user', 'besteller', 'lieferanschriftUser', 'aktionen.user', 'auftragszeilen', 'kassenbon']);

        if ($typ === BestellungTyp::Auftragsvergabe && $bestellung->nutztAuftragsvorlage()) {
            return View::make('intranet-app-bestellungen::pdf.auftragsschreiben', [
                ...$this->sharedViewData($bestellung),
                'schreiben' => $this->auftragsschreibenDaten->fuer($bestellung),
            ])->render();
        }

        $view = $typ === BestellungTyp::Auftragsvergabe
            ? 'intranet-app-bestellungen::pdf.auftragsvergabe'
            : 'intranet-app-bestellungen::pdf.bestellschein';

        return View::make($view, [
            ...$this->sharedViewData($bestellung),
            'typ' => $typ->value,
        ])->render();
    }

    /**
     * Gemeinsame Layout-Daten für Bestellschein und Ausnahme-Begründung (Legacy-Parität).
     *
     * @return array{bestellung: Bestellung, lieferant: array<string, ?string>, logoDataUri: string}
     */
    public function sharedViewData(Bestellung $bestellung): array
    {
        $bestellung->loadMissing(['user', 'besteller', 'lieferanschriftUser']);

        return [
            'bestellung' => $bestellung,
            'lieferant' => $this->resolveLieferant($bestellung),
            'logoDataUri' => $this->logoDataUri(),
        ];
    }

    /**
     * Liefert das HWK-Dortmund-Logo als data:-URI (wie Legacy hwkdo_logo.png),
     * damit Gotenberg ohne externe URL auskommt.
     */
    private function logoDataUri(): string
    {
        foreach ($this->logoCandidatePaths() as $path) {
            if (! is_readable($path)) {
                continue;
            }

            $binary = file_get_contents($path);
            if ($binary === false || $binary === '') {
                continue;
            }

            $mime = str_ends_with(strtolower($path), '.svg') ? 'image/svg+xml' : 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($binary);
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function logoCandidatePaths(): array
    {
        return [
            public_path('img/hwkdo_logo.png'),
            public_path('public/img/hwkdo_logo.png'),
            public_path('img/Handwerkskammer-Dortmund-Logo-Header.png'),
        ];
    }

    /**
     * Liefert die Lieferantenanschrift entweder aus dem auf der Bestellung
     * gespeicherten JSON oder als Fallback aus dem lokalen Cache.
     *
     * @return array{name:?string, strasse:?string, hausnummer:?string, plz:?string, ort:?string, nummer:?string}
     */
    private function resolveLieferant(Bestellung $bestellung): array
    {
        $anschrift = is_array($bestellung->lieferanschrift) ? $bestellung->lieferanschrift : [];

        if (empty($anschrift['strasse']) && filled($bestellung->lieferantennummer)) {
            $cache = LieferantCache::query()
                ->where('lieferantennummer', $bestellung->lieferantennummer)
                ->first();

            if ($cache) {
                $anschrift = [
                    'strasse' => $cache->strasse,
                    'hausnummer' => $cache->hausnummer,
                    'plz' => $cache->plz,
                    'ort' => $cache->ort,
                ];
            }
        }

        return [
            'name' => $bestellung->lieferantenname,
            'nummer' => $bestellung->lieferantennummer,
            'strasse' => $anschrift['strasse'] ?? null,
            'hausnummer' => $anschrift['hausnummer'] ?? null,
            'plz' => $anschrift['plz'] ?? null,
            'ort' => $anschrift['ort'] ?? null,
        ];
    }

    /**
     * Erzeugt das Bestellschein-PDF und mergt Positionsanhänge.
     * Bei der Auftragsvergabe hängen die PDFs der übernommenen Angebote an.
     * Liefert den lokalen Dateipfad zur fertigen PDF zurück.
     */
    public function buildFile(Bestellung $bestellung, BestellungTyp $typ = BestellungTyp::Intern): string
    {
        $tmpDir = storage_path('tmp/bestellungen');
        File::ensureDirectoryExists($tmpDir);

        $baseFilename = $this->makeFilename($bestellung);

        // Gotenberg::save() liefert den tatsächlich gespeicherten Dateinamen zurück.
        $savedFilename = $this->pdfService->saveFromHtml($this->html($bestellung, $typ), $tmpDir, $baseFilename);
        $basePath = $tmpDir.'/'.$savedFilename;

        if (! File::exists($basePath)) {
            throw new \RuntimeException(sprintf(
                'Bestellschein-PDF konnte nicht erzeugt werden: erwartete Datei "%s" existiert nicht.',
                $basePath,
            ));
        }

        $anhaenge = $this->collectPositionAttachments($bestellung);

        if ($typ === BestellungTyp::Auftragsvergabe) {
            $anhaenge = array_merge($anhaenge, $this->collectGewaehlteAngebotPdfs($bestellung));
        }

        if ($anhaenge === []) {
            return $basePath;
        }

        return $this->pdfService->mergePdfs(
            array_merge([$basePath], $anhaenge),
            $tmpDir,
            'merged-'.$baseFilename,
        );
    }

    public function inline(Bestellung $bestellung, BestellungTyp $typ = BestellungTyp::Intern): Response
    {
        $path = $this->buildFile($bestellung, $typ);

        return response(File::get($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->makeFilename($bestellung).'"',
        ]);
    }

    public function download(Bestellung $bestellung, BestellungTyp $typ = BestellungTyp::Intern): Response
    {
        $path = $this->buildFile($bestellung, $typ);

        return response(File::get($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->makeFilename($bestellung).'"',
        ]);
    }

    public function makeFilename(Bestellung $bestellung): string
    {
        return 'bestellschein-'.$bestellung->nummer.'.pdf';
    }

    /**
     * @return array<int, string>
     */
    private function collectPositionAttachments(Bestellung $bestellung): array
    {
        $bestellung->loadMissing('positionen.media');

        return $bestellung->positionen
            ->map(static function ($position): ?string {
                $mediaPath = $position->getFirstMediaPath('position_pdf');
                if ($mediaPath !== '') {
                    return $mediaPath;
                }

                $legacyPath = $position->file;
                if (is_string($legacyPath) && $legacyPath !== '' && Storage::disk('local')->exists($legacyPath)) {
                    return Storage::disk('local')->path($legacyPath);
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * PDFs der übernommenen Angebote in Zeilenreihenfolge.
     * Nicht übernommene Angebote bleiben außen vor; sie gehen separat als ANGEB nach D3.
     *
     * @return list<string>
     */
    private function collectGewaehlteAngebotPdfs(Bestellung $bestellung): array
    {
        $bestellung->loadMissing('auftragszeilen.angebot');

        $pfade = [];

        foreach ($bestellung->auftragszeilen as $zeile) {
            $pfad = $zeile->angebot?->absolutePdfPath();

            if ($pfad === null) {
                throw new AuftragsscheinUnvollstaendigException(sprintf(
                    'Die Auftragszeile %d hat kein hinterlegtes Angebot als PDF.',
                    $zeile->nr,
                ));
            }

            $pfade[] = $pfad;
        }

        return $pfade;
    }
}
