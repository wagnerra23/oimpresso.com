// Os 4 indicadores do topo da lista — `kpi` + `KpiCard` do protótipo (produto-blade.jsx, vista
// `lista`). Nada é calculado aqui: os números chegam prontos de
// ProductController::buildProdutoListaKpis, sobre o catálogo INTEIRO (não seguem os filtros).

import KpiCard from '@/Components/shared/KpiCard';
import { brl } from '../../Unificado/_components/catalogo';
import type { KpisLista } from './tipos';

const horaDe = (iso: string | null) =>
  iso ? new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';

export default function IndicadoresLista({ kpis }: { kpis: KpisLista }) {
  return (
    <>
      <KpiCard
        label="Produtos cadastrados"
        value={kpis.total}
        description={`${kpis.variaveis} ${kpis.variaveis === 1 ? 'variável' : 'variáveis'} · ${kpis.composicoes} ${kpis.composicoes === 1 ? 'composição' : 'composições'}`}
      />
      <KpiCard
        label="Abaixo do alerta"
        value={kpis.abaixo_do_alerta}
        tone={kpis.abaixo_do_alerta > 0 ? 'danger' : 'default'}
        description="quantidade de alerta atingida"
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
      <KpiCard label="Inativos" value={kpis.inativos} tone="warning" description="fora da venda, dentro do histórico" />
    </>
  );
}
