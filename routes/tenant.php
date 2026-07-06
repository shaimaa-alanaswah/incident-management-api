<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\EscalationPolicyController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\NotificationLogController;
use App\Http\Controllers\OnCallScheduleController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

Route::post('/alerts', [AlertController::class, 'ingest']);
Route::get('/alerts', [AlertController::class, 'index']);

Route::get('/incidents', [IncidentController::class, 'index']);
Route::get('/incidents/{incident}', [IncidentController::class, 'show']);
Route::patch('/incidents/{incident}/acknowledge', [IncidentController::class, 'acknowledge']);
Route::patch('/incidents/{incident}/resolve', [IncidentController::class, 'resolve']);
Route::patch('/incidents/{incident}/close', [IncidentController::class, 'close']);
Route::get('/incidents/{incident}/logs', [IncidentController::class, 'logs']);
Route::patch('/incidents/{incident}/assign-policy', [IncidentController::class, 'assignPolicy']);

Route::get('/escalation-policies', [EscalationPolicyController::class, 'index']);
Route::post('/escalation-policies', [EscalationPolicyController::class, 'store']);
Route::get('/escalation-policies/{policy}', [EscalationPolicyController::class, 'show']);
Route::put('/escalation-policies/{policy}', [EscalationPolicyController::class, 'update']);
Route::delete('/escalation-policies/{policy}', [EscalationPolicyController::class, 'destroy']);

Route::get('/schedules', [OnCallScheduleController::class, 'index']);
Route::post('/schedules', [OnCallScheduleController::class, 'store']);
Route::get('/schedules/oncall', [OnCallScheduleController::class, 'currentOnCall']);
Route::delete('/schedules/{schedule}', [OnCallScheduleController::class, 'destroy']);

Route::get('/notifications', [NotificationLogController::class, 'index']);

Route::get('/stats/overview', [StatsController::class, 'overview']);
Route::get('/stats/volume', [StatsController::class, 'volume']);
