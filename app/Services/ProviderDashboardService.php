<?php

namespace App\Services;

use App\Models\Experience;
use App\Models\Review;
use App\Models\ServiceProvider;
use App\Models\Setting;
use App\Models\SpPayment;
use App\Models\SpPaymentEntry;
use App\Models\TripDayService;
use App\Models\TripRegion;
use Carbon\CarbonImmutable;

/**
 * The numbers behind a provider's dashboard.
 *
 * Every figure here is read from a fact HCT already records — money actually
 * disbursed, trips actually assigned, reviews actually written. Nothing is
 * stored twice and nothing is invented: where a provider has no such facts yet
 * the figure comes back null and the app leaves that slot out.
 */
class ProviderDashboardService
{
    /** How many months of earnings the sparkline shows, current month last. */
    public const TREND_MONTHS = 7;

    public function forProvider(ServiceProvider $provider): array
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $earnings = $this->monthlyEarnings($provider, $month);
        $current = $earnings[$month->format('Y-m')];
        $previous = $earnings[$month->subMonth()->format('Y-m')];

        return [
            'period' => $month->format('F Y'),
            'earnings' => $current,
            'change_percent' => $this->changePercent($current, $previous),
            'trend' => $this->trend($earnings),
            'bookings' => $this->bookings($provider),
            'next_payout' => $this->nextPayout($provider),
            'rating' => $this->rating($provider),
        ];
    }

    /**
     * What the provider was actually paid, month by month.
     *
     * Payment entries, not invoices: an invoice records what HCT owes and can
     * sit unpaid for months, so bucketing by it would credit a provider for
     * money they never received. Entries carry the date the money moved.
     *
     * Returned keyed 'Y-m', oldest first, with empty months present as 0.0 —
     * a month with no income is a real data point, not a gap.
     */
    private function monthlyEarnings(ServiceProvider $provider, CarbonImmutable $month): array
    {
        $earliest = $month->subMonths(self::TREND_MONTHS - 1);

        $buckets = [];
        for ($i = 0; $i < self::TREND_MONTHS; $i++) {
            $buckets[$earliest->addMonths($i)->format('Y-m')] = 0.0;
        }

        $entries = SpPaymentEntry::query()
            ->whereIn('sp_payment_id', SpPayment::where('service_provider_id', $provider->id)->select('id'))
            ->where('payment_date', '>=', $earliest)
            ->get(['amount', 'payment_date']);

        foreach ($entries as $entry) {
            $key = $entry->payment_date->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key] += (float) $entry->amount;
            }
        }

        return $buckets;
    }

    /**
     * Growth against last month, or null when there is nothing to grow from.
     *
     * A first earning month is not "up 100%" — it has no comparison, and the
     * app hides the pill rather than dress that up as growth.
     */
    private function changePercent(float $current, float $previous): ?int
    {
        if ($previous <= 0.0) {
            return null;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }

    /**
     * The sparkline, as heights between 0 and 1 against the best month.
     *
     * Null while every month is empty: bars all at zero read as a flat business
     * rather than as no data yet.
     */
    private function trend(array $earnings): ?array
    {
        $peak = max($earnings);
        if ($peak <= 0.0) {
            return null;
        }

        return array_values(array_map(
            fn (float $amount) => round($amount / $peak, 3),
            $earnings,
        ));
    }

    /**
     * Trips this provider is on — the same set the bookings screen lists, so
     * the count on the dashboard and the list behind it cannot disagree.
     */
    private function bookings(ServiceProvider $provider): int
    {
        $tripIds = TripDayService::query()
            ->where('trip_day_services.service_provider_id', $provider->id)
            ->join('trip_days', 'trip_days.id', '=', 'trip_day_services.trip_day_id')
            ->pluck('trip_days.trip_id');

        if ($provider->hasType('hrp') && $provider->region_id) {
            $tripIds = $tripIds->merge(
                TripRegion::where('region_id', $provider->region_id)->pluck('trip_id'),
            );
        }

        return $tripIds->unique()->count();
    }

    /**
     * What HCT still owes, and the day it goes out.
     *
     * The balance is a fact on the invoice; the date comes from the payout day
     * HCT sets in Settings, since the schedule is a policy rather than
     * something recorded per provider.
     */
    private function nextPayout(ServiceProvider $provider): ?array
    {
        $outstanding = (float) SpPayment::where('service_provider_id', $provider->id)->sum('balance');
        if ($outstanding <= 0.0) {
            return null;
        }

        $day = (int) Setting::getValue('provider_payout_day', 7);
        $today = CarbonImmutable::today();
        $date = $this->payoutDay($today, $day);
        if ($date->lessThan($today)) {
            $date = $this->payoutDay($today->addMonthNoOverflow(), $day);
        }

        return [
            'amount' => round($outstanding, 2),
            'date' => $date->toDateString(),
        ];
    }

    /** The payout day inside a given month, clamped to months that are shorter. */
    private function payoutDay(CarbonImmutable $month, int $day): CarbonImmutable
    {
        $start = $month->startOfMonth();

        return $start->addDays(min($day, $start->daysInMonth) - 1);
    }

    /**
     * How travellers rated this provider's experiences.
     *
     * Reviews are written about an experience, so this is only answerable for
     * providers who host one. A transport supplier or a regional partner has
     * nothing to average and gets null.
     */
    private function rating(ServiceProvider $provider): ?array
    {
        $reviews = Review::whereIn(
            'experience_id',
            Experience::where('hlh_id', $provider->id)
                ->orWhere('owner_provider_id', $provider->id)
                ->select('id'),
        );

        $count = (clone $reviews)->count();
        if ($count === 0) {
            return null;
        }

        return [
            'value' => round((float) $reviews->avg('rating'), 1),
            'count' => $count,
        ];
    }
}
