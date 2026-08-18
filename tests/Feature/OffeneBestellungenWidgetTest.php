<?php

declare(strict_types=1);

use App\Data\UserDashboardPersonalGrid;
use App\Data\UserDashboardSettings;
use App\Data\UserSettings;
use App\Models\User;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Hwkdo\IntranetAppBestellungen\Dashboard\BestellungenDashboardWidgetProvider;
use Hwkdo\IntranetAppBestellungen\Enums\BestellungStatus;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('see-app-bestellungen', 'web');
});

function bestellungenOffeneWidgetUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo('see-app-bestellungen');

    return $user;
}

test('bestellungen stellt das offene-bestellungen widget bereit', function (): void {
    expect(IntranetAppBestellungen::dashboardWidgetProviders())->toContain(BestellungenDashboardWidgetProvider::class);

    $widget = collect(BestellungenDashboardWidgetProvider::widgets())
        ->firstWhere('key', BestellungenDashboardWidgetProvider::KEY_OFFENE_BESTELLUNGEN);

    expect($widget)->not->toBeNull()
        ->and($widget->title)->toBe('Bestellungen in Bearbeitung')
        ->and($widget->component)->toBe('intranet-app-bestellungen::apps.bestellungen.widgets.offene-bestellungen')
        ->and($widget->supportsItemCount)->toBeTrue()
        ->and($widget->defaultEnabled)->toBeTrue();
});

test('main dashboard registriert das bestellungen widget für berechtigte nutzer', function (): void {
    $user = bestellungenOffeneWidgetUser();

    $keys = collect(app(DashboardWidgetRegistry::class)->widgetsForMainDashboard($user))
        ->map(fn ($definition): string => $definition->key)
        ->all();

    expect($keys)->toContain('bestellungen.offene-bestellungen');
});

test('widget zeigt status angefordeter bestellungen die auf freigabe warten', function (): void {
    $anforderer = bestellungenOffeneWidgetUser();
    $freigeber = User::factory()->create(['vorname' => 'Anna', 'nachname' => 'Freigabe']);

    $bestellung = Bestellung::factory()->extern()->create([
        'user_id' => $anforderer->id,
        'freigeber_id' => $freigeber->id,
        'status' => BestellungStatus::ZurFreigabe,
        'nummer' => '3123456789',
        'betreff' => 'Bürostuhl',
    ]);

    Livewire::actingAs($anforderer)
        ->test('intranet-app-bestellungen::apps.bestellungen.widgets.offene-bestellungen')
        ->assertSee('Bestellungen in Bearbeitung')
        ->assertSee($bestellung->nummer)
        ->assertSee('Bürostuhl')
        ->assertSee('Zur Freigabe')
        ->assertSee('Wartet auf Freigabe durch Anna Freigabe');
});

test('widget zeigt interne bestellungen die auf bestellung durch jemand anderen warten', function (): void {
    $anforderer = bestellungenOffeneWidgetUser();
    $empfaenger = User::factory()->create(['vorname' => 'Max', 'nachname' => 'Besteller']);

    Bestellung::factory()->intern()->create([
        'user_id' => $anforderer->id,
        'interner_empfaenger_user_id' => $empfaenger->id,
        'status' => BestellungStatus::Freigegeben,
        'nummer' => '3987654321',
        'betreff' => 'Toner',
    ]);

    Livewire::actingAs($anforderer)
        ->test('intranet-app-bestellungen::apps.bestellungen.widgets.offene-bestellungen')
        ->assertSee('3987654321')
        ->assertSee('Toner')
        ->assertSee('Freigegeben')
        ->assertSee('Wartet auf Bestellung durch Max Besteller');
});

test('widget blendet entwuerfe abgeschlossene und fremde bestellungen aus', function (): void {
    $anforderer = bestellungenOffeneWidgetUser();
    $anderer = User::factory()->create();
    $empfaenger = User::factory()->create();

    Bestellung::factory()->extern()->create([
        'user_id' => $anforderer->id,
        'status' => BestellungStatus::Entwurf,
        'nummer' => '3000000001',
        'betreff' => 'Entwurf-Bestellung',
    ]);
    Bestellung::factory()->extern()->bestellt()->create([
        'user_id' => $anforderer->id,
        'nummer' => '3000000002',
        'betreff' => 'Fertige-Bestellung',
    ]);
    Bestellung::factory()->extern()->create([
        'user_id' => $anforderer->id,
        'status' => BestellungStatus::Abgelehnt,
        'nummer' => '3000000003',
        'betreff' => 'Abgelehnte-Bestellung',
    ]);
    Bestellung::factory()->extern()->create([
        'user_id' => $anderer->id,
        'freigeber_id' => $empfaenger->id,
        'status' => BestellungStatus::ZurFreigabe,
        'nummer' => '3000000004',
        'betreff' => 'Fremde-Bestellung',
    ]);
    Bestellung::factory()->extern()->create([
        'user_id' => $anforderer->id,
        'freigeber_id' => $empfaenger->id,
        'status' => BestellungStatus::ZurFreigabe,
        'nummer' => '3000000005',
        'betreff' => 'Offene-Bestellung',
    ]);

    Livewire::actingAs($anforderer)
        ->test('intranet-app-bestellungen::apps.bestellungen.widgets.offene-bestellungen')
        ->assertSee('Offene-Bestellung')
        ->assertDontSee('Entwurf-Bestellung')
        ->assertDontSee('Fertige-Bestellung')
        ->assertDontSee('Abgelehnte-Bestellung')
        ->assertDontSee('Fremde-Bestellung');
});

