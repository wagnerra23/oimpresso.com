// Manufacturing/Report — o relatório de produção do período, em `/manufacturing/report`.
//
// FONTE DE DESIGN: `prototipo-ui/cowork/Wagner/manufacturing-producao.jsx::MfgRelatorio` — mesmo
// bundle já aplicado inteiro pela Onda 1 (Recipes.tsx); nenhuma classe CSS nova aqui.
// F1 PLAN: memory/requisitos/Manufacturing/RUNBOOK-report.md (inclui a prova algébrica do
// cálculo de custo — REGRA MESTRE de VALOR, proibicoes.md).
//
// US-MANU-002 (SPEC.md). Desde o cutover de 2026-09-04 este é o endereço canônico; o Blade
// antigo responde no MESMO endereço com `?legacy=1`.
//
// O agrupamento/filtro é SERVIDOR, não cliente (ao contrário de Recipes.tsx, que filtra um
// conjunto já carregado): De/Até/Só-finalizadas disparam `router.get` — o mesmo idioma que
// `Manufacturing/Index.tsx` já usa pros filtros de produção.

import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import AppShellV2 from '@/Layouts/AppShellV2';
import DataTable from '@/Components/shared/DataTable';
import { Checkbox } from '@/Components/ui/checkbox';
import { fmt, num } from './_lib/formato';
import type { FiltrosRelatorio, LinhaRelatorio, Relatorio } from './_lib/tipos';
import FabricacaoAbas from './_components/FabricacaoAbas';
import { Input } from '@/Components/ui/input';
import { Inline } from '@/Components/layout/inline';
import { Stack } from '@/Components/layout/stack';
import { CAMPO_DATA, ROTULO_CAMPO, ROTULO_CHECKBOX, intervaloAplicavel } from './_lib/filtros';
import '../../../css/cowork-manufacturing-bundle.css';

interface Props {
  relatorio: Relatorio;
  filters: FiltrosRelatorio;
  permissions: { prod: boolean };
  producao: { total: number; rascunhos: number };
  recipes_count: number;
}

const ROUTE = '/manufacturing/report';

/**
 * Colunas do `manufacturing-producao.jsx::MfgRelatorio`. Sem ordenação: o protótipo não declara
 * `sortable`, e a lista já chega ordenada pelo custo, do maior para o menor.
 * Todo número vem do servidor (`ProductionService::reportByProduct`); aqui só se formata.
 */
const COLUNAS: ColumnDef<LinhaRelatorio, unknown>[] = [
  { id: 'produto', accessorFn: (l) => l.nome, header: 'Produto' },
  { id: 'ordens', accessorFn: (l) => l.ordens, header: 'Ordens', meta: { align: 'right', mono: true } },
  {
    id: 'qtd',
    header: 'Quantidade',
    meta: { align: 'right', mono: true },
    cell: ({ row: { original: l } }) => `${num(l.quantidade, 2)} ${l.unidade}`,
  },
  {
    id: 'custo',
    header: 'Custo total',
    meta: { align: 'right', mono: true },
    cell: ({ row: { original: l } }) => fmt(l.custo_total),
  },
  {
    id: 'medio',
    header: 'Custo médio',
    meta: { align: 'right', mono: true },
    cell: ({ row: { original: l } }) => fmt(l.custo_medio),
  },
  {
    id: 'pct',
    header: '% do período',
    meta: { align: 'right' },
    // Barra + número, como o `Progress` + texto do protótipo. A barra é a local do bundle
    // (`.mfg-bar-mini`): o `Progress` do DS não tem par React (`prototipo-ui/design-system/HANDOFF.md`
    // §4, "Sem par no main"; medido 2026-10-04: nenhum `progress` em `Components/ui`).
    cell: ({ row: { original: l } }) => (
      <>
        <span className="mfg-bar-mini">
          <i style={{ width: `${l.percentual}%` }} />
        </span>
        <span className="mfg-num dim">{num(l.percentual, 0)}%</span>
      </>
    ),
  },
];

