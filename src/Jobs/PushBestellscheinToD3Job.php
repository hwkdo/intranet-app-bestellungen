<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Jobs;

use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\D3\BestellscheinD3Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushBestellscheinToD3Job implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $bestellungId,
        public readonly bool $rePush = false,
    ) {}

    public function handle(BestellscheinD3Service $service): void
    {
        $bestellung = Bestellung::find($this->bestellungId);
        if (! $bestellung) {
            return;
        }

        if ($this->rePush) {
            $service->rePush($bestellung);

            return;
        }

        $service->push($bestellung);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('bestellungen.d3_push_job_failed', [
            'bestellung_id' => $this->bestellungId,
            're_push' => $this->rePush,
            'error' => $exception->getMessage(),
        ]);
    }
}
