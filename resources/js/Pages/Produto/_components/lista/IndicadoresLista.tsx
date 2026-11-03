// Os 4 indicadores do topo da lista — `kpi` + `KpiCard` do protótipo (produto-blade.jsx, vista
// `lista`). Nada é calculado aqui: os números chegam prontos de
// ProductController::buildProdutoListaKpis, sobre o catálogo INTEIRO (não seguem os filtros).
//
// UC-PIDX-35 (item 2.1 de absorcao-v2-em-todos-os-produtos.md): "Abaixo do alerta" e "Inativos" filtram
// a lista ao clicar. São `variant="filter"` com anel de seleção e escrevem no MESMO estado dos filtros
// (o chip aparece em "Filtrando por"); o 2º clique desliga. "Produtos cadastrados" e "Valor em estoque"
// continuam de leitura, sem clique: placa que não recorta não pode parecer botão.

import KpiCard from '@/Components/shared/KpiCard';
import { brl } from '../../Unificado/_components/catalogo';
import type { FiltrosLista, KpisLista } from './tipos';

const horaDe = (iso: string | null) =>
  iso ? new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';

export default function IndicadoresLista({
  kpis,
  filtros,
  aplicar,
}: {
  kpis: KpisLista;
  filtros: FiltrosLista;
  aplicar: (patch: Partial<FiltrosLista>) => void;
}) {
  const soInativos = filtros.active_state === 'inactive';
  return (
    <>
      <KpiCard
        label="Produtos cadastrados"
        value={kpis.total}
        description={`${kpis.variaveis} ${kpis.variaveis === 1 ? 'variável' : 'variáveis'} · ${kpis.composicoes} ${kpis.composicoes === 1 ? 'composição' : 'composições'}`}
      />
      <KpiCard
        variant="filter"
        label="Abaixo do alerta"
        value={kpis.abaixo_do_alerta}
        icon="AlertTriangle"
        filterTone="rose"
        description="quantidade de alerta atingida"
        selected={filtros.abaixo_do_alerta}
        onClick={() => aplicar({ abaixo_do_alerta: !filtros.abaixo_do_alerta })}
      />
      {/* Sem a permissão de ver valor do estoque a chave não vem: ausência não é zero. */}
      {kpis.valor_custo === null ? (
        <KpiCard label="Valor em estoque (custo)" value="—" description="sem permissão para ver valores do estoque" />
      ) : (
        <KpiCard
          label="Valor em estoque (custo)"
          value={brl(kpis.valor_custo)}
          description={`pelo preço pago nas compras · total do relatório de estoque · calculado às ${horaDe(kpis.valor_calculado_em)}`}
        />
      )}
      <KpiCard
        variant="filter"
        label="Inativos"
        value={kpis.inativos}
        icon="Archive"
        filterTone="amber"
        description="fora da venda, dentro do histórico"
        selected={soInativos}
        onClick={() => aplicar({ active_state: soInativos ? null : 'inactive' })}
      />
    </>
  );
}