export default function Report({
  relatorio,
  filters,
  permissions,
  producao,
  recipes_count,
}: Props) {
  const [de, setDe] = useState(filters.start_date ?? '');
  const [ate, setAte] = useState(filters.end_date ?? '');
  const [soFinal, setSoFinal] = useState(filters.is_final);

  // Espelha `applyFilter`/`applyDateRange` de Index.tsx — is_final é flag de presença no
  // backend (`request()->boolean('is_final')`), sempre enviado aqui pra o default "ligado"
  // não depender de ausência de param (o servidor trata ausência como true — ver reportV2()).
  const recarregar = (patch: Partial<{ de: string; ate: string; soFinal: boolean }>) => {
    const next = {
      de: patch.de ?? de,
      ate: patch.ate ?? ate,
      soFinal: patch.soFinal ?? soFinal,
    };
    setDe(next.de);
    setAte(next.ate);
    setSoFinal(next.soFinal);
    router.get(
      ROUTE,
      {
        start_date: next.de || undefined,
        end_date: next.ate || undefined,
        is_final: next.soFinal ? 1 : 0,
      },
      { preserveState: true, preserveScroll: true, only: ['relatorio', 'filters'], replace: true },
    );
  };

  // D-MFG-DATA ([W] 2026-09-25): a data aplica AO ESCOLHER, igual à aba Ordens — não no `onBlur`,
  // que fazia o Relatório reagir diferente da aba ao lado. O guard é o mesmo (`intervaloAplicavel`):
  // só recarrega com as duas datas completas ou as duas vazias; um só campo não gasta request.
  const mudarDatas = (novoDe: string, novoAte: string) => {
    setDe(novoDe);
    setAte(novoAte);
    const intervalo = intervaloAplicavel(novoDe, novoAte);
    if (intervalo && (intervalo.de !== (filters.start_date ?? '') || intervalo.ate !== (filters.end_date ?? ''))) {
      recarregar(intervalo);
    }
  };

  const { linhas, total } = relatorio;

  return (
    <div className="mfg-root" data-screen-label="Fabricação · Relatório">
      <div className="os-page-h" data-contract="cabecalho">
        <div className="os-page-h-l">
          <h1>Fabricação</h1>
          <p>Relatório de produção do período · custo agrupado por produto</p>
        </div>
      </div>

      {/* Mesma aba do módulo que Recipes.tsx — "Relatório" ativa aqui. */}
      <FabricacaoAbas ativa="relatorio" receitas={recipes_count} producao={producao} podeProduzir={permissions.prod} />

      {/* Filtros com a MESMA peça da aba Ordens (`_lib/filtros.ts`): rótulo, campo de data 150×36 e
          "Só finalizadas". No protótipo as duas abas usam o mesmo `Campo` + `DatePicker`; aqui o
          Relatório ainda usava as classes antigas do bundle (data 140×32, canto 6) e a diferença
          aparecia ao trocar de aba ([M] 2026-10-06). */}
      <div className="px-5 py-3" data-contract="filtros">
        <Inline gap={3} align="end" wrap>
          <Inline gap={2} align="end">
            <Stack gap={1} asChild>
              <label htmlFor="mfg-rel-data-inicial">
                <span className={ROTULO_CAMPO}>De</span>
                <Input
                  variant="shadcn"
                  id="mfg-rel-data-inicial"
                  type="date"
                  value={de}
                  onChange={(e) => mudarDatas(e.target.value, ate)}
                  className={CAMPO_DATA}
                />
              </label>
            </Stack>
            <Stack gap={1} asChild>
              <label htmlFor="mfg-rel-data-final">
                <span className={ROTULO_CAMPO}>Até</span>
                <Input
                  variant="shadcn"
                  id="mfg-rel-data-final"
                  type="date"
                  value={ate}
                  onChange={(e) => mudarDatas(de, e.target.value)}
                  className={CAMPO_DATA}
                />
              </label>
            </Stack>
          </Inline>
          {/* ds/no-native-checkbox (eslint DS) — Checkbox canônico, como na aba Ordens. */}
          <Inline gap={2} align="center" asChild>
            <label className={ROTULO_CHECKBOX} htmlFor="mfg-rel-so-finalizadas">
              <Checkbox
                id="mfg-rel-so-finalizadas"
                checked={soFinal}
                onCheckedChange={(v) => recarregar({ soFinal: v === true })}
              />
              Só finalizadas
            </label>
          </Inline>
        </Inline>
      </div>

      <div className="mfg-tablewrap" data-contract="lista">
        {/* A grade é o `shared/DataTable` na anatomia `grid` — o par React do `DataGrid` do DS,
            como no protótipo (`manufacturing-producao.jsx::MfgRelatorio`, `pagination={false}`).
            Sem produção no período o protótipo NÃO desenha a grade: mostra só o vazio. */}
        {linhas.length > 0 ? (
          <DataTable<LinhaRelatorio>
            columns={COLUNAS}
            data={linhas}
            caption="Relatório de produção"
            density="grid"
            showSearch={false}
            rowKey={(l) => l.recipe_id}
          />
        ) : (
          <div className="mfg-empty">
            <b>Sem produção no período</b>
            <span>Ajuste as datas ou inclua os rascunhos.</span>
          </div>
        )}

        {linhas.length > 0 && (
          <p className="mfg-foot">
            Custo de produção do período <b>{fmt(total)}</b> · lançado como entrada de estoque no{' '}
            <b>Financeiro</b>
          </p>
        )}
      </div>
    </div>
  );
}

Report.layout = (page: ReactNode) => (
  <AppShellV2
    title="Relatório · Fabricação"
    breadcrumbItems={[{ label: 'Fabricação' }, { label: 'Relatório' }]}
  >
    {page}
  </AppShellV2>
);
