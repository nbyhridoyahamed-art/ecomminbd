import type { User } from "@/types/auth";

export function can(user: User | undefined, permission: string): boolean {
  return Boolean(user?.permissions?.includes(permission));
}
