<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use App\Models\User;
use Hwkdo\IntranetAppBase\Services\UserSignatureStore;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Enums\UnterschriftArt;
use Hwkdo\IntranetAppBestellungen\Exceptions\WorkflowException;
use Hwkdo\IntranetAppBestellungen\Models\Aktion;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\AuftragsschreibenDaten;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AuftragsscheinUnterschriftService
{
    public function __construct(
        private readonly UserSignatureStore $signatures,
        private readonly AuftragsschreibenDaten $auftragsschreibenDaten,
    ) {}

    public function beiFreigabe(Bestellung $bestellung, User $unterzeichner): void
    {
        if (! $bestellung->istAuftragsvergabe()) {
            return;
        }

        $this->auftragsschreibenDaten->brieftextEinfrieren($bestellung);

        if ($this->signatures->active($unterzeichner) === null) {
            return;
        }

        $this->digitalUnterschreiben($bestellung->fresh() ?? $bestellung, $unterzeichner);
    }

    public function digitalUnterschreiben(Bestellung $bestellung, User $user): void
    {
        $this->ensureOffen($bestellung);
        $this->ensureIstUnterzeichner($bestellung, $user);

        $bytes = $this->signatures->useFor($user, 'bestellungen.auftragsschein', $bestellung);
        $path = 'bestellungen/unterschriften/'.$bestellung->getKey().'/unterschrift.png';
        Storage::disk('local')->put($path, $bytes);

        if (filled($bestellung->unterschrift_bild_pfad) && $bestellung->unterschrift_bild_pfad !== $path) {
            Storage::disk('local')->delete($bestellung->unterschrift_bild_pfad);
        }

        $bestellung->forceFill([
            'unterschrift_bild_pfad' => $path,
            'unterschrieben_at' => now(),
            'unterschrift_art' => $bestellung->unterschrift_art === UnterschriftArt::Scan
                ? UnterschriftArt::Scan
                : UnterschriftArt::Digital,
        ])->save();

        $this->log($bestellung, $user, AktionTyp::Unterschrieben, 'Auftragsschein digital unterschrieben.');
    }

    public function scanHochladen(Bestellung $bestellung, User $user, UploadedFile $pdf): void
    {
        $this->ensureOffen($bestellung);
        $this->ensureDarfScanHochladen($bestellung, $user);

        if ($bestellung->unterschrift_art === UnterschriftArt::Digital) {
            throw new WorkflowException('Der Auftragsschein ist bereits digital unterschrieben.');
        }

        $path = 'bestellungen/unterschriften/'.$bestellung->getKey().'/scan.pdf';
        Storage::disk('local')->put($path, $pdf->get());

        $bestellung->forceFill([
            'unterschrift_art' => UnterschriftArt::Scan,
            'unterschrift_scan_pfad' => $path,
            'unterschrift_scan_von_id' => $user->getKey(),
            'unterschrieben_at' => $bestellung->unterschrieben_at ?? now(),
        ])->save();

        $this->log($bestellung, $user, AktionTyp::UnterschriftScanHochgeladen, 'Unterschriebener Auftragsschein hochgeladen.');
    }

    public function scanEntfernen(Bestellung $bestellung, User $user): void
    {
        $this->ensureOffen($bestellung);
        $this->ensureDarfScanHochladen($bestellung, $user);

        if (filled($bestellung->unterschrift_scan_pfad)) {
            Storage::disk('local')->delete($bestellung->unterschrift_scan_pfad);
        }

        $hatBild = filled($bestellung->unterschrift_bild_pfad);

        $bestellung->forceFill([
            'unterschrift_scan_pfad' => null,
            'unterschrift_scan_von_id' => null,
            'unterschrift_art' => $hatBild ? UnterschriftArt::Digital : null,
            'unterschrieben_at' => $hatBild ? $bestellung->unterschrieben_at : null,
        ])->save();

        $this->log($bestellung, $user, AktionTyp::UnterschriftScanEntfernt, 'Unterschriebenen Scan entfernt.');
    }

    public function ensureDarfScanHochladen(Bestellung $bestellung, User $user): void
    {
        $istUnterzeichner = (int) $bestellung->unterzeichner_id === (int) $user->getKey();

        if (! $istUnterzeichner && ! $bestellung->darfVonUserBestelltAbschliessen($user)) {
            throw new WorkflowException('Sie dürfen den unterschriebenen Auftragsschein nicht hochladen.');
        }
    }

    private function ensureIstUnterzeichner(Bestellung $bestellung, User $user): void
    {
        if ((int) $bestellung->unterzeichner_id !== (int) $user->getKey()) {
            throw new WorkflowException('Nur der Unterzeichner darf den Auftragsschein digital unterschreiben.');
        }
    }

    private function ensureOffen(Bestellung $bestellung): void
    {
        if ($bestellung->status !== BestellungStatus::Freigegeben) {
            throw new WorkflowException('Der Auftragsschein kann nur im Status „Freigegeben“ unterschrieben werden.');
        }
    }

    private function log(Bestellung $bestellung, User $user, AktionTyp $typ, string $nachricht): void
    {
        Aktion::query()->create([
            'bestellung_id' => $bestellung->getKey(),
            'user_id' => $user->getKey(),
            'typ' => $typ,
            'von_status' => $bestellung->status?->value,
            'nach_status' => $bestellung->status?->value,
            'nachricht' => $nachricht,
        ]);
    }
}
