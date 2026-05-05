<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\D3;

use App\Services\PdfService;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\D3RestLaravel\models\Angebot as D3Angebot;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class AngebotD3Service
{
    public function __construct(
        private readonly PdfService $pdfService,
    ) {}

    /**
     * Pusht ein Angebot/Begründungs-PDF nach D3.
     */
    public function push(Angebot $angebot): ?string
    {
        $angebot->loadMissing('bestellung.user');
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
            'Benutzer' => array_filter([optional($bestellung->user)->name]),
            'Belegdatum' => optional($angebot->created_at)->format('Y-m-d'),
            'Begründung' => $istBegruendung ? 'Ja' : 'Nein',
            'Angebotsnummer' => $angebot->nummer ?? '-',
            'Abteilung' => array_filter([optional($bestellung->user)->abteilung ?? null]),
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
        $angebot->loadMissing('bestellung.user');

        $html = View::make('intranet-app-bestellungen::pdf.angebot-begruendung', [
            'bestellung' => $angebot->bestellung,
            'begruendung' => (string) $angebot->begruendung,
        ])->render();

        $relPath = 'bestellungen/angebote/'.$angebot->bestellung_id.'/begruendung-'.$angebot->getKey().'.pdf';
        $absDir = Storage::disk('local')->path(dirname($relPath));
        File::ensureDirectoryExists($absDir);

        $this->pdfService->saveFromHtml($html, $absDir, basename($relPath));

        $angebot->pdf_path = $relPath;
        $angebot->save();

        return Storage::disk('local')->path($relPath);
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
