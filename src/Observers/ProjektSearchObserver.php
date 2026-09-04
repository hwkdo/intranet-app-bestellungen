<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Observers;

use Hwkdo\IntranetAppBestellungen\Models\Projekt;

class ProjektSearchObserver
{
    public function updated(Projekt $projekt): void
    {
        if (! $projekt->wasChanged('name')) {
            return;
        }

        $projekt->bestellungen()->searchable();
    }
}
