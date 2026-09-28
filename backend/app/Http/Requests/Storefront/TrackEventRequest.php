<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackEventRequest extends FormRequest
{
    /** @var list<string> */
    public const EVENT_TYPES = [
        'page_view', 'product_view', 'category_view', 'search',
        'add_to_cart', 'remove_from_cart', 'checkout_start', 'purchase',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Deliberately typed, narrow fields rather than a freeform `metadata`
     * object accepted straight from the client — the controller builds the
     * stored `metadata` itself from whichever of these apply to
     * `event_type`, and for `purchase` specifically resolves the real order
     * server-side rather than trusting a client-supplied amount (see
     * Storefront\AnalyticsController::track()).
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'max:64'],
            'event_type' => ['required', 'string', Rule::in(self::EVENT_TYPES)],
            'path' => ['nullable', 'string', 'max:500'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'query' => ['nullable', 'string', 'max:255'],
            'results_count' => ['nullable', 'integer', 'min:0'],
            'order_uuid' => ['nullable', 'string', 'max:36'],
        ];
    }
}
