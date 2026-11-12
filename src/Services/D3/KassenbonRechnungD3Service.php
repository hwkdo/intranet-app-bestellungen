<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\D3;

use App\Models\User;
use App\Services\PdfService;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\D3RestLaravel\models\Zahlungsbeleg;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Models\Kassenbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use RuntimeException;

class KassenbonRechnungD3Service
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly D3AbteilungResolver $abteilungResolver,
        private readonly D3BenutzerResolver $benutzerResolver,
    ) {}

    public function push(Kassenbon $kassenbon, User $actor): ?string
    {
        if (filled($kassenbon->d3id_rechnung)) {
            return $kassenbon->d3id_rechnung;
        }

        $kassenbon->loadMissing('bestellung.user');
        $bestellung = $kassenbon->bestellung;
        if ($bestellung === null) {
            throw new RuntimeException('Dem Kassenbon ist keine Bestellung zugeordnet.');
        }

        $empfaenger = $this->benutzerResolver->wert($actor);
        if ($empfaenger === null || $empfaenger === '') {
            throw new RuntimeException('Für den Benutzer ist kein LDAP-Anzeigename hinterlegt. D3 erwartet ihn als WFL-Empfänger.');
        }

        $pdfPath = $this->belegPdf($kassenbon, (string) $bestellung->nummer);
        $filename = 'BEN-'.$bestellung->nummer.'-Rechnung.pdf';

        $dokument = new Zahlungsbeleg([
            'Nummer' => (int) $bestellung->nummer,
            'Benutzer' => $this->benutzerResolver->resolve($bestellung),
            'Abteilung' => $this->abteilungResolver->resolve($bestellung),
            'Belegtyp_ZB' => 'Rechnung',
            'Rechnungsnummer' => filled($kassenbon->belegnummer) ? (string) $kassenbon->belegnummer : '0',
            'Nettobetrag' => $this->betrag($kassenbon->nettobetrag),
            'Bruttobetrag' => $this->betrag($kassenbon->bruttobetrag),
            'Mehrwertsteuerbetrag' => $this->betrag($kassenbon->steuerbetrag),
            'Lieferantennummer' => (string) $bestellung->lieferantennummer,
            'Lieferantenname' => (string) $bestellung->lieferantenname,
            'Belegdatum' => optional($kassenbon->belegdatum)->format('Y-m-d'),
            'WFL_Empfaenger' => $empfaenger,
            'filename' => $filename,
            'doc_type' => DocTypeEnum::Zahlungsbeleg,
        ]);

        $response = $dokument->save(filepath: $pdfPath);

        if (! $response->success) {
            Log::warning('bestellungen.kassenbon_rechnung.push_failed', [
                'kassenbon_id' => $kassenbon->getKey(),
                'bestellung_id' => $bestellung->getKey(),
                'response_message' => $response->message,
            ]);

            throw new RuntimeException('D3 hat die Rechnung abgelehnt: '.$this->d3Grund($response->message));
        }

        $kassenbon->forceFill([
            'd3id_rechnung' => $response->id,
            'd3_rechnung_pushed_at' => now(),
        ])->save();

        $bestellung->aktionen()->create([
            'user_id' => $actor->getKey(),
            'typ' => AktionTyp::D3Push->value,
            'von_status' => $bestellung->status?->value,
            'nach_status' => $bestellung->status?->value,
            'payload' => ['d3id' => $response->id, 'art' => 'rechnung'],
        ]);

        return $response->id;
    }

    private function belegPdf(Kassenbon $kassenbon, string $nummer): string
    {
        $tmpDir = storage_path('tmp/bestellungen');
        File::ensureDirectoryExists($tmpDir);

        $bildDataUri = $kassenbon->istBild() ? $this->bildDataUri($kassenbon->dateiAbsolut()) : '';
        $html = View::make('intranet-app-bestellungen::pdf.kassenbon-beleg', [
            'nummer' => $nummer,
            'belegnummer' => filled($kassenbon->belegnummer) ? $kassenbon->belegnummer : '0',
            'bildDataUri' => $bildDataUri,
        ])->render();

        $coverName = $this->pdfService->saveFromHtml($html, $tmpDir, 'kassenbon-'.$nummer);
        $coverPath = $tmpDir.'/'.$coverName;

        if ($kassenbon->istBild()) {
            return $coverPath;
        }

        return $this->pdfService->mergePdfs(
            [$coverPath, $kassenbon->dateiAbsolut()],
            $tmpDir,
            'kassenbon-merged-'.$nummer,
        );
    }

    private function bildDataUri(string $absolutePath): string
    {
        $binary = file_get_contents($absolutePath);
        if ($binary === false || $binary === '') {
            return '';
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function d3Grund(?string $message): string
    {
        if ($message === null || trim($message) === '') {
            return 'ohne Angabe';
        }

        $decoded = json_decode($message, true);
        if (is_array($decoded) && filled($decoded['reason'] ?? null)) {
            return (string) $decoded['reason'];
        }

        return $message;
    }

    private function betrag(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '');
    }
}
