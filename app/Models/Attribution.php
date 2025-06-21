<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attribution extends Model
{
    protected $table = 'attributions';
    protected $guarded = [
        'id'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];
}
