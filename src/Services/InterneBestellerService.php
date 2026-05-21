<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services;

use App\Models\User;
use Hwkdo\IntranetAppBestellungen\Models\IntranetAppBestellungenSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InterneBestellerService
{
    public function rollenName(): string
    {
        $name = trim(IntranetAppBestellungenSettings::resolvedAppSettings()->interneBestellerGruppe);

        if ($name !== '') {
            return $name;
        }

        return (string) config('intranet-app-bestellungen.roles.interne_besteller.name', 'App-Bestellungen-InterneBesteller');
    }

    /**
     * @return Builder<User>
     */
    public function mitgliederQuery(): Builder
    {
        return User::query()
            ->aktiv()
            ->role($this->rollenName())
            ->orderBy('nachname')
            ->orderBy('vorname');
    }

    /**
     * @return Collection<int, User>
     */
    public function mitglieder(): Collection
    {
        return $this->mitgliederQuery()->get();
    }

    public function istMitglied(User $user): bool
    {
        return $user->hasRole($this->rollenName());
    }
}
