// Sells/_components/SellsCheatSheet — cheat-sheet overlay reusável (gap P3 #12 KB-9.75).
// Refs:
//  - prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/vendas-shortcuts.jsx (canon)
//  - memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md
//  - resources/css/sells-kb975-cheatsheet.css (CSS escopado fora .sells-cowork)
//
// Estado controlado externamente via `open` + `onClose` — facilita compartilhar
// entre Sells/Index.tsx, Sells/Show.tsx, Sells/Caixa, etc. Cada Page declara
// seu próprio array de shortcuts (lista canônica J/K/Enter/N/E/?/Esc/Cmd+K
// no Index; subset operacional em Show).
//
// Ativação canônica: a Page que renderiza este componente deve adicionar
// `useEffect` com `keydown` listener pra setar `open=true` quando `e.key === '?'`.
// O componente em si trata `Esc` e `?` pra fechar (idempotente, listener próprio).

import { useEffect, type ReactNode } from 'react';
import { X } from 'lucide-react';

export interface SellsShortcut {
  /** Sequência de keys (uma ou várias, ex: ["⌘", "K"]). Cada item vira <kbd>. */
  kbd: string | string[];
  /** Label descritivo. Pode conter <b>/<code>/JSX já que aceita ReactNode. */
  label: ReactNode;
  /** Agrupador opcional ("Navegar", "Ações", "⌘K palette", "Sair"). Default "Geral". */
  area?: string;
}

export interface SellsCheatSheetProps {
  /** Visibilidade controlada pelo pai. */
  open: boolean;
  /** Callback fechar — chamado em click no backdrop, no botão X, ou ao pressionar Esc/?. */
  onClose: () => void;
  /** Lista de atalhos a renderizar. Agrupa por `area`. */
  shortcuts: SellsShortcut[];
  /** Título do cabeçalho. Default "Atalhos do balcão". */
  title?: string;
  /** Subtítulo abaixo do título. Default texto Cowork canon. */
  subtitle?: string;
  /** Rodapé esquerdo (contexto). */
  footerLeft?: ReactNode;
  /** Rodapé direito (versão). Default "KB-9.75 · maio/2026". */
  footerRight?: ReactNode;
}

const DEFAULT_TITLE = 'Atalhos do balcão';
const DEFAULT_SUBTITLE = 'Vendas opera sem mouse. Pressione qualquer combinação abaixo.';
const DEFAULT_FOOTER_RIGHT = 'KB-9.75 · maio/2026';
const DEFAULT_AREA = 'Geral';

function normalizeKbd(kbd: string | string[]): string[] {
  return Array.isArray(kbd) ? kbd : [kbd];
}

function groupByArea(shortcuts: SellsShortcut[]): Map<string, SellsShortcut[]> {
  const groups = new Map<string, SellsShortcut[]>();
  for (const sc of shortcuts) {
    const key = sc.area ?? DEFAULT_AREA;
    const arr = groups.get(key) ?? [];
    arr.push(sc);
    groups.set(key, arr);
  }
  return groups;
}

