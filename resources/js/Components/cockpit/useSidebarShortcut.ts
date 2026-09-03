// @memcofre
//   modulo: Cockpit (Sidebar · atalhos de teclado "G X")
//   adrs: 0180 (sidebar v3 — Fase 8 "atalhos kbd G X X"), UI-0011 (single-pane)
//   nota: CONSUMIDOR do campo `shortcut` que o backend já declara desde a
//         Fase 4 da ADR 0180. O produtor existe e está populado; o consumidor
//         nunca foi escrito — medido em 2026-08-28: 14 atalhos `G X` vindos de
//         20 DataControllers, ZERO leitores em `resources/js`.
//         Cadeia do dado: `Modules/<X>/Http/Controllers/DataController::items()`
//         → nwidart MenuItem (attributes) → `App\Services\LegacyMenuAdapter`
//         (pass-through explícito de `shortcut`) → shared prop `shell.menu`.

import { useEffect } from 'react';

import type { ShellMenuItem } from './shared';

/** Janela pra digitar a letra seguinte depois do `G`. Espelha o protótipo
 *  Cowork (`prototipo-ui/cowork/sidebar.jsx` — "arma no G, janela de 1.5s"). */
export const JANELA_SEQUENCIA_MS = 1500;

export interface IndiceAtalhos {
  /** sequência normalizada (ex. `S`, `C D`) → href de destino. */
  porSequencia: Map<string, string>;
  /** rótulos crus (ex. `G S`) que PODEM ser exibidos como dica. */
  usaveis: Set<string>;
  /** sequências descartadas por conflito — nunca navegam, nunca viram dica. */
  ambiguas: string[];
}

/**
 * Extrai a sequência de letras de um `shortcut` cru.
 *
 * O backend valida `/^G [A-Z]( [A-Z])?$/` (`App\Sidebar\SidebarMenuItem`), então
 * `G S` vira `S` e `G C D` vira `C D`. Qualquer coisa fora do formato devolve
 * `null` — preferimos ignorar a declarar um atalho que não corresponde ao que
 * a dica mostra.
 */
export function normalizarSequencia(cru: string | undefined | null): string | null {
  if (typeof cru !== 'string') return null;
  const partes = cru.trim().toUpperCase().split(/\s+/);
  if (partes.length < 2 || partes[0] !== 'G') return null;
  const letras = partes.slice(1);
  if (!letras.every((l) => /^[A-Z]$/.test(l))) return null;
  return letras.join(' ');
}

/**
 * Monta o índice de atalhos a partir do `shell.menu`.
 *
 * Regra de segurança (decisão de implementação, 2026-08-28): sequência em
 * conflito é DESCARTADA dos dois lados — não navega e não mostra dica. Navegar
 * pra um de dois destinos plausíveis é pior que não ter atalho, e a dica some
 * junto pra não prometer o que não cumpre.
 *
 * São dois conflitos possíveis:
 *  1. **duplicata** — dois items declaram a mesma sequência;
 *  2. **prefixo** — `C` e `C D` coexistem, e o `C` dispararia antes de dar
 *     tempo de digitar o `D`.
 *
 * Nenhuma validação de unicidade existe no backend (o validador é por item,
 * não global), então o conflito chega até aqui e é aqui que ele é contido.
 */
export function construirIndiceAtalhos(items: ShellMenuItem[] | undefined): IndiceAtalhos {
  const candidatos = new Map<string, { primeiroHref: string; hrefs: Set<string>; crus: Set<string> }>();

  const visitar = (lista: ShellMenuItem[] | undefined): void => {
    for (const item of lista ?? []) {
      const seq = normalizarSequencia(item.shortcut);
      const href = item.href;
      if (seq && href && href !== '#') {
        const atual = candidatos.get(seq) ?? { primeiroHref: href, hrefs: new Set<string>(), crus: new Set<string>() };
        atual.hrefs.add(href);
        if (item.shortcut) atual.crus.add(item.shortcut);
        candidatos.set(seq, atual);
      }
      visitar(item.children);
    }
  };
  visitar(items);

  const sequencias = [...candidatos.keys()];
  const ehPrefixo = (a: string, b: string): boolean => a !== b && b.startsWith(`${a} `);

  const porSequencia = new Map<string, string>();
  const usaveis = new Set<string>();
  const ambiguas: string[] = [];

  for (const [seq, dados] of candidatos) {
    const duplicada = dados.hrefs.size > 1;
    const colidePrefixo = sequencias.some((outra) => ehPrefixo(seq, outra) || ehPrefixo(outra, seq));
    if (duplicada || colidePrefixo) {
      ambiguas.push(seq);
      continue;
    }
    porSequencia.set(seq, dados.primeiroHref);
    for (const cru of dados.crus) usaveis.add(cru);
  }

  ambiguas.sort();
  return { porSequencia, usaveis, ambiguas };
}

const editando = (el: Element | null): boolean =>
  !!el &&
  (el.tagName === 'INPUT' ||
    el.tagName === 'TEXTAREA' ||
    el.tagName === 'SELECT' ||
    (el as HTMLElement).isContentEditable);

/**
 * Instala o listener global da sequência `G X`.
 *
 * Convive com o ⌘K/Ctrl+K da `CommandPalette`: qualquer tecla com
 * meta/ctrl/alt sai antes de armar. Digitação dentro de campo editável
 * (inclusive o input da própria paleta) também sai.
 *
 * Navega com `window.location.assign` de propósito — é exatamente o que o
 * `<a href>` do `SidebarMenuItem` já faz hoje. Trocar por `router.visit` aqui
 * criaria duas semânticas de navegação pro mesmo destino.
 */
export function useSidebarShortcut(items: ShellMenuItem[] | undefined): void {
  useEffect(() => {
    const indice = construirIndiceAtalhos(items);
    if (indice.porSequencia.size === 0) return;

    const sequencias = [...indice.porSequencia.keys()];
    let armadoEm = 0;
    let buffer: string[] = [];

    const desarmar = (): void => {
      armadoEm = 0;
      buffer = [];
    };

    const aoTeclar = (e: KeyboardEvent): void => {
      if (e.metaKey || e.ctrlKey || e.altKey || e.repeat) return;
      if (editando(document.activeElement)) return;

      const tecla = (e.key || '').toUpperCase();
      if (!/^[A-Z]$/.test(tecla)) {
        desarmar();
        return;
      }

      const dentroDaJanela = armadoEm !== 0 && Date.now() - armadoEm < JANELA_SEQUENCIA_MS;

      if (!dentroDaJanela) {
        // Fora da janela o `G` só arma — nunca navega sozinho.
        if (tecla === 'G') {
          armadoEm = Date.now();
          buffer = [];
        } else {
          desarmar();
        }
        return;
      }

      const tentativa = [...buffer, tecla];
      const seq = tentativa.join(' ');
      const destino = indice.porSequencia.get(seq);

      if (destino) {
        e.preventDefault();
        desarmar();
        window.location.assign(destino);
        return;
      }

      // Ainda pode virar sequência maior (`C` a caminho de `C D`).
      if (sequencias.some((s) => s.startsWith(`${seq} `))) {
        buffer = tentativa;
        armadoEm = Date.now();
        return;
      }

      desarmar();
    };

    document.addEventListener('keydown', aoTeclar);
    return () => document.removeEventListener('keydown', aoTeclar);
  }, [items]);
}
