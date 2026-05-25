// @memcofre tela=/financeiro/plano-contas module=Financeiro status=implementada
//
// Onda 18 (2026-05-19) #48 — Tela dedicada de Plano de Contas BR.
// Resolve workaround do botão "Plano de contas" no header de /unificado
// que apontava pra /categorias (Onda 16). Agora destino real.
//
// Persona: Eliana [E] (financeiro escritório, densidade alta).
// Canon: AppShellV2 + .fin-curadoria .vendas-aplus + os-page-h + fin-stats.

import AppShellV2 from '@/Layouts/AppShellV2';
import { type ReactNode, useMemo, useState } from 'react';
import { Lock, FileText, Search } from 'lucide-react';
import { router } from '@inertiajs/react';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { PageHeader } from '@/Components/PageHeader';
import FinanceiroPrimaryButton from '@/Pages/Financeiro/_shared/FinanceiroPrimaryButton';

interface PlanoConta {
  id: number;
  codigo: string;    // ex "1.1.01.001"
  nome: string;     // ex "Caixa"
  tipo: 'ativo' | 'passivo' | 'patrimonio' | 'receita' | 'despesa' | 'custo';
  nivel: number;    // 1=raiz, 4=folha
  parent_id: number | null;
  natureza: 'debito' | 'credito';
  aceita_lancamento: boolean;
  protegido: boolean;
}

interface Stats {
  total: number;
  receita: number;
  despesa: number;
  ativo: number;
  passivo: number;
  patrimonio: number;
  custo: number;
}

interface Props {
  planos: PlanoConta[];
  stats: Stats;
}

const TIPO_COLOR: Record<PlanoConta['tipo'], string> = {
  ativo:      'text-emerald-700 bg-emerald-50',
  passivo:    'text-rose-700 bg-rose-50',
  patrimonio: 'text-blue-700 bg-blue-50',
  receita:    'text-emerald-700 bg-emerald-50',
  despesa:    'text-rose-700 bg-rose-50',
  custo:      'text-amber-700 bg-amber-50',
};