export default function SellsCheatSheet({
  open,
  onClose,
  shortcuts,
  title = DEFAULT_TITLE,
  subtitle = DEFAULT_SUBTITLE,
  footerLeft,
  footerRight = DEFAULT_FOOTER_RIGHT,
}: SellsCheatSheetProps): ReactNode {
  // Listener próprio pra Esc/? — idempotente: fecha mesmo se a Page também
  // capturar (a Page geralmente abre via '?' e o cheat-sheet fecha via '?').
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent): void => {
      if (e.key === 'Escape' || e.key === '?') {
        e.preventDefault();
        onClose();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  const groups = groupByArea(shortcuts);

  return (
    <div
      className="vd-cheat-bd"
      onClick={onClose}
      role="presentation"
      data-testid="sells-cheatsheet-backdrop"
    >
      <div
        className="vd-cheat"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-label={title}
        aria-modal="true"
      >
        <header className="vd-cheat-h">
          <span className="vd-cheat-ic" aria-hidden="true">⌨</span>
          <h2>{title}</h2>
          <p>{subtitle}</p>
          <button
            type="button"
            className="vd-cheat-x"
            onClick={onClose}
            aria-label="Fechar cheat-sheet"
          >
            <kbd>esc</kbd>
            <X size={12} aria-hidden="true" />
          </button>
        </header>

        <div className="vd-cheat-grid">
          {Array.from(groups.entries()).map(([area, items]) => (
            <section key={area} className="vd-cheat-sec">
              <h4>{area}</h4>
              {items.map((sc, idx) => {
                const keys = normalizeKbd(sc.kbd);
                return (
                  <div key={idx} className="vd-cs-row">
                    <div className="vd-cs-keys">
                      {keys.map((k, i) => (
                        <span key={i} className="vd-cs-keys-item">
                          {i > 0 && <span className="vd-cs-plus" aria-hidden="true">+</span>}
                          <kbd>{k}</kbd>
                        </span>
                      ))}
                    </div>
                    <div className="vd-cs-lbl">{sc.label}</div>
                  </div>
                );
              })}
            </section>
          ))}
        </div>

        <footer className="vd-cheat-ft">
          <span>{footerLeft ?? 'Atalhos persistem em toda sub-rota de Vendas.'}</span>
          <span>{footerRight}</span>
        </footer>
      </div>
    </div>
  );
}

// Lista canônica de atalhos pra Sells/Index (Lista de vendas).
// Reusável também por Sells/Caixa, Sells/Drafts, Sells/Quotations.
export const SELLS_INDEX_SHORTCUTS: SellsShortcut[] = [
  { area: 'Navegar', kbd: 'J', label: <span>próxima venda <b>↓</b></span> },
  { area: 'Navegar', kbd: 'K', label: <span>anterior venda <b>↑</b></span> },
  { area: 'Navegar', kbd: 'Enter', label: 'abrir drawer da venda focada' },
  { area: 'Navegar', kbd: '/', label: 'focar campo de busca' },
  { area: 'Navegar', kbd: ['⌘', 'K'], label: 'command palette (busca global + ações)' },

  { area: 'Ações na venda focada', kbd: 'N', label: 'nova venda' },
  { area: 'Ações na venda focada', kbd: 'E', label: 'editar venda (drawer Create)' },
  { area: 'Ações na venda focada', kbd: 'R', label: 'imprimir recibo (térmica/A4)' },
  { area: 'Ações na venda focada', kbd: 'F', label: 'faturar NF-e / NFS-e' },
  { area: 'Ações na venda focada', kbd: 'B', label: 'favoritar linha (pessoal)' },
  { area: 'Ações na venda focada', kbd: 'X', label: 'selecionar pra ação em lote' },

  { area: '⌘K prefixos', kbd: '/', label: <span>filtra <b>ações</b> · ex: <code>/faturar lote</code></span> },
  { area: '⌘K prefixos', kbd: '#', label: <span>busca por <b>ID</b> · ex: <code>#7825</code></span> },
  { area: '⌘K prefixos', kbd: '@', label: <span>filtra por <b>vendedor</b> · ex: <code>@bruna</code></span> },
  { area: '⌘K prefixos', kbd: '$', label: <span>valor <b>mínimo</b> · ex: <code>$2000</code></span> },

  { area: 'Sair / ajuda', kbd: 'Esc', label: 'fechar palette · drawer · cheat-sheet' },
  { area: 'Sair / ajuda', kbd: '?', label: 'abrir/fechar este cheat-sheet' },
];

// Lista canônica de atalhos pra Sells/Show (Detalhe de venda).
export const SELLS_SHOW_SHORTCUTS: SellsShortcut[] = [
  { area: 'Navegar', kbd: 'Esc', label: 'voltar pra lista' },

  { area: 'Ações', kbd: 'E', label: 'editar venda' },
  { area: 'Ações', kbd: 'P', label: 'imprimir comprovante' },

  { area: 'Sair / ajuda', kbd: '?', label: 'abrir/fechar este cheat-sheet' },
];
