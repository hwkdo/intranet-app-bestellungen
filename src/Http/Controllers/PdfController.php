<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Controllers;

use Hwkdo\IntranetAppBestellungen\Enums\BestellungTyp;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\BestellscheinPdfService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use ValueError;

class PdfController extends Controller
{
    public function __construct(
        private readonly BestellscheinPdfService $pdfService,
    ) {}

    public function inline(Bestellung $bestellung, string $typ = 'intern'): Response
    {
        $this->ensureVisible($bestellung);

        return $this->pdfService->inline($bestellung, $this->resolveTyp($bestellung, $typ));
    }

    public function download(Bestellung $bestellung, string $typ = 'intern'): Response
    {
        $this->ensureVisible($bestellung);

        return $this->pdfService->download($bestellung, $this->resolveTyp($bestellung, $typ));
    }

    private function ensureVisible(Bestellung $bestellung): void
    {
        $user = auth()->user();
        abort_unless($user !== null && $bestellung->istSichtbarFuer($user), 403);
    }

    private function resolveTyp(Bestellung $bestellung, string $typ): BestellungTyp
    {
        try {
            $resolved = BestellungTyp::from($typ);
        } catch (ValueError) {
            abort(404);
        }

        $erlaubt = array_map(
            static fn (BestellungTyp $variante): string => $variante->value,
            BestellungTyp::bestellscheinVariantenFuer($bestellung->typ ?? BestellungTyp::Extern),
        );

        abort_unless(in_array($resolved->value, $erlaubt, true), 404);

        return $resolved;
    }
}
