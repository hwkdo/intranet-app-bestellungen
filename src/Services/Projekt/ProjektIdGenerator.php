<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Services\Projekt;

use Hwkdo\IntranetAppBestellungen\Models\Projekt;
use Illuminate\Support\Str;

class ProjektIdGenerator
{
    private const int MAX_LENGTH = 35;

    private const string FALLBACK_BASE = 'projekt';

    public function generate(string $name, ?int $excludeProjektId = null): string
    {
        $base = $this->slugBase($name);
        $candidate = $base;
        $suffix = 2;

        while ($this->exists($candidate, $excludeProjektId)) {
            $suffixPart = '-'.$suffix;
            $candidate = Str::substr($base, 0, self::MAX_LENGTH - strlen($suffixPart)).$suffixPart;
            $suffix++;
        }

        return $candidate;
    }

    private function slugBase(string $name): string
    {
        $slug = Str::slug(trim($name), '-');

        if ($slug === '') {
            $slug = self::FALLBACK_BASE;
        }

        return Str::substr($slug, 0, self::MAX_LENGTH);
    }

    private function exists(string $candidate, ?int $excludeProjektId): bool
    {
        return Projekt::query()
            ->when($excludeProjektId !== null, fn ($query) => $query->where('id', '!=', $excludeProjektId))
            ->where('d3_projekt_id', $candidate)
            ->exists();
    }
}
