<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripRegion extends Model
{
    protected $fillable = ['trip_id', 'region_id', 'hrp_id', 'added_by'];

    /** Chosen by HCT, not read off an experience; the region sync keeps it. */
    public const ADDED_BY_HCT = 'hct';

    /** Read off the trip's experiences; goes when they do. */
    public const ADDED_BY_SYSTEM = 'system';

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function hrp()
    {
        return $this->belongsTo(ServiceProvider::class, 'hrp_id');
    }
}
