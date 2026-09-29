"use client";

import { useState } from "react";
import { MessageSquare, Star } from "lucide-react";

import { useCustomerAuthToken } from "@/lib/customer-auth-token";
import { useCurrentCustomer } from "@/hooks/use-customer-auth";
import { useAccountReviews, useStorefrontProductReviews } from "@/hooks/use-reviews";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { WriteReviewDialog } from "@/components/reviews/write-review-dialog";
import type { StorefrontProductDetail } from "@/types/storefront";

function Stars({ value, size = "size-4" }: { value: number; size?: string }) {
  return (
    <div className="flex gap-0.5" aria-hidden="true">
      {[1, 2, 3, 4, 5].map((n) => (
        <Star key={n} className={`${size} ${n <= Math.round(value) ? "fill-warning text-warning" : "text-border"}`} />
      ))}
    </div>
  );
}

export function ProductReviews({ product }: { product: StorefrontProductDetail }) {
  const [page, setPage] = useState(1);
  const token = useCustomerAuthToken();
  const { data: customer } = useCurrentCustomer();
  const { data: myReviews } = useAccountReviews();
  const { data, isLoading } = useStorefrontProductReviews(product.slug, page);

  const alreadyReviewed = myReviews?.some((review) => review.product?.id === product.id) ?? false;
  const reviews = data?.data ?? [];

  return (
    <section id="reviews" className="space-y-4 border-t border-border pt-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="space-y-1">
          <h2 className="text-section font-semibold text-text-primary">Reviews</h2>
          {product.reviews_count > 0 ? (
            <div className="flex items-center gap-2">
              <Stars value={product.average_rating ?? 0} />
              <span className="text-sm text-text-secondary">
                {product.average_rating?.toFixed(1)} out of 5 ({product.reviews_count} review
                {product.reviews_count === 1 ? "" : "s"})
              </span>
            </div>
          ) : (
            <p className="text-sm text-text-secondary">No reviews yet.</p>
          )}
        </div>

        {token && customer && !alreadyReviewed ? (
          <WriteReviewDialog
            productId={product.id}
            productName={product.name}
            trigger={<Button variant="outline">Write a review</Button>}
          />
        ) : null}
      </div>

      {isLoading ? (
        <div className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-20 w-full" />
          ))}
        </div>
      ) : reviews.length === 0 ? (
        <EmptyState
          icon={<MessageSquare />}
          title="No reviews yet"
          description="Be the first to share your experience with this product."
        />
      ) : (
        <div className="space-y-4">
          {reviews.map((review) => (
            <div key={review.id} className="space-y-1 border-b border-border pb-4 last:border-0">
              <div className="flex items-center gap-2">
                <Stars value={review.rating} />
                {review.title ? <p className="font-medium text-text-primary">{review.title}</p> : null}
              </div>
              <p className="text-sm text-text-secondary">{review.body}</p>
              <p className="text-xs text-text-muted">
                {review.customer_name ?? "Verified buyer"} · {new Date(review.created_at).toLocaleDateString()}
              </p>
            </div>
          ))}
        </div>
      )}

      {data?.meta && data.meta.last_page > 1 ? (
        <div className="flex justify-center gap-2">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            disabled={page >= data.meta.last_page}
            onClick={() => setPage((p) => p + 1)}
          >
            Next
          </Button>
        </div>
      ) : null}
    </section>
  );
}
