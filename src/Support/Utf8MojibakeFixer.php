<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Support;

/**
 * Korrigiert typisches Mojibake: UTF-8-Bytes wurden als ISO-8859-1-Zeichen gelesen
 * (z. B. Anzeige „MÃ¼nchen“ statt „München“).
 *
 * Technisch: ein Aufruf {@see mb_convert_encoding} von UTF-8 **nach** ISO-8859-1 liefert
 * die ursprüngliche Bytefolge; diese ist in PHP als String gültiges UTF-8 für den
 * gewünschten Text. Ein **zusätzliches** ISO-8859-1→UTF-8 wäre falsch und erzeugt
 * erneut Mojibake (z. B. „MÃÂ¼nchen“).
 *
 * Bei mehrfach hintereinander fehlkodierter Anzeige (selten): bis zu 3 Durchläufe,
 * solange die Heuristik noch greift und sich der String ändert.
 */
final class Utf8MojibakeFixer
{
    private const MAX_PASSES = 3;

    public static function fixIfLikelyMojibake(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! self::looksLikeUtf8MisreadAsLatin1($value)) {
            return $value;
        }

        $current = $value;

        for ($i = 0; $i < self::MAX_PASSES; $i++) {
            if (! self::looksLikeUtf8MisreadAsLatin1($current)) {
                break;
            }

            $next = mb_convert_encoding($current, 'ISO-8859-1', 'UTF-8');
            if ($next === false || $next === '' || $next === $current) {
                break;
            }

            $current = $next;
        }

        return $current;
    }

    private static function looksLikeUtf8MisreadAsLatin1(string $value): bool
    {
        if (str_contains($value, 'Ã') || str_contains($value, 'Â')) {
            return true;
        }

        return false;
    }
}
