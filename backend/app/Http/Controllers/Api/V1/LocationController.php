<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\BdUpazila;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function divisions(): JsonResponse
    {
        return ApiResponse::success(
            BdDivision::query()->orderBy('name_en')->get(['id', 'name_en', 'name_bn', 'code']),
            'Divisions fetched successfully.',
        );
    }

    public function districts(Request $request): JsonResponse
    {
        $districts = BdDistrict::query()
            ->when($request->filled('division_id'), fn ($query) => $query->where('bd_division_id', $request->integer('division_id')))
            ->orderBy('name_en')
            ->get(['id', 'bd_division_id', 'name_en', 'name_bn', 'code']);

        return ApiResponse::success($districts, 'Districts fetched successfully.');
    }

    public function upazilas(Request $request): JsonResponse
    {
        $upazilas = BdUpazila::query()
            ->when($request->filled('district_id'), fn ($query) => $query->where('bd_district_id', $request->integer('district_id')))
            ->orderBy('name_en')
            ->get(['id', 'bd_district_id', 'name_en', 'name_bn', 'code']);

        return ApiResponse::success($upazilas, 'Upazilas fetched successfully.');
    }
}
