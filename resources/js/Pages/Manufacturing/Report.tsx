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
import AppShellV2 from '@/Layouts/AppShellV2';
import { Checkbox } from '@/Components/ui/checkbox';
import { fmt, num } from './_lib/formato';
import type { FiltrosRelatorio, LinhaRelatorio, Relatorio } from './_lib/tipos';
import FabricacaoAbas from './_components/FabricacaoAbas';
import GradeFabricacao, { type ColunaGrade } from './_components/GradeFabricacao';
import '../../../css/cowork-manufacturing-bundle.css';

interface Props {
  relatorio: Relatorio;
  filters: FiltrosRelatorio;
  permissions: { prod: boolean };
  producao: { total: number; rascunhos: number };
  recipes_count: number;
}

const ROUTE = '/manufacturing/report';

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

  // Mesmo guard de `Index.tsx::applyDateRange` — só recarrega quando as DUAS datas estão
  // preenchidas, ou as DUAS estão vazias. Um único campo preenchido dispararia um round-trip
  // que o backend ignora (`reportByProduct` só filtra por data com as duas presentes),
  // gastando request à toa. `onBlur`, não `onChange`, pelo mesmo motivo do irmão: o
  // `<input type="date">` já só emite `onChange` com data completa, mas o `onBlur` evita
  // disparar de novo quando o usuário só clicou pra fora sem mudar nada.
  const aplicarDatas = () => {
    if ((de && ate) || (!de && !ate)) {
      recarregar({});
    }
  };

  const { linhas, total } = relatorio;

  // Colunas como o `DataGrid` do protótipo (manufacturing-producao.jsx, relatório).
  const COLUNAS: ColunaGrade<LinhaRelatorio>[] = [
    { key: 'produto', label: 'Produto', render: (l) => l.nome },
    { key: 'ordens', label: 'Ordens', align: 'right', mono: true, render: (l) => l.ordens },
    { key: 'qtd', label: 'Quantidade', align: 'right', mono: true, render: (l) => `${num(l.quantidade, 2)} ${l.unidade}` },
    { key: 'custo', label: 'Custo total', align: 'right', mono: true, render: (l) => fmt(l.custo_total) },
    { key: 'medio', label: 'Custo médio', align: 'right', mono: true, render: (l) => fmt(l.custo_medio) },
    {
      key: 'pct',
      label: '% do período',
      align: 'right',
      render: (l) => (
        <span className="mfg-pct">
          <span className="mfg-bar-mini">
            <i style={{ width: `${l.percentual}%` }} />
          </span>
          <span className="mfg-num dim">{num(l.percentual, 0)}%</span>
        </span>
      ),
    },
  ];

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

      <div className="mfg-filters" data-contract="filtros">
        <Campo label="De" w={140}>
          <input
            className="mfg-inp"
            type="date"
            value={de}
            onChange={(e) => setDe(e.target.value)}
            onBlur={aplicarDatas}
          />
        </Campo>
        <Campo label="Até" w={140}>
          <input
            className="mfg-inp"
            type="date"
            value={ate}
            onChange={(e) => setAte(e.target.value)}
            onBlur={aplicarDatas}
          />
        </Campo>
        <label className="mfg-check">
          {/* ds/no-native-checkbox (eslint DS) — Checkbox canônico, não <input type="checkbox">.
              O protótipo (manufacturing-producao.jsx) usa nativo; aqui segue a regra do DS,
              igual às Checkbox de linha em Recipes.tsx. */}
          <Checkbox
            checked={soFinal}
            onCheckedChange={(v) => recarregar({ soFinal: v === true })}
          />
          Só finalizadas
        </label>
      </div>

      <div className="mfg-tablewrap" data-contract="lista">
        {linhas.length > 0 ? (
          <GradeFabricacao<LinhaRelatorio>
            caption="Relatório de produção"
            colunas={COLUNAS}
            linhas={linhas}
            idDe={(l) => l.recipe_id}
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

function Campo({ label, w, children }: { label: string; w: number; children: ReactNode }) {
  return (
    <label className="mfg-fld" style={{ width: w }}>
      <span>{label}</span>
      {children}
    </label>
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
