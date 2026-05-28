<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Http\Requests\Api\StoreBestellungAngebotRequest;
use Hwkdo\IntranetAppBestellungen\Http\Resources\ApiAngebotResource;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\Api\BestellungAngebotUploadService;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BestellungAngebotController extends Controller
{
    public function store(
        StoreBestellungAngebotRequest $request,
        Bestellung $bestellung,
        BestellungAngebotUploadService $uploadService
    ): JsonResponse {
        $authenticatedUserId = (int) $request->user()->getAuthIdentifier();
        $isOwner = (int) $bestellung->user_id === $authenticatedUserId;
        $isUploadAllowedStatus = in_array($bestellung->status, [BestellungStatus::Entwurf, BestellungStatus::Abgelehnt], true);

        Log::info('Outlook API: angebot upload request', [
            'auth_user_id' => $authenticatedUserId,
            'bestellung_id' => $bestellung->getKey(),
            'bestellung_owner_user_id' => (int) $bestellung->user_id,
            'bestellung_nummer' => $bestellung->nummer,
            'bestellung_status' => $bestellung->status?->value,
            'is_owner' => $isOwner,
            'is_upload_allowed_status' => $isUploadAllowedStatus,
        ]);

        if (! $isOwner || ! $isUploadAllowedStatus) {
            Log::warning('Outlook API: angebot upload forbidden', [
                'auth_user_id' => $authenticatedUserId,
                'bestellung_id' => $bestellung->getKey(),
                'bestellung_owner_user_id' => (int) $bestellung->user_id,
                'bestellung_status' => $bestellung->status?->value,
                'is_owner' => $isOwner,
                'is_upload_allowed_status' => $isUploadAllowedStatus,
            ]);

            abort(403);
        }

        $angebot = $uploadService->store(
            bestellung: $bestellung,
            userId: $authenticatedUserId,
            payload: $request->validated(),
        );

        /** @var User|null $user */
        $user = $request->user();
        app(BestellungWorkflow::class)->logAktion(
            $bestellung,
            $user,
            AktionTyp::AngebotViaOutlookHochgeladen,
            'Angebot wurde über das Outlook-Add-in hochgeladen.',
            payload: [
                'angebot_id' => $angebot->getKey(),
                'channel' => 'outlook_addin',
                'extraction_status' => $angebot->extraction_status,
                'extraction_source' => $angebot->extraction_source,
            ],
        );

        Log::info('Outlook API: angebot upload stored', [
            'auth_user_id' => $authenticatedUserId,
            'bestellung_id' => $bestellung->getKey(),
            'angebot_id' => $angebot->getKey(),
        ]);

        return (new ApiAngebotResource($angebot))
            ->response()
            ->setStatusCode(201);
    }
}
