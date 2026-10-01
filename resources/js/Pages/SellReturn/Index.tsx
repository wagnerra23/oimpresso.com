// SellReturn/Index — lista de devoluções de venda (/sell-return), visita Inertia.
// Thread 03 de venda-menu, PR 1 de 2: só a LISTA. O registro (/sell-return/add/{venda})
// segue na Blade até o PR 2, que espera a REGRA MESTRE de valor/estoque.
// Refs:
//  - prototipo-ui/cowork/Wagner/vendas-extras.jsx · VendasDevolucoesPage (.vd-dev-page)
//  - governance/design/targets/vendas--devolucao--index.secoes.json (header · tabs · kpis · tabela)
//  - resources/js/Pages/SellReturn/Index.charter.md · Index.casos.md (UC-SRIDX-*)
//  - memory/requisitos/Sells/RUNBOOK-sell-return-index.md · ADR 0104 · ADR 0093

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';

// ──────────────────────────────────────────────────────────────
// TIPOS — paridade SellReturnController@inertiaIndex
// ──────────────────────────────────────────────────────────────
interface LinhaDevolucao {
  id: number;
  data: string;
  numero: string;
  venda_id: number;
  venda_numero: string;
  cliente: string | null;
  local: string;
  situacao_pagamento: string; // paid | partial | due
  total: number;
  pago: number;
}

interface Kpis {
  com_saldo: number;
  no_mes: number;
  valor_mes: number;
}

interface ListaPayload {
  linhas: LinhaDevolucao[];
  total: number;
  limite: number;
}

interface SellReturnIndexProps {
  kpis?: Kpis; // deferida (grupo "lista")
  devolucoes?: ListaPayload; // deferida (grupo "lista")
  permissions: { ver_todas: boolean; ver_proprias: boolean };
}

// ──────────────────────────────────────────────────────────────
// HELPERS
// ──────────────────────────────────────────────────────────────
const fmtBRL = (n: number): string =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0);

const fmtData = (valor: string): string => {
  const dia = String(valor || '').split(' ')[0] ?? '';
  const partes = dia.split('-');
  return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : valor;
};

const SITUACAO: Record<string, { label: string; tom: string }> = {
  paid: { label: 'Pago', tom: 'green' },
  partial: { label: 'Parcial', tom: 'amber' },
  due: { label: 'A pagar', tom: 'red' },
};

const NAV_VENDAS = [
  { href: '/sells', label: 'Vendas', ativo: false },
  { href: '/vendas/caixa', label: 'Caixa do dia', ativo: false },
  { href: '/sell-return', label: 'Devoluções', ativo: true },
];

const KPI_LABELS = ['Com saldo a pagar', 'Devoluções no mês', 'Valor devolvido no mês'];

// ──────────────────────────────────────────────────────────────
// COMPONENTE
// ──────────────────────────────────────────────────────────────
export default function SellReturnIndex({ kpis, devolucoes, permissions }: SellReturnIndexProps) {
  const soProprias = !permissions.ver_todas && permissions.ver_proprias;

  return (
    <>
      <Head title="Devoluções de venda" />
      <div className="sells-cowork">
        <div className="os-page vd-dev-page vd-subpage">
          <header className="os-head">
            <div className="os-head-l">
              <h1>Devoluções de venda</h1>
              <p>
                Devoluções registradas, venda de origem e o que falta pagar ao cliente
                {soProprias ? ' · só as suas' : ''}
              </p>
            </div>
            <div className="os-head-r">
              {/* A devolução começa pela venda: abre-se a venda e usa-se Devolver. */}
              <Button asChild>
                <Link href="/sells" title="A devolução começa pela venda — abra a venda e use Devolver">
                  + Nova devolução
                </Link>
              </Button>
            </div>
          </header>

          <nav className="os-tabs" aria-label="Vendas e telas vinculadas">
            {NAV_VENDAS.map(item => (
              <Link
                key={item.href}
                href={item.href}
                className={item.ativo ? 'os-tab active' : 'os-tab'}
                aria-current={item.ativo ? 'page' : undefined}
              >
                {item.label}
              </Link>
            ))}
          </nav>

          <Deferred data={['kpis', 'devolucoes']} fallback={<CarregandoLista />}>
            <ListaDevolucoes kpis={kpis} devolucoes={devolucoes} />
          </Deferred>
        </div>
      </div>
    </>
  );
}

