<?php

namespace App\Console\Commands;

use App\Models\Experience;
use App\Models\ExperienceDay;
use Illuminate\Console\Command;

class FillExperienceDays extends Command
{
    protected $signature = 'experiences:fill-days
                            {--dry-run : Show what would be created without persisting}
                            {--id= : Backfill a single experience id}
                            {--rebuild : Overwrite earlier generic template content with the richer type-aware version. Hand-edited rows are left alone.}';

    /**
     * Generic titles previously written by this command. Used by --rebuild
     * to identify rows whose content is safe to overwrite (i.e. nobody
     * customized them). Anything not in this list is treated as user-edited.
     */
    protected array $genericTitles = [
        'Arrival & Acclimatization',
        'Final Day & Departure',
        'Experience Day',
        'Experience',
    ];

    protected $description = 'Create blank-but-templated ExperienceDay rows for any experience that is missing per-day details';

    public function handle(): int
    {
        $query = Experience::query();
        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }
        $experiences = $query->with('days')->get();

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $patched = 0;
        $touchedExp = 0;

        foreach ($experiences as $exp) {
            $targetDays = $this->targetDayCount($exp);
            if ($targetDays < 1) continue;

            $existing = $exp->days->keyBy('day_number');
            $expCreated = 0;
            $expPatched = 0;

            for ($n = 1; $n <= $targetDays; $n++) {
                $template = $this->buildDayRow($exp, $n, $targetDays);
                $row = $existing->get($n);

                if (!$row) {
                    // Day is missing — create from scratch.
                    if ($expCreated === 0 && $expPatched === 0) {
                        $this->line("");
                        $this->info("→ {$exp->name} (#{$exp->id}, {$exp->duration_type}, target={$targetDays}d)");
                    }
                    $this->line("   + Day {$n}: {$template['title']}  [" . implode(', ', $template['inclusions']) . "]");
                    if (!$dryRun) {
                        ExperienceDay::create([
                            'experience_id'     => $exp->id,
                            'day_number'        => $n,
                            'sort_order'        => $n - 1,
                            'title'             => $template['title'],
                            'short_description' => $template['short_description'],
                            'start_time'        => $template['start_time'],
                            'end_time'          => $template['end_time'],
                            'inclusions'        => $template['inclusions'],
                        ]);
                    }
                    $expCreated++;
                    continue;
                }

                // Row exists — fill blank fields, plus (with --rebuild) overwrite
                // earlier generic template strings while leaving hand-edited content alone.
                $rebuild = (bool) $this->option('rebuild');
                $patches = [];

                $isGenericTitle = $rebuild && $this->matchesGenericTitle($row->title);
                if ($this->isBlank($row->title) || $isGenericTitle) {
                    $patches['title'] = $template['title'];
                }

                $isGenericDesc = $rebuild && $this->looksLikeOldTemplateDescription($row->short_description);
                if (($this->isBlank($row->short_description) || $isGenericDesc) && $template['short_description']) {
                    $patches['short_description'] = $template['short_description'];
                }

                if ($this->isBlank($row->start_time))        $patches['start_time'] = $template['start_time'];
                if ($this->isBlank($row->end_time))          $patches['end_time']   = $template['end_time'];
                if (empty($row->inclusions) || !is_array($row->inclusions)) {
                    $patches['inclusions'] = $template['inclusions'];
                }

                if (!empty($patches)) {
                    if ($expCreated === 0 && $expPatched === 0) {
                        $this->line("");
                        $this->info("→ {$exp->name} (#{$exp->id}, {$exp->duration_type}, target={$targetDays}d)");
                    }
                    $this->line("   ~ Day {$n}: filled " . implode(', ', array_keys($patches)));
                    if (!$dryRun) {
                        $row->update($patches);
                    }
                    $expPatched++;
                }
            }

            if ($expCreated || $expPatched) $touchedExp++;
            $created += $expCreated;
            $patched += $expPatched;
        }

