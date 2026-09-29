"use client";

import { useState } from "react";
import { Star } from "lucide-react";

import { useSubmitReview } from "@/hooks/use-reviews";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";

function RatingInput({ value, onChange }: { value: number; onChange: (rating: number) => void }) {
  return (
    <div className="flex gap-1">
      {[1, 2, 3, 4, 5].map((n) => (
        <button key={n} type="button" aria-label={`${n} star${n === 1 ? "" : "s"}`} onClick={() => onChange(n)}>
          <Star className={`size-6 ${n <= value ? "fill-warning text-warning" : "text-border"}`} />
        </button>
      ))}
    </div>
  );
}

interface WriteReviewDialogProps {
  productId: number;
  productName?: string;
  trigger: React.ReactNode;
}

/** Reusable review-submission dialog — used from the storefront PDP and the account order-detail page. */
export function WriteReviewDialog({ productId, productName, trigger }: WriteReviewDialogProps) {
  const [open, setOpen] = useState(false);
  const [rating, setRating] = useState(5);
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const submitReview = useSubmitReview();

  const reset = () => {
    setRating(5);
    setTitle("");
    setBody("");
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (!next) reset();
      }}
    >
      <DialogTrigger asChild>{trigger}</DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{productName ? `Review ${productName}` : "Write a review"}</DialogTitle>
          <DialogDescription>
            Only customers who bought and received this product can leave a review.
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-3">
          <RatingInput value={rating} onChange={setRating} />
          <Input placeholder="Title (optional)" value={title} onChange={(event) => setTitle(event.target.value)} />
          <Textarea
            placeholder="Share your thoughts about this product..."
            value={body}
            onChange={(event) => setBody(event.target.value)}
            rows={4}
          />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => setOpen(false)}>
            Cancel
          </Button>
          <Button
            loading={submitReview.isPending}
            disabled={!body.trim()}
            onClick={() =>
              submitReview.mutate(
                { product_id: productId, rating, title: title.trim() || null, body },
                {
                  onSuccess: () => {
                    setOpen(false);
                    reset();
                  },
                },
              )
            }
          >
            Submit review
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
