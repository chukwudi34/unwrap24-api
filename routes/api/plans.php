<?php

use App\Http\Controllers\Api\PlanController;
use Illuminate\Support\Facades\Route;

Route::get('/plans', [PlanController::class, 'index']);
