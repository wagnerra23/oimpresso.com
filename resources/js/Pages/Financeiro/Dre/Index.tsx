// @memcofre
//   tela: /financeiro/dre
//   module: Financeiro
//   status: live
//   stories: US-FIN-014a, US-FIN-014d (balanco), US-FIN-014e (balancete)
//   adrs: ui/0114, 0093, 0104, 0107, 0109
//   tests: Modules/Financeiro/Tests/Feature/DreControllerTest, DreBalancoBalanceteTest
//
// Canon: prototipo-ui/cowork/Wagner/financeiro-telas-extras.jsx (TelaDRE linha 361-483)
// Visual-comparison: memory/requisitos/Financeiro/dre-visual-comparison.md (status: approved 2026-05-20)
// Charter: ./Index.charter.md (entregue via PR A #1266)
//
// Fase 4 deprecação legacy (2026-05-21): tabs Balanço + Balancete absorvem
// telas legacy `/account/balance-sheet` e `/account/trial-balance` (redirects
// 301 via PR #1283). Versão GERENCIAL — banner obrigatório.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Download } from 'lucide-react';
import { BalancoView, type BalancoData } from './_components/BalancoView';
import { BalanceteView, type BalanceteData } from './_components/BalanceteView';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';

// ---------- Tipos das linhas (espelha DRE_LINES canon TelaDRE) ----------

interface LinhaH {
  type: 'h';
  label: string;
  kind: 'rec' | 'ded';
  v: number;
  prev: number;
  pct_rl: number;
  delta_pct: number;
}

interface LinhaI {
  type: 'i';
  label: string;
  indent: number;
  v: number;
  prev: number;
  pct_rl: number;
  delta_pct: number;
}

interface LinhaSubtotal {
  type: 'subtotal';
  label: string;
  v: number;
  prev: number;
  pct_rl: number;
  delta_pct: number;
  highlight?: boolean;
}

type Linha = LinhaH | LinhaI | LinhaSubtotal;

// Fase 4 (2026-05-21): tabs Demonstrativo / Balanço / Balancete
type AbaAtiva = 'demonstrativo' | 'balanco' | 'balancete';

// ---------- Props vindas do DreController (PR B) ----------

interface Props {
  meta: {
    periodo_label: string;       // ex "Maio 2026"
    periodo_label_prev: string;  // ex "Abr/2026"
    base_rl: number;             // Receita Líquida (=100% denominador)
    business_name: string;       // ex "ROTA LIVRE"
    aviso_sem_mapping: boolean;  // banner amber se categorias sem hierarquia
  };
  linhas: Linha[];
  margem_operacional: {
    atual_pct: number;
    meta_pct: number;            // 12.0 hardcode F1 (US-FIN-DRE-META backlog config tenant)
    prev_pct: number;
    delta_pp: number;
  };
  top_categorias_receita: { label: string; valor: number; pct: number }[];
  // Fase 4 — opcionais (só preenche tab ativa)
  aba?: AbaAtiva;
  balanco?: BalancoData | null;
  balancete?: BalanceteData | null;
}

// ---------- Helpers ----------

const brl = (v: number): string =>
  new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  }).format(v ?? 0);

const brlNoSign = (v: number): string => brl(v).replace('R$', '').trim();

// Extrai "Mai" + "2026" de "Maio 2026" (label do mês corrente)
// Usado pro cabeçalho da coluna do mês atual.
function shortMesAno(periodoLabel: string): string {
  const parts = periodoLabel.split(' ');
  const mes = parts[0]?.slice(0, 3) ?? '';
  const ano = parts[1]?.slice(-4) ?? '';
  return `${mes}/${ano}`;
}

// ---------- Tab Switcher ----------

