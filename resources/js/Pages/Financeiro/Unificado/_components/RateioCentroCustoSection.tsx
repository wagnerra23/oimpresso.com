// US-FIN-075 — seção "Rateio por centro de custo" do drawer do título.
// Fonte de design: prototipo-ui/cowork/Wagner/financeiro-centro-custo-orcado.html (aba "Rateio no título").
// A conta é a de _lib/rateio.ts, a mesma do RateioService: a soma mostrada é a soma gravada.

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { X } from 'lucide-react';
import { deInteiro, distribuir, dividirIgual, type RateioLinha, type RateioModo } from '../_lib/rateio';

export interface CentroOpcao {
  id: number;
  codigo: string | null;
  nome: string;
  ativo: boolean;
}

interface Props {
  totalCentavos: number;
  modo: RateioModo;
  linhas: RateioLinha[];
  centros: CentroOpcao[];
  onChange: (modo: RateioModo, linhas: RateioLinha[]) => void;
  erro?: string;
}

const brl = (centavos: number) => (centavos / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const pct = (dezmil: number) => `${(dezmil / 10000).toLocaleString('pt-BR', { maximumFractionDigits: 4 })}%`;
const rotulo = (c: CentroOpcao) => `${c.codigo ? `${c.codigo} · ` : ''}${c.nome}${c.ativo ? '' : ' (inativo)'}`;

/** Ok de rateio para o drawer bloquear o salvar: sem linha = sem rateio, que é válido. */
export function rateioValido(totalCentavos: number, modo: RateioModo, linhas: RateioLinha[]): boolean {
  return linhas.length === 0 || distribuir(totalCentavos, modo, linhas).ok;
}

export function RateioCentroCustoSection({ totalCentavos, modo, linhas, centros, onChange, erro }: Props) {
  const r = distribuir(totalCentavos, modo, linhas);
  const usados = new Set(linhas.map((l) => l.centro_custo_id));
  // Na escolha entra todo centro ativo + o inativo que já está neste rateio.
  const escolhiveis = centros.filter((c) => c.ativo || usados.has(c.id));
  const livre = centros.find((c) => c.ativo && !usados.has(c.id));

  const mudarLinha = (i: number, campo: keyof RateioLinha, valor: string | number) =>
    onChange(modo, linhas.map((l, j) => (j === i ? { ...l, [campo]: valor } : l)));

  const trocarModo = (novo: RateioModo) => {
    if (novo === modo) return;
    // Leva o que já fecha para o outro modo, para a pessoa não redigitar.
    const convertidas = r.ok
      ? linhas.map((l, i) => ({ ...l, valor: deInteiro(r.valores[i], 2), percentual: deInteiro(r.pcts[i], 4) }))
      : linhas;
    onChange(novo, convertidas);
  };

  const somaCentavos = r.valores.reduce((a, b) => a + (Number.isNaN(b) ? 0 : b), 0);

  return (
    <section className="space-y-2.5 border-t pt-4" aria-labelledby="rateio-titulo">
      <div className="flex flex-wrap items-center gap-2">
        <h3 id="rateio-titulo" className="text-[13px] font-semibold m-0">Rateio por centro de custo</h3>
        <span className="text-[11px] text-muted-foreground">opcional</span>
        <div className="ml-auto inline-flex rounded-md border overflow-hidden" role="group" aria-label="Modo do rateio">
          {(['percentual', 'valor'] as const).map((m) => (
            <button
              key={m}
              type="button"
              aria-pressed={modo === m}
              onClick={() => trocarModo(m)}
              className={`h-7 px-2.5 text-[11.5px] ${modo === m ? 'bg-accent/15 text-foreground font-medium' : 'text-muted-foreground hover:bg-muted'}`}
            >
              {m === 'percentual' ? 'Por %' : 'Por valor'}
            </button>
          ))}
        </div>
      </div>

      {linhas.length === 0 ? (
        <p className="text-[12.5px] text-muted-foreground">Sem rateio: o título entra como “sem centro” nos relatórios.</p>
      ) : (
        <div className="space-y-2">
          {linhas.map((l, i) => (
            <div key={i} className="grid grid-cols-[minmax(0,1fr)_96px_110px_28px] gap-2 items-center">
              <select
                id={`rateio-centro-${i}`}
                aria-label={`Centro de custo da linha ${i + 1}`}
                value={l.centro_custo_id}
                onChange={(e) => mudarLinha(i, 'centro_custo_id', Number(e.target.value))}
                className="h-8 min-w-0 rounded-md border bg-background px-2 text-[12.5px]"
              >
                {escolhiveis
                  .filter((c) => c.id === l.centro_custo_id || !usados.has(c.id))
                  .map((c) => (
                    <option key={c.id} value={c.id}>{rotulo(c)}</option>
                  ))}
              </select>
              <Input
                id={`rateio-${modo}-${i}`}
                aria-label={`${modo === 'percentual' ? 'Percentual' : 'Valor'} da linha ${i + 1}`}
                inputMode="decimal"
                value={modo === 'percentual' ? l.percentual : l.valor}
                onChange={(e) => mudarLinha(i, modo, e.target.value)}
                className="h-8 text-right"
              />
              <span className="text-right text-[12.5px] text-muted-foreground tabular-nums">
                {modo === 'percentual'
                  ? Number.isNaN(r.valores[i]) ? '—' : brl(r.valores[i])
                  : r.valores[i] > 0 ? pct(r.pcts[i]) : '—'}
              </span>
              <button
                type="button"
                aria-label={`Remover linha ${i + 1}`}
                onClick={() => onChange(modo, linhas.filter((_, j) => j !== i))}
                className="text-muted-foreground hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          ))}
        </div>
      )}

      <div className="flex flex-wrap gap-2 items-center">
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={!livre}
          onClick={() => livre && onChange(modo, [...linhas, { centro_custo_id: livre.id, percentual: '', valor: '' }])}
        >
          + Adicionar centro
        </Button>
        <Button type="button" size="sm" variant="outline" disabled={linhas.length === 0} onClick={() => onChange(modo, dividirIgual(totalCentavos, modo, linhas))}>
          Dividir igual
        </Button>
        {linhas.length > 0 && (
          <button type="button" className="ml-auto text-[11.5px] text-muted-foreground underline underline-offset-2" onClick={() => onChange(modo, [])}>
            Remover rateio
          </button>
        )}
        {centros.length === 0 && (
          <span className="text-[11.5px] text-muted-foreground">
            Nenhum centro cadastrado. <a href="/financeiro/centros-custo" className="underline">Cadastrar centros</a>
          </span>
        )}
      </div>

      {linhas.length > 0 && (
        <div
          role="status"
          className={`flex flex-wrap gap-x-4 gap-y-1 items-center rounded-md px-3 py-2 text-[12.5px] ${
            r.ok ? 'bg-success/10 text-success' : 'bg-destructive/10 text-destructive'
          }`}
        >
          {r.ok ? (
            <>
              <span>Soma {modo === 'percentual' ? '100%' : brl(somaCentavos)}</span>
              <span className="ml-auto tabular-nums">{brl(somaCentavos)} de {brl(totalCentavos)} ✓</span>
            </>
          ) : modo === 'percentual' ? (
            <>
              <span>{r.falta > 0 ? `Faltam ${pct(r.falta)}` : `Passou ${pct(-r.falta)}`}</span>
              <span className="ml-auto">Os percentuais precisam somar 100%</span>
            </>
          ) : (
            <>
              <span>{r.falta > 0 ? `Faltam ${brl(r.falta)}` : `Passou ${brl(-r.falta)}`}</span>
              <span className="ml-auto">Os valores precisam somar {brl(totalCentavos)}</span>
            </>
          )}
        </div>
      )}
      {erro && <p className="text-xs text-destructive">{erro}</p>}
      <p className="text-[11.5px] text-muted-foreground">
        No modo %, os centavos que sobram do arredondamento vão para as linhas de maior resto. A soma das linhas é sempre o valor do título.
      </p>
    </section>
  );
}
