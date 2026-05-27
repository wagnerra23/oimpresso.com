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

/** POST /api/consent — fetch raw + Inertia partial reload do `consent` shared prop.
 *
 * Wagner 2026-05-27 reportou "da erro quando aceita": ConsentController retorna
 * `noContent()` (204 sem body Inertia). `router.post(...)` Inertia espera resposta
 * Inertia válida (X-Inertia headers) — sem isso dispara `onError` mesmo backend
 * tendo salvo o cookie corretamente. Resultado: cookie gravado mas banner não some
 * + toast.error aparece.
 *
 * Fix: fetch raw pro endpoint, depois `router.reload({ only: ['consent'] })` pra
 * atualizar a shared prop e fazer banner sumir.
 */
export function setConsent(categories: { analytics: boolean; marketing: boolean }): Promise<void> {
  const csrfToken =
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

  return fetch('/api/consent', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken,
    },
    credentials: 'same-origin',
    body: JSON.stringify(categories),
  }).then((res) => {
    if (!res.ok) {
      throw new Error(`HTTP ${res.status} ${res.statusText}`);
    }
    // 204 OK — cookie salvo. Recarrega só `consent` shared prop pra banner sumir.
    router.reload({ only: ['consent'] });
  });
}

/** Sincroniza window.__consent pra hasConsent() funcionar fora de React. */
export function syncConsentToWindow(snapshot: ConsentSnapshot): void {
  if (typeof window === 'undefined') return;
  (window as unknown as { __consent?: ConsentSnapshot }).__consent = snapshot;
}
