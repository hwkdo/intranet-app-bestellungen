<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Http\Resources\ApiBestellungResource;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class BestellungController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $bestellungen = Bestellung::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereIn('status', [
                BestellungStatus::Entwurf->value,
                BestellungStatus::Abgelehnt->value,
            ])
            ->latest()
            ->get();

        return ApiBestellungResource::collection($bestellungen);
    }
}
