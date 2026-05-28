<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\D3;

use App\Services\PdfService;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\D3RestLaravel\models\Angebot as D3Angebot;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\BestellscheinPdfService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class AngebotD3Service
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly BestellscheinPdfService $bestellscheinPdfService,
        private readonly D3AbteilungResolver $abteilungResolver,
        private readonly D3BenutzerResolver $benutzerResolver,
    ) {}

    /**
     * Pusht ein Angebot/Begründungs-PDF nach D3.
     */
    public function push(Angebot $angebot): ?string
    {
        $angebot->loadMissing('bestellung');
        $bestellung = $angebot->bestellung;

        $pdfPath = $this->resolvePdfPath($angebot);
        if ($pdfPath === null) {
            Log::warning('bestellungen.d3_angebot_push.no_pdf', ['angebot_id' => $angebot->getKey()]);

            return null;
        }

        $istBegruendung = $angebot->typ === 'begruendung';

        $dokument = new D3Angebot([
            'betreff' => 'Angebot zur Bestellung '.$bestellung->nummer,
            'Nummer' => (int) preg_replace('/\D+/', '', $bestellung->nummer) ?: 0,
            'Erfassungsdatum' => optional($angebot->created_at)->format('Y-m-d'),
            'Benutzer' => $this->benutzerResolver->resolve($bestellung),
            'Belegdatum' => optional($angebot->created_at)->format('Y-m-d'),
            'Begründung' => $istBegruendung ? 'Ja' : 'Nein',
            'Angebotsnummer' => $angebot->nummer ?? '-',
            'Abteilung' => $this->abteilungResolver->resolve($bestellung),
            'doc_type' => DocTypeEnum::Angebote,
            'filename' => basename($pdfPath),
        ]);

        $response = $dokument->save(filepath: $pdfPath);

        if (! $response->success) {
            Log::error('bestellungen.d3_angebot_push.failed', [
                'angebot_id' => $angebot->getKey(),
                'message' => $response->message,
            ]);

            return null;
        }

        $angebot->d3id = $response->id;
        $angebot->d3_pushed_at = now();
        $angebot->save();

        $bestellung->aktionen()->create([
            'user_id' => $angebot->user_id,
            'typ' => AktionTyp::AngebotHinzugefuegt->value,
            'von_status' => $bestellung->status?->value,
            'nach_status' => $bestellung->status?->value,
            'payload' => [
                'angebot_id' => $angebot->getKey(),
                'd3id' => $response->id,
                'typ' => $angebot->typ,
            ],
        ]);

        return $response->id;
    }

    /**
     * Erzeugt ein Begründungs-PDF aus Text-Begründung und legt es im Storage ab.
     */
    public function generateBegruendungPdf(Angebot $angebot): string
    {
        $angebot->loadMissing('bestellung');

        $html = View::make('intranet-app-bestellungen::pdf.angebot-begruendung', [
            ...$this->bestellscheinPdfService->sharedViewData($angebot->bestellung),
            'begruendung' => (string) $angebot->begruendung,
        ])->render();

        $relDir = 'bestellungen/angebote/'.$angebot->bestellung_id;
        $absDir = Storage::disk('local')->path($relDir);
        File::ensureDirectoryExists($absDir);

        // Gotenberg hängt „.pdf“ an outputFilename an – daher ohne Endung übergeben.
        $savedFilename = $this->pdfService->saveFromHtml(
            $html,
            $absDir,
            'begruendung-'.$angebot->getKey(),
        );

        $relPath = $relDir.'/'.$savedFilename;

        $angebot->pdf_path = $relPath;
        $angebot->save();

        return Storage::disk('local')->path($relPath);
    }

    /**
     * Überträgt alle noch nicht nach D3 gepushten Angebote/Ausnahme-Begründungen einer Bestellung.
     *
     * @throws \RuntimeException wenn ein erforderlicher Push fehlschlägt
     */
    public function pushPendingForBestellung(Bestellung $bestellung): void
    {
        $bestellung->loadMissing('angebote');

        foreach ($bestellung->angebote->whereNull('d3id') as $angebot) {
            if ($this->push($angebot) === null) {
                $label = $angebot->typ === 'begruendung'
                    ? 'Ausnahme-Begründung'
                    : 'Vergleichsangebot';

                throw new \RuntimeException(
                    sprintf('Die D3-Übertragung der %s (ID %d) ist fehlgeschlagen.', $label, $angebot->getKey()),
                );
            }
        }
    }

    private function resolvePdfPath(Angebot $angebot): ?string
    {
        if ($angebot->typ === 'begruendung' && empty($angebot->pdf_path)) {
            return $this->generateBegruendungPdf($angebot);
        }

        if (! $angebot->pdf_path) {
            return null;
        }

        return Storage::disk('local')->exists($angebot->pdf_path)
            ? Storage::disk('local')->path($angebot->pdf_path)
            : null;
    }
}
