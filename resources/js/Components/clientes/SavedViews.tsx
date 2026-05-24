// Components/clientes/SavedViews.tsx
//
// PTDP Onda 1 do Cliente · 4 saved views fixas + atalho `g + 1..4` (Cowork chat1 ref).
// Atende critério-âncora: "Bruna acha cliente em ≤3 clicks":
//   Clique 1 (saved view) → Clique 2 (linha) → Clique 3 (drawer abre)
//
// Atalhos teclado (operador power-user):
//   - g + 1 = Pra ligar hoje
//   - g + 2 = Sem compra 90d
//   - g + 3 = VIPs
//   - g + 4 = Inadimplentes
//
// Refs:
//   - prototipo-ui/prototipos/clientes/clientes-ptdp.jsx::SavedViews (Cowork canon)
//   - HANDOFF_CLIENTES.md §6 KB-9.75 (saved views feature N3)
//   - Constituição UI v2 · ADR UI-0013 (camada 4-Módulo)
//   - localStorage `oimpresso.cliente.savedViews.active` (multi-tenant Tier 0)

import { useEffect, useState } from 'react';
import { AlertCircle, Clock, Phone, Plus, Star } from 'lucide-react';

/** Filtros canônicos aplicáveis · subset dos filtros do Index.tsx.
 * Tipos `string` largos pra match com union de Index (statusFilter, saldoFilter). */
export interface SavedViewFilters {
  statusFilter?: string;
  tagsFilter?: string[];
  staleFilter?: string;
  saldoFilter?: string;
}

export interface SavedView {
  /** Identificador estável usado pelo `localStorage` + atalho `g+N`. */
  key: 'ligar' | 'sem-90' | 'vips' | 'inad';
  label: string;
  /** Tooltip de descrição. */
  desc: string;
  /** Ícone lucide (não emoji). */
  icon: typeof Phone;
  /** Conjunto de filtros aplicado quando ativa. */
  filters: SavedViewFilters;
  /** Tecla numérica do atalho `g + N`. */
  kbd: '1' | '2' | '3' | '4';
}

export const SAVED_VIEWS: ReadonlyArray<SavedView> = [
  {
    key: 'ligar',
    label: 'Pra ligar hoje',
    desc: 'Ativos · sem contato recente',
    icon: Phone,
    filters: { statusFilter: 'active' },
    kbd: '1',
  },
  {
    key: 'sem-90',
    label: 'Sem compra 90d',
    desc: 'Risco de churn · vale uma reativação',
    icon: Clock,
    filters: { statusFilter: 'active', staleFilter: '90' },
    kbd: '2',
  },
  {
    key: 'vips',
    label: 'VIPs',
    desc: 'Prioridade total na agenda',
    icon: Star,
    filters: { tagsFilter: ['vip'] },
    kbd: '3',
  },
  {
    key: 'inad',
    label: 'Inadimplentes',
    desc: 'Com saldo em aberto',
    icon: AlertCircle,
    filters: { saldoFilter: 'devedor' },
    kbd: '4',
  },
];

export interface SavedViewsProps {
  /** View ativa hoje (controlado pelo Index). */
  activeKey: string | null;
  /** Callback ao aplicar/desaplicar. `view=null` quando user clica novamente (toggle). */
  onApply: (view: SavedView | null) => void;
  /** localStorage key namespace (default `oimpresso.cliente.savedViews.active`). */
  storageKey?: string;
}

/**
 * Saved views fixas com pills lucide + atalho `g + 1..4`.
 *
 * Toggle: clicar 2x na mesma view desativa.
 * Persistência: `localStorage` prefixo `oimpresso.cliente.savedViews.active`.
 */
export function SavedViews({
  activeKey,
  onApply,
  storageKey = 'oimpresso.cliente.savedViews.active',
}: SavedViewsProps) {
  // Atalho teclado `g + 1..4`.
  const [gPending, setGPending] = useState(false);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const onKey = (e: KeyboardEvent) => {
      // Ignora se digitando em input/textarea/contenteditable.
      const t = e.target as HTMLElement | null;
      if (
        t &&
        (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)
      ) {
        return;
      }

      if (e.key === 'g' && !gPending) {
        e.preventDefault();
        setGPending(true);
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => setGPending(false), 800);
        return;
      }

      if (gPending && ['1', '2', '3', '4'].includes(e.key)) {
        e.preventDefault();
        setGPending(false);
        if (timer) clearTimeout(timer);
        const view = SAVED_VIEWS.find((v) => v.kbd === e.key);
        if (view) {
          onApply(activeKey === view.key ? null : view);
        }
        return;
      }

      // Qualquer outra tecla cancela g-pending.
      if (gPending) setGPending(false);
    };

    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('keydown', onKey);
      if (timer) clearTimeout(timer);
    };
  }, [gPending, activeKey, onApply]);

  // Persistência: restaurar view ativa do localStorage no mount + escrever ao mudar.
  // Inclui prefixo `oimpresso.<modulo>` (multi-tenant Tier 0 ADR 0093).
  useEffect(() => {
    if (typeof window === 'undefined') return;
    try {
      if (activeKey) {
        localStorage.setItem(storageKey, activeKey);
      } else {
        localStorage.removeItem(storageKey);
      }
    } catch {
      // localStorage indisponível (private mode, etc) · silenciar.
    }
  }, [activeKey, storageKey]);

  return (
    <div className="flex items-center gap-1.5 flex-wrap mt-3">
      <span className="text-[10px] font-semibold tracking-wider uppercase text-muted-foreground mr-1">
        Visões salvas
      </span>
      {SAVED_VIEWS.map((v) => {
        const Icon = v.icon;
        const on = activeKey === v.key;
        return (
          <button
            key={v.key}
            type="button"
            onClick={() => onApply(on ? null : v)}
            title={v.desc}
            className={
              'inline-flex items-center gap-1.5 h-6 px-2 rounded-full text-[11px] font-medium transition-colors border ' +
              (on
                ? 'bg-primary text-primary-foreground border-transparent'
                : 'bg-background text-muted-foreground border-border hover:text-foreground hover:border-muted-foreground')
            }
          >
            <Icon className="h-3 w-3" />
            <span>{v.label}</span>
            <kbd
              className={
                'font-mono text-[9px] px-1 rounded ' +
                (on
                  ? 'bg-primary-foreground/20 text-primary-foreground/90'
                  : 'bg-muted text-muted-foreground border border-border')
              }
            >
              g {v.kbd}
            </kbd>
          </button>
        );
      })}
      {gPending && (
        <span
          className="text-[10px] text-muted-foreground italic ml-1"
          role="status"
          aria-live="polite"
        >
          aperte 1-4…
        </span>
      )}
    </div>
  );
}

export default SavedViews;