function FinanceiroPlanoContas({ planos, stats }: Props) {
  const [busca, setBusca] = useState('');
  const [tipoFilter, setTipoFilter] = useState<PlanoConta['tipo'] | 'all'>('all');

  const filtered = useMemo(() => {
    return planos.filter((p) => {
      if (tipoFilter !== 'all' && p.tipo !== tipoFilter) return false;
      if (busca) {
        const q = busca.toLowerCase();
        return p.codigo.toLowerCase().includes(q) || p.nome.toLowerCase().includes(q);
      }
      return true;
    });
  }, [planos, busca, tipoFilter]);

  return (
    <div className="fin-curadoria vendas-aplus">
      {/* Onda 18 — header canon paridade Unificado */}
      {/* Wave 4 (2026-05-25): migrado pra <PageHeader> canon v3.8 */}
      <PageHeader
        title="Plano de Contas"
        suffix=" · Estrutura contábil BR"
        subtitle={<>{stats.total} contas hierárquicas (Receita Federal/DCASP) — Eliana classifica lançamentos pelo plano</>}
      >
        <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
          <FinanceiroSubNav active="plano-contas" hidePrimary />
          <FinanceiroPrimaryButton onClick={() => router.visit('/financeiro/plano-contas/create')}>
            Nova conta
          </FinanceiroPrimaryButton>
        </div>
      </PageHeader>

      {/* KPI strip canon */}
      <div className="fin-stats">
        <div className="fin-stat fin-stat-hero">
          <small>TOTAL DE CONTAS</small>
          <b>{stats.total}</b>
          <span className="fin-stat-hint">Hierarquia BR padrão (4 níveis)</span>
        </div>
        <div className="fin-stat">
          <small>RECEITA</small>
          <b className="fin-num-pos">{stats.receita}</b>
          <span className="fin-stat-hint">contas tipo receita</span>
        </div>
        <div className="fin-stat">
          <small>DESPESA</small>
          <b className="fin-num-neg">{stats.despesa}</b>
          <span className="fin-stat-hint">contas tipo despesa</span>
        </div>
        <div className="fin-stat">
          <small>ATIVO</small>
          <b>{stats.ativo}</b>
          <span className="fin-stat-hint">contas tipo ativo</span>
        </div>
        <div className="fin-stat">
          <small>PASSIVO + PATRIM.</small>
          <b>{stats.passivo + stats.patrimonio}</b>
          <span className="fin-stat-hint">{stats.passivo} passivo + {stats.patrimonio} patrim.</span>
        </div>
      </div>

      {/* Filtros */}
      <div className="fin-toolbar mt-4">
        <div className="fin-filter-group" role="group" aria-label="Filtrar por tipo">
          {(['all', 'receita', 'despesa', 'ativo', 'passivo', 'patrimonio', 'custo'] as const).map((t) => (
            <label
              key={t}
              className={'fin-filter-cb' + (tipoFilter === t ? ' on' : '')}
              style={{ ['--cb-hue' as string]: t === 'receita' ? 145 : t === 'despesa' ? 25 : t === 'ativo' ? 145 : t === 'passivo' ? 25 : 240 } as React.CSSProperties}
            >
              <input type="radio" name="tipo" checked={tipoFilter === t} onChange={() => setTipoFilter(t)} />
              <span className="fin-filter-cb-box" />
              <span>{t === 'all' ? 'Todos' : t.charAt(0).toUpperCase() + t.slice(1)}</span>
              <span className="fin-filter-ct">
                {t === 'all' ? stats.total : (stats as unknown as Record<string, number>)[t] ?? 0}
              </span>
            </label>
          ))}
        </div>

        <span className="fin-filter-sep" />

        <div className="fin-toolbar-r">
          <div className="fin-search-wrap">
            <Search size={13} aria-hidden="true" />
            <input
              placeholder="Buscar por código ou nome…"
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
            />
          </div>
        </div>
      </div>

      {/* Tabela hierárquica */}
      <div className="mt-3 rounded-md border border-stone-200 overflow-hidden">
        <table className="w-full text-[13px]">
          <thead>
            <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
              <th className="px-3 py-2 text-left font-medium w-[110px]">Código</th>
              <th className="px-3 py-2 text-left font-medium">Conta</th>
              <th className="px-3 py-2 text-left font-medium w-[110px]">Tipo</th>
              <th className="px-3 py-2 text-left font-medium w-[80px]">Natureza</th>
              <th className="px-3 py-2 text-center font-medium w-[100px]">Aceita lanç.</th>
              <th className="px-3 py-2 text-center font-medium w-[80px]">Protegido</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((p) => (
              <tr key={p.id} className="border-b border-stone-100 hover:bg-stone-50/50">
                <td className="px-3 py-1.5 font-mono text-stone-700 tabular-nums" style={{ paddingLeft: 12 + (p.nivel - 1) * 16 }}>
                  {p.codigo}
                </td>
                <td className="px-3 py-1.5">
                  <span style={{ fontWeight: p.nivel <= 2 ? 600 : 400 }}>{p.nome}</span>
                </td>
                <td className="px-3 py-1.5">
                  <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium ${TIPO_COLOR[p.tipo]}`}>
                    {p.tipo}
                  </span>
                </td>
                <td className="px-3 py-1.5 text-stone-500 text-[12px]">{p.natureza}</td>
                <td className="px-3 py-1.5 text-center">
                  {p.aceita_lancamento ? (
                    <FileText size={14} className="text-emerald-600 inline" aria-label="Aceita lançamento" />
                  ) : (
                    <span className="text-stone-300 text-[11px]">—</span>
                  )}
                </td>
                <td className="px-3 py-1.5 text-center">
                  {p.protegido && <Lock size={13} className="text-amber-600 inline" aria-label="Conta protegida" />}
                </td>
              </tr>
            ))}
            {filtered.length === 0 && (
              <tr>
                <td colSpan={6} className="py-12 text-center text-stone-500">
                  {planos.length === 0
                    ? 'Plano de contas ainda não seedado pra este business. Rode `php artisan tinker --execute=\"(new \\Modules\\Financeiro\\Database\\Seeders\\PlanoContasBrSeeder)->run({biz_id});\"` no SSH.'
                    : 'Nenhuma conta com os filtros atuais.'}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Footer canon */}
      <div className="fin-footer-tips">
        <span className="fin-footer-summary">
          <b>{filtered.length}</b> de <b>{stats.total}</b> contas
          <span className="fin-footer-sep">·</span>
          <b className="fin-num-pos">{stats.receita}</b> receita
          <span className="fin-footer-sep">·</span>
          <b className="fin-num-neg">{stats.despesa}</b> despesa
        </span>
        <span className="spacer" />
        <span>📖 Hierarquia BR · Receita Federal DCASP simplificado</span>
      </div>
    </div>
  );
}

FinanceiroPlanoContas.layout = (page: ReactNode) => (
  <AppShellV2
    title="Plano de Contas — Financeiro"
    breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Plano de Contas' }]}
  >
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);

export default FinanceiroPlanoContas;
