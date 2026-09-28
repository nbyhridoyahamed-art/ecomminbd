<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A staff member's own notification inbox — always scoped to
 * $request->user(), never a client-supplied recipient id, so there's no
 * permission to gate beyond being an authenticated staff user (same
 * reasoning as auth/me).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 20), 100);
        $notifications = $request->user()->notifications()->paginate($perPage);

        return ApiResponse::success(
            NotificationResource::collection($notifications),
            'Notifications fetched successfully.',
            [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        );
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return ApiResponse::success(new NotificationResource($notification), 'Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return ApiResponse::success(null, 'All notifications marked as read.');
    }
}
