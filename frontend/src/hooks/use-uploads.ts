"use client";

import { useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { ApiError } from "@/types/api";

interface UploadResult {
  path: string;
  url: string;
}

export function useImageUpload(folder: "categories" | "brands") {
  return useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData();
      formData.append("image", file);
      formData.append("folder", folder);
      return api.post<UploadResult>("/uploads", formData);
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : "Could not upload image.");
    },
  });
}
