<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PredictIAQI extends Model
{
    protected $table = 'predict_iaqi';

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'datetime',
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

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
