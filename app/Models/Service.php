<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;

class Service extends Model
{
    protected $fillable = [
        'country_id',
        'city_id',
        'name',
        'description',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function providers()
    {
        return $this->hasMany(ServiceProvider::class);
    }
}