test('widget blendet bestellungen aus bei denen der anforderer selbst handelt', function (): void {
    $anforderer = bestellungenOffeneWidgetUser();

    Bestellung::factory()->extern()->create([
        'user_id' => $anforderer->id,
        'freigeber_id' => $anforderer->id,
        'status' => BestellungStatus::ZurFreigabe,
        'nummer' => '3000000011',
        'betreff' => 'Selbst-Freigeben',
    ]);
    Bestellung::factory()->extern()->create([
        'user_id' => $anforderer->id,
        'status' => BestellungStatus::Freigegeben,
        'nummer' => '3000000012',
        'betreff' => 'Selbst-Bestellen',
    ]);
    Bestellung::factory()->intern()->create([
        'user_id' => $anforderer->id,
        'interner_empfaenger_user_id' => $anforderer->id,
        'status' => BestellungStatus::Freigegeben,
        'nummer' => '3000000013',
        'betreff' => 'Selbst-Intern',
    ]);

    Livewire::actingAs($anforderer)
        ->test('intranet-app-bestellungen::apps.bestellungen.widgets.offene-bestellungen')
        ->assertSee('Keine Bestellungen in Bearbeitung.')
        ->assertDontSee('Selbst-Freigeben')
        ->assertDontSee('Selbst-Bestellen')
        ->assertDontSee('Selbst-Intern');
});

test('widget zeigt standardmäßig fünf einträge und weitere anzeigen', function (): void {
    $anforderer = bestellungenOffeneWidgetUser();
    $freigeber = User::factory()->create();

    foreach (range(1, 6) as $index) {
        Bestellung::factory()->extern()->create([
            'user_id' => $anforderer->id,
            'freigeber_id' => $freigeber->id,
            'status' => BestellungStatus::ZurFreigabe,
            'nummer' => '310000000'.$index,
            'betreff' => 'Limit-Termin '.$index,
            'created_at' => now()->subMinutes($index),
        ]);
    }

    Livewire::actingAs($anforderer)
        ->test('intranet-app-bestellungen::apps.bestellungen.widgets.offene-bestellungen')
        ->assertSee('Limit-Termin 1')
        ->assertSee('Limit-Termin 5')
        ->assertDontSee('Limit-Termin 6')
        ->assertSee('Weitere anzeigen');
});

test('widget respektiert die konfigurierte anzahl im dashboard', function (): void {
    $anforderer = bestellungenOffeneWidgetUser();
    $freigeber = User::factory()->create();

    foreach (range(1, 3) as $index) {
        Bestellung::factory()->extern()->create([
            'user_id' => $anforderer->id,
            'freigeber_id' => $freigeber->id,
            'status' => BestellungStatus::ZurFreigabe,
            'nummer' => '320000000'.$index,
            'betreff' => 'Count-Termin '.$index,
            'created_at' => now()->subMinutes($index),
        ]);
    }

    $existing = $anforderer->settings;
    $anforderer->settings = new UserSettings(
        app: $existing->app,
        general: $existing->general,
        dashboard: new UserDashboardSettings(
            autoplayInterval: $existing->dashboard->autoplayInterval,
            autoplay: $existing->dashboard->autoplay,
            hideAufgabenWhenEmpty: $existing->dashboard->hideAufgabenWhenEmpty,
            newsCount: $existing->dashboard->newsCount,
            personalGrid: new UserDashboardPersonalGrid(
                widgetItemCounts: [
                    'bestellungen.offene-bestellungen' => 2,
                ],
            ),
        ),
        ai: $existing->ai,
    );
    $anforderer->save();

    Livewire::actingAs($anforderer->fresh())
        ->test('intranet-app-bestellungen::apps.bestellungen.widgets.offene-bestellungen')
        ->assertSee('Count-Termin 1')
        ->assertSee('Count-Termin 2')
        ->assertDontSee('Count-Termin 3');
});

test('widget zeigt leerzustand ohne offene bestellungen', function (): void {
    $user = bestellungenOffeneWidgetUser();

    Livewire::actingAs($user)
        ->test('intranet-app-bestellungen::apps.bestellungen.widgets.offene-bestellungen')
        ->assertSee('Keine Bestellungen in Bearbeitung.')
        ->assertDontSee('Weitere anzeigen');
});

test('widget ist ohne app-berechtigung nicht sichtbar', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('intranet-app-bestellungen::apps.bestellungen.widgets.offene-bestellungen')
        ->assertForbidden();
});
