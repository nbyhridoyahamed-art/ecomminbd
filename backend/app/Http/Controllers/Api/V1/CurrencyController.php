<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        $currencies = Currency::query()->where('status', 'active')->orderBy('code')->get();

        return ApiResponse::success(CurrencyResource::collection($currencies), 'Currencies fetched successfully.');
    }
}
