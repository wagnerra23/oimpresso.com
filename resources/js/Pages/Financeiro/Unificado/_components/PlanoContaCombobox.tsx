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

const TIPO_HUE: Record<PlanoConta['tipo'], string> = {
  receita:    'text-emerald-700 bg-emerald-50',
  ativo:      'text-emerald-700 bg-emerald-50',
  despesa:    'text-rose-700 bg-rose-50',
  custo:      'text-amber-700 bg-amber-50',
  passivo:    'text-rose-700 bg-rose-50',
  patrimonio: 'text-blue-700 bg-blue-50',
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
        className="w-full h-9 rounded-md border border-stone-300 bg-white px-3 text-left text-[13px] flex items-center justify-between gap-2 hover:border-stone-400 disabled:bg-stone-100 disabled:cursor-not-allowed"
        aria-haspopup="listbox"
        aria-expanded={open}
      >
        {selecionado ? (
          <span className="flex items-center gap-2 truncate">
            <span className="font-mono text-stone-700 text-[12px] tabular-nums">{selecionado.codigo}</span>
            <span className="truncate">{selecionado.nome}</span>
            <span className={`shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${TIPO_HUE[selecionado.tipo]}`}>
              {selecionado.tipo}
            </span>
          </span>
        ) : (
          <span className="text-stone-400">{placeholder ?? '(Sem plano de contas)'}</span>
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
            className="shrink-0 text-stone-400 hover:text-stone-700 cursor-pointer"
          >
            <X size={14} />
          </span>
        )}
      </button>

      {open && (
        <div className="absolute z-20 left-0 right-0 mt-1 rounded-md border border-stone-200 bg-white shadow-lg max-h-[280px] flex flex-col">
          <div className="flex items-center gap-2 px-3 py-2 border-b border-stone-200">
            <Search size={14} className="text-stone-400" />
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
              <li className="px-3 py-4 text-center text-[12px] text-stone-500">
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
                  className={`flex items-center gap-2 px-3 py-1.5 text-[13px] cursor-pointer hover:bg-stone-50 ${isSelected ? 'bg-stone-100' : ''}`}
                  style={{ paddingLeft: 12 + (p.nivel - 1) * 12 }}
                >
                  <span className="font-mono text-stone-700 text-[12px] tabular-nums shrink-0">{p.codigo}</span>
                  <span className="truncate flex-1">{p.nome}</span>
                  <span className={`shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${TIPO_HUE[p.tipo]}`}>
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
