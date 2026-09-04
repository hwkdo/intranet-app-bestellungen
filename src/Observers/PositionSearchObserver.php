<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Observers;

use Hwkdo\IntranetAppBestellungen\Models\Position;

class PositionSearchObserver
{
    public function created(Position $position): void
    {
        $this->reindexBestellung($position);
    }

    public function updated(Position $position): void
    {
        $this->reindexBestellung($position);
    }

    public function deleted(Position $position): void
    {
        $this->reindexBestellung($position);
    }

    private function reindexBestellung(Position $position): void
    {
        $position->bestellung?->searchable();
    }
}
