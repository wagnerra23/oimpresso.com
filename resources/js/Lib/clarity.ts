// @memcofre
//   util: clarity
//   adrs: ADR 0191 (Microsoft Clarity session replay LGPD-compliant)
//   nota: helpers opcionais pra interagir com window.clarity em runtime.
//         O snippet em si vem do Blade (resources/views/layouts/partials/clarity.blade.php)
//         pra carregar cedo e evitar race com hydration React.

import { hasConsent } from './consent';

declare global {
  interface Window {
    clarity?: (...args: unknown[]) => void;
  }
}

/**
 * INTENCIONAL no-op. NÃO chamar clarity('identify', user_id) — ADR 0191
 * §pegadinha 2: passar user_id pra Microsoft + cruzando com IP permite
 * re-identificação. Mantemos sessões pseudoanônimas e filtramos via custom
 * tags (business_id, user_type) já setadas no Blade snippet.
 */
export function clarityIdentify(_userId: string): void {
  if (typeof console !== 'undefined') {
    // eslint-disable-next-line no-console
    console.warn('clarityIdentify is intentionally a no-op. See ADR 0191.');
  }
}

/**
 * Custom event pro Clarity dashboard. Útil pra marcar momentos relevantes
 * (ex.: "venda_concluida", "erro_pagamento"). Respeita opt-in analytics.
 */
export function clarityEvent(name: string, data?: Record<string, unknown>): void {
  if (typeof window === 'undefined' || !window.clarity) return;
  if (!hasConsent('analytics')) return;
  window.clarity('event', name, data);
}

/**
 * Força upgrade da sessão atual pra session replay prioritário no Clarity
 * (sessão será sampled-up mesmo que aleatoriamente caísse fora). Útil pra
 * capturar fluxos críticos (erro fatal, abandono carrinho).
 */
export function clarityUpgradeSession(reason: string): void {
  if (typeof window === 'undefined' || !window.clarity) return;
  if (!hasConsent('analytics')) return;
  window.clarity('upgrade', reason);
}
