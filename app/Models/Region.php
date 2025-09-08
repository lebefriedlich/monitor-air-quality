<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Region extends Model
{
    protected $table = 'regions';
    protected $guarded = [
        'id'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];
    protected $keyType = 'string';
    public $incrementing = false;
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = Str::uuid();
        });
    }

    public function aqi()
    {
        return $this->hasMany(AQI::class, 'region_id', 'id');
    }

    public function predictaqi()
    {
        return $this->hasMany(PredictAQI::class, 'region_id', 'id');
    }

    public function latestAqi()
    {
        return $this->hasOne(AQI::class)->latest('observed_at');
    }
}
