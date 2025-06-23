<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Index::class, 'index'])
    ->name('index');
