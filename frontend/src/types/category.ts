import type { Seo } from "@/types/seo";

export interface Category {
  id: number;
  uuid: string;
  store_id: number;
  parent_id: number | null;
  name: string;
  slug: string;
  description: string | null;
  image_path: string | null;
  image_url: string | null;
  sort_order: number;
  status: "active" | "inactive";
  seo: Seo | null;
  products_count?: number;
  created_at: string;
  updated_at: string;
}

export interface CategoryTreeNode extends Category {
  children: CategoryTreeNode[];
  depth: number;
}

export function buildCategoryTree(categories: Category[]): CategoryTreeNode[] {
  const byParent = new Map<number | null, Category[]>();
  for (const category of categories) {
    const key = category.parent_id;
    if (!byParent.has(key)) byParent.set(key, []);
    byParent.get(key)!.push(category);
  }

  function build(parentId: number | null, depth: number): CategoryTreeNode[] {
    return (byParent.get(parentId) ?? []).map((category) => ({
      ...category,
      depth,
      children: build(category.id, depth + 1),
    }));
  }

  return build(null, 0);
}

export function flattenCategoryTree(nodes: CategoryTreeNode[]): CategoryTreeNode[] {
  return nodes.flatMap((node) => [node, ...flattenCategoryTree(node.children)]);
}
