// useBoardShortcuts — atalhos de teclado do Board (PMG-008).
//
// POR QUE É UM HOOK (e não ficou inline no Index.tsx): a lógica de teclado só é
// verificável se puder ser montada sem a Page inteira (AppShellV2 + Inertia +
// defer). Extraída, ela roda em jsdom — tests/forjaBoardShortcuts.spec.tsx, lane
// .github/workflows/forja-shortcuts-gate.yml. O comportamento de J/K/E/A e `/` é
// o MESMO de antes da extração; o spec cobre os legados como anti-regressão.
//
// ⚠️ `E` NÃO foi reatribuído. O SPEC (PMG-008) pedia `e` = "editar task
// selecionada", mas Board/Index.charter.md (lei) fixa E = avançar status e
// A = voltar — e a precedência de memory/proibicoes.md é charter > SPEC. Roubar
// o E quebraria a memória muscular de quem usa o board todo dia. O "abrir pra
// editar" ficou no `Enter`, que estava livre.
//
// Novo em PMG-008: `?` abre/fecha o overlay de ajuda · `Esc` fecha · `Enter`
// abre o DetailSheet da task selecionada.

import { useEffect, useRef } from 'react';

export interface ShortcutTask {
  task_id: string;
  status: string;
}

export interface UseBoardShortcutsArgs<T extends ShortcutTask> {
  /** Lista linear (ordem visual das colunas) usada pela navegação J/K. */
  tasks: T[];
  selectedId: string | null;
  onSelect: (taskId: string | null) => void;
  /** E — avança status (todo→doing→review→done). */
  onAdvance: (task: T) => void;
  /** A — volta status. */
  onRetreat: (task: T) => void;
  /** Enter — abre o DetailSheet da task selecionada. */
  onOpenDetail: (taskId: string) => void;
  /** `/` — foca o campo de busca. */
  onFocusSearch: () => void;
  helpOpen: boolean;
  onHelpToggle: (open: boolean) => void;
}

/**
 * Campo de texto em foco: nenhum atalho de letra pode disparar, senão digitar
 * "java" na busca move 2 cards e avança 1 status.
 */
export function isTypingTarget(target: EventTarget | null): boolean {
  const el = target as HTMLElement | null;
  if (!el || typeof el.tagName !== 'string') return false;
  return el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable === true;
}

export default function useBoardShortcuts<T extends ShortcutTask>(
  args: UseBoardShortcutsArgs<T>,
): void {
  // Latest-ref: o listener registra 1× e sempre lê os valores do render atual.
  // Sem isso as deps seriam as callbacks (recriadas a cada render do Index) e o
  // listener re-registraria a cada tecla — era o motivo do eslint-disable antigo.
  const ref = useRef(args);
  ref.current = args;

  useEffect(() => {
    function onKey(e: KeyboardEvent): void {
      const {
        tasks,
        selectedId,
        onSelect,
        onAdvance,
        onRetreat,
        onOpenDetail,
        onFocusSearch,
        helpOpen,
        onHelpToggle,
      } = ref.current;

      const typing = isTypingTarget(e.target);

      // `?` (Shift+/) alterna a ajuda — fora de campo de texto, senão digitar
      // "?" numa busca abriria o overlay no meio da frase.
      if (e.key === '?' && !typing) {
        e.preventDefault();
        onHelpToggle(!helpOpen);
        return;
      }

      // Com a ajuda aberta só o Esc responde: navegar cards por trás de um
      // modal é o anti-padrão que o overlay existe pra evitar.
      if (helpOpen) {
        if (e.key === 'Escape') {
          e.preventDefault();
          onHelpToggle(false);
        }
        return;
      }

      if (e.key === '/' && !typing) {
        e.preventDefault();
        onFocusSearch();
        return;
      }

      if (typing) return;

      const idx = selectedId ? tasks.findIndex((t) => t.task_id === selectedId) : -1;
      const current = idx >= 0 ? (tasks[idx] ?? null) : null;

      if (e.key === 'j' || e.key === 'J') {
        e.preventDefault();
        const next = idx < 0 ? 0 : Math.min(tasks.length - 1, idx + 1);
        onSelect(tasks[next]?.task_id ?? null);
      } else if (e.key === 'k' || e.key === 'K') {
        e.preventDefault();
        const prev = idx <= 0 ? 0 : idx - 1;
        onSelect(tasks[prev]?.task_id ?? null);
      } else if (e.key === 'Enter') {
        if (!current) return;
        e.preventDefault();
        onOpenDetail(current.task_id);
      } else if (e.key === 'e' || e.key === 'E') {
        if (!current) return;
        e.preventDefault();
        onAdvance(current);
      } else if (e.key === 'a' || e.key === 'A') {
        if (!current) return;
        e.preventDefault();
        onRetreat(current);
      }
    }

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);
}
