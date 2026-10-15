<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Background;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen;
use Hwkdo\IntranetAppBestellungen\Jobs\ExtractAngebotMetadataJob;
use Hwkdo\IntranetAppBestellungen\Jobs\PushBestellscheinToD3Job;
use Hwkdo\IntranetAppBestellungen\Mail\FehlenderLieferantGemeldetMail;

final class BestellungenBackgroundActivities
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public static function definitions(): array
    {
        $id = IntranetAppBestellungen::identifier();
        $name = IntranetAppBestellungen::app_name();

        return [
            BackgroundActivityDefinition::queued(key: 'bestellungen.push-d3', label: 'Bestellschein nach D3', appIdentifier: $id, appName: $name, job: PushBestellscheinToD3Job::class, sort: 10),
            BackgroundActivityDefinition::queued(key: 'bestellungen.angebot-metadata', label: 'Angebotsmetadaten', appIdentifier: $id, appName: $name, job: ExtractAngebotMetadataJob::class, sort: 20),
            BackgroundActivityDefinition::mail(key: 'bestellungen.mail-lieferant', label: 'Mail: fehlender Lieferant', appIdentifier: $id, appName: $name, mailable: FehlenderLieferantGemeldetMail::class, sort: 30),
        ];
    }
}
