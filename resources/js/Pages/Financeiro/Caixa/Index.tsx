// @memcofre tela=/financeiro/caixa module=Financeiro
// Wagner 2026-05-21 Fase 6 deprecação legacy Soft (wrapper Inertia).
// Tela read-only sobre cash_registers core UltimatePOS. Lifecycle (abrir/fechar)
// continua na header POS — esta tela é descoberta + histórico no Financeiro.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, usePage } from '@inertiajs/react';
import { ReactNode, useMemo } from 'react';
import { Lightbulb } from 'lucide-react';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { PageHeader } from '@/Components/PageHeader';

interface CaixaRow {
  id: number;
  status: 'open' | 'close';
  open_time: string | null;
  close_time: string | null;
  closing_amount: number;
  total_credit: number;
  total_debit: number;
  total_card_slips: number;
  total_cheques: number;
  closing_note: string | null;
  user_id: number;
  user_name: string;
  location_id: number;
  location_name: string;
  // ADR 0183 PR C — ponte fin_titulos status integração
  fin_titulo_id: number | null;
  fin_titulo_status: string | null;
  fin_titulo_valor: number | null;
  integracao_status: 'lancado' | 'pendente' | 'nao_aplicavel';
}

interface Stats {
  total_caixas: number;
  caixas_abertos: number;
  soma_fechamentos: number;
}

interface Props {
  caixas: CaixaRow[];
  stats: Stats;
  filters: { status: string | null; limit: number };
  links: { pos_create: string; cash_register_legacy: string };
}

