<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Livewire\Apps\Bestellungen\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppBase\Contracts\AiConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetBaseAiConfigSourceInterface;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class KiEinstellungen extends Component
{
    public string $aiTextProviderOverride = '';

    public string $aiTextModelOverride = '';

    public string $aiImageProviderOverride = '';

    public string $aiImageModelOverride = '';

    public function mount(): void
    {
        $settings = IntranetAppBestellungenSettings::resolvedAppSettings();

        $this->aiTextProviderOverride = $settings->aiTextProviderOverride?->value ?? '';
        $this->aiTextModelOverride = $settings->textModelOverride() ?? '';
        $this->aiImageProviderOverride = $settings->aiImageProviderOverride?->value ?? '';
        $this->aiImageModelOverride = $settings->imageModelOverride() ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'aiTextProviderOverride' => ['nullable', 'string', Rule::enum(AiProvider::class)],
            'aiTextModelOverride' => 'nullable|string|max:100',
            'aiImageProviderOverride' => ['nullable', 'string', Rule::enum(AiProvider::class)],
            'aiImageModelOverride' => 'nullable|string|max:100',
        ]);

        $model = IntranetAppBestellungenSettings::current();
        if ($model === null) {
            $model = IntranetAppBestellungenSettings::query()->create([
                'version' => 1,
                'settings' => new AppSettings,
            ]);
        }

        $current = $model->settings;
        $values = $current instanceof AppSettings ? $current->toArray() : [];

        $values['aiTextProviderOverride'] = $this->providerOrNull($this->aiTextProviderOverride);
        $values['aiTextModelOverride'] = $this->blankToNull($this->aiTextModelOverride);
        $values['aiImageProviderOverride'] = $this->providerOrNull($this->aiImageProviderOverride);
        $values['aiImageModelOverride'] = $this->blankToNull($this->aiImageModelOverride);

        $model->settings = AppSettings::from($values);
        $model->save();

        unset($this->baseAiTextSummary, $this->baseAiImageSummary, $this->effectiveAiTextSummary, $this->effectiveAiImageSummary);

        Flux::toast(
            heading: 'Gespeichert',
            text: 'KI-Einstellungen wurden gespeichert.',
            variant: 'success',
        );
    }

    #[Computed]
    public function baseAiTextSummary(): string
    {
        $base = app(IntranetBaseAiConfigSourceInterface::class);
        $model = $base->textModel() ?? 'Provider-Standard';

        return $base->textProvider()->label().' / '.$model;
    }

    #[Computed]
    public function baseAiImageSummary(): string
    {
        $base = app(IntranetBaseAiConfigSourceInterface::class);
        $model = $base->imageModel() ?? 'Provider-Standard';

        return $base->imageProvider()->label().' / '.$model;
    }

    #[Computed]
    public function effectiveAiTextSummary(): string
    {
        $resolved = app(AiConfigResolverInterface::class)->resolve('bestellungen', AiCapability::Agent);

        return $resolved->provider->label().' / '.($resolved->model ?? 'Provider-Standard');
    }

    #[Computed]
    public function effectiveAiImageSummary(): string
    {
        $resolved = app(AiConfigResolverInterface::class)->resolve('bestellungen', AiCapability::Image);

        return $resolved->provider->label().' / '.($resolved->model ?? 'Provider-Standard');
    }

    public function render(): View
    {
        return view('intranet-app-bestellungen::livewire.apps.bestellungen.admin.ki-einstellungen');
    }

    private function providerOrNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function blankToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
