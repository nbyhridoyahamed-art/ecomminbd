"use client";

import Link from "next/link";
import { MessageSquare, Star } from "lucide-react";

import { useAccountReviews } from "@/hooks/use-reviews";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import type { ReviewStatus } from "@/types/review";

const STATUS_LABELS: Record<ReviewStatus, string> = {
  pending: "Pending approval",
  approved: "Published",
  rejected: "Not approved",
};

const STATUS_VARIANTS: Record<ReviewStatus, BadgeProps["variant"]> = {
  pending: "warning",
  approved: "success",
  rejected: "danger",
};

function Stars({ value }: { value: number }) {
  return (
    <div className="flex gap-0.5" aria-hidden="true">
      {[1, 2, 3, 4, 5].map((n) => (
        <Star key={n} className={`size-4 ${n <= value ? "fill-warning text-warning" : "text-border"}`} />
      ))}
    </div>
  );
}

export default function AccountReviewsPage() {
  const { data: reviews, isLoading } = useAccountReviews();

  if (isLoading) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-24 w-full" />
        <Skeleton className="h-24 w-full" />
      </div>
    );
  }

  if (!reviews || reviews.length === 0) {
    return (
      <Card>
        <CardContent className="p-4">
          <EmptyState
            icon={<MessageSquare />}
            title="No reviews yet"
            description="Reviews you write from a delivered order will show up here, along with their approval status."
          />
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-3">
      {reviews.map((review) => (
        <Card key={review.id}>
          <CardContent className="space-y-2 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              {review.product ? (
                <Link
                  href={`/products/${review.product.slug}`}
                  className="font-medium text-primary hover:underline"
                >
                  {review.product.name}
                </Link>
              ) : (
                <span className="font-medium text-text-primary">Product</span>
              )}
              <Badge variant={STATUS_VARIANTS[review.status]}>{STATUS_LABELS[review.status]}</Badge>
            </div>
            <Stars value={review.rating} />
            {review.title ? <p className="font-medium text-text-primary">{review.title}</p> : null}
            <p className="text-sm text-text-secondary">{review.body}</p>
            <p className="text-xs text-text-muted">{new Date(review.created_at).toLocaleDateString()}</p>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
