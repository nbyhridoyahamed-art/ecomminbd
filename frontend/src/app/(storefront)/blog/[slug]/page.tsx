"use client";

import { use } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";

import { BlogPostCard } from "@/components/storefront/blog-post-card";
import { Skeleton } from "@/components/ui/skeleton";
import { useStorefrontBlogPost } from "@/hooks/use-storefront-blog";
import { ApiError } from "@/types/api";

export default function BlogPostDetailPage({ params }: PageProps<"/blog/[slug]">) {
  const { slug } = use(params);
  const { data, isLoading, error } = useStorefrontBlogPost(slug);

  if (error instanceof ApiError && error.status === 404) {
    notFound();
  }

  if (isLoading) {
    return (
      <div className="mx-auto max-w-3xl space-y-4 px-4 py-8">
        <Skeleton className="h-8 w-2/3" />
        <Skeleton className="aspect-[16/9] w-full" />
        <Skeleton className="h-40 w-full" />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center">
        <p className="text-text-secondary">Something went wrong loading this post. Please try again.</p>
      </div>
    );
  }

  const { post, related_posts: relatedPosts } = data;

  return (
    <article className="mx-auto max-w-3xl space-y-6 px-4 py-8">
      <header className="space-y-3">
        {post.category ? (
          <Link
            href={`/blog/category/${post.category.slug}`}
            className="text-xs font-semibold uppercase text-primary hover:underline"
          >
            {post.category.name}
          </Link>
        ) : null}
        <h1 className="text-page-title font-semibold text-text-primary">{post.title}</h1>
        <div className="flex flex-wrap items-center gap-2 text-sm text-text-muted">
          {post.published_at ? <span>{new Date(post.published_at).toLocaleDateString()}</span> : null}
          <span>&middot;</span>
          <span>{post.reading_time_minutes} min read</span>
        </div>
        {post.tags.length > 0 ? (
          <div className="flex flex-wrap gap-2">
            {post.tags.map((tag) => (
              <Link
                key={tag.slug}
                href={`/blog/tag/${tag.slug}`}
                className="rounded-full border border-border px-3 py-1 text-xs text-text-secondary hover:border-primary hover:text-primary"
              >
                {tag.name}
              </Link>
            ))}
          </div>
        ) : null}
      </header>

      {post.featured_image_url ? (
        <div className="overflow-hidden rounded-lg bg-border/20">
          {/* eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset */}
          <img src={post.featured_image_url} alt={post.title} className="w-full object-cover" />
        </div>
      ) : null}

      {post.body ? (
        <div className="rich-text-content" dangerouslySetInnerHTML={{ __html: post.body }} />
      ) : null}

      {relatedPosts.length > 0 ? (
        <div className="space-y-4 border-t border-border pt-6">
          <h2 className="text-lg font-semibold text-text-primary">Related posts</h2>
          <div className="grid grid-cols-1 gap-4 tablet:grid-cols-3">
            {relatedPosts.map((related) => (
              <BlogPostCard key={related.id} post={related} />
            ))}
          </div>
        </div>
      ) : null}
    </article>
  );
}
