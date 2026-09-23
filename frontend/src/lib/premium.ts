type PremiumTool = { premium_listings?: { ends_at?: string | null; plan?: string | null }[] | null };

export const PILOT_DISCLOSURE = 'Bezpłatne wyróżnienie w ramach programu pilotażowego aifirmy.pl — producent nie płaci za tę pozycję.';

export function activePremiumPlan(tool: PremiumTool): string | null {
  return tool.premium_listings?.find(p => p.ends_at && new Date(p.ends_at) > new Date())?.plan ?? null;
}

export function isActivePremium(tool: PremiumTool): boolean {
  return activePremiumPlan(tool) !== null;
}
