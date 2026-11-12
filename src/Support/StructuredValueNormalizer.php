<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Support;

final class StructuredValueNormalizer
{
    public static function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function nullableFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if (str_contains($trimmed, ',') && str_contains($trimmed, '.')) {
            $normalized = str_replace('.', '', $trimmed);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($trimmed, ',')) {
            $normalized = str_replace(',', '.', $trimmed);
        } else {
            $normalized = $trimmed;
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    public static function nullableDate(mixed $value): ?string
    {
        $raw = self::nullableString(is_string($value) ? $value : null);
        if ($raw === null) {
            return null;
        }

        $normalized = str_replace('/', '.', $raw);

        foreach (['Y-m-d', 'd.m.Y', 'd.m.y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $normalized);
            if ($parsed instanceof \DateTimeImmutable && $parsed->format($format) === $normalized) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }
}
