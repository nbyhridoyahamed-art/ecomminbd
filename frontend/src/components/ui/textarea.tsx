import * as React from "react";

import { cn } from "@/lib/utils";

export interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  error?: boolean;
}

function Textarea({ className, error, ...props }: TextareaProps) {
  return (
    <textarea
      data-error={error ? "true" : undefined}
      className={cn(
        "flex min-h-24 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text-primary shadow-sm transition-colors placeholder:text-text-muted",
        "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1",
        "disabled:cursor-not-allowed disabled:opacity-50",
        "data-[error=true]:border-danger data-[error=true]:focus-visible:ring-danger",
        className,
      )}
      {...props}
    />
  );
}

export { Textarea };
