<?php

use App\Http\Controllers\AlertController;
use Illuminate\Support\Facades\Route;

Route::post('/alerts', [AlertController::class, 'ingest']);
Route::get('/alerts', [AlertController::class, 'index']);
