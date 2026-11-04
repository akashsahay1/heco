<?php

namespace App\Http\Middleware;

use App\Services\TripStatusClock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the trip statuses catch up with the calendar before a page or an API
 * answer is built, so the Trips list, the dashboard and the provider app all
 * read the same Running or Completed. See TripStatusClock.
 */
class MoveTripsByDate
{
    public function __construct(private TripStatusClock $clock)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->clock->tick();

        return $next($request);
    }
}
