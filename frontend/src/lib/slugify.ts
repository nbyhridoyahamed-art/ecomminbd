/**
 * Converts a name into a URL-safe slug (lowercase letters, numbers, hyphens).
 * Used to auto-fill slug fields from a name while the user hasn't manually
 * edited the slug themselves.
 */
export function slugify(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}
