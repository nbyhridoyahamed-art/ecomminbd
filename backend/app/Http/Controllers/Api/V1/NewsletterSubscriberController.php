<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Only exists because the Newsletter homepage block needs somewhere to put
 * its captures — same "direct permission check, no dedicated policy"
 * pattern UploadController/stock-adjustments already use for a resource
 * that isn't really its own manageable entity.
 */
class NewsletterSubscriberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('builder.view')) {
            throw new AuthorizationException;
        }

        $subscribers = NewsletterSubscriber::query()
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->latest('subscribed_at')
            ->get();

        return ApiResponse::success(NewsletterSubscriberResource::collection($subscribers), 'Newsletter subscribers fetched successfully.');
    }

    public function destroy(Request $request, NewsletterSubscriber $newsletterSubscriber): JsonResponse
    {
        if (! $request->user()->can('builder.edit')) {
            throw new AuthorizationException;
        }

        $newsletterSubscriber->delete();

        return ApiResponse::success(message: 'Subscriber removed successfully.');
    }
}
