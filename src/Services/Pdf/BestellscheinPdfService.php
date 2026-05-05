<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Pdf;

use App\Services\PdfService;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class BestellscheinPdfService
{
    public function __construct(
        private readonly PdfService $pdfService,
    ) {}

    public function html(Bestellung $bestellung): string
    {
        $bestellung->loadMissing(['positionen.art', 'user', 'besteller', 'aktionen.user']);

        return View::make('intranet-app-bestellungen::pdf.bestellschein', [
            'bestellung' => $bestellung,
            'lieferant' => $this->resolveLieferant($bestellung),
            'logoDataUri' => $this->logoDataUri(),
        ])->render();
    }

    /**
     * Liefert das HWK-Dortmund-Logo als data:image/svg+xml;base64-URI,
     * damit das PDF-Rendering ohne externe URL auskommt.
     */
    private function logoDataUri(): string
    {
        $svgPath = __DIR__.'/../../../resources/img/logo-hwk.svg';

        if (! is_file($svgPath)) {
            return '';
        }

        $svg = (string) file_get_contents($svgPath);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
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
            $cache = \Hwkdo\IntranetAppBestellungen\Models\LieferantCache::query()
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
     * Erzeugt das Bestellschein-PDF und mergt ggf. Anhänge der Positionen.
     * Liefert den lokalen Dateipfad zur fertigen PDF zurück.
     */
    public function buildFile(Bestellung $bestellung): string
    {
        $tmpDir = storage_path('tmp/bestellungen');
        File::ensureDirectoryExists($tmpDir);

        $baseFilename = $this->makeFilename($bestellung);

        // Gotenberg::save() liefert den tatsächlich gespeicherten Dateinamen zurück.
        $savedFilename = $this->pdfService->saveFromHtml($this->html($bestellung), $tmpDir, $baseFilename);
        $basePath = $tmpDir.'/'.$savedFilename;

        if (! File::exists($basePath)) {
            throw new \RuntimeException(sprintf(
                'Bestellschein-PDF konnte nicht erzeugt werden: erwartete Datei "%s" existiert nicht.',
                $basePath,
            ));
        }

        $positionAnhaenge = $this->collectPositionAttachments($bestellung);

        if ($positionAnhaenge === []) {
            return $basePath;
        }

        return $this->pdfService->mergePdfs(
            array_merge([$basePath], $positionAnhaenge),
            $tmpDir,
            'merged-'.$baseFilename,
        );
    }

    public function inline(Bestellung $bestellung): Response
    {
        $path = $this->buildFile($bestellung);

        return response(File::get($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->makeFilename($bestellung).'"',
        ]);
    }

    public function download(Bestellung $bestellung): Response
    {
        $path = $this->buildFile($bestellung);

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
}
