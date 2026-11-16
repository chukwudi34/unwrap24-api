<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * Public: the pricing page and checkout flow both read plans from here
     * rather than hardcoding prices, so what a visitor sees is always what
     * they'd actually be charged.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return response()->json(['data' => PlanResource::collection($plans)]);
    }
}
