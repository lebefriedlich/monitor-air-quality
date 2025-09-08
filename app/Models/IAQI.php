<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IAQI extends Model
{
    protected $table = 'iaqi';

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


    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
