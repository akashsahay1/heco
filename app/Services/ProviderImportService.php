<?php

namespace App\Services;

use App\Models\Region;
use App\Models\ServiceProvider;
use App\Models\SystemList;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Bring HECO's existing service providers in from a CSV, so every one of them
 * is in the list when a service is added to a trip (feedback, 2026-10-28).
 *
 * The file is the template's columns, made in Excel or Google Sheets. Good
 * rows are saved and every bad one is named with its line and what is wrong,
 * so the file can be corrected and sent again: a row already in (same SP ID or
 * email) is reported, never doubled.
 *
 * An imported provider is an approved Other Service Provider with no login.
 * Nothing is emailed: whether and when HECO invites them to sign in is HECO's
 * call, and a bulk import is not the moment to write to dozens of people.
 */
class ProviderImportService
{
    /** The columns. Every one is required (the feedback lists no optional ones yet). */
    public const COLUMNS = ['sp_id', 'name', 'phone', 'email', 'region', 'service_type'];

    /** How many rows one file may hold. */
    public const MAX_ROWS = 2000;

    /** The template: the header row alone. */
    public function template(): string
    {
        return implode(',', self::COLUMNS) . "\r\n";
    }

    /**
     * What a provider can be said to offer: the Service Categories HECO keeps
     * in the System List, the same list signup and Add Provider tick from, so
     * an imported provider reads the same as any other.
     */
    public function serviceTypes(): array
    {
        return SystemList::ofType('service_category')->where('is_active', true)->orderBy('sort_order')->pluck('name')->all();
    }

    /**
     * @return array{created: int, failed: array<int, array{line: int, reason: string}>, error?: string}
     */
    public function import(UploadedFile $file, User $by): array
    {
        $text = $this->asUtf8((string) @file_get_contents($file->getRealPath()));
        if (trim($text) === '') {
            return ['created' => 0, 'failed' => [], 'error' => 'The file is empty.'];
        }
        // Windows, Mac and Unix line endings alike.
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Excel in much of Europe separates with semicolons; "Unicode Text"
        // with tabs. The separator is whichever the heading row uses.
        $firstLine = strtok($text, "\n");
        $delimiter = ',';
        foreach ([';', "\t"] as $d) {
            if (substr_count($firstLine, $d) > substr_count($firstLine, $delimiter)) {
                $delimiter = $d;
            }
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle, 0, $delimiter) ?: []);
        $missing = array_diff(self::COLUMNS, $header);
        if ($missing) {
            fclose($handle);
            return ['created' => 0, 'failed' => [], 'error' => 'These columns are missing: ' . implode(', ', $missing)
                . '. Download the template and keep its first row as it is.'];
        }
        $twice = array_keys(array_filter(array_count_values(array_filter($header)), fn ($n) => $n > 1));
        if ($twice) {
            fclose($handle);
            return ['created' => 0, 'failed' => [], 'error' => 'These columns appear more than once: ' . implode(', ', $twice) . '.'];
        }

        $regions = Region::where('is_active', true)->get(['id', 'name'])
            ->keyBy(fn ($r) => $this->key($r->name));
        $categories = collect($this->serviceTypes())->keyBy(fn ($c) => $this->key($c));

