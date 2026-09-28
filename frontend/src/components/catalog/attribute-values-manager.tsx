"use client";

import { useState } from "react";
import { Check, Pencil, Plus, Trash2, X } from "lucide-react";

import { slugify } from "@/lib/slugify";
import {
  useCreateAttributeValue,
  useDeleteAttributeValue,
  useUpdateAttributeValue,
} from "@/hooks/use-attributes";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import type { ProductAttributeValue } from "@/types/attribute";

interface AttributeValuesManagerProps {
  attributeId: number;
  values: ProductAttributeValue[];
  canEdit: boolean;
}

export function AttributeValuesManager({ attributeId, values, canEdit }: AttributeValuesManagerProps) {
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editValue, setEditValue] = useState("");
  const [newValue, setNewValue] = useState("");
  const [valueToDelete, setValueToDelete] = useState<ProductAttributeValue | null>(null);

  const createValue = useCreateAttributeValue(attributeId);
  const updateValue = useUpdateAttributeValue(attributeId);
  const deleteValue = useDeleteAttributeValue(attributeId);

  const startEdit = (value: ProductAttributeValue) => {
    setEditingId(value.id);
    setEditValue(value.value);
  };

  const saveEdit = (valueId: number) => {
    if (!editValue.trim()) return;
    updateValue.mutate(
      { valueId, value: editValue, slug: slugify(editValue) },
      { onSuccess: () => setEditingId(null) },
    );
  };

  const addValue = () => {
    if (!newValue.trim()) return;
    createValue.mutate({ value: newValue, slug: slugify(newValue) }, { onSuccess: () => setNewValue("") });
  };

  return (
    <div className="space-y-2">
      {values.length === 0 ? <p className="text-sm text-text-muted">No values yet.</p> : null}

      <ul className="space-y-1">
        {values.map((value) => (
          <li key={value.id} className="flex items-center gap-2 rounded-md border border-border px-3 py-2">
            {editingId === value.id ? (
              <>
                <Input
                  value={editValue}
                  onChange={(e) => setEditValue(e.target.value)}
                  className="h-8 flex-1"
                  autoFocus
                />
                <Button size="icon" variant="ghost" onClick={() => saveEdit(value.id)} aria-label="Save">
                  <Check className="text-success" />
                </Button>
                <Button size="icon" variant="ghost" onClick={() => setEditingId(null)} aria-label="Cancel">
                  <X />
                </Button>
              </>
            ) : (
              <>
                <span className="flex-1 text-sm text-text-primary">{value.value}</span>
                <span className="text-xs text-text-muted">{value.slug}</span>
                {canEdit ? (
                  <>
                    <Button size="icon" variant="ghost" onClick={() => startEdit(value)} aria-label={`Edit ${value.value}`}>
                      <Pencil />
                    </Button>
                    <Button
                      size="icon"
                      variant="ghost"
                      onClick={() => setValueToDelete(value)}
                      aria-label={`Delete ${value.value}`}
                    >
                      <Trash2 className="text-danger" />
                    </Button>
                  </>
                ) : null}
              </>
            )}
          </li>
        ))}
      </ul>

      {canEdit ? (
        <div className="flex items-center gap-2 pt-1">
          <Input
            value={newValue}
            onChange={(e) => setNewValue(e.target.value)}
            placeholder="Add a value, e.g. Red"
            className="h-9 flex-1"
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                addValue();
              }
            }}
          />
          <Button size="sm" onClick={addValue} loading={createValue.isPending}>
            <Plus />
            Add
          </Button>
        </div>
      ) : null}

      {valueToDelete ? (
        <div className="flex items-center justify-between rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm">
          <span>Delete &ldquo;{valueToDelete.value}&rdquo;? This can&apos;t be undone.</span>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" onClick={() => setValueToDelete(null)}>
              Cancel
            </Button>
            <Button
              size="sm"
              variant="destructive"
              loading={deleteValue.isPending}
              onClick={() => deleteValue.mutate(valueToDelete.id, { onSuccess: () => setValueToDelete(null) })}
            >
              Delete
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
