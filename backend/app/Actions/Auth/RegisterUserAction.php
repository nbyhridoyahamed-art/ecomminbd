<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    /**
     * Creates a bare platform user with no role. A Super Admin or Store
     * Owner assigns a role afterwards (see RoleController) — self-service
     * registration must never grant permissions by default.
     */
    public function execute(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            // Set explicitly rather than leaning on the migration's column
            // defaults: create() returns the in-memory model, not a fresh
            // SELECT, so these would come back null in the response.
            'status' => 'active',
            'locale' => 'en',
            'timezone' => 'Asia/Dhaka',
        ]);
    }
}
