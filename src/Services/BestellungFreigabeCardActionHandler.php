<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Support\BestellungFreigabeAdaptiveCard;
use Hwkdo\IntranetAppTeamsBot\Data\TeamsAdaptiveCardAction;
use Hwkdo\IntranetAppTeamsBot\Interfaces\TeamsAdaptiveCardActionHandlerInterface;
use Hwkdo\IntranetAppTeamsBot\Support\TeamsAdaptiveCardInvokeResponse;
use Illuminate\Contracts\Auth\Authenticatable;

class BestellungFreigabeCardActionHandler implements TeamsAdaptiveCardActionHandlerInterface
{
    public function __construct(
        private readonly BestellungWorkflow $workflow,
        private readonly WertgrenzenService $wertgrenzen,
    ) {}

    public function supports(TeamsAdaptiveCardAction $action): bool
    {
        return in_array($action->verb, [
            BestellungFreigabeAdaptiveCard::VERB_APPROVE,
            BestellungFreigabeAdaptiveCard::VERB_REJECT,
        ], true);
    }

    public function handle(Authenticatable $user, TeamsAdaptiveCardAction $action): array
    {
        if (! $user instanceof User) {
            return TeamsAdaptiveCardInvokeResponse::message('Ungültiger Benutzer.');
        }

        $bestellungId = $action->data['bestellung_id'] ?? null;

        if (! is_numeric($bestellungId)) {
            return TeamsAdaptiveCardInvokeResponse::message('Bestellung nicht gefunden.');
        }

        $bestellung = Bestellung::query()->find((int) $bestellungId);

        if ($bestellung === null) {
            return TeamsAdaptiveCardInvokeResponse::message('Bestellung nicht gefunden.');
        }

        if (! in_array($bestellung->status, [
            BestellungStatus::ZurFreigabe,
            BestellungStatus::ZurZweitenFreigabe,
        ], true)) {
            return TeamsAdaptiveCardInvokeResponse::card(
                BestellungFreigabeAdaptiveCard::statusCard(
                    $bestellung,
                    'Bereits erledigt',
                    'Diese Bestellung ist nicht mehr zur Freigabe offen.',
                ),
            );
        }

        if (! $this->wertgrenzen->darfFreigeben($user, $bestellung)) {
            return TeamsAdaptiveCardInvokeResponse::card(
                BestellungFreigabeAdaptiveCard::statusCard(
                    $bestellung,
                    'Keine Berechtigung',
                    'Sie dürfen diese Bestellung derzeit nicht freigeben oder ablehnen.',
                ),
            );
        }

        if ($action->verb === BestellungFreigabeAdaptiveCard::VERB_REJECT) {
            return $this->ablehnen($user, $bestellung, $action);
        }

        return $this->freigeben($user, $bestellung);
    }

    private function freigeben(User $user, Bestellung $bestellung): array
    {
        $result = $this->workflow->freigeben($bestellung, $user);

        $headline = $result->status === BestellungStatus::ZurZweitenFreigabe
            ? 'Erstfreigabe erteilt'
            : 'Freigegeben';

        $detail = $result->status === BestellungStatus::ZurZweitenFreigabe
            ? 'Die Bestellung '.$result->nummer.' wurde erstfreigegeben und wartet auf die zweite Freigabe.'
            : 'Die Bestellung '.$result->nummer.' wurde freigegeben.';

        return TeamsAdaptiveCardInvokeResponse::card(
            BestellungFreigabeAdaptiveCard::statusCard($result, $headline, $detail),
        );
    }

    private function ablehnen(User $user, Bestellung $bestellung, TeamsAdaptiveCardAction $action): array
    {
        $grund = trim((string) ($action->data['ablehnenGrund'] ?? ''));

        if (mb_strlen($grund) < 3) {
            $card = BestellungFreigabeAdaptiveCard::forBestellung($bestellung);
            array_unshift($card['body'], [
                'type' => 'TextBlock',
                'text' => 'Bitte einen Ablehnungsgrund mit mindestens 3 Zeichen angeben.',
                'color' => 'Attention',
                'wrap' => true,
            ]);

            return TeamsAdaptiveCardInvokeResponse::card($card);
        }

        $result = $this->workflow->ablehnen($bestellung, $user, $grund);

        return TeamsAdaptiveCardInvokeResponse::card(
            BestellungFreigabeAdaptiveCard::statusCard(
                $result,
                'Abgelehnt',
                'Die Bestellung '.$result->nummer.' wurde abgelehnt.',
            ),
        );
    }
}
