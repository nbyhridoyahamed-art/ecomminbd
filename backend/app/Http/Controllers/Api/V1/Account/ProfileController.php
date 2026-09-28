<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ProfileRequest;
use App\Http\Resources\CustomerResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function update(ProfileRequest $request): JsonResponse
    {
        $customer = Auth::user();
        $customer->update($request->validated());

        return ApiResponse::success(new CustomerResource($customer), 'Profile updated successfully.');
    }
}
