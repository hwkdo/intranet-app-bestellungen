<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppBestellungen\Enums\AuftragsvorlageVariable;
use Hwkdo\IntranetAppBestellungen\Models\Auftragsvorlage;
use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Hwkdo\IntranetAppBestellungen\Services\Pdf\AuftragsvorlageRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AuftragsvorlagenEditor extends Component
{
    public ?int $editId = null;

    public string $name = '';

    public string $schluessel = '';

    public ?string $beschreibung = null;

    public string $inhalt = '';

    /** @var list<string> */
    public array $variablen = [];

    public bool $anredeAutomatisch = true;

    public bool $aktiv = true;

    public int $sort = 100;

    /**
     * @return Collection<int, Auftragsvorlage>
     */
    #[Computed]
    public function vorlagen(): Collection
    {
        return Auftragsvorlage::query()->orderBy('sort')->orderBy('name')->get();
    }

    /**
     * @return list<AuftragsvorlageVariable>
     */
    #[Computed]
    public function variablenKatalog(): array
    {
        return AuftragsvorlageVariable::cases();
    }

    public function vorschauHtml(): string
    {
        $vorlage = new Auftragsvorlage([
            'inhalt' => $this->inhalt,
            'variablen' => $this->variablen,
        ]);

        return app(AuftragsvorlageRenderer::class)->html(
            $vorlage,
            app(AuftragsvorlageRenderer::class)->beispielWerte(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'schluessel' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('intranet_app_bestellungen_auftragsvorlagen', 'schluessel')->ignore($this->editId),
            ],
            'beschreibung' => ['nullable', 'string', 'max:500'],
            'inhalt' => ['required', 'string', 'max:20000'],
            'variablen' => ['array'],
            'variablen.*' => [Rule::enum(AuftragsvorlageVariable::class)],
            'anredeAutomatisch' => ['boolean'],
            'aktiv' => ['boolean'],
            'sort' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function neu(): void
    {
        $this->reset(['editId', 'name', 'schluessel', 'beschreibung', 'inhalt', 'variablen']);
        $this->anredeAutomatisch = true;
        $this->aktiv = true;
        $this->sort = 100;
        $this->resetValidation();
        Flux::modal('auftragsvorlage-modal')->show();
    }

    public function edit(int $id): void
    {
        $vorlage = Auftragsvorlage::query()->findOrFail($id);
        $this->editId = $vorlage->id;
        $this->name = $vorlage->name;
        $this->schluessel = $vorlage->schluessel;
        $this->beschreibung = $vorlage->beschreibung;
        $this->inhalt = (string) $vorlage->inhalt;
        $this->variablen = array_values(array_map(
            static fn (AuftragsvorlageVariable $variable): string => $variable->value,
            $vorlage->variablenEnums(),
        ));
        $this->anredeAutomatisch = $vorlage->anrede_automatisch;
        $this->aktiv = $vorlage->aktiv;
        $this->sort = $vorlage->sort;
        $this->resetValidation();
        Flux::modal('auftragsvorlage-modal')->show();
    }

    public function speichern(): void
    {
        $daten = $this->validate();

        $pruefling = new Auftragsvorlage([
            'inhalt' => $daten['inhalt'],
            'variablen' => array_values($daten['variablen'] ?? []),
        ]);

        $unbekannt = $pruefling->unbekanntePlatzhalter();
        if ($unbekannt !== []) {
            $this->addError('inhalt', 'Unbekannte Platzhalter: '.implode(', ', $unbekannt).'.');

            return;
        }

        $felder = [
            'name' => $daten['name'],
            'schluessel' => $daten['schluessel'],
            'beschreibung' => $daten['beschreibung'] ?? null,
            'inhalt' => $daten['inhalt'],
            'variablen' => array_values($daten['variablen'] ?? []),
            'anrede_automatisch' => $this->anredeAutomatisch,
            'aktiv' => $this->aktiv,
            'sort' => $daten['sort'],
        ];

        if ($this->editId !== null) {
            Auftragsvorlage::query()->whereKey($this->editId)->update($felder);
        } else {
            Auftragsvorlage::query()->create($felder);
        }

        $ohnePlatzhalter = $pruefling->variablenOhnePlatzhalter();

        Flux::modal('auftragsvorlage-modal')->close();
        Flux::toast(heading: 'Vorlage gespeichert', text: 'Die Auftragsvorlage wurde gespeichert.', variant: 'success');

        if ($ohnePlatzhalter !== []) {
            Flux::toast(
                heading: 'Platzhalter fehlen',
                text: 'Im Text fehlt der Platzhalter für: '.implode(', ', $ohnePlatzhalter).'.',
                variant: 'warning',
            );
        }

        unset($this->vorlagen);
    }

    public function loeschen(int $id): void
    {
        $vorlage = Auftragsvorlage::query()->find($id);
        if (! $vorlage instanceof Auftragsvorlage) {
            return;
        }

        if (Bestellung::withTrashed()->where('auftragsvorlage_id', $vorlage->getKey())->exists()) {
            $vorlage->forceFill(['aktiv' => false])->save();
            Flux::toast(
                heading: 'Vorlage deaktiviert',
                text: 'Die Vorlage wird noch verwendet und wurde deshalb nur deaktiviert.',
                variant: 'warning',
            );
            unset($this->vorlagen);

            return;
        }

        $vorlage->delete();
        Flux::toast(heading: 'Vorlage gelöscht', text: 'Die Auftragsvorlage wurde gelöscht.', variant: 'success');
        unset($this->vorlagen);
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.auftragsvorlagen-editor');
    }
}
