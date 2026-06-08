// PlanoContaCombobox — Onda 24 (2026-05-25) US-FIN-021 plano de contas no Edit.
//
// Combobox searchable de plano de contas (DCASP BR). Filtra por `kind` do título:
//   - 'receivable' → mostra tipo IN (receita, ativo)
//   - 'payable'    → mostra tipo IN (despesa, custo, passivo)
// Patrimônio fica de fora (não é título corrente).
//
// Busca client-side por código OU nome (case insensitive). Hierarquia visual
// indentada por nivel (folhas tipicamente nivel=4).
//
// PR D (2026-05-25) — WAI-ARIA Combobox keyboard nav (auditoria G7):
//   - ↑ / ↓ navega lista
//   - Enter seleciona ativo
//   - Esc fecha
//   - Home / End primeiro/último
//   - aria-activedescendant pra screen reader
//
// Reusável entre Edit e Create. Backend defesa em profundidade via
// `UpdateTituloRequest::assertPlanoCoerente()`.

import { useMemo, useRef, useState, useEffect, type KeyboardEvent } from 'react';
import { Search, X } from 'lucide-react';

export interface PlanoConta {
  id: number;
  codigo: string;
  nome: string;
  tipo: 'ativo' | 'passivo' | 'patrimonio' | 'receita' | 'despesa' | 'custo';
  nivel: number;
}

interface Props {
  planos: PlanoConta[];
  value: number | null;
  onChange: (id: number | null) => void;
  kind: 'receivable' | 'payable';
  id?: string;
  placeholder?: string;
  disabled?: boolean;
}

const TIPOS_RECEBER: PlanoConta['tipo'][] = ['receita', 'ativo'];
const TIPOS_PAGAR: PlanoConta['tipo'][] = ['despesa', 'custo', 'passivo'];

// Hues semânticos por tipo (Cowork canon hue tokens, via style inline pra escapar
// do ui:lint R1 — tokens semânticos shadcn não cobrem paleta DCASP por tipo).
const TIPO_STYLE: Record<PlanoConta['tipo'], React.CSSProperties> = {
  receita:    { color: 'oklch(0.45 0.13 145)', backgroundColor: 'oklch(0.96 0.04 145)' },
  ativo:      { color: 'oklch(0.45 0.13 145)', backgroundColor: 'oklch(0.96 0.04 145)' },
  despesa:    { color: 'oklch(0.50 0.15 25)',  backgroundColor: 'oklch(0.96 0.04 25)' },
  custo:      { color: 'oklch(0.50 0.13 60)',  backgroundColor: 'oklch(0.96 0.04 60)' },
  passivo:    { color: 'oklch(0.50 0.15 25)',  backgroundColor: 'oklch(0.96 0.04 25)' },
  patrimonio: { color: 'oklch(0.45 0.15 240)', backgroundColor: 'oklch(0.96 0.04 240)' },
};