function fmtMoney(v: number): string {
  return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function fmtDateTime(s: string | null): string {
  if (!s) return '—';
  try {
    const d = new Date(s);
    return d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  } catch {
    return s;
  }
}

function statusBadge(status: 'open' | 'close'): { label: string; className: string } {
  if (status === 'open') {
    return {
      label: 'Aberto',
      className: 'bg-emerald-100 text-emerald-900 border border-emerald-300',
    };
  }
  return {
    label: 'Fechado',
    className: 'bg-stone-100 text-stone-800 border border-stone-300',
  };
}

function Caixa({ caixas, stats, filters, links }: Props) {
  const saldoAtual = useMemo(
    () => caixas.reduce((acc, c) => acc + (c.total_credit - c.total_debit), 0),
    [caixas]
  );

  const setStatusFilter = (s: 'open' | 'close' | null) => {
    // D-14: partial reload — só re-busca o que muda com filtro
    // (stats/links são por business → closures no controller, pulam no partial).
    router.visit('/financeiro/caixa', {
      data: s ? { status: s } : {},
      preserveScroll: true,
      replace: true,
      only: ['caixas', 'filters'],
    });
  };

  return (
    <div className="fin-curadoria p-6 max-w-7xl mx-auto space-y-6">
      {/* Wave 4 (2026-05-25): migrado pra <PageHeader> canon v3.8 (PR #1496) */}
      <PageHeader
        title="Caixa do turno"
        suffix=" · Histórico e fechamentos"
        subtitle={
          <>
            Visão read-only dos turnos de caixa (abertura e fechamento) feitos pela equipe via tela
            POS. Para abrir ou fechar um caixa, use os botões na header da{' '}
            <a href={links.pos_create} className="underline text-success">
              tela de venda
            </a>
            .
          </>
        }
      >
        <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
          <FinanceiroSubNav active="caixa" hidePrimary />
        </div>
      </PageHeader>

      {/* Banner explicativo — esta tela é WRAPPER, não substitui POS */}
      <div className="rounded-md border border-info/20 bg-info-soft px-4 py-3 text-sm text-info-fg">
        <strong>ℹ️ Por que essa tela existe?</strong>
        <span className="ml-1">
          Fluxo de Caixa (mensal) ≠ Caixa do turno (POS). Esta é uma vista do Financeiro pros
          fechamentos de turno feitos no balcão. O ciclo abre/vende/fecha continua na{' '}
          <a href={links.pos_create} className="underline">
            tela POS
          </a>
          . Versão legacy:{' '}
          <a href={links.cash_register_legacy} className="underline">
            {links.cash_register_legacy}
          </a>
          .
        </span>
      </div>

      {/* KPI cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="rounded-md border bg-white p-4">
          <div className="text-xs text-stone-500 uppercase tracking-wide">Caixas registrados</div>
          <div className="text-2xl font-semibold mt-1">{stats.total_caixas}</div>
        </div>
        <div className="rounded-md border bg-white p-4">
          <div className="text-xs text-stone-500 uppercase tracking-wide">Caixas abertos agora</div>
          <div className="text-2xl font-semibold mt-1 text-success">
            {stats.caixas_abertos}
          </div>
        </div>
        <div className="rounded-md border bg-white p-4">
          <div className="text-xs text-stone-500 uppercase tracking-wide">
            Soma de fechamentos
          </div>
          <div className="text-2xl font-semibold mt-1">{fmtMoney(stats.soma_fechamentos)}</div>
        </div>
        <div className="rounded-md border bg-white p-4">
          <div className="text-xs text-stone-500 uppercase tracking-wide">
            Saldo nos últimos {caixas.length} mostrados
          </div>
          <div className="text-2xl font-semibold mt-1">{fmtMoney(saldoAtual)}</div>
        </div>
      </div>

      {/* Filtros — pill segmented control */}
      <div className="flex items-center gap-2">
        <span className="text-sm text-stone-600">Filtrar:</span>
        <div className="inline-flex rounded-md border bg-white p-1 text-sm">
          <button
            type="button"
            onClick={() => setStatusFilter(null)}
            className={`px-3 py-1 rounded ${!filters.status ? 'bg-stone-900 text-white' : 'text-stone-700 hover:bg-stone-100'}`}
          >
            Todos
          </button>
          <button
            type="button"
            onClick={() => setStatusFilter('open')}
            className={`px-3 py-1 rounded ${filters.status === 'open' ? 'bg-stone-900 text-white' : 'text-stone-700 hover:bg-stone-100'}`}
          >
            Abertos
          </button>
          <button
            type="button"
            onClick={() => setStatusFilter('close')}
            className={`px-3 py-1 rounded ${filters.status === 'close' ? 'bg-stone-900 text-white' : 'text-stone-700 hover:bg-stone-100'}`}
          >
            Fechados
          </button>
        </div>
        <span className="ml-auto text-xs text-stone-500">
          Mostrando últimos {caixas.length} (limite {filters.limit})
        </span>
      </div>

      {/* Tabela */}
      <section className="rounded-md border bg-white overflow-hidden">
        {caixas.length === 0 ? (
          <div className="px-5 py-12 text-center text-sm text-stone-500">
            Nenhum caixa encontrado{filters.status ? ' com este filtro' : ''}.{' '}
            <a href={links.pos_create} className="text-success underline">
              Abra um caixa via POS
            </a>{' '}
            pra começar.
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-stone-50 text-xs text-stone-600">
              <tr className="text-left">
                <th className="px-4 py-2 font-medium">#</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Operador</th>
                <th className="px-4 py-2 font-medium">Loja</th>
                <th className="px-4 py-2 font-medium">Abertura</th>
                <th className="px-4 py-2 font-medium">Fechamento</th>
                <th className="px-4 py-2 font-medium text-right">Entradas</th>
                <th className="px-4 py-2 font-medium text-right">Saídas</th>
                <th className="px-4 py-2 font-medium text-right">Fechou em</th>
                <th className="px-4 py-2 font-medium">Financeiro</th>
              </tr>
            </thead>
            <tbody>
              {caixas.map((c) => {
                const badge = statusBadge(c.status);
                return (
                  <tr key={c.id} className="border-t hover:bg-stone-50">
                    <td className="px-4 py-3 font-mono text-xs text-stone-500">#{c.id}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-block rounded px-2 py-0.5 text-xs ${badge.className}`}>
                        {badge.label}
                      </span>
                    </td>
                    <td className="px-4 py-3">{c.user_name}</td>
                    <td className="px-4 py-3 text-stone-700">{c.location_name}</td>
                    <td className="px-4 py-3 text-stone-700">{fmtDateTime(c.open_time)}</td>
                    <td className="px-4 py-3 text-stone-700">
                      {c.status === 'open' ? '—' : fmtDateTime(c.close_time)}
                    </td>
                    <td className="px-4 py-3 text-right text-success">
                      {fmtMoney(c.total_credit)}
                    </td>
                    <td className="px-4 py-3 text-right text-destructive">
                      {fmtMoney(c.total_debit)}
                    </td>
                    <td className="px-4 py-3 text-right font-medium">
                      {c.status === 'open' ? '—' : fmtMoney(c.closing_amount)}
                    </td>
                    {/* ADR 0183 PR C — status integração ponte fin_titulos */}
                    <td className="px-4 py-3">
                      {c.integracao_status === 'lancado' && c.fin_titulo_id && (
                        <a
                          href={`/financeiro/unificado?titulo=${c.fin_titulo_id}`}
                          className="inline-flex items-center gap-1 text-success hover:underline text-xs"
                          title={`fin_titulo #${c.fin_titulo_id} · ${c.fin_titulo_status} · ${c.fin_titulo_valor ? fmtMoney(c.fin_titulo_valor) : '—'}`}
                        >
                          ✅ #{c.fin_titulo_id}
                        </a>
                      )}
                      {c.integracao_status === 'pendente' && (
                        <button
                          type="button"
                          onClick={() => {
                            if (!confirm(`Lançar fin_titulo retroativo pra caixa #${c.id}? (idempotente — re-clicar não duplica)`)) return;
                            router.post(`/financeiro/caixa/${c.id}/lancar`, {}, {
                              preserveScroll: true,
                              onSuccess: () => router.reload({ only: ['caixas'] }),
                            });
                          }}
                          className="inline-flex items-center gap-1 text-amber-700 hover:bg-amber-50 px-2 py-1 rounded border border-amber-300 text-xs"
                          title="Caixa fechado sem fin_titulo — clique pra lançar retroativo"
                        >
                          ⚠️ Lançar agora
                        </button>
                      )}
                      {c.integracao_status === 'nao_aplicavel' && (
                        <span className="text-stone-400 text-xs">—</span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </section>

      <div className="text-xs text-stone-500">
        <Lightbulb className="h-3.5 w-3.5 mr-1 inline align-text-bottom" /> Para fechar um caixa aberto, clique no botão "Fechar caixa registradora" na header da{' '}
        <a href={links.pos_create} className="underline">
          tela POS
        </a>
        .
      </div>
    </div>
  );
}

Caixa.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro — Caixa do turno"
    breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Caixa do turno' }]}
  >
    {page}
  </AppShellV2>
);

export default Caixa;