function CarregandoLista() {
  return (
    <>
      <div className="os-kpis">
        {KPI_LABELS.map(label => (
          <div key={label} className="os-kpi">
            <span className="os-kpi-label">{label}</span>
            <span className="os-kpi-value">—</span>
            <span className="os-kpi-sub">carregando</span>
          </div>
        ))}
      </div>
      <div className="os-table-wrap">
        <p className="vc-empty">Carregando devoluções…</p>
      </div>
    </>
  );
}

function ListaDevolucoes({ kpis, devolucoes }: { kpis?: Kpis; devolucoes?: ListaPayload }) {
  const linhas = devolucoes?.linhas ?? [];
  const total = devolucoes?.total ?? 0;
  const comSaldo = kpis?.com_saldo ?? 0;

  return (
    <>
      <div className="os-kpis">
        <div className={comSaldo > 0 ? 'os-kpi os-kpi-alert' : 'os-kpi'}>
          <span className="os-kpi-label">{KPI_LABELS[0]}</span>
          <span className="os-kpi-value">{comSaldo}</span>
          <span className="os-kpi-sub">devolução ainda não paga ao cliente</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">{KPI_LABELS[1]}</span>
          <span className="os-kpi-value">{kpis?.no_mes ?? 0}</span>
          <span className="os-kpi-sub">total registrado</span>
        </div>
        <div className="os-kpi">
          <span className="os-kpi-label">{KPI_LABELS[2]}</span>
          <span className="os-kpi-value">{fmtBRL(kpis?.valor_mes ?? 0)}</span>
          <span className="os-kpi-sub">soma das devoluções do mês</span>
        </div>
      </div>

      <div className="os-table-wrap">
        {linhas.length === 0 ? (
          <EmptyState
            icon="rotate-ccw"
            title="Nenhuma devolução registrada"
            description="A devolução começa pela venda: abra a venda e use Devolver."
          />
        ) : (
          <>
            <table className="os-table">
              <thead>
                <tr>
                  <th>Devolução</th>
                  <th>Data</th>
                  <th>Venda orig.</th>
                  <th>Cliente</th>
                  <th>Local</th>
                  <th className="os-th-val">Total</th>
                  <th className="os-th-val">Pago</th>
                  <th>Situação</th>
                  <th>
                    <span className="sr-only">Ações</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {linhas.map(d => {
                  const sit = SITUACAO[d.situacao_pagamento] ?? {
                    label: d.situacao_pagamento,
                    tom: 'muted',
                  };
                  return (
                    <tr key={d.id} className="os-row">
                      <td className="tabular-nums">#{d.numero}</td>
                      <td className="tabular-nums">{fmtData(d.data)}</td>
                      <td className="tabular-nums">
                        <Link href={`/sells/${d.venda_id}`} aria-label={`Abrir a venda ${d.venda_numero}`}>
                          #{d.venda_numero}
                        </Link>
                      </td>
                      <td>
                        <strong>{d.cliente ?? '—'}</strong>
                      </td>
                      <td>{d.local}</td>
                      <td className="os-th-val tabular-nums">{fmtBRL(d.total)}</td>
                      <td className="os-th-val tabular-nums">{fmtBRL(d.pago)}</td>
                      <td>
                        <span className={`os-stage ${sit.tom}`}>{sit.label}</span>
                      </td>
                      <td>
                        {/* Registro/edição segue na Blade até o PR 2 (SellReturn/Add). */}
                        <a href={`/sell-return/add/${d.venda_id}`} aria-label={`Editar a devolução ${d.numero}`}>
                          Editar
                        </a>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
            {total > linhas.length && (
              <p className="vc-empty">
                Mostrando as {linhas.length} mais recentes de {total}.
              </p>
            )}
          </>
        )}
      </div>
    </>
  );
}

SellReturnIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
