// US-FIN-075 — seletor "Centro de custo" dos relatórios do Financeiro (DRE, Fluxo realizado).
// O filtro é a query `?centro_custo=` (vazio = todos · `sem` = só títulos sem rateio · id).
// Troca por partial reload só das props que o filtro muda; o resto da query fica.

import { router } from '@inertiajs/react';

export interface CentroCustoOpcao {
  id: number;
  codigo: string | null;
  nome: string;
  ativo: boolean;
}

interface Props {
  centros: CentroCustoOpcao[];
  /** null = todos · 0 = sem centro · id */
  valor: number | null;
  /** props que o servidor recalcula com o filtro (partial reload) */
  only: string[];
}

/** URL atual com `centro_custo` trocado (preserva aba/tab e o resto da query). */
export function urlComCentro(valor: string): string {
  const url = new URL(window.location.href);
  if (valor === '') url.searchParams.delete('centro_custo');
  else url.searchParams.set('centro_custo', valor);
  return url.pathname + url.search;
}

export function CentroCustoSelect({ centros, valor, only }: Props) {
  if (centros.length === 0) return null;

  const atual = valor === null ? '' : valor === 0 ? 'sem' : String(valor);
  return (
    <select
      id="filtro-centro-custo"
      aria-label="Centro de custo"
      value={atual}
      onChange={(e) =>
        router.visit(urlComCentro(e.target.value), { preserveScroll: true, preserveState: true, replace: true, only: [...only, 'centro_custo'] })
      }
      className="h-[30px] max-w-[220px] rounded-[7px] border border-[var(--border)] bg-[var(--surface)] px-2 text-[length:var(--fs-3)] text-[var(--text)]"
    >
      <option value="">Todos os centros</option>
      <option value="sem">Sem centro</option>
      {centros.map((c) => (
        <option key={c.id} value={c.id}>{`${c.codigo ? `${c.codigo} · ` : ''}${c.nome}${c.ativo ? '' : ' (inativo)'}`}</option>
      ))}
    </select>
  );
}
