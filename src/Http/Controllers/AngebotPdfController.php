<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Controllers;

use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\AngebotPdfService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class AngebotPdfController extends Controller
{
    public function __construct(
        private readonly AngebotPdfService $pdfService,
    ) {}

    public function inline(Bestellung $bestellung, Angebot $angebot): Response
    {
        abort_unless((int) $angebot->bestellung_id === (int) $bestellung->getKey(), 404);

        $user = auth()->user();
        abort_unless($user !== null && $bestellung->istSichtbarFuer($user), 403);

        return $this->pdfService->inline($angebot);
    }
}
