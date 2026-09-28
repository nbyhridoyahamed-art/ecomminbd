"use client";

import { use, useState } from "react";
import { notFound } from "next/navigation";
import { Newspaper } from "lucide-react";

import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { BlogPostCard } from "@/components/storefront/blog-post-card";
import { StorefrontPagination } from "@/components/storefront/storefront-pagination";
import { useStorefrontBlogCategory } from "@/hooks/use-storefront-blog";
import { ApiError } from "@/types/api";

export default function BlogCategoryPage({ params }: PageProps<"/blog/category/[slug]">) {
  const { slug } = use(params);
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useStorefrontBlogCategory(slug, page);

  if (error instanceof ApiError && error.status === 404) {
    notFound();
  }

  if (isLoading) {
    return (
      <div className="mx-auto max-w-[1100px] space-y-6 px-4 py-8">
        <Skeleton className="h-8 w-1/3" />
        <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2 desktop:grid-cols-3">
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} className="aspect-[4/3] w-full" />
          ))}
        </div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="mx-auto max-w-[1100px] px-4 py-16 text-center">
        <p className="text-text-secondary">Something went wrong loading this category. Please try again.</p>
      </div>
    );
  }

  const { category, posts } = data.data;

  return (
    <div className="mx-auto max-w-[1100px] space-y-6 px-4 py-8">
      <div className="space-y-2">
        <h1 className="text-page-title font-semibold text-text-primary">{category.name}</h1>
        {category.description ? <p className="text-text-secondary">{category.description}</p> : null}
      </div>

      {posts.length === 0 ? (
        <EmptyState icon={<Newspaper />} title="No posts in this category yet" />
      ) : (
        <>
          <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2 desktop:grid-cols-3">
            {posts.map((post) => (
              <BlogPostCard key={post.id} post={post} />
            ))}
          </div>
          <StorefrontPagination meta={data.meta} itemLabel="posts" onPageChange={setPage} />
        </>
      )}
    </div>
  );
}
