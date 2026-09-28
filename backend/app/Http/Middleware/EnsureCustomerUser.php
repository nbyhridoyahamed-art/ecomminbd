<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The customer-side mirror of EnsureStaffUser — rejects a staff token on
 * an account/* route the same explicit way, rather than letting it
 * through just because auth:sanctum found some valid token.
 */
class EnsureCustomerUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof Customer) {
            return ApiResponse::error('Unauthenticated.', [], 401);
        }

        return $next($request);
    }
}
