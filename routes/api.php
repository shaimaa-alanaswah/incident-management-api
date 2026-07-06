<?php

use App\Http\Controllers\Auth\TenantAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [TenantAuthController::class, 'register']);
