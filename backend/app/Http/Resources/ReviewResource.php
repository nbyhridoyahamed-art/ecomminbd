<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared by the admin moderation queue, the customer's own /account/reviews,
 * and the public storefront listing — which of those it's used from decides
 * what's visible by scoping the *query* (approved-only for the storefront,
 * own-customer-only for the account endpoint), not by hiding fields here.
 */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer->name),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
