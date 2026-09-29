<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Hwkdo\IntranetAppBestellungen\Notifications\BestellungZurFreigabeNotification;
use Hwkdo\IntranetAppBestellungen\Services\BestellungFreigabeCardActionHandler;
use Hwkdo\IntranetAppBestellungen\Services\BestellungWorkflow;
use Hwkdo\IntranetAppBestellungen\Services\WertgrenzenService;
use Hwkdo\IntranetAppBestellungen\Support\BestellungFreigabeAdaptiveCard;
use Hwkdo\IntranetAppTeamsBot\Data\TeamsAdaptiveCardAction;

it('baut freigabe card mit positionen und actions', function (): void {
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'betreff' => 'Test-Bestellung',
        'begruendung' => 'Weil nötig',
        'gesamtbetrag' => 123.45,
    ]);

    Position::factory()->create([
        'bestellung_id' => $bestellung->id,
        'bezeichnung' => 'Schrauben',
        'menge' => 2,
        'preis' => 10,
        'einheit' => 'Stk',
    ]);

    $card = BestellungFreigabeAdaptiveCard::forBestellung($bestellung->fresh(['user', 'positionen']));

    expect($card['type'])->toBe('AdaptiveCard')
        ->and($card['actions'])->toHaveCount(3)
        ->and(collect($card['actions'])->pluck('verb')->filter()->all())
        ->toContain(BestellungFreigabeAdaptiveCard::VERB_APPROVE)
        ->toContain(BestellungFreigabeAdaptiveCard::VERB_REJECT)
        ->and($card)->toHaveKey('refresh')
        ->and($card['refresh']['userIds'])->toBe([])
        ->and($card['refresh']['action']['verb'])->toBe(BestellungFreigabeAdaptiveCard::VERB_REFRESH);

    $user = User::factory()->create([
        'socialite_id' => 'AABBCCDD-1234-5678-90AB-CDEF01234567',
    ]);
    $payload = (new BestellungZurFreigabeNotification($bestellung))->toTeams($user);

    expect($payload)->toHaveKey('card')
        ->and($payload['card']['type'])->toBe('AdaptiveCard')
        ->and($payload['card'])->toHaveKey('refresh')
        ->and($payload['card']['refresh']['userIds'])
        ->toBe(['aabbccdd-1234-5678-90ab-cdef01234567']);
});

it('setzt azure-guid bzw teams mri in refresh userIds', function (): void {
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
    ]);

    $guid = 'AABBCCDD-1234-5678-90AB-CDEF01234567';
    $withGuid = BestellungFreigabeAdaptiveCard::forBestellung($bestellung, $guid);

    expect($withGuid)->toHaveKey('refresh')
        ->and($withGuid['refresh']['action']['verb'])->toBe(BestellungFreigabeAdaptiveCard::VERB_REFRESH)
        ->and($withGuid['refresh']['action']['data']['bestellung_id'])->toBe($bestellung->id)
        ->and($withGuid['refresh']['userIds'])->toBe([strtolower($guid)]);

    $mri = '29:1bSnHZ7Js2STWrgk6ScEErLk1Lp2zQuD5H2qQ960rtvstKp8tKLl-3r8b6DoW0QxZimuTxk';
    $withMri = BestellungFreigabeAdaptiveCard::forBestellung($bestellung, $mri);

    expect($withMri['refresh']['userIds'])->toBe([$mri]);

    $status = BestellungFreigabeAdaptiveCard::statusCard($bestellung, 'Bereits erledigt', 'Fertig.');

    expect($status)->not->toHaveKey('refresh');
});

it('gibt bestellung ueber card action frei wenn berechtigt', function (): void {
    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'gesamtbetrag' => 50,
    ]);

    $wertgrenzen = Mockery::mock(WertgrenzenService::class);
    $wertgrenzen->shouldReceive('darfFreigeben')->once()->andReturn(true);

    $workflow = Mockery::mock(BestellungWorkflow::class);
    $workflow->shouldReceive('freigeben')
        ->once()
        ->andReturn($bestellung->setAttribute('status', BestellungStatus::Freigegeben));

    $handler = new BestellungFreigabeCardActionHandler($workflow, $wertgrenzen);

    $action = new TeamsAdaptiveCardAction(
        azureUserId: 'test',
        verb: BestellungFreigabeAdaptiveCard::VERB_APPROVE,
        data: ['bestellung_id' => $bestellung->id],
        activity: [],
        conversationRef: [],
    );

    $response = $handler->handle($user, $action);

    expect($response['statusCode'])->toBe(200)
        ->and($response['type'])->toBe('application/vnd.microsoft.card.adaptive')
        ->and($response['value']['body'][0]['text'] ?? null)->toBe('Freigegeben')
        ->and($response['value'])->not->toHaveKey('refresh');
});

