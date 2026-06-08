// Setup global Vitest (jsdom) — Onda 4 / ADR 0211.
//
// Mínimo: garante limpeza de timers/mocks entre testes. Os testes que usam MSW
// instanciam o server localmente (setupServer) e gerenciam seu próprio
// beforeAll/afterEach/afterAll — mantém o setup global agnóstico.

import { afterEach, vi } from 'vitest';

// Polyfills jsdom — Radix UI (Popover) usa APIs ausentes no jsdom. Sem isso o
// render do componente quebra com "ResizeObserver is not defined" etc.
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
}

if (typeof Element !== 'undefined') {
  // Radix/scroll helpers ausentes no jsdom.
  // @ts-expect-error jsdom não implementa
  Element.prototype.scrollIntoView ??= () => {};
  // @ts-expect-error jsdom não implementa hasPointerCapture (Radix usa)
  Element.prototype.hasPointerCapture ??= () => false;
  // @ts-expect-error jsdom não implementa releasePointerCapture
  Element.prototype.releasePointerCapture ??= () => {};
  // @ts-expect-error jsdom não implementa setPointerCapture
  Element.prototype.setPointerCapture ??= () => {};
}

if (typeof window !== 'undefined' && !window.matchMedia) {
  window.matchMedia = (query: string) =>
    ({
      matches: false,
      media: query,
      onchange: null,
      addListener: () => {},
      removeListener: () => {},
      addEventListener: () => {},
      removeEventListener: () => {},
      dispatchEvent: () => false,
    }) as unknown as MediaQueryList;
}

afterEach(() => {
  // Restaura timers reais caso um teste tenha esquecido vi.useRealTimers().
  vi.useRealTimers();
  vi.clearAllMocks();
});
