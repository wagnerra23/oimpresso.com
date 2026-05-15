// US-SELL-P0-3 — Atalhos teclado paridade Blade legacy /sells/create.
// Refs: memory/requisitos/Sells/RUNBOOK-paridade-create.md §3.7 + §5 P0-3
//       resources/views/sale_pos/partials/keyboard_shortcuts.blade.php (Mousetrap Blade)
//       Pain #1 reunião 13/maio: "velocidade pra abrir uma venda"
//       Canary: MARTINHO CAÇAMBAS biz=164 — Lara faz 30 vendas/dia, atalhos = P0
//
// Hook registra listener `keydown` global. Guards obrigatórios:
//   1. NÃO dispara se foco está em <input>/<textarea>/<select> (exceto Esc)
//   2. NÃO dispara se modal aberto (aria-modal=true / [role=dialog]) salvo Esc
//   3. Preserva atalhos browser nativos (Ctrl+R reload NÃO é interceptado)
//
// Atalhos default (preserva os 2 já existentes + adiciona 5 novos):
//   /         → focusProduct       (foca campo busca de produto)         [P0]
//   F2        → focusFirstField    (foca primeiro input/cliente)         [P1]
//   F9        → submit             (finalizar venda)                     [P0]
//   Alt+P     → togglePrint        (preview/save-and-print)              [P1]
//   Alt+R     → reset              (resetar form com confirm)            [P1]
//   Ctrl+Enter→ submit             (já existia — preservado)             [P0]
//   Esc       → blur active        (já existia — preservado)             [P0]
//
// TODO próxima iteração: carregar atalhos dinâmicos de
// `business.pos_settings.shortcuts` JSON (paridade Mousetrap configurável Blade).
// Por ora, defaults hardcoded suficientes pra canary Martinho 19/maio.

import { useEffect } from 'react';

export interface SellsHotkeysHandlers {
  /** `/` — foca campo busca de produto (helper focusProductSearch da Page). */
  onFocusProduct?: () => void;
  /** F2 — foca primeiro input visível (genérico: cliente ou local). */
  onFocusFirstField?: () => void;
  /** F9 — finalizar venda (submit). */
  onSubmit?: () => void;
  /** Alt+P — alternar preview/imprimir. */
  onPrint?: () => void;
  /** Alt+R — resetar form (com confirm dialog nativo). */
  onReset?: () => void;
}

/**
 * Detecta se um elemento é "editável" (input/textarea/select/contenteditable).
 * Usado pra decidir se atalho de tecla única (`/`, F2) deve ser engolido pelo input.
 */
function isEditableTarget(target: EventTarget | null): boolean {
  if (!target || !(target instanceof HTMLElement)) return false;
  const tag = target.tagName;
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
  if (target.isContentEditable) return true;
  return false;
}

/**
 * Detecta se existe um modal/dialog aberto no DOM.
 * Convenção shadcn/radix: role="dialog" + data-state="open" OU aria-modal="true".
 */
function isModalOpen(): boolean {
  // shadcn/Radix Dialog
  const radixOpen = document.querySelector(
    '[role="dialog"][data-state="open"], [aria-modal="true"]',
  );
  if (radixOpen) return true;
  // Bootstrap legacy modal (paridade Blade — se Blade modal vier embutido)
  const bootstrapOpen = document.querySelector('.modal.in, .modal.show');
  if (bootstrapOpen) return true;
  return false;
}

export function useSellsHotkeys(handlers: SellsHotkeysHandlers): void {
  // Captura handlers via closure — useEffect com deps em handlers re-registra
  // listener quando handlers mudam (raro mas evita stale closure).
  const {
    onFocusProduct,
    onFocusFirstField,
    onSubmit,
    onPrint,
    onReset,
  } = handlers;

  useEffect(() => {
    const onKeyDown = (e: KeyboardEvent) => {
      const modalOpen = isModalOpen();
      const editable = isEditableTarget(e.target);

      // ---- Esc: SEMPRE permitido (preservado — já tratado no Create.tsx
      // num useEffect separado pra blur). Aqui apenas garantimos que nosso
      // hook não consome Esc indevidamente.
      if (e.key === 'Escape') {
        return;
      }

      // ---- Ctrl+Enter / Cmd+Enter: submit (preservado — já tratado no
      // Create.tsx num useEffect separado). Aqui apenas garantimos que
      // nosso hook não consome indevidamente.
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        return;
      }

      // ---- F9: submit (funciona MESMO em input/textarea pra Lara não
      // precisar tirar foco antes de salvar)
      if (e.key === 'F9') {
        if (modalOpen) return;
        e.preventDefault();
        onSubmit?.();
        return;
      }

      // ---- F2: foca primeiro field (cliente). Funciona em qualquer foco.
      if (e.key === 'F2') {
        if (modalOpen) return;
        e.preventDefault();
        onFocusFirstField?.();
        return;
      }

      // ---- Alt+P: toggle print (Salvar e Imprimir)
      // Não usa Ctrl+P pra não conflitar com browser native print.
      if (e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey && (e.key === 'p' || e.key === 'P')) {
        if (modalOpen) return;
        e.preventDefault();
        onPrint?.();
        return;
      }

      // ---- Alt+R: reset form (com confirm)
      // Não usa Ctrl+R pra não conflitar com browser native reload.
      if (e.altKey && !e.ctrlKey && !e.metaKey && !e.shiftKey && (e.key === 'r' || e.key === 'R')) {
        if (modalOpen) return;
        e.preventDefault();
        // confirm dialog nativo — Wagner trauma "não remover dados sem aviso"
        const ok = window.confirm(
          'Resetar formulário? Todos os campos preenchidos serão limpos.',
        );
        if (ok) onReset?.();
        return;
      }

      // ---- "/" : foca produto. ENGOLIDO se foco está em input (senão
      // Larissa digitando "/" no nome cliente foca produto). Excessão:
      // se foco é no PRÓPRIO search de produto, atalho é redundante mas inofensivo.
      if (e.key === '/' && !e.ctrlKey && !e.altKey && !e.metaKey && !e.shiftKey) {
        if (modalOpen) return;
        if (editable) return; // guard #1 — não rouba "/" do input
        e.preventDefault();
        onFocusProduct?.();
        return;
      }
    };

    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [onFocusProduct, onFocusFirstField, onSubmit, onPrint, onReset]);
}
