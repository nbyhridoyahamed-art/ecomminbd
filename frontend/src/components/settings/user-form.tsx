"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { useRoles } from "@/hooks/use-roles";
import type { UserFormValues } from "@/hooks/use-users";
import type { User } from "@/types/auth";

const baseSchema = {
  name: z.string().min(1, "Name is required."),
  email: z.string().min(1, "Email is required.").email("Enter a valid email address."),
  phone: z.string().optional().or(z.literal("")),
  status: z.enum(["active", "suspended"]),
  roles: z.array(z.string()),
};

const createSchema = z.object({
  ...baseSchema,
  password: z.string().min(8, "Password must be at least 8 characters."),
});

const editSchema = z.object({
  ...baseSchema,
  password: z.string().min(8, "Password must be at least 8 characters.").optional().or(z.literal("")),
});

interface UserFormProps {
  mode: "create" | "edit";
  defaultValues?: Partial<User>;
  onSubmit: (values: UserFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function UserForm({ mode, defaultValues, onSubmit, isPending, serverError, submitLabel }: UserFormProps) {
  const { data: roles, isLoading: rolesLoading } = useRoles();
  const schema = mode === "create" ? createSchema : editSchema;

  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<z.infer<typeof schema>>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: defaultValues?.name ?? "",
      email: defaultValues?.email ?? "",
      phone: defaultValues?.phone ?? "",
      status: defaultValues?.status ?? "active",
      roles: defaultValues?.roles ?? [],
      password: "",
    },
  });

  const selectedRoles = useWatch({ control, name: "roles" });
  const status = useWatch({ control, name: "status" });
  const STATUS_LABELS: Record<string, string> = { active: "Active", suspended: "Suspended" };

  const toggleRole = (roleName: string, checked: boolean) => {
    const next = checked
      ? [...selectedRoles, roleName]
      : selectedRoles.filter((name) => name !== roleName);
    setValue("roles", next, { shouldDirty: true });
  };

  const submit = handleSubmit((values) => {
    onSubmit({
      name: values.name,
      email: values.email,
      phone: values.phone || null,
      password: values.password || undefined,
      status: values.status,
      roles: values.roles,
    });
  });

  return (
    <form onSubmit={submit} className="space-y-4">
      {serverError ? (
        <Alert variant="danger">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="name">Name</Label>
          <Input id="name" error={Boolean(errors.name)} {...register("name")} />
          {errors.name ? <p className="text-xs text-danger">{errors.name.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="email">Email</Label>
          <Input id="email" type="email" error={Boolean(errors.email)} {...register("email")} />
          {errors.email ? <p className="text-xs text-danger">{errors.email.message}</p> : null}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="phone">Phone (Bangladesh)</Label>
          <Input id="phone" placeholder="01712345678" {...register("phone")} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password">
            Password {mode === "edit" ? <span className="text-text-muted">(leave blank to keep current)</span> : null}
          </Label>
          <Input id="password" type="password" error={Boolean(errors.password)} {...register("password")} />
          {errors.password ? <p className="text-xs text-danger">{errors.password.message}</p> : null}
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="user-form-status">Status</Label>
        <Select
          value={status}
          onValueChange={(value) => setValue("status", value as "active" | "suspended", { shouldDirty: true })}
        >
          <SelectTrigger id="user-form-status" className="max-w-48">
            <SelectValue>{status ? STATUS_LABELS[status] : undefined}</SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="suspended">Suspended</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="space-y-2">
        <Label>Roles</Label>
        {rolesLoading ? (
          <Skeleton className="h-24 w-full" />
        ) : (
          <div className="grid grid-cols-2 gap-2 rounded-md border border-border p-3 sm:grid-cols-3">
            {(roles ?? []).map((role) => (
              <label key={role.id} className="flex items-center gap-2 text-sm text-text-primary">
                <Checkbox
                  checked={selectedRoles.includes(role.name)}
                  onCheckedChange={(checked) => toggleRole(role.name, checked === true)}
                />
                {role.name}
              </label>
            ))}
          </div>
        )}
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
