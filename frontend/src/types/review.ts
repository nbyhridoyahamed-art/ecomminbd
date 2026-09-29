export type ReviewStatus = "pending" | "approved" | "rejected";

export interface Review {
  id: number;
  uuid: string;
  rating: number;
  title: string | null;
  body: string;
  status: ReviewStatus;
  customer_name?: string;
  product?: { id: number; name: string; slug: string };
  created_at: string;
}

export interface ReviewSubmissionPayload {
  product_id: number;
  rating: number;
  title?: string | null;
  body: string;
}
