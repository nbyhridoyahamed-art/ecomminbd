import Link from "next/link";
import { Newspaper } from "lucide-react";

import type { StorefrontBlogPostSummary } from "@/types/storefront";

export function BlogPostCard({ post }: { post: StorefrontBlogPostSummary }) {
  return (
    <Link
      href={`/blog/${post.slug}`}
      className="group flex flex-col overflow-hidden rounded-lg border border-border bg-surface transition-shadow hover:shadow-md"
    >
      <div className="relative aspect-[16/9] w-full overflow-hidden bg-border/20">
        {post.featured_image_url ? (
          // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
          <img
            src={post.featured_image_url}
            alt={post.title}
            className="size-full object-cover transition-transform group-hover:scale-105"
          />
        ) : (
          <div className="flex size-full items-center justify-center">
            <Newspaper className="size-10 text-text-muted" />
          </div>
        )}
      </div>

      <div className="flex flex-1 flex-col gap-2 p-4">
        {post.category ? <p className="text-xs font-semibold uppercase text-primary">{post.category.name}</p> : null}
        <p className="line-clamp-2 font-medium text-text-primary">{post.title}</p>
        {post.excerpt ? <p className="line-clamp-2 text-sm text-text-secondary">{post.excerpt}</p> : null}
        <div className="mt-auto flex items-center gap-2 text-xs text-text-muted">
          {post.published_at ? <span>{new Date(post.published_at).toLocaleDateString()}</span> : null}
          <span>&middot;</span>
          <span>{post.reading_time_minutes} min read</span>
        </div>
      </div>
    </Link>
  );
}
