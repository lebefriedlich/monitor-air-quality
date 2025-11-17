<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Index::class, 'index'])
    ->name('index');
Route::get('/{region_id}', [App\Http\Controllers\Index::class, 'show'])
    ->name('region.show');
