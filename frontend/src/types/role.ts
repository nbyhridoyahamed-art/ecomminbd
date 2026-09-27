export interface Role {
  id: number;
  name: string;
  permissions: string[];
  created_at: string;
}

/** Grouped by the part before the first dot, e.g. "products.view" -> "products". */
export function groupPermissions(permissions: string[]): Record<string, string[]> {
  return permissions.reduce<Record<string, string[]>>((groups, permission) => {
    const [group] = permission.split(".");
    groups[group] ??= [];
    groups[group].push(permission);
    return groups;
  }, {});
}
