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
        return $this->pdfService->inline($bestellung, $this->resolveTyp($typ));
    }

    public function download(Bestellung $bestellung, string $typ = 'intern'): Response
    {
        return $this->pdfService->download($bestellung, $this->resolveTyp($typ));
    }

    private function resolveTyp(string $typ): BestellungTyp
    {
        try {
            return BestellungTyp::from($typ);
        } catch (ValueError) {
            abort(404);
        }
    }
}
