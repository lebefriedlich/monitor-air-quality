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

    public function iaqi()
    {
        return $this->hasMany(IAQI::class, 'region_id', 'id');
    }

    public function predictIaqi()
    {
        return $this->hasMany(PredictIAQI::class, 'region_id', 'id');
    }

    public function latestIaqi()
    {
        return $this->hasOne(IAQI::class)->latest('observed_at');
    }
}
