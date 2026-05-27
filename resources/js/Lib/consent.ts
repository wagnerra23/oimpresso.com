// @memcofre
//   util: consent
//   adrs: ADR 0191 (Microsoft Clarity + consent banner LGPD)
//   nota: API client-side. Fonte de verdade = cookie HttpOnly + Inertia share
//         (usePage().props.consent). window.__consent é cache pra leitura
//         fora de React (scripts inline em layouts legacy).

import { router } from '@inertiajs/react';

export type ConsentCategory = 'necessary' | 'analytics' | 'marketing';

export interface ConsentSnapshot {
  needs_banner: boolean;
  analytics_accepted: boolean;
  marketing_accepted: boolean;
}

/** Lê window.__consent (sincronizado pelo ConsentBanner). Use fora de React. */
export function hasConsent(category: ConsentCategory): boolean {
  if (category === 'necessary') return true;
  if (typeof window === 'undefined') return false;
  const snap = (window as unknown as { __consent?: ConsentSnapshot }).__consent;
  if (!snap) return false;
  if (category === 'analytics') return snap.analytics_accepted;
  if (category === 'marketing') return snap.marketing_accepted;
  return false;
}

/** POST /api/consent — Inertia recarrega só `consent`, banner some sem trocar de página. */
export function setConsent(categories: { analytics: boolean; marketing: boolean }): Promise<void> {
  return new Promise((resolve, reject) => {
    router.post('/api/consent', categories, {
      preserveScroll: true,
      preserveState: true,
      only: ['consent'],
      onSuccess: () => resolve(),
      onError: (errors) => reject(new Error(JSON.stringify(errors))),
    });
  });
}

/** Sincroniza window.__consent pra hasConsent() funcionar fora de React. */
export function syncConsentToWindow(snapshot: ConsentSnapshot): void {
  if (typeof window === 'undefined') return;
  (window as unknown as { __consent?: ConsentSnapshot }).__consent = snapshot;
}
