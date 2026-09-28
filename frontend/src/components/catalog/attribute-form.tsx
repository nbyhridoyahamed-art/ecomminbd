"use client";

import { useRef } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { slugify } from "@/lib/slugify";
import type { AttributeFormValues } from "@/hooks/use-attributes";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { ProductAttribute } from "@/types/attribute";

const attributeSchema = z.object({
  name: z.string().min(1, "Name is required."),
  slug: z
    .string()
    .min(1, "Slug is required.")
    .regex(/^[a-z0-9-]+$/, "Slug may only contain lowercase letters, numbers, and hyphens."),
});

type FormValues = z.infer<typeof attributeSchema>;

interface AttributeFormProps {
  storeId: number;
  defaultValues?: ProductAttribute;
  onSubmit: (values: AttributeFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function AttributeForm({ storeId, defaultValues, onSubmit, isPending, serverError, submitLabel }: AttributeFormProps) {
  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(attributeSchema),
    defaultValues: { name: defaultValues?.name ?? "", slug: defaultValues?.slug ?? "" },
  });

  const slugTouched = useRef(Boolean(defaultValues?.slug));
  const nameField = register("name");
  const slugField = register("slug");

  const submit = handleSubmit((values) => {
    onSubmit({ store_id: storeId, name: values.name, slug: values.slug });
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
          <Input
            id="name"
            placeholder="e.g. Color"
            error={Boolean(errors.name)}
            {...nameField}
            onChange={(event) => {
              nameField.onChange(event);
              if (!slugTouched.current) {
                setValue("slug", slugify(event.target.value), { shouldValidate: true });
              }
            }}
          />
          {errors.name ? <p className="text-xs text-danger">{errors.name.message}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="slug">Slug</Label>
          <Input
            id="slug"
            error={Boolean(errors.slug)}
            {...slugField}
            onChange={(event) => {
              slugTouched.current = true;
              slugField.onChange(event);
            }}
          />
          {errors.slug ? <p className="text-xs text-danger">{errors.slug.message}</p> : null}
        </div>
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
