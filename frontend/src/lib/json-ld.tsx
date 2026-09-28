/**
 * A `<script type="application/ld+json">` embed is a common XSS vector when
 * the payload includes user-generated text (a product description, a blog
 * title) that happens to contain "</script>" — escaping "<" defeats that
 * without affecting how any JSON-LD consumer parses the payload.
 */
export function JsonLd({ data }: { data: object }) {
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(data).replace(/</g, "\\u003c") }} />;
}

export function productJsonLd(product: {
  name: string;
  description?: string | null;
  images?: string[];
  sku: string;
  price: number;
  currencyCode: string;
  inStock: boolean;
  url: string;
}) {
  return {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.name,
    description: product.description ?? undefined,
    image: product.images,
    sku: product.sku,
    offers: {
      "@type": "Offer",
      url: product.url,
      priceCurrency: product.currencyCode,
      price: product.price,
      availability: product.inStock ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
    },
  };
}

export function breadcrumbJsonLd(items: { name: string; url: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: item.url,
    })),
  };
}

export function articleJsonLd(article: {
  headline: string;
  description?: string | null;
  image?: string | null;
  datePublished?: string | null;
  authorName?: string | null;
  url: string;
}) {
  return {
    "@context": "https://schema.org",
    "@type": "Article",
    headline: article.headline,
    description: article.description ?? undefined,
    image: article.image ?? undefined,
    datePublished: article.datePublished ?? undefined,
    author: article.authorName ? { "@type": "Person", name: article.authorName } : undefined,
    mainEntityOfPage: article.url,
  };
}

export function organizationAndWebsiteJsonLd(store: { name: string; url: string }) {
  return [
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      name: store.name,
      url: store.url,
    },
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      name: store.name,
      url: store.url,
    },
  ];
}
