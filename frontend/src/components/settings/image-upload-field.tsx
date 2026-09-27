"use client";

import { useRef, useState } from "react";
import { ImagePlus, Loader2, X } from "lucide-react";

import { cn } from "@/lib/utils";
import { useImageUpload } from "@/hooks/use-uploads";
import { Button } from "@/components/ui/button";

interface ImageUploadFieldProps {
  imageUrl: string | null;
  onUploaded: (path: string) => void;
  onRemove: () => void;
  folder: "categories" | "brands";
  label: string;
  shape?: "square" | "circle";
}

export function ImageUploadField({
  imageUrl,
  onUploaded,
  onRemove,
  folder,
  label,
  shape = "square",
}: ImageUploadFieldProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const upload = useImageUpload(folder);

  const displayUrl = preview ?? imageUrl;

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setPreview(URL.createObjectURL(file));
    upload.mutate(file, {
      onSuccess: (result) => onUploaded(result.path),
      onError: () => setPreview(null),
    });
    event.target.value = "";
  };

  return (
    <div className="space-y-1.5">
      <p className="text-sm font-medium text-text-primary">{label}</p>
      <div className="flex items-center gap-3">
        <div
          className={cn(
            "flex size-20 shrink-0 items-center justify-center overflow-hidden border border-border bg-border/20",
            shape === "circle" ? "rounded-full" : "rounded-md",
          )}
        >
          {upload.isPending ? (
            <Loader2 className="size-5 animate-spin text-text-muted" />
          ) : displayUrl ? (
            // eslint-disable-next-line @next/next/no-img-element -- previewing an uploaded file/remote storage URL, not a static asset Next can optimize
            <img src={displayUrl} alt="" className="size-full object-cover" />
          ) : (
            <ImagePlus className="size-5 text-text-muted" />
          )}
        </div>

        <div className="flex gap-2">
          <Button type="button" variant="outline" size="sm" onClick={() => inputRef.current?.click()}>
            {displayUrl ? "Replace" : "Upload"}
          </Button>
          {displayUrl ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => {
                setPreview(null);
                onRemove();
              }}
            >
              <X />
              Remove
            </Button>
          ) : null}
        </div>

        <input
          ref={inputRef}
          type="file"
          accept="image/png,image/jpeg,image/webp,image/avif"
          className="hidden"
          onChange={handleFileChange}
        />
      </div>
    </div>
  );
}
