// @memcofre
//   tela: /financeiro/dre
//   module: Financeiro
//   status: live
//   stories: US-FIN-014a
//   adrs: ui/0114, 0093, 0104, 0107, 0109
//   tests: Modules/Financeiro/Tests/Feature/DreControllerTest
//
// Canon: public/cowork-preview/erp-shell/financeiro-telas-extras.jsx (TelaDRE linha 361-483)
// Visual-comparison: memory/requisitos/Financeiro/dre-visual-comparison.md (status: approved 2026-05-20)
// Charter: ./Index.charter.md (entregue via PR A #1266)
//
// Reaplicação canon DRE — PR C (frontend). PR B (backend DreController + DreService
// + rotas) é paralelo e merge antes deste virar runtime-ativo. Sem PR B, esta Page
// nunca recebe Props (rota /financeiro/dre não existe ainda).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import {
  Search,
  Sparkles,
  CheckSquare,
  Play,
  RefreshCw,
  FolderOpen,
  Download,
  Plus,
} from 'lucide-react';

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

// ---------- Page ----------

function FinanceiroDre({
  meta,
  linhas,
  margem_operacional,
  top_categorias_receita,
}: Props) {
  // F1: só "mes" funcional. Demais opções renderizam disabled com tooltip
  // "Em breve" (Q4 do visual-comparison; backlog US-FIN-DRE-PERIODOS).
  const [periodoTab] = useState<'mes' | 'trimestre' | 'ano' | '12m'>('mes');

  return (
    <div className="fin-curadoria vendas-aplus">
      {/* Topnav contextual módulo — copy-paste inline do bloco `os-page-h` de
          Unificado/Index.tsx:956-1043, adaptando handlers DRE-specific.
          Extração `<FinModuleTopnav>` shared = backlog US-FIN-TOPNAV-COMPONENT (Q8a). */}
      <header className="os-page-h fin-page-h">
        <div className="os-page-h-l fin-page-h-l">
          <h1>
            Financeiro <span className="fin-hero-title-sub">· DRE / Relatórios</span>
          </h1>
          <p>
            {meta.periodo_label} · {meta.business_name} · caixa unificado
          </p>
        </div>
        <div className="os-page-h-r fin-page-h-r">
          <button
            type="button"
            className="os-btn ghost"
            // TODO US-FIN-TOPNAV-COMPONENT: integrar Cmd palette compartilhada.
            onClick={() => {
              /* stub F1 — Cmd palette compartilhada vira F2 */
            }}
          >
            <Search size={13} /> Buscar <kbd>⌘K</kbd>
          </button>
          <button
            type="button"
            className="os-btn ghost fin-btn-ai"
            title="Resumo executivo do mês (narrativa DRE compute-based)"
            // TODO US-FIN-DRE-RESUMO: extender FinMonthResumeDialog pra payload DRE.
            onClick={() => {
              /* stub F1 — narrativa DRE vira F2 */
            }}
          >
            <Sparkles size={13} /> Resumir mês
          </button>
          <button
            type="button"
            className="os-btn ghost fin-btn-trilha"
            title="Trilha de 12 passos do fechamento mensal"
            // TODO US-FIN-DRE-FECHAMENTO: integrar FinChecklistFechamento.
            onClick={() => {
              /* stub F1 — checklist fechamento vira F2 */
            }}
          >
            <CheckSquare size={13} /> Fechamento
          </button>
          <button
            type="button"
            className="os-btn ghost fin-btn-present"
            title="Modo apresentação fullscreen (Esc fecha)"
            // TODO US-FIN-DRE-PRESENT: integrar FinPresentationMode com payload DRE.
            onClick={() => {
              /* stub F1 — modo apresentação vira F2 */
            }}
          >
            <Play size={13} /> Apresentar
          </button>
          <button
            type="button"
            className="os-btn ghost"
            title="Conciliação OFX — upload extrato + match com títulos"
            onClick={() => router.visit('/financeiro/conciliacao')}
          >
            <RefreshCw size={13} /> Conciliar
          </button>
          <button
            type="button"
            className="os-btn ghost"
            title="Plano de contas hierárquico BR (Receita Federal/DCASP)"
            onClick={() => router.visit('/financeiro/plano-contas')}
          >
            <FolderOpen size={13} /> Plano de contas
          </button>
          <button
            type="button"
            className="os-btn ghost"
            title="Exportar DRE (XLSX / PDF)"
            aria-label="Exportar"
            // Atalho rápido — duplica os botões Exportar do Card pro padrão canon
            // (stub F1; F2 abre Cmd palette com opções de export).
            onClick={() => {
              /* stub F1 */
            }}
            style={{ padding: '0 8px' }}
          >
            <Download size={13} />
          </button>
          <button
            type="button"
            className="os-btn primary"
            onClick={() => router.visit('/financeiro/unificado/novo')}
          >
            <Plus size={13} /> Novo lançamento
          </button>
        </div>
      </header>

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

      {/* Card grande — Demonstração de Resultado hierárquica */}
      <div className="px-6 pt-4">
        <div className="bg-white border border-stone-200 rounded-md shadow-sm overflow-hidden">
          <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">
                Demonstração de Resultado
              </div>
              <div className="text-[16px] font-semibold mt-0.5 whitespace-nowrap">
                {meta.periodo_label}
              </div>
            </div>
            <div className="ml-auto flex items-center gap-2">
              {/* Pill toggle Mês/Trimestre/Ano/12m — só Mês funcional F1 (Q4). */}
              <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
                {(['Mês', 'Trimestre', 'Ano', '12m'] as const).map((p) => {
                  const ativo = p === 'Mês' && periodoTab === 'mes';
                  const disabled = p !== 'Mês';
                  return (
                    <button
                      key={p}
                      type="button"
                      disabled={disabled}
                      title={disabled ? 'Em breve' : undefined}
                      className={`h-7 px-3 rounded text-[12.5px] ${
                        ativo ? 'bg-white shadow-sm font-medium' : 'text-stone-600'
                      } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
                    >
                      {p}
                    </button>
                  );
                })}
              </div>
              <a
                href="/financeiro/dre/export-pdf"
                className="h-8 px-3 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50 inline-flex items-center"
              >
                Exportar PDF
              </a>
              <a
                href="/financeiro/dre/export-xlsx"
                className="h-8 px-3 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50 inline-flex items-center"
              >
                Excel
              </a>
            </div>
          </div>

          <table className="w-full text-[12.5px] tabular-nums">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
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
                    <tr key={i} className="border-b border-stone-100">
                      <td className="pl-6 pr-2 py-2 font-medium text-stone-900">
                        {l.label}
                      </td>
                      <td className="px-2 py-2 text-right font-semibold">
                        {brlNoSign(l.v)}
                      </td>
                      <td className="px-2 py-2 text-right text-stone-500">
                        {l.pct_rl.toFixed(1)}%
                      </td>
                      <td className="px-2 py-2 text-right text-stone-500">
                        {brlNoSign(l.prev)}
                      </td>
                      <td
                        className={`px-2 py-2 text-right ${
                          l.delta_pct > 0
                            ? 'text-emerald-700'
                            : l.delta_pct < 0
                              ? 'text-rose-700'
                              : 'text-stone-400'
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
                    <tr key={i} className="border-b border-stone-100 row-hover">
                      <td
                        className="pl-6 pr-2 py-1.5 text-stone-600"
                        style={{ paddingLeft: 24 + l.indent * 16 }}
                      >
                        {l.label}
                      </td>
                      <td className="px-2 py-1.5 text-right text-stone-700">
                        {brlNoSign(l.v)}
                      </td>
                      <td className="px-2 py-1.5 text-right text-stone-400">
                        {l.pct_rl.toFixed(1)}%
                      </td>
                      <td className="px-2 py-1.5 text-right text-stone-400">
                        {brlNoSign(l.prev)}
                      </td>
                      <td
                        className={`px-2 py-1.5 text-right text-[11.5px] ${
                          l.delta_pct > 0
                            ? 'text-emerald-600'
                            : l.delta_pct < 0
                              ? 'text-rose-600'
                              : 'text-stone-400'
                        }`}
                      >
                        {l.delta_pct > 0 ? '+' : ''}
                        {l.delta_pct.toFixed(0)}%
                      </td>
                      <td className="pl-2 pr-6 py-1.5">
                        <div className="h-1 bg-stone-100 rounded-full overflow-hidden">
                          <div
                            className={`h-full ${positive ? 'bg-emerald-400' : 'bg-rose-400'}`}
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
                    className={`border-y-2 border-stone-200 ${
                      l.highlight ? 'bg-stone-900 text-white' : 'bg-stone-50'
                    }`}
                  >
                    <td
                      className={`pl-6 pr-2 py-2.5 font-semibold ${l.highlight ? 'text-white' : ''}`}
                    >
                      {l.label}
                    </td>
                    <td
                      className={`px-2 py-2.5 text-right font-bold text-[14px] ${
                        l.highlight
                          ? 'text-white'
                          : positive
                            ? 'text-emerald-700'
                            : 'text-rose-700'
                      }`}
                    >
                      {brlNoSign(l.v)}
                    </td>
                    <td
                      className={`px-2 py-2.5 text-right font-medium ${
                        l.highlight ? 'text-stone-300' : 'text-stone-600'
                      }`}
                    >
                      {l.pct_rl.toFixed(1)}%
                    </td>
                    <td
                      className={`px-2 py-2.5 text-right ${
                        l.highlight ? 'text-stone-400' : 'text-stone-600'
                      }`}
                    >
                      {brlNoSign(l.prev)}
                    </td>
                    <td
                      className={`px-2 py-2.5 text-right font-semibold ${
                        l.delta_pct > 0
                          ? 'text-emerald-400'
                          : l.delta_pct < 0
                            ? 'text-rose-400'
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
        <div className="bg-white border border-stone-200 rounded-md shadow-sm p-5">
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">
            Margem operacional
          </div>
          <div className="mt-1 text-[28px] font-semibold tracking-tight tabular-nums">
            {margem_operacional.atual_pct.toFixed(1)}%
          </div>
          <div className="mt-2 text-[11.5px] text-stone-500">
            vs <span className="tabular-nums">{margem_operacional.prev_pct.toFixed(1)}%</span>{' '}
            em {meta.periodo_label_prev.toLowerCase()} ·{' '}
            <span
              className={`font-medium ${
                margem_operacional.delta_pp >= 0 ? 'text-emerald-700' : 'text-rose-700'
              }`}
            >
              {margem_operacional.delta_pp >= 0 ? '+' : ''}
              {margem_operacional.delta_pp.toFixed(1)}pp
            </span>
          </div>
          <div className="mt-4 h-2 bg-stone-100 rounded-full overflow-hidden">
            <div
              className="h-full bg-stone-900"
              style={{
                width: `${Math.max(0, Math.min(100, margem_operacional.atual_pct))}%`,
              }}
            />
          </div>
          <div className="mt-1.5 flex justify-between text-[10.5px] text-stone-400">
            <span>0%</span>
            <span>meta {margem_operacional.meta_pct.toFixed(0)}%</span>
            <span>100%</span>
          </div>
        </div>

        <div className="bg-white border border-stone-200 rounded-md shadow-sm p-5">
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">
            Top categorias receita · {meta.periodo_label.toLowerCase()}
          </div>
          <div className="mt-3 space-y-2.5">
            {top_categorias_receita.map((c) => (
              <div key={c.label}>
                <div className="flex items-baseline justify-between text-[12.5px]">
                  <span className="text-stone-700">{c.label}</span>
                  <span className="tabular-nums font-medium">
                    {brl(c.valor)}{' '}
                    <span className="text-stone-400">· {c.pct.toFixed(0)}%</span>
                  </span>
                </div>
                <div className="mt-1 h-1 bg-stone-100 rounded-full overflow-hidden">
                  <div
                    className="h-full bg-emerald-500"
                    style={{ width: `${c.pct}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
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
