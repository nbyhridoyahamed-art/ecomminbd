"use client";

import { History } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { useBlogPostVersions, useRestoreBlogPostVersion } from "@/hooks/use-blog-posts";
import { timeAgo } from "@/lib/utils";

interface BlogPostVersionHistorySheetProps {
  postId: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  canEdit: boolean;
}

/** A snapshot is taken before every save and every restore — same server-side history model as the homepage builder's block revisions, sized for a Save-button form instead of a live-autosave canvas. */
export function BlogPostVersionHistorySheet({ postId, open, onOpenChange, canEdit }: BlogPostVersionHistorySheetProps) {
  const { data: versions, isLoading } = useBlogPostVersions(open ? postId : null);
  const restore = useRestoreBlogPostVersion(postId);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full max-w-sm">
        <SheetHeader>
          <SheetTitle>Version history</SheetTitle>
        </SheetHeader>

        <div className="flex-1 space-y-2 overflow-y-auto">
          {isLoading ? (
            <>
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-16 w-full" />
            </>
          ) : !versions || versions.length === 0 ? (
            <p className="p-2 text-sm text-text-muted">No versions yet — one is saved every time this post is updated.</p>
          ) : (
            versions.map((version, index) => (
              <div key={version.id} className="flex items-start justify-between gap-3 rounded-md border border-border p-3">
                <div>
                  <p className="text-sm font-medium text-text-primary" title={new Date(version.created_at).toLocaleString()}>
                    {index === 0 ? "Most recent" : timeAgo(version.created_at)}
                  </p>
                  <p className="text-xs text-text-muted">{version.snapshot.title}</p>
                  <p className="text-xs text-text-muted">
                    {version.created_by ?? "Unknown"} · {new Date(version.created_at).toLocaleString()}
                  </p>
                </div>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={!canEdit}
                  loading={restore.isPending}
                  onClick={() => restore.mutate(version.id, { onSuccess: () => onOpenChange(false) })}
                >
                  <History className="size-3.5" />
                  Restore
                </Button>
              </div>
            ))
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
