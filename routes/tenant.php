<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\IncidentController;
use Illuminate\Support\Facades\Route;

Route::post('/alerts', [AlertController::class, 'ingest']);
Route::get('/alerts', [AlertController::class, 'index']);

Route::get('/incidents', [IncidentController::class, 'index']);
Route::get('/incidents/{incident}', [IncidentController::class, 'show']);
Route::patch('/incidents/{incident}/acknowledge', [IncidentController::class, 'acknowledge']);
Route::patch('/incidents/{incident}/resolve', [IncidentController::class, 'resolve']);
Route::patch('/incidents/{incident}/close', [IncidentController::class, 'close']);
Route::get('/incidents/{incident}/logs', [IncidentController::class, 'logs']);
