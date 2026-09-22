<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One cell of an experiential stay's pricing grid: what a given occupancy costs
 * on a given meal plan.
 *
 * The client's shape is "Pricing table (single, double, triple, meal plans)" —
 * a grid rather than the single per-person price the other experience
 * categories use, because a homestay charges by room and board, not by head.
 */
class ExperienceRoomRate extends Model
{
    protected $fillable = [
        'experience_id', 'occupancy', 'meal_plan', 'price', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function experience()
    {
        return $this->belongsTo(Experience::class);
    }

    /**
     * How many travellers this rate covers.
     *
     * The occupancy is a phrase the host picks from a list, not a number:
     * "per single", "per double", "per triple", "per quad", "per room". The
     * first four say how many people sleep in what is being priced; "per room"
     * says the whole room goes at that price whoever is in it, so the answer
     * comes from the listing's own capacity.
     *
     * Anything unrecognised counts as two, which is what a per-person price
     * has always assumed elsewhere.
     */
    public function seats(): int
    {
        $word = strtolower(trim((string) $this->occupancy));

        foreach (['single' => 1, 'double' => 2, 'twin' => 2, 'triple' => 3, 'quad' => 4] as $name => $n) {
            if (str_contains($word, $name)) {
                return $n;
            }
        }

        if (str_contains($word, 'room') || str_contains($word, 'dorm')) {
            $stay = $this->relationLoaded('experience') ? $this->experience : $this->experience()->first();
            $rooms = max((int) ($stay->total_rooms ?? 0), 1);
            $guests = (int) ($stay->total_guests ?? 0);
            return $guests > 0 ? max((int) floor($guests / $rooms), 1) : 2;
        }

        return 2;
    }

    /**
     * What this rate costs a party of $heads for $nights, before markup:
     * the rate times the rooms they need times the nights they stay.
     */
    public function costFor(int $heads, int $nights): int
    {
        $rooms = max((int) ceil(max($heads, 1) / max($this->seats(), 1)), 1);

        return (int) round((float) $this->price * $rooms * max($nights, 1));
    }
}
