"use client";

import { Suspense, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Newspaper, Search } from "lucide-react";

import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { BlogPostCard } from "@/components/storefront/blog-post-card";
import { StorefrontPagination } from "@/components/storefront/storefront-pagination";
import { useStorefrontBlogPosts } from "@/hooks/use-storefront-blog";

function BlogGridSkeleton() {
  return (
    <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2 desktop:grid-cols-3">
      {Array.from({ length: 6 }).map((_, index) => (
        <Skeleton key={index} className="aspect-[4/3] w-full" />
      ))}
    </div>
  );
}

function BlogPageContent() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const search = searchParams.get("search") ?? "";
  const page = Number(searchParams.get("page") ?? "1");
  const [searchInput, setSearchInput] = useState(search);

  const { data, isLoading } = useStorefrontBlogPosts(page, search || undefined);
  const posts = data?.data ?? [];

  function updateParam(key: string, value: string | null) {
    const params = new URLSearchParams(searchParams.toString());
    if (value) {
      params.set(key, value);
    } else {
      params.delete(key);
    }
    if (key !== "page") {
      params.delete("page");
    }
    router.push(`/blog?${params.toString()}`);
  }

  const submitSearch = (event: React.FormEvent) => {
    event.preventDefault();
    updateParam("search", searchInput.trim() || null);
  };

  return (
    <div className="mx-auto max-w-[1100px] space-y-6 px-4 py-8">
      <div className="space-y-2">
        <h1 className="text-page-title font-semibold text-text-primary">
          {search ? `Search results for "${search}"` : "Blog"}
        </h1>
        <p className="text-text-secondary">News, guides, and updates from our team.</p>
      </div>

      <form onSubmit={submitSearch} className="flex max-w-sm gap-2">
        <Input
          type="search"
          placeholder="Search posts..."
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          aria-label="Search posts"
        />
        <Button type="submit" variant="secondary" size="icon" aria-label="Search">
          <Search className="size-4" />
        </Button>
      </form>

      {isLoading ? (
        <BlogGridSkeleton />
      ) : posts.length === 0 ? (
        <EmptyState icon={<Newspaper />} title="No posts found" description="Try a different search." />
      ) : (
        <>
          <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2 desktop:grid-cols-3">
            {posts.map((post) => (
              <BlogPostCard key={post.id} post={post} />
            ))}
          </div>
          <StorefrontPagination
            meta={data?.meta}
            itemLabel="posts"
            onPageChange={(newPage) => updateParam("page", String(newPage))}
          />
        </>
      )}
    </div>
  );
}

export default function BlogPage() {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-[1100px] px-4 py-8">
          <BlogGridSkeleton />
        </div>
      }
    >
      <BlogPageContent />
    </Suspense>
  );
}
