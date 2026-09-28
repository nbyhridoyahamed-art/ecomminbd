"use client";

import Link from "next/link";
import { ChevronDown } from "lucide-react";

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useStorefrontCategories } from "@/hooks/use-storefront-catalog";

export function CategoryMegaMenu() {
  const { data: categories } = useStorefrontCategories();

  if (!categories || categories.length === 0) {
    return null;
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="flex items-center gap-1 text-sm font-medium text-text-primary outline-none hover:text-primary">
        Categories
        <ChevronDown className="size-4" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-[min(90vw,720px)] p-4">
        <div className="grid grid-cols-2 gap-6 tablet:grid-cols-3 desktop:grid-cols-4">
          {categories.map((category) => (
            <div key={category.id} className="space-y-2">
              <DropdownMenuItem asChild className="p-0">
                <Link href={`/category/${category.slug}`} className="block font-semibold text-text-primary">
                  {category.name}
                </Link>
              </DropdownMenuItem>
              {category.children.length > 0 ? (
                <ul className="space-y-1.5">
                  {category.children.map((child) => (
                    <li key={child.id}>
                      <DropdownMenuItem asChild className="p-0">
                        <Link href={`/category/${child.slug}`} className="block text-text-secondary">
                          {child.name}
                        </Link>
                      </DropdownMenuItem>
                    </li>
                  ))}
                </ul>
              ) : null}
            </div>
          ))}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
