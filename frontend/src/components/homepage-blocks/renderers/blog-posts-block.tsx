import Link from "next/link";

import type { LimitOnlySettings } from "@/types/homepage-block";
import type { StorefrontBlogPostSummary } from "@/types/storefront";

export function BlogPostsBlock({ settings, posts }: { settings: LimitOnlySettings; posts: StorefrontBlogPostSummary[] }) {
  if (posts.length === 0) return null;

  return (
    <section className="space-y-4">
      <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2>
      <div className="grid grid-cols-1 gap-4 tablet:grid-cols-2 desktop:grid-cols-3">
        {posts.map((post) => (
          // /blog/[slug] doesn't exist yet — a future phase owns the real blog;
          // this is an intentional placeholder link, not something to fix here.
          <Link
            key={post.id}
            href={`/blog/${post.slug}`}
            className="group flex flex-col overflow-hidden rounded-lg border border-border bg-surface transition-shadow hover:shadow-md"
          >
            {post.featured_image_url ? (
              <div className="aspect-video w-full overflow-hidden bg-border/20">
                {/* eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset */}
                <img
                  src={post.featured_image_url}
                  alt={post.title}
                  className="size-full object-cover transition-transform group-hover:scale-105"
                />
              </div>
            ) : null}
            <div className="flex flex-1 flex-col gap-2 p-4">
              <p className="font-medium text-text-primary">{post.title}</p>
              {post.excerpt ? <p className="line-clamp-3 text-sm text-text-secondary">{post.excerpt}</p> : null}
              {post.published_at ? <p className="mt-auto text-xs text-text-muted">{new Date(post.published_at).toLocaleDateString()}</p> : null}
            </div>
          </Link>
        ))}
      </div>
    </section>
  );
}
