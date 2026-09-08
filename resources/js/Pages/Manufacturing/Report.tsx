// Manufacturing/Report — o relatório de produção do período, em `/manufacturing/report`.
//
// FONTE DE DESIGN: `prototipo-ui/cowork/manufacturing-producao.jsx::MfgRelatorio` — mesmo
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

import { Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Checkbox } from '@/Components/ui/checkbox';
import { fmt, num } from './_lib/formato';
import type { FiltrosRelatorio, Relatorio } from './_lib/tipos';
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

  return (
    <div className="mfg-root" data-screen-label="Manufacturing · Relatório">
      <div className="os-page-h" data-contract="cabecalho">
        <div className="os-page-h-l">
          <h1>Manufacturing</h1>
          <p>Relatório de produção do período · custo agrupado por produto</p>
        </div>
      </div>

      {/* Mesma aba do módulo que Recipes.tsx — "Relatório" ativa aqui. */}
      <nav className="mfg-tabs" aria-label="Manufacturing">
        <Link className="mfg-tab" href="/manufacturing/recipe">
          Receitas
          <span className="mfg-tab-n">{recipes_count}</span>
        </Link>
        <Link className="mfg-tab" href="/manufacturing/insumos">
          Insumos
        </Link>
        {permissions.prod && (
          <Link className="mfg-tab" href="/manufacturing/production">
            Ordens de produção
            <span className="mfg-tab-n">
              {producao.total}
              {producao.rascunhos ? ` · ${producao.rascunhos} rasc.` : ''}
            </span>
          </Link>
        )}
        <span className="mfg-tab act" aria-current="page">
          Relatório
        </span>
        {/* Ver a nota em Recipes.tsx: era âncora crua pra rota Blade legada, que saía do
            SPA. O cutover da rota legada segue decisão [W]. */}
        <Link className="mfg-tab" href="/manufacturing/settings">
          Configurações
        </Link>
      </nav>

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
        <div className="mfg-table rep">
          <div className="mfg-tr mfg-thead">
            <span className="mfg-th">Produto</span>
            <span className="mfg-th r">Ordens</span>
            <span className="mfg-th r">Quantidade</span>
            <span className="mfg-th r">Custo total</span>
            <span className="mfg-th r">Custo médio</span>
            <span className="mfg-th r">% do período</span>
          </div>

          {linhas.map((l) => (
            <div className="mfg-tr" key={l.recipe_id}>
              <span className="mfg-name">
                <b>{l.nome}</b>
              </span>
              <span className="mfg-num dim r">{l.ordens}</span>
              <span className="mfg-num r">
                {num(l.quantidade, 2)}
                <span className="mfg-u">{l.unidade}</span>
              </span>
              <span className="mfg-num r">{fmt(l.custo_total)}</span>
              <span className="mfg-num dim r">{fmt(l.custo_medio)}</span>
              <span className="r">
                <span className="mfg-bar-mini">
                  <i style={{ width: `${l.percentual}%` }} />
                </span>
                <span className="mfg-num dim">{num(l.percentual, 0)}%</span>
              </span>
            </div>
          ))}

          {linhas.length === 0 && (
            <div className="mfg-empty">
              <b>Sem produção no período</b>
              <span>Ajuste as datas ou inclua os rascunhos.</span>
            </div>
          )}
        </div>

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
    title="Relatório · Manufacturing"
    breadcrumbItems={[{ label: 'Manufacturing' }, { label: 'Relatório' }]}
  >
    {page}
  </AppShellV2>
);
