const CURRENCY_SYMBOLS: Record<string, string> = {
  BDT: "৳",
};

/**
 * The only place that formats a decimal money value into a display
 * string — components read this instead of interpolating currency
 * symbols themselves, so the symbol stays data-driven (spec section 8:
 * never hard-code currency symbols).
 */
export function formatMoney(amount: number, currencyCode: string): string {
  const symbol = CURRENCY_SYMBOLS[currencyCode] ?? currencyCode;
  return `${symbol} ${amount.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
