<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Enums\AktionTyp;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Aktion;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Models\Position;
use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Hwkdo\IntranetAppBestellungen\Search\BestellungenSearchSource;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    config(['scout.driver' => 'collection']);
});

it('registers the bestellungen search route', function (): void {
    expect(Route::has('apps.bestellungen.search'))->toBeTrue();
});

it('registers the bestellungen search source for global search', function (): void {
    expect(IntranetAppBestellungen::searchSources())->toContain(BestellungenSearchSource::class);
});

it('builds a searchable payload with ben betreff projekt and positionen', function (): void {
    $owner = User::factory()->create();
    $freigeber = User::factory()->create();
    $besteller = User::factory()->create();
    $empfaenger = User::factory()->create();
    $historischerFreigeber = User::factory()->create();

    $projekt = Projekt::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Digitalisierung 2026',
    ]);

    $bestellung = Bestellung::factory()->intern()->create([
        'user_id' => $owner->id,
        'nummer' => '3123456789',
        'betreff' => 'Laptops für Azubis',
        'projekt_id' => $projekt->id,
        'freigeber_id' => $freigeber->id,
        'besteller_id' => $besteller->id,
        'interner_empfaenger_user_id' => $empfaenger->id,
        'status' => BestellungStatus::Bestellt,
    ]);

    Position::factory()->for($bestellung)->create([
        'oberbegriff' => 'Hardware',
        'bezeichnung' => 'Notebook ThinkPad',
    ]);

    Aktion::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $historischerFreigeber->id,
        'typ' => AktionTyp::Freigegeben,
        'von_status' => BestellungStatus::ZurFreigabe->value,
        'nach_status' => BestellungStatus::Freigegeben->value,
    ]);

    $bestellung->refresh()->load(['projekt', 'positionen', 'aktionen']);
    $payload = $bestellung->toSearchableArray();

    expect($payload['id'])->toBe((string) $bestellung->id)
        ->and($payload['nummer'])->toBe('3123456789')
        ->and($payload['betreff'])->toBe('Laptops für Azubis')
        ->and($payload['projekt_name'])->toBe('Digitalisierung 2026')
        ->and($payload['positionen_text'])->toContain('Hardware')
        ->and($payload['positionen_text'])->toContain('Notebook ThinkPad')
        ->and($payload['visible_user_ids'])->toContain($owner->id)
        ->and($payload['visible_user_ids'])->toContain($freigeber->id)
        ->and($payload['visible_user_ids'])->toContain($besteller->id)
        ->and($payload['visible_user_ids'])->toContain($empfaenger->id)
        ->and($payload['visible_user_ids'])->toContain($historischerFreigeber->id);
});

it('erlaubt sichtbarkeit fuer owner freigeber besteller empfaenger und admins', function (): void {
    Permission::findOrCreate('manage-app-bestellungen', 'web');

    $owner = User::factory()->create();
    $freigeber = User::factory()->create();
    $besteller = User::factory()->create();
    $empfaenger = User::factory()->create();
    $historischerFreigeber = User::factory()->create();
    $fremd = User::factory()->create();
    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-app-bestellungen');

    $bestellung = Bestellung::factory()->intern()->create([
        'user_id' => $owner->id,
        'freigeber_id' => $freigeber->id,
        'besteller_id' => $besteller->id,
        'interner_empfaenger_user_id' => $empfaenger->id,
    ]);

    Aktion::query()->create([
        'bestellung_id' => $bestellung->id,
        'user_id' => $historischerFreigeber->id,
        'typ' => AktionTyp::ErstFreigegeben,
        'von_status' => BestellungStatus::ZurFreigabe->value,
        'nach_status' => BestellungStatus::ZurZweitenFreigabe->value,
    ]);

    $bestellung->refresh()->load('aktionen');

    expect($bestellung->istSichtbarFuer($owner))->toBeTrue()
        ->and($bestellung->istSichtbarFuer($freigeber))->toBeTrue()
        ->and($bestellung->istSichtbarFuer($besteller))->toBeTrue()
        ->and($bestellung->istSichtbarFuer($empfaenger))->toBeTrue()
        ->and($bestellung->istSichtbarFuer($historischerFreigeber))->toBeTrue()
        ->and($bestellung->istSichtbarFuer($admin))->toBeTrue()
        ->and($bestellung->istSichtbarFuer($fremd))->toBeFalse();
});

it('sperrt die detailseite fuer unbeteiligte nutzer', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $owner = User::factory()->create();
    $fremd = User::factory()->create();
    $fremd->givePermissionTo('see-app-bestellungen');

    $bestellung = Bestellung::factory()->create([
        'user_id' => $owner->id,
    ]);

    $this->actingAs($fremd)
        ->get(route('apps.bestellungen.detail', $bestellung))
        ->assertForbidden();
});

it('erlaubt die detailseite fuer den antragsteller', function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');

    $owner = User::factory()->create();
    $owner->givePermissionTo('see-app-bestellungen');

    $bestellung = Bestellung::factory()->create([
        'user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('apps.bestellungen.detail', $bestellung))
        ->assertSuccessful();
});
