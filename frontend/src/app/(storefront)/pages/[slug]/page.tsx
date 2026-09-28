"use client";

import { use } from "react";
import { notFound } from "next/navigation";

import { ApiError } from "@/types/api";
import { Skeleton } from "@/components/ui/skeleton";
import { useStorefrontPage } from "@/hooks/use-storefront-pages";

export default function StorefrontPageDetail({ params }: PageProps<"/pages/[slug]">) {
  const { slug } = use(params);
  const { data: page, isLoading, error } = useStorefrontPage(slug);

  if (error instanceof ApiError && error.status === 404) {
    notFound();
  }

  if (isLoading) {
    return (
      <div className="mx-auto max-w-3xl space-y-4 px-4 py-8">
        <Skeleton className="h-8 w-1/2" />
        <Skeleton className="h-40 w-full" />
      </div>
    );
  }

  if (error || !page) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center">
        <p className="text-text-secondary">Something went wrong loading this page. Please try again.</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl space-y-4 px-4 py-8">
      <h1 className="text-page-title font-semibold text-text-primary">{page.title}</h1>
      {page.content ? <p className="whitespace-pre-line text-text-secondary">{page.content}</p> : null}
    </div>
  );
}
