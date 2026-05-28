<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Resources;

use Hwkdo\IntranetAppBestellungen\Models\Bestellung;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bestellung */
class ApiBestellungResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayLabel = $this->betreff
            ? trim($this->betreff).' ('.$this->nummer.')'
            : trim((string) $this->lieferantenname).' ('.$this->nummer.')';

        return [
            'id' => $this->getKey(),
            'name' => $displayLabel,
            'nummer' => $this->nummer,
            'status' => $this->status?->value,
            'typ' => $this->typ?->value,
            'betreff' => $this->betreff,
            'lieferantenname' => $this->lieferantenname,
            'gesamtbetrag' => $this->gesamtbetrag,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
