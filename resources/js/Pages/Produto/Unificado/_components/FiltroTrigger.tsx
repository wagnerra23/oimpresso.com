/**
 * Gatilho de filtro da toolbar de índice — variante LEVE (handoff §4.5).
 *
 * Aparência de CAMPO, nunca de chip preenchido em repouso: fundo `--surface`, borda
 * `--border`, texto `--text-dim`, chevron a 60%, altura 30, raio 8. O hover só clareia. Chip
 * preenchido em repouso muda o peso visual da linha e foi reprovado na conferência de
 * regressão do handoff.
 *
 * Selecionado é o único estado que ganha cor — é informação (tem recorte aplicado), não
 * decoração.
 *
 * O protótipo entrega os cinco gatilhos SEM popover (pendência §14 item 3). Aqui eles abrem de
 * verdade, com as opções servidas pelo backend: gatilho que não abre nada é affordance
 * mentindo, e a lista de opções é dado do tenant, não decisão de design.
 */

import { useEffect, useRef, useState } from 'react';
import { Check, ChevronDown } from 'lucide-react';

export type OpcaoFiltro = { value: string; label: string };

export interface FiltroTriggerProps {
  label: string;
  value: string;
  options: ReadonlyArray<OpcaoFiltro>;
  onChange: (v: string) => void;
}

export function FiltroTrigger({ label, value, options, onChange }: FiltroTriggerProps) {
  const [aberto, setAberto] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!aberto) return;
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setAberto(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setAberto(false);
    };
    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [aberto]);

  const selecionada = options.find((o) => o.value === value);
  const texto = selecionada ? `${label}: ${selecionada.label}` : label;

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setAberto((v) => !v)}
        aria-haspopup="listbox"
        aria-expanded={aberto}
        className={
          'inline-flex items-center gap-1.5 h-[30px] px-[11px] rounded-lg border text-xs transition-colors ' +
          (selecionada
            ? 'border-primary/40 bg-primary/10 text-primary'
            : 'border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted/60')
        }
      >
        <span className="max-w-[180px] truncate">{texto}</span>
        <ChevronDown size={12} className="opacity-60 shrink-0" />
      </button>

      {aberto && (
        <div
          role="listbox"
          className="absolute z-50 top-full mt-1 left-0 min-w-[200px] max-h-[320px] overflow-y-auto rounded-md border border-border bg-background shadow-lg p-1"
        >
          {options.length === 0 && (
            <p className="px-2 py-2 text-xs text-muted-foreground">Nada cadastrado ainda.</p>
          )}
          {selecionada && (
            <button
              type="button"
              onClick={() => {
                onChange('');
                setAberto(false);
              }}
              className="w-full text-left rounded px-2 py-1.5 text-xs text-muted-foreground hover:bg-muted border-b border-border mb-0.5"
            >
              Limpar
            </button>
          )}
          {options.map((opt) => {
            const on = opt.value === value;
            return (
              <button
                key={opt.value}
                type="button"
                role="option"
                aria-selected={on}
                onClick={() => {
                  onChange(on ? '' : opt.value);
                  setAberto(false);
                }}
                className={
                  'w-full text-left rounded px-2 py-1.5 text-xs flex items-center gap-2 transition-colors ' +
                  (on ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted')
                }
              >
                <span className="flex-1 truncate">{opt.label}</span>
                {on && <Check size={11} className="text-primary shrink-0" />}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

export default FiltroTrigger;