function TabSwitcher({ aba }: { aba: AbaAtiva }) {
  // Fase 4 — pill segmented control consistente com Fluxo/Index.tsx pattern.
  // router.visit preserva scroll + replace na URL (?aba=X) pra deep-link funcionar.
  const trocaAba = (alvo: AbaAtiva) => {
    if (alvo === aba) return;
    // D-14: partial reload — troca de aba só re-busca aba/balanco/balancete
    // (demonstrativo meta/linhas/margem são closures no controller, pulam no partial).
    router.visit(`/financeiro/dre?aba=${alvo}`, {
      preserveScroll: true,
      replace: true,
      only: ['aba', 'balanco', 'balancete'],
    });
  };

  const items: { id: AbaAtiva; label: string; hint: string }[] = [
    { id: 'demonstrativo', label: 'Demonstrativo', hint: 'DRE' },
    { id: 'balanco', label: 'Balanço', hint: 'patrimonial' },
    { id: 'balancete', label: 'Balancete', hint: 'verificação' },
  ];

  return (
    <div className="px-6 pt-3 pb-1">
      {/* FIN-1 (2026-09-23): paleta fixa → tokens do tema (mesmo mapa do card abaixo). */}
      <div className="inline-flex bg-[var(--bg-2)] rounded-md p-0.5 border border-[var(--border)]">
        {items.map((it) => (
          <button
            key={it.id}
            type="button"
            onClick={() => trocaAba(it.id)}
            className={
              'h-8 px-4 rounded text-[length:var(--fs-3)] flex items-center gap-2 transition tabular-nums ' +
              (aba === it.id
                ? 'bg-[var(--surface)] shadow-sm font-medium text-[var(--text)]'
                : 'text-[var(--text-dim)] hover:text-[var(--text)]')
            }
            aria-pressed={aba === it.id}
          >
            <span>{it.label}</span>
            <span
              className={
                'text-[length:var(--fs-1)] uppercase tracking-wider ' +
                (aba === it.id ? 'text-[var(--text-dim)]' : 'text-[var(--text-mute)]')
              }
            >
              {it.hint}
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}

// ---------- Page ----------

function FinanceiroDre({
  meta,
  linhas,
  margem_operacional,
  top_categorias_receita,
  aba,
  balanco,
  balancete,
}: Props) {
  // F1: só "mes" funcional. Demais opções renderizam disabled com tooltip
  // "Em breve" (Q4 do visual-comparison; backlog US-FIN-DRE-PERIODOS).
  const [periodoTab] = useState<'mes' | 'trimestre' | 'ano' | '12m'>('mes');
  const abaAtiva: AbaAtiva = aba ?? 'demonstrativo';

  return (
    <div className="fin-curadoria vendas-aplus">
      {/* Topnav contextual módulo — copy-paste inline do bloco `os-page-h` de
          Unificado/Index.tsx:956-1043, adaptando handlers DRE-specific.
          Extração `<FinModuleTopnav>` shared = backlog US-FIN-TOPNAV-COMPONENT (Q8a). */}
      {/* Wave 4 (2026-05-25): migrado pra <PageHeader> canon v3.8 */}
      <PageHeader
        title="Financeiro"
        suffix={
          ' · ' +
          (abaAtiva === 'balanco'
            ? 'Balanço Patrimonial'
            : abaAtiva === 'balancete'
              ? 'Balancete de Verificação'
              : 'DRE / Relatórios')
        }
        subtitle={<>{meta.periodo_label} · {meta.business_name} · caixa unificado</>}
      >
        <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
          {/* ADR 0180 Fase 5 refine — botões features → ⋯ Mais; primary `Novo título` separado */}
          <FinanceiroSubNav
            active="dre"
            hidePrimary
            extraOverflowItems={[
              // B6 "botões honestos" (2026-05-31): só permanece a ação que tem
              // capacidade real AGORA. Os stubs F1 (Buscar ⌘K / Resumir mês /
              // Fechamento / Apresentar) foram REMOVIDOS do render — não há
              // backend/handler ainda (roadmap vive no charter, não em botão
              // morto). Reentram quando a capacidade existir.
              {
                key: 'exportar-csv',
                label: 'Exportar CSV',
                icon: <Download size={13} />,
                onClick: () => {
                  // Rota real GET /financeiro/dre/export-csv (DreController::exportCsv,
                  // StreamedResponse BOM UTF-8). PDF + Excel já têm âncoras inline no card.
                  window.location.href = '/financeiro/dre/export-csv';
                },
                title: 'Baixar DRE em CSV (Excel BR)',
              },
            ]}
          />
          {/* FIN-1 (2026-09-23): rótulo "Novo título" = protótipo (TelaDRE / financeiro-page.jsx:224)
              e o mesmo do Financeiro/Dashboard, que já aponta pro mesmo destino. Medido na FIN-0a
              (dre-visual-comparison.md). Só o rótulo muda; destino e comportamento iguais. */}
          <PageHeaderPrimary
            label="Novo título"
            onClick={() => router.visit('/financeiro/unificado/novo')}
          />
        </div>
      </PageHeader>

      <TabSwitcher aba={abaAtiva} />

      {abaAtiva === 'balanco' && <BalancoView balanco={balanco ?? null} />}
      {abaAtiva === 'balancete' && <BalanceteView balancete={balancete ?? null} />}

      {abaAtiva === 'demonstrativo' && (
        <>
      {meta.aviso_sem_mapping && (
        <div
          style={{
            background: 'oklch(0.96 0.04 70)',
            border: '1px solid oklch(0.85 0.10 70)',
            borderRadius: 8,
            padding: '12px 16px',
            margin: '16px 24px',
            fontSize: 13,
            color: 'oklch(0.40 0.13 70)',
          }}
        >
          ⓘ Categorias não mapeadas hierarquicamente (codigo 1.1.x, 2.1.x, etc) —
          DRE mostra valores zerados.{' '}
          <a
            href="/financeiro/categorias"
            style={{ textDecoration: 'underline', fontWeight: 600 }}
          >
            Configurar plano de contas →
          </a>
        </div>
      )}

      {/* Card grande — Demonstração de Resultado hierárquica.
          FIN-1 (2026-09-23): paleta fixa stone-* / bg-white trocada pelos tokens do tema, espelhando
          TelaDRE (financeiro-telas-extras.jsx:455-530). Medido na FIN-0a: o cartão saía BRANCO no
          tema escuro. Apelidos do protótipo (styles.css:6269-6274) → nome vigente na produção:
          --sunken=--bg-2 · --hairline=--border-2 · --text-2=--text-dim · --text-3/4=--text-mute.
          `.fin-card`/`.fin-sysbtn`/`.fin-ink` não existem no CSS da produção: reproduzidos com
          utilitários (financeiro.css:777-783 e :1903-1910). Só className — valores e formatadores intactos. */}
      <div className="px-6 pt-4">
        <div className="bg-[var(--surface)] border border-[var(--border)] rounded-[11px] shadow-[var(--sh-1)] overflow-hidden">
          <div className="px-5 py-3 border-b border-[var(--border)] flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] font-medium whitespace-nowrap">
                Demonstração de Resultado
              </div>
              <div className="text-[length:var(--fs-5)] font-semibold mt-0.5 whitespace-nowrap">
                {meta.periodo_label}
              </div>
            </div>
            <div className="ml-auto flex items-center gap-2">
              {/* Pill toggle Mês/Trimestre/Ano/12m — só Mês funcional F1 (Q4). */}
              <div className="inline-flex bg-[var(--bg-2)] rounded-md p-0.5 border border-[var(--border)]">
                {(['Mês', 'Trimestre', 'Ano', '12m'] as const).map((p) => {
                  const ativo = p === 'Mês' && periodoTab === 'mes';
                  const disabled = p !== 'Mês';
                  return (
                    <button
                      key={p}
                      type="button"
                      disabled={disabled}
                      title={disabled ? 'Em breve' : undefined}
                      className={`h-7 px-3 rounded text-[length:var(--fs-3)] ${
                        ativo
                          ? 'bg-[var(--surface)] shadow-sm font-medium text-[var(--text)]'
                          : 'text-[var(--text-dim)]'
                      } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
                    >
                      {p}
                    </button>
                  );
                })}
              </div>
              <a
                href="/financeiro/dre/export-pdf"
                className="h-[30px] px-3 rounded-[7px] border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-3)] font-medium text-[var(--text-dim)] hover:bg-[var(--bg-2)] hover:text-[var(--text)] hover:border-[var(--text-mute)] inline-flex items-center gap-1.5 whitespace-nowrap"
              >
                <Download size={13} aria-hidden="true" /> PDF
              </a>
              <a
                href="/financeiro/dre/export-xlsx"
                className="h-[30px] px-3 rounded-[7px] border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-3)] font-medium text-[var(--text-dim)] hover:bg-[var(--bg-2)] hover:text-[var(--text)] hover:border-[var(--text-mute)] inline-flex items-center gap-1.5 whitespace-nowrap"
              >
                Excel
              </a>
            </div>
          </div>

          <table className="w-full text-[length:var(--fs-3)] tabular-nums">
            <thead>
              <tr className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] border-b border-[var(--border)] bg-[var(--bg-2)]">
                <th className="pl-6 pr-2 py-2 text-left font-medium">Conta</th>
                <th className="px-2 py-2 text-right font-medium w-[140px]">
                  {shortMesAno(meta.periodo_label)}
                </th>
                <th className="px-2 py-2 text-right font-medium w-[100px]">% RL</th>
                <th className="px-2 py-2 text-right font-medium w-[140px]">
                  {meta.periodo_label_prev}
                </th>
                <th className="px-2 py-2 text-right font-medium w-[80px]">Δ</th>
                <th className="pl-2 pr-6 py-2 w-[160px]"></th>
              </tr>
            </thead>
            <tbody>
              {linhas.map((l, i) => {
                if (l.type === 'h') {
                  return (
                    <tr key={i} className="border-b border-[var(--border-2)]">
                      <td className="pl-6 pr-2 py-2 font-medium text-[var(--text)]">
                        {l.label}
                      </td>
                      <td className="px-2 py-2 text-right font-semibold">
                        {brlNoSign(l.v)}
                      </td>
                      <td className="px-2 py-2 text-right text-[var(--text-dim)]">
                        {l.pct_rl.toFixed(1)}%
                      </td>
                      <td className="px-2 py-2 text-right text-[var(--text-dim)]">
                        {brlNoSign(l.prev)}
                      </td>
                      <td
                        className={`px-2 py-2 text-right ${
                          l.delta_pct > 0
                            ? 'text-[var(--pos)]'
                            : l.delta_pct < 0
                              ? 'text-[var(--neg)]'
                              : 'text-[var(--text-mute)]'
                        }`}
                      >
                        {l.delta_pct > 0 ? '+' : ''}
                        {l.delta_pct.toFixed(0)}%
                      </td>
                      <td className="pl-2 pr-6"></td>
                    </tr>
                  );
                }
                if (l.type === 'i') {
                  const positive = l.v >= 0;
                  return (
                    <tr key={i} className="border-b border-[var(--border-2)] row-hover">
                      <td
                        className="pl-6 pr-2 py-1.5 text-[var(--text-dim)]"
                        style={{ paddingLeft: 24 + l.indent * 16 }}
                      >
                        {l.label}
                      </td>
                      <td className="px-2 py-1.5 text-right text-[var(--text)]">
                        {brlNoSign(l.v)}
                      </td>
                      <td className="px-2 py-1.5 text-right text-[var(--text-mute)]">
                        {l.pct_rl.toFixed(1)}%
                      </td>
                      <td className="px-2 py-1.5 text-right text-[var(--text-mute)]">
                        {brlNoSign(l.prev)}
                      </td>
                      <td
                        className={`px-2 py-1.5 text-right text-[length:var(--fs-2)] ${
                          l.delta_pct > 0
                            ? 'text-[var(--pos)]'
                            : l.delta_pct < 0
                              ? 'text-[var(--neg)]'
                              : 'text-[var(--text-mute)]'
                        }`}
                      >
                        {l.delta_pct > 0 ? '+' : ''}
                        {l.delta_pct.toFixed(0)}%
                      </td>
                      <td className="pl-2 pr-6 py-1.5">
                        <div className="h-1 bg-[var(--bg-2)] rounded-full overflow-hidden">
                          <div
                            className={`h-full ${positive ? 'bg-[var(--pos)]' : 'bg-[var(--neg)]'}`}
                            style={{
                              width: `${Math.min(100, Math.abs(l.pct_rl) * 3)}%`,
                            }}
                          />
                        </div>
                      </td>
                    </tr>
                  );
                }
                // subtotal
                const positive = l.v >= 0;
                return (
                  <tr
                    key={i}
                    className={`border-y-2 border-[var(--border)] ${
                      // .fin-ink do protótipo (financeiro.css:777-778): não há token equivalente
                      // na produção, então os dois valores vêm literais, com a variante do tema escuro.
                      l.highlight
                        ? 'bg-[oklch(0.24_0.012_80)] dark:bg-[oklch(0.32_0.014_80)] text-white'
                        : 'bg-[var(--bg-2)]'
                    }`}
                  >
                    <td
                      className={`pl-6 pr-2 py-2.5 font-semibold ${l.highlight ? 'text-white' : ''}`}
                    >
                      {l.label}
                    </td>
                    <td
                      className={`px-2 py-2.5 text-right font-bold text-[length:var(--fs-4)] ${
                        l.highlight
                          ? 'text-white'
                          : positive
                            ? 'text-[var(--pos)]'
                            : 'text-[var(--neg)]'
                      }`}
                    >
                      {brlNoSign(l.v)}
                    </td>
                    <td
                      className={`px-2 py-2.5 text-right font-medium ${
                        l.highlight ? 'text-[var(--text-mute)]' : 'text-[var(--text-dim)]'
                      }`}
                    >
                      {l.pct_rl.toFixed(1)}%
                    </td>
                    <td
                      className={`px-2 py-2.5 text-right ${
                        l.highlight ? 'text-[var(--text-mute)]' : 'text-[var(--text-dim)]'
                      }`}
                    >
                      {brlNoSign(l.prev)}
                    </td>
                    <td
                      className={`px-2 py-2.5 text-right font-semibold ${
                        l.delta_pct > 0
                          ? 'text-[var(--pos)]'
                          : l.delta_pct < 0
                            ? 'text-[var(--neg)]'
                            : ''
                      }`}
                    >
                      {l.delta_pct > 0 ? '+' : ''}
                      {l.delta_pct.toFixed(0)}%
                    </td>
                    <td className="pl-2 pr-6"></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Bottom grid 2-col — Margem operacional + Top categorias receita */}
      <div className="px-6 mt-4 mb-4 grid grid-cols-2 gap-4">
        <div className="bg-[var(--surface)] border border-[var(--border)] rounded-[11px] shadow-[var(--sh-1)] p-5">
          <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] font-medium">
            Margem operacional
          </div>
          <div className="mt-1 text-[length:var(--fs-8)] font-semibold tracking-tight tabular-nums">
            {margem_operacional.atual_pct.toFixed(1)}%
          </div>
          <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-dim)]">
            vs <span className="tabular-nums">{margem_operacional.prev_pct.toFixed(1)}%</span>{' '}
            em {meta.periodo_label_prev.toLowerCase()} ·{' '}
            <span
              className={`font-medium ${
                margem_operacional.delta_pp >= 0 ? 'text-[var(--pos)]' : 'text-[var(--neg)]'
              }`}
            >
              {margem_operacional.delta_pp >= 0 ? '+' : ''}
              {margem_operacional.delta_pp.toFixed(1)}pp
            </span>
          </div>
          <div className="mt-4 h-2 bg-[var(--bg-2)] rounded-full overflow-hidden">
            <div
              className="h-full bg-[var(--accent)]"
              style={{
                width: `${Math.max(0, Math.min(100, margem_operacional.atual_pct))}%`,
              }}
            />
          </div>
          <div className="mt-1.5 flex justify-between text-[length:var(--fs-1)] text-[var(--text-mute)]">
            <span>0%</span>
            <span>meta {margem_operacional.meta_pct.toFixed(0)}%</span>
            <span>100%</span>
          </div>
        </div>

        <div className="bg-[var(--surface)] border border-[var(--border)] rounded-[11px] shadow-[var(--sh-1)] p-5">
          <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] font-medium">
            Top categorias receita · {meta.periodo_label.toLowerCase()}
          </div>
          <div className="mt-3 space-y-2.5">
            {top_categorias_receita.map((c) => (
              <div key={c.label}>
                <div className="flex items-baseline justify-between text-[length:var(--fs-3)]">
                  <span className="text-[var(--text)]">{c.label}</span>
                  <span className="tabular-nums font-medium">
                    {brl(c.valor)}{' '}
                    <span className="text-[var(--text-mute)]">· {c.pct.toFixed(0)}%</span>
                  </span>
                </div>
                <div className="mt-1 h-1 bg-[var(--bg-2)] rounded-full overflow-hidden">
                  <div
                    className="h-full bg-[var(--pos)]"
                    style={{ width: `${c.pct}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
        </>
      )}
    </div>
  );
}

FinanceiroDre.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro — DRE gerencial"
    breadcrumbItems={[
      { label: 'Financeiro', href: '/financeiro' },
      { label: 'DRE' },
    ]}
  >
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);

export default FinanceiroDre;
