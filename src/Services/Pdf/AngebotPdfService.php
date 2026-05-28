<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Pdf;

use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Hwkdo\IntranetAppBestellungen\Services\D3\AngebotD3Service;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AngebotPdfService
{
    public function __construct(
        private readonly AngebotD3Service $angebotD3Service,
    ) {}

    public function inline(Angebot $angebot): Response
    {
        $absolutePath = $this->resolveAbsolutePath($angebot);

        if ($absolutePath === null) {
            abort(404);
        }

        $filename = $angebot->typ === 'begruendung'
            ? 'ausnahme-begruendung-'.$angebot->getKey().'.pdf'
            : 'angebot-'.$angebot->getKey().'.pdf';

        return response(File::get($absolutePath), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function canDisplay(Angebot $angebot): bool
    {
        if (filled($angebot->pdf_path) && Storage::disk('local')->exists($angebot->pdf_path)) {
            return true;
        }

        return $angebot->typ === 'begruendung' && filled($angebot->begruendung);
    }

    private function resolveAbsolutePath(Angebot $angebot): ?string
    {
        if ($angebot->typ === 'begruendung' && filled($angebot->begruendung)) {
            $relPath = $this->resolveStoredRelativePath($angebot);

            if ($relPath === null) {
                $this->angebotD3Service->generateBegruendungPdf($angebot);
                $angebot->refresh();
                $relPath = $this->resolveStoredRelativePath($angebot);
            }

            return $relPath !== null ? Storage::disk('local')->path($relPath) : null;
        }

        $relPath = $this->resolveStoredRelativePath($angebot);

        return $relPath !== null ? Storage::disk('local')->path($relPath) : null;
    }

    /**
     * Löst den relativen Storage-Pfad auf; korrigiert ggf. doppelte „.pdf“-Endung (Gotenberg-Legacy).
     */
    private function resolveStoredRelativePath(Angebot $angebot): ?string
    {
        if (! filled($angebot->pdf_path)) {
            return null;
        }

        if (Storage::disk('local')->exists($angebot->pdf_path)) {
            return $angebot->pdf_path;
        }

        $doubleExtensionPath = $angebot->pdf_path.'.pdf';

        if (Storage::disk('local')->exists($doubleExtensionPath)) {
            $angebot->forceFill(['pdf_path' => $doubleExtensionPath])->saveQuietly();

            return $doubleExtensionPath;
        }

        return null;
    }
}
