<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The GST report between any two days: a month, a financial year, several
 * years. The screen and its Excel
 * download both read it here, so they cannot disagree.
 *
 * A trip counts in the month it was created. Whether it should count in the
 * month the traveller paid instead is for HECO's accountant to say.
 */
class GstReportService
{
    /**
     * The first and last moment of the days asked for (Y-m-d each), or a
     * sentence saying what is wrong with them.
     *
     * @return array{0: Carbon, 1: Carbon}|string
     */
    public function period($fromDate, $toDate): array|string
    {
        $parse = function ($d) {
            if (! is_string($d) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                return null;
            }
            $c = Carbon::createFromFormat('!Y-m-d', $d);
            return $c && $c->format('Y-m-d') === $d ? $c : null;
        };
        $from = $parse($fromDate);
        $to = $parse($toDate);
        if (! $from || ! $to) {
            return 'Pick a From date and a To date.';
        }
        if ($from->gt($to)) {
            return 'From has to come before To.';
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    /** @return array{summary: array, trips: Collection} */
    public function report(Carbon $from, Carbon $to): array
    {
        $gstPercent = (float) Setting::getValue('gst_percent', 5);

        $trips = Trip::whereIn('status', ['confirmed', 'running', 'completed'])
            ->whereBetween('created_at', [$from, $to])
            ->with('user')
            ->orderBy('created_at')
            ->get()
            ->map(function ($t) use ($gstPercent) {
                $subtotal = (float) ($t->subtotal ?? 0);
                $gstAmount = (float) ($t->gst_amount ?? 0);
                // Derive the effective rate per trip when possible; fall back to the global setting.
                $effectiveRate = $subtotal > 0 ? round($gstAmount / $subtotal * 100, 2) : $gstPercent;
                return [
                    'id' => $t->id,
                    'trip_id' => $t->trip_id,
                    'created_at' => $t->created_at?->toDateString(),
                    'user' => [
                        'full_name' => $t->user->full_name ?? $t->user->email ?? '-',
                        'email' => $t->user->email ?? '',
                    ],
                    'start_date' => $t->start_date,
                    'end_date' => $t->end_date,
                    'subtotal' => $subtotal,
                    'gst_percent' => $effectiveRate,
                    'gst_amount' => $gstAmount,
                    'final_price' => (float) ($t->final_price ?? 0),
                    'status' => $t->status,
                ];
            })->values();

        return [
            'summary' => [
                'total_revenue' => $trips->sum('final_price'),
                'total_gst' => $trips->sum('gst_amount'),
                'net_revenue' => $trips->sum('subtotal'),
            ],
            'trips' => $trips,
        ];
    }

    /** The report as a CSV Excel opens as it is: UTF-8 with its byte-order mark. */
    public function csv(Carbon $from, Carbon $to): string
    {
        $report = $this->report($from, $to);
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\u{FEFF}");
        fputcsv($out, ['GST report', $from->format('d M Y') . ' to ' . $to->format('d M Y')]);
        fputcsv($out, []);
        fputcsv($out, ['Trip ID', 'Created', 'Traveller', 'Email', 'Start date', 'End date', 'Status', 'Subtotal', 'GST %', 'GST amount', 'Final price']);
        foreach ($report['trips'] as $t) {
            fputcsv($out, [
                $t['trip_id'], $t['created_at'], $t['user']['full_name'], $t['user']['email'],
                $t['start_date']?->toDateString(), $t['end_date']?->toDateString(), $t['status'],
                $t['subtotal'], $t['gst_percent'], $t['gst_amount'], $t['final_price'],
            ]);
        }
        $s = $report['summary'];
        fputcsv($out, ['Totals', '', '', '', '', '', '', $s['net_revenue'], '', $s['total_gst'], $s['total_revenue']]);
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
