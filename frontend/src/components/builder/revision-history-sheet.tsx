"use client";

import { History } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import type { EditableBlockSource } from "@/hooks/use-editable-homepage-block";
import { useHomepageBlockRevisions, useRestoreHomepageBlockRevision } from "@/hooks/use-homepage-blocks";
import { timeAgo } from "@/lib/utils";

interface RevisionHistorySheetProps {
  blockId: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  canEdit: boolean;
  /** Re-syncs the open BuilderPanel's local draft — restoring changes this block's content without changing its id, so the panel's own block.id-keyed reset never fires on its own. */
  onRestored: (block: EditableBlockSource) => void;
}

/**
 * The durable, server-side "already-committed change" history (spec
 * section 62) — a snapshot is taken before every settings/publish/
 * unpublish/restore mutation. Deliberately separate from BuilderPanel's
 * local undo/redo, which only steps through this editing session's
 * not-yet-saved keystrokes; see useEditableHomepageBlock's own comment
 * for why the two aren't merged into one mechanism.
 */
export function RevisionHistorySheet({ blockId, open, onOpenChange, canEdit, onRestored }: RevisionHistorySheetProps) {
  const { data: revisions, isLoading } = useHomepageBlockRevisions(open ? blockId : null);
  const restore = useRestoreHomepageBlockRevision(blockId);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full max-w-sm">
        <SheetHeader>
          <SheetTitle>Revision history</SheetTitle>
        </SheetHeader>

        <div className="flex-1 space-y-2 overflow-y-auto">
          {isLoading ? (
            <>
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-16 w-full" />
            </>
          ) : !revisions || revisions.length === 0 ? (
            <p className="p-2 text-sm text-text-muted">No revisions yet — one is saved every time this block changes.</p>
          ) : (
            revisions.map((revision, index) => (
              <div key={revision.id} className="flex items-start justify-between gap-3 rounded-md border border-border p-3">
                <div>
                  <p className="text-sm font-medium text-text-primary" title={new Date(revision.created_at).toLocaleString()}>
                    {index === 0 ? "Most recent" : timeAgo(revision.created_at)}
                  </p>
                  <p className="text-xs text-text-muted">
                    {revision.created_by ?? "Unknown"} · {new Date(revision.created_at).toLocaleString()}
                  </p>
                </div>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={!canEdit}
                  loading={restore.isPending}
                  onClick={() =>
                    restore.mutate(revision.id, {
                      onSuccess: (restored) => {
                        onRestored(restored as unknown as EditableBlockSource);
                        onOpenChange(false);
                      },
                    })
                  }
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
