<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripRegion extends Model
{
    protected $fillable = ['trip_id', 'region_id', 'hrp_id'];

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
