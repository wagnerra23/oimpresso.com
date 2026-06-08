// Tipos globais compartilhados pelas Pages Inertia.
//
// Carregado automaticamente pelo tsconfig.json (`include: ["resources/js/**/*.ts"]`).
// Não exportar nada deste arquivo — é só `declare global`.

/**
 * Ziggy `route()` global, injetado em runtime via `@routes` directive em
 * `resources/views/layouts/inertia.blade.php`. Pacote `tightenco/ziggy`
 * gera `window.route` automaticamente quando o Blade renderiza.
 *
 * Uso típico:
 *   route('whatsapp.conversations.show', { id: 42 })
 *   route('whatsapp.conversations.index')
 *   route('whatsapp.conversations.send', conv.id)  // posicional
 *
 * Tipo simplificado (não usa `import { Config } from 'ziggy-js'` pra evitar
 * dep npm extra — `tightenco/ziggy` no composer já basta pra Blade gerar
 * o JS inline).
 *
 * Refs:
 * - https://github.com/tighten/ziggy
 * - inertia.blade.php (@routes injection)
 */
/* eslint-disable @typescript-eslint/no-explicit-any */
type RouteParams = string | number | Record<string, string | number | boolean>;

declare global {
  function route(): {
    current(name?: string, params?: any): boolean | string;
    has(name: string): boolean;
    params: Record<string, any>;
  };
  function route(name: string, params?: RouteParams, absolute?: boolean): string;

  interface Window {
    Ziggy: {
      url: string;
      port: number | null;
      defaults: Record<string, unknown>;
      routes: Record<string, unknown>;
    };
    route: typeof route;
  }
}

export {};