        $this->line("");
        if ($dryRun) {
            $this->warn("DRY RUN — would create {$created} new rows and patch {$patched} existing rows across {$touchedExp} experiences. Re-run without --dry-run to persist.");
        } else {
            $this->info("Created {$created} rows, patched blanks on {$patched} rows, across {$touchedExp} experiences.");
        }

        return self::SUCCESS;
    }

    protected function isBlank($value): bool
    {
        return $value === null || $value === '' || (is_string($value) && trim($value) === '');
    }

    protected function targetDayCount(Experience $exp): int
    {
        return match ($exp->duration_type) {
            'multi_day' => max(1, (int) ($exp->duration_days ?? 1)),
            'single_day', 'less_than_day' => 1,
            default => 1,
        };
    }

    /**
     * Build a templated day row. Title varies by position; inclusions follow
     * a sensible default for the experience type. Description is filled only
     * for Day 1 (from the experience's short_description) so admins can edit
     * the rest without overwriting template text.
     */
    protected function buildDayRow(Experience $exp, int $n, int $total): array
    {
        $isFirst = $n === 1;
        $isLast = $n === $total && $total > 1;
        $expName = $exp->name;
        $regionName = $exp->region->name ?? null;
        $regionPart = $regionName ? " in {$regionName}" : '';
        $type = (string) $exp->type;

        // Single day / less-than-day: same shape, type-flavored copy.
        if ($exp->duration_type === 'less_than_day') {
            return [
                'title'             => $expName . ' — Highlights',
                'short_description' => "A short, immersive {$type} experience{$regionPart}. "
                    . ($exp->short_description ?: "Discover the essence of {$expName} in just a few hours with a local guide."),
                'start_time'        => '09:00:00',
                'end_time'          => '13:00:00',
                'inclusions'        => ['Lunch'],
            ];
        }
        if ($exp->duration_type === 'single_day' || $total === 1) {
            return [
                'title'             => $expName . ' — Full-Day Experience',
                'short_description' => "Spend a full day on {$expName}{$regionPart}. "
                    . ($exp->short_description ?: "Includes guided activities, lunch, and transport from your pickup point."),
                'start_time'        => '09:00:00',
                'end_time'          => '17:00:00',
                'inclusions'        => ['Lunch', 'Transport'],
            ];
        }

        // Multi-day — type-aware copy. Picks vocabulary per experience type.
        $copy = $this->multiDayCopy($type, $expName, $regionName, $n, $total);

        $inclusions = ['Breakfast', 'Dinner', 'Accommodation'];
        if (!$isFirst && !$isLast) {
            $inclusions[] = 'Lunch';
        }
        // Treks and adventure days typically include a guide.
        if (in_array($type, ['Trek', 'Adventure', 'Wildlife'], true) && !$isFirst) {
            $inclusions[] = 'Guide';
        }
        $inclusions = array_values(array_unique($inclusions));

        return [
            'title'             => $copy['title'],
            'short_description' => trim($copy['description']),
            'start_time'        => $isFirst ? '14:00:00' : ($isLast ? '07:00:00' : '07:00:00'),
            'end_time'          => $isFirst ? '20:00:00' : ($isLast ? '14:00:00' : '19:00:00'),
            'inclusions'        => $inclusions,
        ];
    }

    /**
     * Type-aware title + description for a multi-day positional slot.
     */
    protected function multiDayCopy(string $type, string $expName, ?string $regionName, int $n, int $total): array
    {
        $isFirst = $n === 1;
        $isLast = $n === $total;
        $regionPart = $regionName ? " in {$regionName}" : '';
        $shortName = $this->shortName($expName);

        // Per-type vocabulary for the middle "main activity" days.
        $verbBank = [
            'Trek'                 => ['Trekking',     'Begin guided trek',                    'a steady ascent through scenic ridges and forest trails'],
            'Adventure'            => ['Adventure',    'Tackle today\'s adventure leg',         'high-energy activities led by certified local guides'],
            'Wildlife'             => ['Tracking',     'Set out at dawn with local spotters',  'tracking through the habitat for sightings and field notes'],
            'Cultural Immersion'   => ['Cultural',     'Spend the day with the host community',  'workshops, traditional meals, and storytelling sessions'],
            'Culinary'             => ['Tasting',      'Hands-on cooking with local hosts',     'market visits, ingredient prep, and a long shared meal'],
            'Nature'               => ['Nature',       'Easy nature walks and quiet exploration', 'birdwatching, riverside time, and slow-paced discovery'],
        ];
        $bank = $verbBank[$type] ?? ['Activities', 'Day of guided activities', 'walks, encounters, and time at your own pace'];
        [$activityWord, $verbPhrase, $detailPhrase] = $bank;

        // Day 1 — arrival & briefing.
        if ($isFirst) {
            $title = 'Arrival & Acclimatization (' . $shortName . ')';
            $description = "Arrival at {$expName}{$regionPart} base. Meet your guide and the host team for "
                . "a briefing about the {$total}-day journey ahead. Check in to your accommodation, "
                . "settle in, and enjoy a welcome dinner with your hosts.";
            return ['title' => $title, 'description' => $description];
        }

        // Final day — wind-down & departure.
        if ($isLast) {
            $title = 'Wind-Down & Departure (' . $shortName . ')';
            $description = "Wake up early in the peaceful {$regionName} environment. Enjoy breakfast, "
                . "a short morning walk, and final farewells with your hosts before transferring to the next leg of your trip.";
            return ['title' => $title, 'description' => $description];
        }

        // Middle days — main activity, type-flavored.
        $title = $activityWord . ' Day — ' . $shortName . ' (Day ' . $n . ' of ' . $total . ')';
        $description = "Wake up early and have breakfast. {$verbPhrase}: {$detailPhrase}. "
            . "Lunch on the trail or at a local stop, return to base by evening for dinner and rest.";
        return ['title' => $title, 'description' => $description];
    }

    /**
     * Compact short name for use in titles. Picks an acronym for very long
     * names (e.g. "Great Himalayan National Park" → "GHNP"), else the name.
     */
    protected function shortName(string $name): string
    {
        if (strlen($name) <= 28) return $name;
        // Build acronym from words ≥ 4 chars (keeps "Great Himalayan National Park" → "GHNP").
        $words = preg_split('/\s+/', preg_replace('/[^A-Za-z\s]/', '', $name));
        $caps = array_filter(array_map(fn($w) => ctype_upper($w[0] ?? '') ? $w[0] : '', $words));
        $acro = implode('', $caps);
        return strlen($acro) >= 3 ? $acro : $name;
    }

    /**
     * True if the title looks like one this command produced earlier
     * (so --rebuild is safe to overwrite). Hand-edited titles fall through.
     */
    protected function matchesGenericTitle(?string $title): bool
    {
        if ($this->isBlank($title)) return false;
        $t = trim((string) $title);
        if (in_array($t, $this->genericTitles, true)) return true;
        // "Activities — Day N" pattern.
        if (preg_match('/^Activities\s+(?:—|-)\s+Day\s+\d+$/u', $t)) return true;
        return false;
    }

    /**
     * True if the description matches phrasing the previous templates produced.
     */
    protected function looksLikeOldTemplateDescription(?string $desc): bool
    {
        if ($this->isBlank($desc)) return false;
        $d = (string) $desc;
        $patterns = [
            '/^Day \d+ of \d+\. Continue exploring/u',
            '/^Wrap up your .+ journey with a leisurely morning/u',
            '/^Arrive(?:\s+in\s+\S+)?\s+and settle in\. Brief orientation/u',
            '/^A relaxing .+ homestay/u',
            '/^A \d+-day journey through/u',
            '/^A winter wildlife expedition/u',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $d)) return true;
        }
        return false;
    }
}
