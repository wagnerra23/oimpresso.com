import React from 'react';
import { Head } from '@inertiajs/react';

/**
 * Manufacturing/Index — esqueleto MWART inicial (Wave J 2026-05-16).
 *
 * Página minimal para listar produções (production_purchase). Rota nova
 * `/manufacturing/v2/production` em coexistência com Blade legacy
 * `/manufacturing/production` (Tier 0: preservar comportamento existente).
 *
 * Próximos passos (NÃO neste PR):
 *  - Migrar tabela legacy DataTables → TanStack Table
 *  - Adicionar filtros (location_id, date range)
 *  - Wire-up CRUD via Inertia forms
 *  - Charter ao lado deste arquivo (Index.charter.md) define Mission/Non-Goals
 */

type Production = {
  id: number;
  ref_no: string | null;
  transaction_date: string;
  location_name: string | null;
  final_total: number;
  mfg_is_final: number;
};

type Summary = {
  total_count: number;
  final_count: number;
  pending_count: number;
  total_value: number;
};

type Props = {
  productions: Production[];
  summary: Summary;
};

export default function Index({ productions = [], summary }: Props) {
  return (
    <>
      <Head title="Produção (Manufacturing)" />

      <div className="p-6 space-y-6">
        {/* PageHeader */}
        <header className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">Produção</h1>
            <p className="text-sm text-gray-500">
              Ordens de produção (Manufacturing) — versão Inertia/React.
            </p>
          </div>
          <button
            type="button"
            className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700"
            disabled
            title="Em construção — use a rota legacy enquanto isso"
          >
            + Nova produção
          </button>
        </header>

        {/* Summary cards */}
        <section className="grid grid-cols-1 sm:grid-cols-4 gap-4">
          <SummaryCard label="Total" value={summary?.total_count ?? 0} />
          <SummaryCard label="Finalizadas" value={summary?.final_count ?? 0} />
          <SummaryCard label="Pendentes" value={summary?.pending_count ?? 0} />
          <SummaryCard
            label="Valor total"
            value={summary?.total_value ?? 0}
            isCurrency
          />
        </section>

        {/* Lista */}
        <section className="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Ref
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Data
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Local
                </th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Total
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {productions.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-12 text-center">
                    <EmptyState />
                  </td>
                </tr>
              ) : (
                productions.map((p) => (
                  <tr key={p.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-sm text-gray-900">
                      {p.ref_no ?? '—'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500">
                      {p.transaction_date}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500">
                      {p.location_name ?? '—'}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-900 text-right">
                      {formatCurrency(p.final_total)}
                    </td>
                    <td className="px-4 py-3 text-sm">
                      {p.mfg_is_final ? (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                          Finalizada
                        </span>
                      ) : (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                          Pendente
                        </span>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </section>
      </div>
    </>
  );
}

function SummaryCard({
  label,
  value,
  isCurrency = false,
}: {
  label: string;
  value: number;
  isCurrency?: boolean;
}) {
  return (
    <div className="bg-white border border-gray-200 rounded-lg p-4">
      <p className="text-xs font-medium text-gray-500 uppercase">{label}</p>
      <p className="mt-1 text-2xl font-semibold text-gray-900">
        {isCurrency ? formatCurrency(value) : value}
      </p>
    </div>
  );
}

function EmptyState() {
  return (
    <div className="text-center">
      <p className="text-sm text-gray-500">Nenhuma produção cadastrada.</p>
      <p className="mt-1 text-xs text-gray-400">
        Use a rota legacy em /manufacturing/production para criar enquanto a
        migração MWART está em andamento.
      </p>
    </div>
  );
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(value ?? 0);
}
