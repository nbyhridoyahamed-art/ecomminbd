<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(private readonly RegisterUserAction $registerUserAction) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerUserAction->execute($request->validated());

        $token = $user->createToken('api')->plainTextToken;

        return ApiResponse::success(
            ['user' => new UserResource($user->load('roles')), 'token' => $token],
            'Registration successful.',
            status: 201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Auth::getProvider()->validateCredentials($user, $request->only('password'))) {
            return ApiResponse::error('The provided credentials are incorrect.', [
                'email' => ['The provided credentials are incorrect.'],
            ], 422);
        }

        if ($user->status !== 'active') {
            return ApiResponse::error('Your account is not active. Please contact support.', [], 403);
        }

        $token = $user->createToken('api')->plainTextToken;

        return ApiResponse::success(
            ['user' => new UserResource($user->load('roles')), 'token' => $token],
            'Login successful.',
        );
    }

    public function logout(): JsonResponse
    {
        $user = Auth::user();
        $user?->currentAccessToken()?->delete();

        return ApiResponse::success(message: 'Logged out successfully.');
    }

    public function me(): JsonResponse
    {
        $user = Auth::user()->load(['roles', 'currentStore']);

        return ApiResponse::success(new UserResource($user), 'Current user fetched successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return ApiResponse::error('Unable to send the reset link.', [
                'email' => [__($status)],
            ], 422);
        }

        return ApiResponse::success(message: 'Password reset link sent to your email.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error('Unable to reset the password.', [
                'email' => [__($status)],
            ], 422);
        }

        return ApiResponse::success(message: 'Password reset successfully.');
    }
}
