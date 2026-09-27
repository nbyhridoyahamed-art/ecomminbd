"use client";

import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";

export interface BdLocation {
  id: number;
  name_en: string;
  name_bn: string;
  code: string;
}

export function useDivisions() {
  return useQuery({
    queryKey: ["locations", "divisions"],
    queryFn: () => api.get<BdLocation[]>("/locations/divisions"),
  });
}

export function useDistricts(divisionId: number | null | undefined) {
  return useQuery({
    queryKey: ["locations", "districts", divisionId],
    queryFn: () => api.get<BdLocation[]>(`/locations/districts?division_id=${divisionId}`),
    enabled: Boolean(divisionId),
  });
}

export function useUpazilas(districtId: number | null | undefined) {
  return useQuery({
    queryKey: ["locations", "upazilas", districtId],
    queryFn: () => api.get<BdLocation[]>(`/locations/upazilas?district_id=${districtId}`),
    enabled: Boolean(districtId),
  });
}