        $created = 0;
        $failed = [];
        $seenIds = [];
        $seenEmails = [];
        $line = 1;
        $rows = 0;

        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;   // a blank line
            }
            if (++$rows > self::MAX_ROWS) {
                $failed[] = ['line' => $line, 'reason' => 'More than ' . self::MAX_ROWS . ' rows; split the file and send the rest separately.'];
                break;
            }

            $row = [];
            foreach ($header as $i => $name) {
                // Spaces inside a value are the writer's, but runs of them
                // and the ends are not.
                $row[$name] = trim(preg_replace('/\s+/u', ' ', (string) ($cells[$i] ?? '')));
            }

            $reason = $this->problemWith($row, $regions, $categories, $seenIds, $seenEmails);
            if ($reason !== null) {
                $failed[] = ['line' => $line, 'reason' => $reason];
                continue;
            }

            $email = mb_strtolower($row['email']);
            try {
                DB::transaction(function () use ($row, $email, $regions, $categories, $by) {
                    ServiceProvider::create([
                        'sp_code' => $row['sp_id'],
                        'provider_types' => ['osp'],
                        'name' => $row['name'],
                        'email' => $email,
                        'phone_1' => $row['phone'],
                        'region_id' => $regions[$this->key($row['region'])]->id,
                        // What they offer, in the System List's own words, where
                        // signup and Add Provider keep it ("told us").
                        'service_categories' => array_values(array_map(fn ($k) => $categories[$k], $this->typesIn($row['service_type'], $categories))),
                        'status' => 'approved',
                        'approved_at' => now(),
                        'approved_by' => $by->id,
                        'last_updated_by' => $by->id,
                        'last_updated_by_role' => 'admin',
                    ]);
                });
            } catch (\Throwable $e) {
                // One row the database refuses is that row's problem, named
                // like any other, not the whole file's.
                report($e);
                $failed[] = ['line' => $line, 'reason' => 'This row could not be saved. Check its values and try again.'];
                continue;
            }
            $seenIds[mb_strtolower($row['sp_id'])] = true;
            $seenEmails[$email] = true;
            $created++;
        }
        fclose($handle);

        return ['created' => $created, 'failed' => $failed];
    }

    /**
     * The file's text as UTF-8. Excel's "CSV (Comma delimited)" on Windows is
     * Windows-1252, "Unicode Text" is UTF-16, and Google Sheets is UTF-8 with
     * or without a byte-order mark.
     */
    private function asUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        } elseif (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return preg_replace('/^\x{FEFF}/u', '', $raw) ?? $raw;
    }

    /** A name as it is compared: case and spacing do not count. */
    private function key(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }

    /** What is wrong with a row, in words a person can act on, or null. */
    private function problemWith(array $row, $regions, $categories, array $seenIds, array $seenEmails): ?string
    {
        $empty = array_values(array_filter(self::COLUMNS, fn ($c) => ($row[$c] ?? '') === ''));
        if ($empty) {
            return 'Missing: ' . implode(', ', $empty) . '.';
        }
        if (mb_strlen($row['sp_id']) > 50) {
            return 'sp_id is longer than 50 characters.';
        }
        if (mb_strlen($row['name']) > 255) {
            return 'name is longer than 255 characters.';
        }
        if (mb_strlen($row['email']) > 255 || ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            return "\"{$row['email']}\" is not an email address.";
        }
        if (! preg_match('/^\+?[0-9][0-9 \-]{5,19}$/', $row['phone'])) {
            return "\"{$row['phone']}\" is not a phone number (digits, spaces or dashes, 6 to 20).";
        }
        if (! isset($regions[$this->key($row['region'])])) {
            return "No region called \"{$row['region']}\". Use one of: " . $regions->pluck('name')->sort()->join(', ') . '.';
        }
        if (! $this->typesIn($row['service_type'], $categories)) {
            return "\"{$row['service_type']}\" is not a service category. Use one of: " . $categories->values()->join(', ')
                . ' (several may be separated by commas).';
        }

        $email = mb_strtolower($row['email']);
        $id = mb_strtolower($row['sp_id']);
        if (isset($seenIds[$id])) {
            return "SP ID {$row['sp_id']} appears more than once in this file.";
        }
        if (isset($seenEmails[$email])) {
            return "{$row['email']} appears more than once in this file.";
        }
        if (ServiceProvider::whereRaw('LOWER(sp_code) = ?', [$id])->exists()) {
            return "SP ID {$row['sp_id']} is already in HECO.";
        }
        if (ServiceProvider::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return "{$row['email']} is already a provider in HECO.";
        }

        return null;
    }

    /** The categories a cell names (as keys), or [] when any of it is not one. */
    private function typesIn(string $cell, $categories): array
    {
        $found = [];
        foreach (explode(',', $cell) as $word) {
            $key = $this->key($word);
            if ($key === '') {
                continue;
            }
            if (! isset($categories[$key])) {
                return [];
            }
            $found[$key] = $key;
        }

        return array_values($found);
    }
}