it('lehnt bestellung nur mit gueltigem grund ab', function (): void {
    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
        'gesamtbetrag' => 50,
    ]);

    $wertgrenzen = Mockery::mock(WertgrenzenService::class);
    $wertgrenzen->shouldReceive('darfFreigeben')->twice()->andReturn(true);

    $workflow = Mockery::mock(BestellungWorkflow::class);
    $workflow->shouldReceive('ablehnen')->once()->andReturn(
        $bestellung->setAttribute('status', BestellungStatus::Abgelehnt),
    );

    $handler = new BestellungFreigabeCardActionHandler($workflow, $wertgrenzen);

    $short = $handler->handle($user, new TeamsAdaptiveCardAction(
        azureUserId: 'test',
        verb: BestellungFreigabeAdaptiveCard::VERB_REJECT,
        data: ['bestellung_id' => $bestellung->id, 'ablehnenGrund' => 'no'],
        activity: [],
        conversationRef: [],
    ));

    expect($short['value']['body'][0]['text'] ?? null)->toContain('Ablehnungsgrund');

    $ok = $handler->handle($user, new TeamsAdaptiveCardAction(
        azureUserId: 'test',
        verb: BestellungFreigabeAdaptiveCard::VERB_REJECT,
        data: ['bestellung_id' => $bestellung->id, 'ablehnenGrund' => 'Zu teuer'],
        activity: [],
        conversationRef: [],
    ));

    expect($ok['value']['body'][0]['text'] ?? null)->toBe('Abgelehnt')
        ->and($ok['value'])->not->toHaveKey('refresh');
});

it('verweigert card action ohne berechtigung', function (): void {
    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
    ]);

    $wertgrenzen = Mockery::mock(WertgrenzenService::class);
    $wertgrenzen->shouldReceive('darfFreigeben')->once()->andReturn(false);

    $workflow = Mockery::mock(BestellungWorkflow::class);
    $workflow->shouldNotReceive('freigeben');

    $handler = new BestellungFreigabeCardActionHandler($workflow, $wertgrenzen);

    $response = $handler->handle($user, new TeamsAdaptiveCardAction(
        azureUserId: 'test',
        verb: BestellungFreigabeAdaptiveCard::VERB_APPROVE,
        data: ['bestellung_id' => $bestellung->id],
        activity: [],
        conversationRef: [],
    ));

    expect($response['value']['body'][0]['text'] ?? null)->toBe('Keine Berechtigung')
        ->and($response['value'])->not->toHaveKey('refresh');
});

it('liefert beim refresh offene freigabe card mit refresh block', function (): void {
    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
    ]);

    $wertgrenzen = Mockery::mock(WertgrenzenService::class);
    $wertgrenzen->shouldNotReceive('darfFreigeben');

    $workflow = Mockery::mock(BestellungWorkflow::class);
    $workflow->shouldNotReceive('freigeben');
    $workflow->shouldNotReceive('ablehnen');

    $handler = new BestellungFreigabeCardActionHandler($workflow, $wertgrenzen);

    $response = $handler->handle($user, new TeamsAdaptiveCardAction(
        azureUserId: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        verb: BestellungFreigabeAdaptiveCard::VERB_REFRESH,
        data: ['bestellung_id' => $bestellung->id],
        activity: [],
        conversationRef: [],
    ));

    expect($response['statusCode'])->toBe(200)
        ->and($response['value']['body'][0]['text'] ?? null)->toBe('Bestellung zur Freigabe')
        ->and($response['value'])->toHaveKey('refresh')
        ->and($response['value']['refresh']['userIds'])->toBe(['aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'])
        ->and($response['value']['refresh']['action']['verb'])->toBe(BestellungFreigabeAdaptiveCard::VERB_REFRESH);
});

it('setzt beim refresh teams mri als userIds wenn in activity vorhanden', function (): void {
    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::ZurFreigabe,
    ]);
    $mri = '29:1bSnHZ7Js2STWrgk6ScEErLk1Lp2zQuD5H2qQ960rtvstKp8tKLl-3r8b6DoW0QxZimuTxk';

    $handler = new BestellungFreigabeCardActionHandler(
        Mockery::mock(BestellungWorkflow::class),
        Mockery::mock(WertgrenzenService::class),
    );

    $response = $handler->handle($user, new TeamsAdaptiveCardAction(
        azureUserId: 'actor-azure-id',
        verb: BestellungFreigabeAdaptiveCard::VERB_REFRESH,
        data: ['bestellung_id' => $bestellung->id],
        activity: ['from' => ['id' => $mri]],
        conversationRef: [],
    ));

    expect($response['value']['refresh']['userIds'])->toBe([$mri]);
});

it('liefert beim refresh erledigter bestellung status card ohne refresh', function (): void {
    $user = User::factory()->create();
    $bestellung = Bestellung::factory()->create([
        'status' => BestellungStatus::Freigegeben,
    ]);

    $wertgrenzen = Mockery::mock(WertgrenzenService::class);
    $wertgrenzen->shouldNotReceive('darfFreigeben');

    $workflow = Mockery::mock(BestellungWorkflow::class);
    $workflow->shouldNotReceive('freigeben');

    $handler = new BestellungFreigabeCardActionHandler($workflow, $wertgrenzen);

    $response = $handler->handle($user, new TeamsAdaptiveCardAction(
        azureUserId: 'actor-azure-id',
        verb: BestellungFreigabeAdaptiveCard::VERB_REFRESH,
        data: ['bestellung_id' => $bestellung->id],
        activity: [],
        conversationRef: [],
    ));

    expect($response['value']['body'][0]['text'] ?? null)->toBe('Bereits erledigt')
        ->and($response['value'])->not->toHaveKey('refresh');
});
