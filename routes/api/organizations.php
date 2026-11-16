<?php

use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->post('/organizations', [OrganizationController::class, 'store']);
