<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorData extends Model
{
    protected $table = 'sensor_data';

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
