"use client";

import { useRef, useState } from "react";
import { ImagePlus, Upload } from "lucide-react";

import { useMedia, useUploadMedia } from "@/hooks/use-media";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import type { Media } from "@/types/media";

interface MediaPickerDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  storeId: number | null | undefined;
  onSelect: (media: Media) => void;
}

/** Browse the store's media library, or upload a new file, and pick one image — reusable across any entity's image field. */
export function MediaPickerDialog({ open, onOpenChange, storeId, onSelect }: MediaPickerDialogProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");

  const { data, isLoading } = useMedia(open ? storeId : null, { page, search: search || undefined });
  const upload = useUploadMedia();

  const choose = (media: Media) => {
    onSelect(media);
    onOpenChange(false);
  };

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file && storeId) {
      upload.mutate({ file, storeId }, { onSuccess: choose });
    }
    event.target.value = "";
  };

  const items = data?.data ?? [];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Choose from media library</DialogTitle>
        </DialogHeader>

        <div className="flex items-center gap-2">
          <Input
            placeholder="Search by filename..."
            value={search}
            onChange={(event) => {
              setSearch(event.target.value);
              setPage(1);
            }}
          />
          <Button
            type="button"
            variant="outline"
            onClick={() => inputRef.current?.click()}
            loading={upload.isPending}
          >
            <Upload />
            Upload new
          </Button>
          <input
            ref={inputRef}
            type="file"
            accept="image/png,image/jpeg,image/webp,image/avif"
            className="hidden"
            onChange={handleFileChange}
          />
        </div>

        <div className="max-h-96 overflow-y-auto">
          {isLoading ? (
            <div className="grid grid-cols-4 gap-3">
              {Array.from({ length: 8 }).map((_, i) => (
                <Skeleton key={i} className="aspect-square w-full rounded-md" />
              ))}
            </div>
          ) : items.length === 0 ? (
            <EmptyState
              icon={<ImagePlus />}
              title="No media yet"
              description="Upload a file above to add it to the library."
            />
          ) : (
            <div className="grid grid-cols-4 gap-3">
              {items.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => choose(item)}
                  aria-label={`Choose ${item.filename}`}
                  className="overflow-hidden rounded-md border border-border transition-colors hover:border-primary"
                >
                  {/* eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset */}
                  <img src={item.url} alt={item.alt_text ?? ""} className="aspect-square w-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </div>

        {data?.meta && data.meta.last_page > 1 ? (
          <div className="flex items-center justify-between text-sm text-text-secondary">
            <p>
              Page {data.meta.current_page} of {data.meta.last_page}
            </p>
            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={data.meta.current_page <= 1}
                onClick={() => setPage((p) => p - 1)}
              >
                Previous
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={data.meta.current_page >= data.meta.last_page}
                onClick={() => setPage((p) => p + 1)}
              >
                Next
              </Button>
            </div>
          </div>
        ) : null}
      </DialogContent>
    </Dialog>
  );
}
