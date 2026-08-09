<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

    protected $fillable = [
        'temperature',
        'humidity',
        'latitude',
        'longitude',
        'speed',
        'altitude',
        'satellites',
        'heading',
        'current',
        'voltage',
        'battery_percent',
    ];
}
