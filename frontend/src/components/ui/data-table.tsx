"use client";

import * as React from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import type { PaginationMeta } from "@/types/api";

export interface DataTableColumn<TData> {
  id: string;
  header: string;
  cell: (row: TData) => React.ReactNode;
  className?: string;
}

// A column with a visually-blank header (an icon-only actions column, a
// flag column, ...) still needs a name a screen reader can announce.
function humanizeColumnId(id: string): string {
  return id.replace(/[_-]/g, " ").replace(/^./, (c) => c.toUpperCase());
}

interface DataTableProps<TData> {
  columns: DataTableColumn<TData>[];
  data: TData[];
  rowKey: (row: TData) => string | number;
  isLoading?: boolean;
  meta?: PaginationMeta;
  onPageChange?: (page: number) => void;
  emptyState?: React.ReactNode;
}

export function DataTable<TData>({
  columns,
  data,
  rowKey,
  isLoading,
  meta,
  onPageChange,
  emptyState,
}: DataTableProps<TData>) {
  return (
    <div className="space-y-3">
      <div className="overflow-x-auto rounded-lg border border-border bg-surface">
        <table className="w-full text-left text-table">
          <thead className="border-b border-border">
            <tr>
              {columns.map((column) => (
                <th key={column.id} className="px-4 py-3 font-medium text-text-secondary">
                  {column.header || (
                    <span className="sr-only">{humanizeColumnId(column.id)}</span>
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <tr key={i} className="border-b border-border last:border-0">
                  {columns.map((column) => (
                    <td key={column.id} className="px-4 py-3">
                      <Skeleton className="h-4 w-full max-w-40" />
                    </td>
                  ))}
                </tr>
              ))
            ) : data.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="p-8">
                  {emptyState ?? (
                    <p className="text-center text-sm text-text-muted">No results found.</p>
                  )}
                </td>
              </tr>
            ) : (
              data.map((row) => (
                <tr key={rowKey(row)} className="border-b border-border last:border-0 hover:bg-border/20">
                  {columns.map((column) => (
                    <td key={column.id} className={`px-4 py-3 text-text-primary ${column.className ?? ""}`}>
                      {column.cell(row)}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between text-sm text-text-secondary">
          <p>
            Page {meta.current_page} of {meta.last_page} &middot; {meta.total} total
          </p>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={meta.current_page <= 1}
              onClick={() => onPageChange?.(meta.current_page - 1)}
            >
              <ChevronLeft />
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => onPageChange?.(meta.current_page + 1)}
            >
              Next
              <ChevronRight />
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
