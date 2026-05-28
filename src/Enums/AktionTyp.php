<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Enums;

enum AktionTyp: string
{
    case Erstellt = 'erstellt';
    case ZurFreigabeEingereicht = 'zur_freigabe_eingereicht';
    case Weitergeleitet = 'weitergeleitet';
    case Freigegeben = 'freigegeben';
    case ErstFreigegeben = 'erst_freigegeben';
    case Abgelehnt = 'abgelehnt';
    case Bestellt = 'bestellt';
    case D3Push = 'd3_push';
    case D3RePush = 'd3_re_push';
    case AngebotHinzugefuegt = 'angebot_hinzugefuegt';
    case AngebotEntfernt = 'angebot_entfernt';
    case AngebotViaOutlookHochgeladen = 'angebot_via_outlook_hochgeladen';
    case AngebotExtraktionAbgeschlossen = 'angebot_extraktion_abgeschlossen';
    case AngebotExtraktionFehlgeschlagen = 'angebot_extraktion_fehlgeschlagen';
    case NotizHinzugefuegt = 'notiz_hinzugefuegt';
    case Wiederholt = 'wiederholt';

    public function label(): string
    {
        return match ($this) {
            self::Erstellt => 'Erstellt',
            self::ZurFreigabeEingereicht => 'Zur Freigabe eingereicht',
            self::Weitergeleitet => 'Weitergeleitet',
            self::Freigegeben => 'Freigegeben',
            self::ErstFreigegeben => 'Erstfreigabe erteilt',
            self::Abgelehnt => 'Abgelehnt',
            self::Bestellt => 'Bestellt',
            self::D3Push => 'An D3 übertragen',
            self::D3RePush => 'D3 Re-Push',
            self::AngebotHinzugefuegt => 'Angebot hinzugefügt',
            self::AngebotEntfernt => 'Angebot entfernt',
            self::AngebotViaOutlookHochgeladen => 'Angebot via Outlook hochgeladen',
            self::AngebotExtraktionAbgeschlossen => 'Angebots-Extraktion abgeschlossen',
            self::AngebotExtraktionFehlgeschlagen => 'Angebots-Extraktion fehlgeschlagen',
            self::NotizHinzugefuegt => 'Notiz hinzugefügt',
            self::Wiederholt => 'Wiederholt aus Vorlage',
        };
    }
}
