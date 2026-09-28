"use client";

import { useState } from "react";
import { Upload } from "lucide-react";

import { useImportProducts, type ProductImportResult } from "@/hooks/use-products";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

interface ProductImportDialogProps {
  storeId: number;
  open: boolean;
  onClose: () => void;
}

export function ProductImportDialog({ storeId, open, onClose }: ProductImportDialogProps) {
  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent>
        {open ? <ProductImportDialogBody storeId={storeId} onClose={onClose} /> : null}
      </DialogContent>
    </Dialog>
  );
}

function ProductImportDialogBody({ storeId, onClose }: { storeId: number; onClose: () => void }) {
  const [file, setFile] = useState<File | null>(null);
  const [result, setResult] = useState<ProductImportResult | null>(null);
  const importProducts = useImportProducts();

  const handleImport = () => {
    if (!file) return;

    importProducts.mutate(
      { storeId, file },
      { onSuccess: (data) => setResult(data) },
    );
  };

  const close = () => {
    setFile(null);
    setResult(null);
    onClose();
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>Import products</DialogTitle>
        <DialogDescription>
          Creates a new simple product for each new SKU, or updates an existing product&apos;s own fields when
          the SKU already exists. Uses the same columns as Export — download that first to see the exact
          format, or to get a starting file to edit.
        </DialogDescription>
      </DialogHeader>

      {result ? (
        <div className="space-y-3">
          <div className="grid grid-cols-3 gap-3 text-center text-sm">
            <div className="rounded-lg border border-border p-3">
              <p className="text-lg font-semibold text-success">{result.created}</p>
              <p className="text-text-muted">Created</p>
            </div>
            <div className="rounded-lg border border-border p-3">
              <p className="text-lg font-semibold text-info">{result.updated}</p>
              <p className="text-text-muted">Updated</p>
            </div>
            <div className="rounded-lg border border-border p-3">
              <p className="text-lg font-semibold text-danger">{result.skipped}</p>
              <p className="text-text-muted">Skipped</p>
            </div>
          </div>
          {result.errors.length > 0 ? (
            <div className="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-border p-3 text-sm">
              {result.errors.map((error, index) => (
                <p key={index} className="text-text-primary">
                  Row {error.row}: <span className="text-danger">{error.message}</span>
                </p>
              ))}
            </div>
          ) : null}
        </div>
      ) : (
        <div className="space-y-1.5">
          <Label htmlFor="import-file">CSV file</Label>
          <Input
            id="import-file"
            type="file"
            accept=".csv,text/csv"
            onChange={(event) => setFile(event.target.files?.[0] ?? null)}
          />
          {importProducts.isError ? (
            <Alert variant="danger">
              <AlertDescription>Could not import the file.</AlertDescription>
            </Alert>
          ) : null}
        </div>
      )}

      <DialogFooter>
        <Button variant="outline" onClick={close}>
          {result ? "Close" : "Cancel"}
        </Button>
        {!result ? (
          <Button onClick={handleImport} loading={importProducts.isPending} disabled={!file}>
            <Upload />
            Import
          </Button>
        ) : null}
      </DialogFooter>
    </>
  );
}
