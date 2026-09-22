<?php

namespace App\Support;

/**
 * How many people a priced room sleeps.
 *
 * Occupancy is a phrase somebody picks from a list, never a number: "per
 * single", "per double", "per triple", "per quad", "per room". Casting that
 * phrase to an integer gives 0, and code that did so read every room on the
 * rate card as a single: a party of four was sold four rooms instead of two,
 * charged for four, and four were held at the property.
 *
 * The first four phrases say how many people sleep in what is being priced.
 * "per room" and "per dorm" say the whole room goes at that price whoever is
 * in it, so the number has to come from the thing being sold, which differs
 * between a listing and a rate card. Those return null here and each caller
 * answers it from what it knows.
 */
final class Occupancy
{
    /** Phrase to a head count, or null when the phrase means a whole room. */
    public static function seats(?string $phrase): ?int
    {
        $word = strtolower(trim((string) $phrase));

        if ($word === '') {
            return null;
        }

        // A plain number is honoured as itself: older rows and imports hold one.
        if (ctype_digit($word)) {
            return max((int) $word, 1);
        }

        foreach (['single' => 1, 'double' => 2, 'twin' => 2, 'triple' => 3, 'quad' => 4] as $name => $n) {
            if (str_contains($word, $name)) {
                return $n;
            }
        }

        return null;
    }
}
