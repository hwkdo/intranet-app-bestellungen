<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Models;

use Hwkdo\IntranetAppBestellungen\Data\AppSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class IntranetAppBestellungenSettings extends Model
{
    protected $table = 'intranet_app_bestellungen_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => AppSettings::class.':default',
        ];
    }

    public static function current(): ?IntranetAppBestellungenSettings
    {
        return self::orderBy('version', 'desc')->first();
    }

    public static function resolvedAppSettings(): AppSettings
    {
        if (! Schema::hasTable((new static)->getTable())) {
            return new AppSettings;
        }

        $row = static::current();

        return $row?->settings instanceof AppSettings ? $row->settings : new AppSettings;
    }
}
