/**
 * Gatilho de filtro da toolbar de índice — variante NEUTRA (handoff V2 §4.3).
 *
 * Em repouso o gatilho não tem moldura: borda e fundo transparentes, texto `--text-dim`,
 * chevron 11px. É a mudança do pacote 19/08 sobre o 18/08 — lá cada gatilho tinha cara de
 * campo (`bg-card` + borda), e seis campos vazios lado a lado davam à faixa de filtros mais
 * peso visual do que a lista que ela filtra. Sem moldura, a faixa vira texto até alguém
 * mexer nela.
 *
 * Três estados, nesta ordem de peso:
 *   repouso      → transparente, `--text-dim`
 *   hover/foco   → fundo `--bg-2` a 50%, texto `--text` (transição 150ms)
 *   com valor    → borda accent 40%, fundo accent 10%, texto accent, rótulo `Categoria: X`
 *
 * Cor só no estado COM VALOR, que é informação (tem recorte aplicado), não decoração.
 * Altura 32 pra bater a grade 4/8 do pacote.
 *
 * O protótipo entrega os gatilhos SEM popover (pendência §14 item 3). Aqui eles abrem de
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
          'inline-flex items-center gap-1 h-8 px-2.5 rounded-md border text-xs font-medium transition-colors ' +
          (selecionada
            ? 'border-primary/40 bg-primary/10 text-primary'
            // Repouso SEM moldura. `border-transparent` (e não ausência de borda) mantém a
            // altura estável entre os estados: trocar pra `border-primary/40` no selecionado
            // não pode empurrar a linha 2px.
            : 'border-transparent bg-transparent text-muted-foreground hover:bg-muted/50 hover:text-foreground')
        }
      >
        <span className="max-w-[180px] truncate">{texto}</span>
        <ChevronDown size={11} className="opacity-60 shrink-0" />
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
