@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => [],
])

@php
    $defaultNavItems = [
        ['label' => 'Übersicht', 'href' => route('apps.bestellungen.index'), 'icon' => 'home', 'description' => 'Zurück zur Übersicht', 'buttonText' => 'Übersicht anzeigen'],
        ['label' => 'Neue Bestellung', 'href' => route('apps.bestellungen.erstellen'), 'icon' => 'plus-circle', 'description' => 'Neue Bestellung anlegen', 'buttonText' => 'Bestellung erstellen'],
        ['label' => 'Meine Bestellungen', 'href' => route('apps.bestellungen.meine'), 'icon' => 'document-text', 'description' => 'Meine Bestellungen anzeigen', 'buttonText' => 'Meine Bestellungen'],
        ['label' => 'Projekte', 'href' => route('apps.bestellungen.projekte.index'), 'icon' => 'folder', 'description' => 'Bestellungen in Projekten bündeln', 'buttonText' => 'Projekte öffnen'],
        ['label' => 'Freigaben', 'href' => route('apps.bestellungen.freigaben'), 'icon' => 'check-badge', 'description' => 'Bestellungen zur Freigabe', 'buttonText' => 'Freigaben öffnen'],
        ['label' => 'Meine Einstellungen', 'href' => route('apps.bestellungen.settings.user'), 'icon' => 'cog-6-tooth', 'description' => 'Persönliche Einstellungen anpassen', 'buttonText' => 'Einstellungen öffnen'],
        ['label' => 'App-Info', 'href' => route('apps.bestellungen.info'), 'icon' => 'information-circle', 'description' => 'Installierte Version und Release-Historie', 'buttonText' => 'App-Info anzeigen'],
        ['label' => 'Admin', 'href' => route('apps.bestellungen.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-bestellungen'],
    ];

    $navItems = ! empty($navItems) ? $navItems : $defaultNavItems;
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('bestellungen');
@endphp

@if ($customBgUrl)
    @push('app-styles')
        <style data-app-bg data-ts="{{ uniqid() }}">
            :root {
                --app-bg-image: url('{{ $customBgUrl }}');
            }
        </style>
    @endpush
@endif

@if (request()->routeIs('apps.bestellungen.index'))
    <x-intranet-app-base::app-layout
        app-identifier="bestellungen"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="false"
    >
        <x-intranet-app-base::app-index-auto
            app-identifier="bestellungen"
            app-name="Bestellungen"
            app-description="Bestellungen erstellen, freigeben, verwalten und nach D3 übertragen."
            :nav-items="$navItems"
            welcome-title="Willkommen in der Bestellungen-App"
            welcome-description="Erfasse neue Bestellungen, verfolge offene Freigaben und verwalte den gesamten Bestellprozess bis zur Übergabe an D3."
        />
    </x-intranet-app-base::app-layout>
@else
    <x-intranet-app-base::app-layout
        app-identifier="bestellungen"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="true"
    >
        {{ $slot }}
    </x-intranet-app-base::app-layout>
@endif
