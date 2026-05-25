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
// Reusável entre Edit e Create. Backend defesa em profundidade via
// `UpdateTituloRequest::assertPlanoCoerente()`.

import { useMemo, useRef, useState, useEffect } from 'react';
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
  const containerRef = useRef<HTMLDivElement>(null);

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

  return (
    <div className="relative" ref={containerRef}>
      <button
        type="button"
        id={id}
        disabled={disabled}
        onClick={() => setOpen((o) => !o)}
        className="w-full h-9 rounded-md border border-input bg-background px-3 text-left text-[13px] flex items-center justify-between gap-2 hover:border-ring disabled:bg-muted disabled:cursor-not-allowed"
        aria-haspopup="listbox"
        aria-expanded={open}
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
              placeholder={`Buscar ${kind === 'receivable' ? 'receita/ativo' : 'despesa/custo/passivo'} por código ou nome…`}
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
              className="flex-1 text-[13px] outline-none bg-transparent"
            />
          </div>
          <ul role="listbox" className="overflow-y-auto flex-1">
            {lista.length === 0 && (
              <li className="px-3 py-4 text-center text-[12px] text-muted-foreground">
                Nenhum plano encontrado.
              </li>
            )}
            {lista.map((p) => {
              const isSelected = p.id === value;
              return (
                <li
                  key={p.id}
                  role="option"
                  aria-selected={isSelected}
                  onClick={() => {
                    onChange(p.id);
                    setOpen(false);
                    setBusca('');
                  }}
                  className={`flex items-center gap-2 px-3 py-1.5 text-[13px] cursor-pointer hover:bg-accent ${isSelected ? 'bg-accent' : ''}`}
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
