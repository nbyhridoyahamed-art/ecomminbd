"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { usePermissions } from "@/hooks/use-roles";
import type { RoleFormValues } from "@/hooks/use-roles";
import { groupPermissions, type Role } from "@/types/role";

const roleSchema = z.object({
  name: z.string().min(1, "Role name is required."),
  permissions: z.array(z.string()),
});

interface RoleFormProps {
  defaultValues?: Partial<Role>;
  onSubmit: (values: RoleFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
  /** Built-in roles (Super Admin, Store Owner) keep all permissions and can't be edited. */
  locked?: boolean;
}

export function RoleForm({ defaultValues, onSubmit, isPending, serverError, submitLabel, locked }: RoleFormProps) {
  const { data: allPermissions, isLoading } = usePermissions();

  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<z.infer<typeof roleSchema>>({
    resolver: zodResolver(roleSchema),
    defaultValues: {
      name: defaultValues?.name ?? "",
      permissions: defaultValues?.permissions ?? [],
    },
  });

  const selectedPermissions = useWatch({ control, name: "permissions" });
  const groups = groupPermissions(allPermissions ?? []);

  const togglePermission = (permission: string, checked: boolean) => {
    const next = checked
      ? [...selectedPermissions, permission]
      : selectedPermissions.filter((name) => name !== permission);
    setValue("permissions", next, { shouldDirty: true });
  };

  const toggleGroup = (groupPermissions: string[], checked: boolean) => {
    const next = checked
      ? Array.from(new Set([...selectedPermissions, ...groupPermissions]))
      : selectedPermissions.filter((name) => !groupPermissions.includes(name));
    setValue("permissions", next, { shouldDirty: true });
  };

  const submit = handleSubmit((values) => onSubmit(values));

  return (
    <form onSubmit={submit} className="space-y-4">
      {serverError ? (
        <Alert variant="danger">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}

      {locked ? (
        <Alert variant="info">
          <AlertDescription>
            This is a built-in role and always has full access. It can&apos;t be renamed or
            edited.
          </AlertDescription>
        </Alert>
      ) : null}

      <div className="max-w-sm space-y-1.5">
        <Label htmlFor="name">Role name</Label>
        <Input id="name" disabled={locked} error={Boolean(errors.name)} {...register("name")} />
        {errors.name ? <p className="text-xs text-danger">{errors.name.message}</p> : null}
      </div>

      <div className="space-y-2">
        <Label>Permissions</Label>
        {isLoading ? (
          <Skeleton className="h-64 w-full" />
        ) : (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {Object.entries(groups).map(([group, permissions]) => {
              const allChecked = permissions.every((p) => selectedPermissions.includes(p));
              return (
                <div key={group} className="rounded-md border border-border p-3">
                  <label className="mb-2 flex items-center gap-2 text-sm font-medium capitalize text-text-primary">
                    <Checkbox
                      disabled={locked}
                      checked={allChecked}
                      onCheckedChange={(checked) => toggleGroup(permissions, checked === true)}
                    />
                    {group}
                  </label>
                  <div className="space-y-1 pl-6">
                    {permissions.map((permission) => (
                      <label key={permission} className="flex items-center gap-2 text-xs text-text-secondary">
                        <Checkbox
                          disabled={locked}
                          checked={selectedPermissions.includes(permission)}
                          onCheckedChange={(checked) => togglePermission(permission, checked === true)}
                        />
                        {permission.split(".")[1] ?? permission}
                      </label>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {!locked ? (
        <div className="flex justify-end">
          <Button type="submit" loading={isPending}>
            {submitLabel}
          </Button>
        </div>
      ) : null}
    </form>
  );
}
