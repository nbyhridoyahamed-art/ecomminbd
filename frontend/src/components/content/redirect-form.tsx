"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { RedirectFormValues } from "@/hooks/use-redirects";
import type { Redirect } from "@/types/redirect";

const STATUS_CODE_OPTIONS = [
  { value: "301", label: "301 (Permanent)" },
  { value: "302", label: "302 (Temporary)" },
  { value: "307", label: "307 (Temporary, method preserved)" },
  { value: "308", label: "308 (Permanent, method preserved)" },
] as const;

const STATUS_CODE_LABELS: Record<string, string> = Object.fromEntries(
  STATUS_CODE_OPTIONS.map((option) => [option.value, option.label]),
);

const redirectSchema = z.object({
  from_path: z
    .string()
    .min(1, "From path is required.")
    .regex(/^\//, "Must start with /"),
  to_path: z.string().min(1, "To path is required."),
  status_code: z.enum(["301", "302", "307", "308"]),
});

type FormValues = z.infer<typeof redirectSchema>;

interface RedirectFormProps {
  storeId: number;
  defaultValues?: Redirect;
  onSubmit: (values: RedirectFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function RedirectForm({
  storeId,
  defaultValues,
  onSubmit,
  isPending,
  serverError,
  submitLabel,
}: RedirectFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(redirectSchema),
    defaultValues: {
      from_path: defaultValues?.from_path ?? "",
      to_path: defaultValues?.to_path ?? "",
      status_code: defaultValues ? (String(defaultValues.status_code) as FormValues["status_code"]) : "301",
    },
  });

  const statusCode = useWatch({ control, name: "status_code" });

  const submit = handleSubmit((values) => {
    onSubmit({
      store_id: storeId,
      from_path: values.from_path,
      to_path: values.to_path,
      status_code: Number(values.status_code),
    });
  });

  return (
    <form onSubmit={submit} className="space-y-4">
      {serverError ? (
        <Alert variant="danger">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}

      <div className="space-y-1.5">
        <Label htmlFor="from_path">From path</Label>
        <Input
          id="from_path"
          placeholder="/old-page"
          error={Boolean(errors.from_path)}
          {...register("from_path")}
        />
        {errors.from_path ? <p className="text-xs text-danger">{errors.from_path.message}</p> : null}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="to_path">To path</Label>
        <Input
          id="to_path"
          placeholder="/new-page or https://example.com/..."
          error={Boolean(errors.to_path)}
          {...register("to_path")}
        />
        {errors.to_path ? <p className="text-xs text-danger">{errors.to_path.message}</p> : null}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="redirect-form-status-code">Status code</Label>
        <Select
          value={statusCode}
          onValueChange={(value) => setValue("status_code", value as FormValues["status_code"], { shouldDirty: true })}
        >
          <SelectTrigger id="redirect-form-status-code" className="max-w-xs">
            <SelectValue placeholder="Select status code">
              {statusCode ? STATUS_CODE_LABELS[statusCode] : undefined}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            {STATUS_CODE_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
