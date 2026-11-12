<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Controllers;

use Hwkdo\IntranetAppBestellungen\Models\Kassenbon;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KassenbonDateiController extends Controller
{
    public function show(Kassenbon $kassenbon): BinaryFileResponse
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $bestellung = $kassenbon->bestellung;
        $darf = (int) $kassenbon->user_id === (int) $user->getAuthIdentifier()
            || ($bestellung !== null && $bestellung->istSichtbarFuer($user));

        abort_unless($darf, 403);
        abort_unless(is_file($kassenbon->dateiAbsolut()), 404);

        return response()->file($kassenbon->dateiAbsolut(), [
            'Content-Type' => $kassenbon->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes((string) ($kassenbon->original_name ?: 'kassenbon')).'"',
        ]);
    }
}
