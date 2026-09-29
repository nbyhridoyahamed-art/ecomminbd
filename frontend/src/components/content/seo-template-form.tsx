"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useForm, useWatch } from "react-hook-form";
import { z } from "zod";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { SeoTemplateFormValues } from "@/hooks/use-seo-templates";
import { ENTITY_TYPE_LABELS, ENTITY_TYPE_OPTIONS, type SeoTemplate } from "@/types/seo-template";

const seoTemplateSchema = z.object({
  entity_type: z.enum([
    "App\\Models\\Product",
    "App\\Models\\Category",
    "App\\Models\\Brand",
    "App\\Models\\Page",
    "App\\Models\\BlogPost",
    "App\\Models\\BlogCategory",
    "App\\Models\\BlogTag",
  ]),
  title_template: z.string(),
  description_template: z.string(),
});

type FormValues = z.infer<typeof seoTemplateSchema>;

interface SeoTemplateFormProps {
  storeId: number;
  defaultValues?: SeoTemplate;
  onSubmit: (values: SeoTemplateFormValues) => void;
  isPending: boolean;
  serverError?: string | null;
  submitLabel: string;
}

export function SeoTemplateForm({
  storeId,
  defaultValues,
  onSubmit,
  isPending,
  serverError,
  submitLabel,
}: SeoTemplateFormProps) {
  // entity_type has a per-store uniqueness constraint on the backend, so once a
  // template exists for a type it can't be repointed at another type without
  // risking a silent collision — editing renders it as fixed text instead of a Select.
  const isEditing = Boolean(defaultValues);

  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(seoTemplateSchema),
    defaultValues: {
      entity_type: (defaultValues?.entity_type as FormValues["entity_type"] | undefined) ?? ENTITY_TYPE_OPTIONS[0].value,
      title_template: defaultValues?.title_template ?? "",
      description_template: defaultValues?.description_template ?? "",
    },
  });

  const entityType = useWatch({ control, name: "entity_type" });

  const submit = handleSubmit((values) => {
    onSubmit({
      store_id: storeId,
      entity_type: values.entity_type,
      title_template: values.title_template || null,
      description_template: values.description_template || null,
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
        <Label htmlFor="seo-template-entity-type">Entity type</Label>
        {isEditing ? (
          <>
            <input type="hidden" {...register("entity_type")} />
            <p className="flex h-9 items-center rounded-md border border-border bg-surface-secondary px-3 text-sm text-text-secondary">
              {ENTITY_TYPE_LABELS[entityType] ?? entityType}
            </p>
            <p className="text-xs text-text-muted">Entity type can&apos;t be changed after creation.</p>
          </>
        ) : (
          <Select
            value={entityType}
            onValueChange={(value) => setValue("entity_type", value as FormValues["entity_type"], { shouldDirty: true })}
          >
            <SelectTrigger id="seo-template-entity-type" className="max-w-xs">
              <SelectValue placeholder="Select entity type">
                {entityType ? ENTITY_TYPE_LABELS[entityType] : undefined}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {ENTITY_TYPE_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
        {errors.entity_type ? <p className="text-xs text-danger">{errors.entity_type.message}</p> : null}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="title_template">Title template</Label>
        <Input id="title_template" placeholder="{{title}} | {{store_name}}" {...register("title_template")} />
        <p className="text-xs text-text-muted">Optional hint for how titles are generated for this content type.</p>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="description_template">Description template</Label>
        <Textarea id="description_template" rows={3} {...register("description_template")} />
        <p className="text-xs text-text-muted">Optional hint for how meta descriptions are generated for this content type.</p>
      </div>

      <div className="flex justify-end">
        <Button type="submit" loading={isPending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