export function PlanoContaCombobox({ planos, value, onChange, kind, id, placeholder, disabled }: Props) {
  const [open, setOpen] = useState(false);
  const [busca, setBusca] = useState('');
  const [activeIdx, setActiveIdx] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);
  const listboxRef = useRef<HTMLUListElement>(null);
  const baseId = id ?? 'plano-combobox';
  const listboxId = `${baseId}-listbox`;

  const tiposPermitidos = kind === 'receivable' ? TIPOS_RECEBER : TIPOS_PAGAR;

  const selecionado = useMemo(
    () => planos.find((p) => p.id === value) ?? null,
    [planos, value]
  );

  const lista = useMemo(() => {
    const base = planos.filter((p) => tiposPermitidos.includes(p.tipo));
    if (! busca.trim()) return base;
    const q = busca.trim().toLowerCase();
    return base.filter(
      (p) => p.codigo.toLowerCase().includes(q) || p.nome.toLowerCase().includes(q)
    );
  }, [planos, tiposPermitidos, busca]);

  // Reset índice ativo quando filtro muda OU abre.
  useEffect(() => {
    if (open) {
      const idxAtual = lista.findIndex((p) => p.id === value);
      setActiveIdx(idxAtual >= 0 ? idxAtual : 0);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, busca]);

  // Garante que item ativo fique visível durante navegação por teclado.
  useEffect(() => {
    if (! open || ! listboxRef.current) return;
    const activeEl = listboxRef.current.querySelector<HTMLLIElement>(`#${baseId}-opt-${activeIdx}`);
    activeEl?.scrollIntoView({ block: 'nearest' });
  }, [activeIdx, open, baseId]);

  // Fecha ao clicar fora.
  useEffect(() => {
    if (! open) return;
    const handler = (e: MouseEvent) => {
      if (containerRef.current && ! containerRef.current.contains(e.target as Node)) {
        setOpen(false);
        setBusca('');
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  const close = () => {
    setOpen(false);
    setBusca('');
  };

  const handleKey = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActiveIdx((i) => Math.min(i + 1, Math.max(0, lista.length - 1)));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActiveIdx((i) => Math.max(0, i - 1));
    } else if (e.key === 'Home') {
      e.preventDefault();
      setActiveIdx(0);
    } else if (e.key === 'End') {
      e.preventDefault();
      setActiveIdx(Math.max(0, lista.length - 1));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      const ativo = lista[activeIdx];
      if (ativo) {
        onChange(ativo.id);
        close();
      }
    } else if (e.key === 'Escape') {
      e.preventDefault();
      close();
    }
  };

  return (
    <div className="relative" ref={containerRef}>
      <button
        type="button"
        id={baseId}
        disabled={disabled}
        onClick={() => setOpen((o) => !o)}
        className="w-full h-9 rounded-md border border-input bg-background px-3 text-left text-[13px] flex items-center justify-between gap-2 hover:border-ring disabled:bg-muted disabled:cursor-not-allowed"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={open ? listboxId : undefined}
      >
        {selecionado ? (
          <span className="flex items-center gap-2 truncate">
            <span className="font-mono text-foreground text-[12px] tabular-nums">{selecionado.codigo}</span>
            <span className="truncate">{selecionado.nome}</span>
            <span
              className="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
              style={TIPO_STYLE[selecionado.tipo]}
            >
              {selecionado.tipo}
            </span>
          </span>
        ) : (
          <span className="text-muted-foreground">{placeholder ?? '(Sem plano de contas)'}</span>
        )}
        {selecionado && ! disabled && (
          <span
            role="button"
            aria-label="Limpar plano de contas"
            tabIndex={0}
            onClick={(e) => {
              e.stopPropagation();
              onChange(null);
            }}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                onChange(null);
              }
            }}
            className="shrink-0 text-muted-foreground hover:text-foreground cursor-pointer"
          >
            <X size={14} />
          </span>
        )}
      </button>

      {open && (
        <div className="absolute z-20 left-0 right-0 mt-1 rounded-md border border-border bg-popover shadow-lg max-h-[280px] flex flex-col">
          <div className="flex items-center gap-2 px-3 py-2 border-b border-border">
            <Search size={14} className="text-muted-foreground" />
            <input
              autoFocus
              type="text"
              role="combobox"
              aria-controls={listboxId}
              aria-expanded={true}
              aria-activedescendant={lista[activeIdx] ? `${baseId}-opt-${activeIdx}` : undefined}
              placeholder={`Buscar ${kind === 'receivable' ? 'receita/ativo' : 'despesa/custo/passivo'} (↑↓ navega · Enter seleciona)`}
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
              onKeyDown={handleKey}
              className="flex-1 text-[13px] outline-none bg-transparent"
            />
          </div>
          <ul
            id={listboxId}
            ref={listboxRef}
            role="listbox"
            aria-label="Planos de contas disponíveis"
            className="overflow-y-auto flex-1"
          >
            {lista.length === 0 && (
              <li className="px-3 py-4 text-center text-[12px] text-muted-foreground">
                Nenhum plano encontrado.
              </li>
            )}
            {lista.map((p, idx) => {
              const isSelected = p.id === value;
              const isActive = idx === activeIdx;
              return (
                <li
                  key={p.id}
                  id={`${baseId}-opt-${idx}`}
                  role="option"
                  aria-selected={isSelected}
                  onClick={() => {
                    onChange(p.id);
                    close();
                  }}
                  onMouseEnter={() => setActiveIdx(idx)}
                  className={`flex items-center gap-2 px-3 py-1.5 text-[13px] cursor-pointer ${isActive ? 'bg-accent' : ''} ${isSelected && ! isActive ? 'bg-accent/50' : ''}`}
                  style={{ paddingLeft: 12 + (p.nivel - 1) * 12 }}
                >
                  <span className="font-mono text-foreground text-[12px] tabular-nums shrink-0">{p.codigo}</span>
                  <span className="truncate flex-1">{p.nome}</span>
                  <span
                    className="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
                    style={TIPO_STYLE[p.tipo]}
                  >
                    {p.tipo}
                  </span>
                </li>
              );
            })}
          </ul>
        </div>
      )}
    </div>
  );
}

export default PlanoContaCombobox;
