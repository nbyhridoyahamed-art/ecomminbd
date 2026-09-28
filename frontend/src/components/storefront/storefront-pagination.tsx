import { Button } from "@/components/ui/button";
import type { PaginationMeta } from "@/types/api";

export function StorefrontPagination({
  meta,
  onPageChange,
}: {
  meta: PaginationMeta | undefined;
  onPageChange: (page: number) => void;
}) {
  if (!meta || meta.last_page <= 1) {
    return null;
  }

  return (
    <div className="flex items-center justify-between border-t border-border pt-4">
      <p className="text-sm text-text-secondary">
        Page {meta.current_page} of {meta.last_page} &middot; {meta.total} products
      </p>
      <div className="flex gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
        >
          Previous
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          Next
        </Button>
      </div>
    </div>
  );
}
