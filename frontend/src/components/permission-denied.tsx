import { ShieldAlert } from "lucide-react";

import { EmptyState } from "@/components/ui/empty-state";

export function PermissionDenied({ description }: { description?: string }) {
  return (
    <EmptyState
      icon={<ShieldAlert />}
      title="You don't have permission to view this page"
      description={
        description ?? "Ask a Super Admin or Store Owner to grant you the required role."
      }
    />
  );
}
