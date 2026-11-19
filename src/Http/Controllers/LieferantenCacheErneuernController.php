<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Controllers;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Notifications\LieferantCacheAktualisiertNotification;
use Hwkdo\IntranetAppBestellungen\Services\Stammdaten\StammdatenSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class LieferantenCacheErneuernController extends Controller
{
    public function show(): View
    {
        return view('intranet-app-bestellungen::lieferanten-cache-erneuern', [
            'erneuert' => false,
            'anzahl' => null,
            'fehler' => null,
            'informiert' => false,
        ]);
    }

    public function store(Request $request, StammdatenSyncService $service): View
    {
        try {
            $result = $service->syncLieferanten();
        } catch (Throwable $exception) {
            report($exception);

            return view('intranet-app-bestellungen::lieferanten-cache-erneuern', [
                'erneuert' => false,
                'anzahl' => null,
                'fehler' => 'Der Lieferanten-Cache konnte nicht aktualisiert werden. Bitte versuchen Sie es später erneut.',
                'informiert' => false,
            ]);
        }

        return view('intranet-app-bestellungen::lieferanten-cache-erneuern', [
            'erneuert' => true,
            'anzahl' => $result['count'],
            'fehler' => null,
            'informiert' => $this->informiereMelder($request),
        ]);
    }

    private function informiereMelder(Request $request): bool
    {
        $melderId = $request->integer('melder');
        if ($melderId <= 0) {
            return false;
        }

        $melder = User::query()->find($melderId);
        if (! $melder instanceof User) {
            return false;
        }

        $name = trim((string) $request->query('lieferant', ''));
        $melder->notify(new LieferantCacheAktualisiertNotification(
            $name !== '' ? $name : 'den gemeldeten Lieferanten',
        ));

        return true;
    }
}
