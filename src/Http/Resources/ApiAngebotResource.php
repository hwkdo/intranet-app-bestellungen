<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Resources;

use Hwkdo\IntranetAppBestellungen\Models\Angebot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Angebot */
class ApiAngebotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'bestellung_id' => $this->bestellung_id,
            'typ' => $this->typ,
            'lieferantenname' => $this->lieferantenname,
            'nummer' => $this->nummer,
            'betrag' => $this->betrag,
            'pdf_path' => $this->pdf_path,
            'extraction_status' => $this->extraction_status,
            'extraction_source' => $this->extraction_source,
            'extraction_error' => $this->extraction_error,
            'extracted_at' => $this->extracted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
