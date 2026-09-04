<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Observers;

use Hwkdo\IntranetAppBestellungen\Models\Aktion;

class AktionSearchObserver
{
    public function created(Aktion $aktion): void
    {
        $this->reindexBestellung($aktion);
    }

    public function updated(Aktion $aktion): void
    {
        $this->reindexBestellung($aktion);
    }

    public function deleted(Aktion $aktion): void
    {
        $this->reindexBestellung($aktion);
    }

    private function reindexBestellung(Aktion $aktion): void
    {
        $aktion->bestellung?->searchable();
    }
}
