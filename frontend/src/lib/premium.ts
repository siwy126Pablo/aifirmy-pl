export function isActivePremium(tool: { premium_listings?: { ends_at?: string | null }[] | null }): boolean {
  return tool.premium_listings?.some(p => p.ends_at && new Date(p.ends_at) > new Date()) ?? false;
}
