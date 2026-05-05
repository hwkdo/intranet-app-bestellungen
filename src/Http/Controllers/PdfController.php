<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Controllers;

use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\BestellscheinPdfService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class PdfController extends Controller
{
    public function __construct(
        private readonly BestellscheinPdfService $pdfService,
    ) {}

    public function inline(Bestellung $bestellung): Response
    {
        return $this->pdfService->inline($bestellung);
    }

    public function download(Bestellung $bestellung): Response
    {
        return $this->pdfService->download($bestellung);
    }
}
