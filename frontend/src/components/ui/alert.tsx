import * as React from "react";
import { cva, type VariantProps } from "class-variance-authority";

import { cn } from "@/lib/utils";

const alertVariants = cva(
  "relative w-full rounded-md border p-4 text-sm [&>svg]:size-4 [&>svg]:mt-0.5 flex gap-3",
  {
    variants: {
      variant: {
        info: "border-info/30 bg-info/5 text-info",
        success: "border-success/30 bg-success/5 text-success",
        warning: "border-warning/30 bg-warning/5 text-warning",
        danger: "border-danger/30 bg-danger/5 text-danger",
      },
    },
    defaultVariants: {
      variant: "info",
    },
  },
);

export interface AlertProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof alertVariants> {}

function Alert({ className, variant, ...props }: AlertProps) {
  return <div role="alert" className={cn(alertVariants({ variant, className }))} {...props} />;
}

function AlertTitle({ className, ...props }: React.ComponentProps<"p">) {
  return <p className={cn("font-medium leading-none text-text-primary", className)} {...props} />;
}

function AlertDescription({ className, ...props }: React.ComponentProps<"p">) {
  return <p className={cn("text-text-secondary", className)} {...props} />;
}

export { Alert, AlertTitle, AlertDescription };
