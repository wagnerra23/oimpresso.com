import { useEffect, useRef } from 'react';

/**
 * useKbKeyboardNav — registra atalhos globais da tela KB
 *
 * Atalhos suportados (port do `kb-page.jsx::onKey` Cowork [CC] + charter Index):
 *   - ⌘K / Ctrl+K → abrir command palette
 *   - /           → focar busca da lista (ou abrir palette se não houver input visível)
 *   - Esc         → fechar overlays ativos
 *   - j / ↓       → próximo nó na lista
 *   - k / ↑       → nó anterior na lista
 *   - Enter       → abrir nó selecionado (se preview fechado)
 *   - N           → novo artigo (se can.write)
 *   - A           → abrir IA "Perguntar ao KB" (se can.ai_ask)
 *   - B           → toggle favorito do nó ativo
 *
 * Atalhos só disparam fora de campos digitáveis (INPUT/TEXTAREA/contenteditable).
 *
 * NÃO previne shortcut SE algum overlay já tá aberto (handler do overlay tem
 * precedência via `escapeOverlay`).
 */

export interface KbKeyboardActions {
  /** Estado dos overlays — usado pra decidir se Esc fecha algo já aberto */
  paletteOpen: boolean;
  troubleOpen: boolean;
  aiOpen: boolean;
  pathsOpen: boolean;
  healthOpen: boolean;
  composerOpen: boolean;

  /** Callbacks de ação */
  onOpenPalette: () => void;
  onFocusSearch: () => void;
  onCloseAll: () => void;
  onNext: () => void;
  onPrev: () => void;
  onEnter: () => void;
  onNewArticle?: () => void;
  onOpenAI?: () => void;
  onToggleFav?: () => void;

  /** Capabilities — se ausentes, atalhos correspondentes ignoram */
  canWrite?: boolean;
  canAiAsk?: boolean;
  hasActiveNode?: boolean;
}

function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;
  const tag = target.tagName;
  return (
    tag === 'INPUT' || tag === 'TEXTAREA' || target.isContentEditable === true
  );
}

export function useKbKeyboardNav(actions: KbKeyboardActions): void {
  // Ref-pattern: handler estável que sempre lê o snapshot mais recente
  // de `actions`. Evita re-bind do listener a cada render do parent.
  const ref = useRef(actions);
  ref.current = actions;

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const a = ref.current;
      const typing = isTypingTarget(e.target);
      const mod = e.metaKey || e.ctrlKey;

      // ⌘K — sempre, mesmo digitando
      if (mod && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        a.onOpenPalette();
        return;
      }

      // Esc — fecha overlays ativos (sempre, mesmo digitando)
      if (e.key === 'Escape') {
        const hasAnyOverlay =
          a.paletteOpen ||
          a.troubleOpen ||
          a.aiOpen ||
          a.pathsOpen ||
          a.healthOpen ||
          a.composerOpen;
        if (hasAnyOverlay) {
          e.preventDefault();
          a.onCloseAll();
        } else {
          // Sem overlay, deixa o parent decidir (ex: fechar reader)
          a.onCloseAll();
        }
        return;
      }

      if (typing) return;

      // / — foco na busca (se não estiver com overlay)
      if (e.key === '/') {
        const hasOverlay =
          a.paletteOpen ||
          a.troubleOpen ||
          a.aiOpen ||
          a.pathsOpen ||
          a.composerOpen;
        if (!hasOverlay) {
          e.preventDefault();
          a.onFocusSearch();
        }
        return;
      }

      // j / ArrowDown
      if (e.key === 'j' || e.key === 'ArrowDown') {
        e.preventDefault();
        a.onNext();
        return;
      }

      // k / ArrowUp
      if (e.key === 'k' || e.key === 'ArrowUp') {
        e.preventDefault();
        a.onPrev();
        return;
      }

      // Enter
      if (e.key === 'Enter') {
        e.preventDefault();
        a.onEnter();
        return;
      }

      // n — novo artigo (capability gate)
      if (e.key === 'n' && a.canWrite && a.onNewArticle) {
        e.preventDefault();
        a.onNewArticle();
        return;
      }

      // a — perguntar IA
      if (e.key === 'a' && a.canAiAsk && a.onOpenAI) {
        e.preventDefault();
        a.onOpenAI();
        return;
      }

      // b — favoritar
      if (e.key === 'b' && a.hasActiveNode && a.onToggleFav) {
        e.preventDefault();
        a.onToggleFav();
        return;
      }
    };

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);
}
