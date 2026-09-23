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
import { Lock, FileText, Search, BookOpen } from 'lucide-react';
import { Deferred, router } from '@inertiajs/react';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import FinStatStrip, { FinStat } from '@/Pages/Financeiro/_shared/FinStatStrip';
import { useBusiness, usePageProps } from '@/Hooks/usePageProps';
import { Inline } from '@/Components/layout';
import { brlNoSign } from '@/Pages/Financeiro/Cobranca/_lib/cobranca-shared';

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

/** FIN-6b — movimento do mês por conta (DreService::movimentoMesPorConta). Competência,
 *  sem cancelados; saldo com sinal (receber +, pagar −); conta pai soma tudo abaixo dela.
 *  Conta sem movimento não vem no mapa. */
interface Movimento {
  mes: string;
  mes_label: string;
  contas: Record<number, { lancamentos: number; saldo: number }>;
}

interface Props {
  planos: PlanoConta[];
  stats: Stats;
  movimento?: Movimento; // deferida: undefined até o 2º request
}

// FIN-6 (2026-09-23): selo de tipo no formato do protótipo (TelaPContas,
// financeiro-telas-extras.jsx:636-641) — pílula com ponto, tokens do tema. O protótipo só desenha
// Receita/Despesa; os outros 4 tipos do plano BR seguem a mesma forma com o tom mais próximo.
const TIPO_SELO: Record<PlanoConta['tipo'], { chip: string; ponto: string; rotulo: string }> = {
  receita:    { chip: 'bg-[var(--pos-soft)] text-[var(--pos)]',   ponto: 'bg-[var(--pos)]',       rotulo: 'Receita' },
  ativo:      { chip: 'bg-[var(--pos-soft)] text-[var(--pos)]',   ponto: 'bg-[var(--pos)]',       rotulo: 'Ativo' },
  despesa:    { chip: 'bg-[var(--neg-soft)] text-[var(--neg)]',   ponto: 'bg-[var(--neg)]',       rotulo: 'Despesa' },
  passivo:    { chip: 'bg-[var(--neg-soft)] text-[var(--neg)]',   ponto: 'bg-[var(--neg)]',       rotulo: 'Passivo' },
  custo:      { chip: 'bg-[var(--warn-soft)] text-[var(--warn)]', ponto: 'bg-[var(--warn)]',      rotulo: 'Custo' },
  patrimonio: { chip: 'bg-[var(--bg-2)] text-[var(--text-dim)]',  ponto: 'bg-[var(--text-mute)]', rotulo: 'Patrimônio' },
};

/**
 * As duas células de movimento do mês (FIN-6b), no formato do protótipo (TelaPContas :646-656):
 * quantidade ou "—"; saldo com sinal (+ verde / − neutro), sem "R$", ou "—" quando zero.
 * `mov === undefined` = prop deferida ainda não chegou; `null` = conta sem movimento no mês.
 */
function MovimentoCelulas({ mov }: { mov: { lancamentos: number; saldo: number } | null | undefined }) {
  if (mov === undefined) {
    return (
      <>
        <td className="px-2 py-2 text-right text-[var(--text-mute)]" aria-busy="true">…</td>
        <td className="px-2 py-2 text-right text-[var(--text-mute)]" aria-busy="true">…</td>
      </>
    );
  }
  const qtd = mov?.lancamentos ?? 0;
  const saldo = mov?.saldo ?? 0;
  return (
    <>
      <td className="px-2 py-2 text-right tabular-nums text-[var(--text-dim)]">
        {qtd > 0 ? qtd : <span className="text-[var(--text-mute)]">—</span>}
      </td>
      <td
        className={`px-2 py-2 text-right tabular-nums font-medium ${
          saldo === 0 ? 'text-[var(--text-mute)]' : saldo > 0 ? 'text-[var(--pos)]' : 'text-[var(--text)]'
        }`}
      >
        {saldo === 0 ? (
          '—'
        ) : (
          <>
            <span className="text-[var(--text-dim)] mr-0.5">{saldo > 0 ? '+' : '−'}</span>
            {brlNoSign(Math.abs(saldo))}
          </>
        )}
      </td>
    </>
  );
}

function FinanceiroPlanoContas({ planos, stats, movimento }: Props) {
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

  // Profundidade real do plano deste negócio (o protótipo mostra "… · 2 níveis").
  const niveis = useMemo(() => planos.reduce((m, p) => Math.max(m, p.nivel), 0), [planos]);

  // Nome da empresa: mesma fonte da sidebar, com o da sessão de reserva (idioma de
  // Patrimonio/Index.tsx:248-259 — o da sessão chega vazio em ambiente de teste).
  const shell = usePageProps().shell as ({ cockpit?: { businessNome?: string } } | undefined);
  const nomeDoShell = shell?.cockpit?.businessNome ?? null;
  const nomeDaSessao = useBusiness()?.name ?? null;
  const empresa = nomeDoShell ?? nomeDaSessao ?? '';

  return (
    <div className="fin-curadoria vendas-aplus">
      {/* Onda 18 — header canon paridade Unificado */}
      {/* Wave 4 (2026-05-25): migrado pra <PageHeader> canon v3.8 */}
      {/* FIN-6: título "Financeiro · Plano de contas" e primário "Novo título" = protótipo e padrão
          das outras telas do Financeiro. O primário anterior, "Nova conta", navegava para
          /financeiro/plano-contas/create — rota que NÃO existe (404 medido em prod, 2026-09-23). */}
      <PageHeader
        title="Financeiro"
        suffix=" · Plano de contas"
        subtitle={empresa ? `${empresa} · caixa unificado` : 'caixa unificado'}
      >
        <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
          <FinanceiroSubNav active="plano-contas" hidePrimary />
          <PageHeaderPrimary
            label="Novo título"
            onClick={() => router.visit('/financeiro/unificado/novo')}
          />
        </div>
      </PageHeader>

      {/* KPI strip canon — piloto: migrado de .fin-stats bespoke pro componente
          <FinStatStrip> (passo 2-3 MANUAL-CSS-JS). Visualmente idêntico. */}
      <FinStatStrip>
        <FinStat hero label="TOTAL DE CONTAS" value={stats.total} hint="Hierarquia BR padrão (4 níveis)" />
        <FinStat label="RECEITA" value={stats.receita} tone="pos" hint="contas tipo receita" />
        <FinStat label="DESPESA" value={stats.despesa} tone="neg" hint="contas tipo despesa" />
        <FinStat label="ATIVO" value={stats.ativo} hint="contas tipo ativo" />
        <FinStat
          label="PASSIVO + PATRIM."
          value={stats.passivo + stats.patrimonio}
          hint={`${stats.passivo} passivo + ${stats.patrimonio} patrim.`}
        />
      </FinStatStrip>

      {/* Filtros */}
      <div className="fin-toolbar mt-4">
        <div className="fin-filter-group" role="radiogroup" aria-label="Filtrar por tipo">
          {(['all', 'receita', 'despesa', 'ativo', 'passivo', 'patrimonio', 'custo'] as const).map((t) => (
            <button
              key={t}
              type="button"
              role="radio"
              aria-checked={tipoFilter === t}
              className={'fin-filter-cb' + (tipoFilter === t ? ' on' : '')}
              style={{ ['--cb-hue' as string]: t === 'receita' ? 145 : t === 'despesa' ? 25 : t === 'ativo' ? 145 : t === 'passivo' ? 25 : 240 } as React.CSSProperties}
              onClick={() => setTipoFilter(t)}
            >
              <span className="fin-filter-cb-box" />
              <span>{t === 'all' ? 'Todos' : t.charAt(0).toUpperCase() + t.slice(1)}</span>
              <span className="fin-filter-ct">
                {t === 'all' ? stats.total : (stats as unknown as Record<string, number>)[t] ?? 0}
              </span>
            </button>
          ))}
        </div>

      </div>

      {/* FIN-6: cartão único do protótipo (TelaPContas :599-613) — título do cartão + busca à direita.
          "Importar", "+ Nova" e "editar" do protótipo ficam FORA: o backend não tem essas ações
          (Routes/web.php só declara plano-contas.index). Botão sem ação seria promessa falsa. */}
      <div className="mt-3 bg-[var(--surface)] border border-[var(--border)] rounded-[11px] shadow-[var(--sh-1)] overflow-hidden">
        <Inline gap={3} align="center" className="px-5 py-3 border-b border-[var(--border)]">
          <div className="min-w-0">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] font-medium whitespace-nowrap">Plano de contas</div>
            <div className="text-[length:var(--fs-5)] font-semibold mt-0.5 whitespace-nowrap">
              {/* Plano vazio não tem profundidade: "0 níveis" (1ª captura) lia como defeito. */}
              Receita Federal/DCASP · {niveis === 0 ? 'sem contas' : `${niveis} ${niveis === 1 ? 'nível' : 'níveis'}`}
            </div>
            <Deferred data="movimento" fallback={<div className="text-[length:var(--fs-2)] text-[var(--text-mute)] mt-0.5">carregando movimento do mês…</div>}>
              <div className="text-[length:var(--fs-2)] text-[var(--text-dim)] mt-0.5">
                Lanç. e saldo de {movimento?.mes_label ?? ''} · por competência, sem cancelados
              </div>
            </Deferred>
          </div>
          <div className="ml-auto shrink-0 fin-search-wrap">
            <Search size={13} aria-hidden="true" />
            <input
              placeholder="Buscar por código ou nome…"
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
            />
          </div>
        </Inline>
        <table className="w-full text-[length:var(--fs-3)]">
          <thead>
            <tr className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] border-b border-[var(--border)] bg-[var(--bg-2)]">
              <th className="pl-6 pr-2 py-2 text-left font-medium w-[120px]">Código</th>
              <th className="px-2 py-2 text-left font-medium">Conta</th>
              <th className="px-2 py-2 text-left font-medium w-[120px]">Tipo</th>
              {/* FIN-6b — as duas colunas do protótipo (TelaPContas :625-626), logo depois de Tipo. */}
              <th className="px-2 py-2 text-right font-medium w-[80px]">Lanç. mês</th>
              <th className="px-2 py-2 text-right font-medium w-[140px]">Saldo mês</th>
              <th className="px-2 py-2 text-left font-medium w-[80px]">Natureza</th>
              <th className="px-2 py-2 text-center font-medium w-[100px]">Aceita lanç.</th>
              <th className="pl-2 pr-6 py-2 text-center font-medium w-[80px]">Protegido</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((p) => (
              <tr
                key={p.id}
                className={`border-b border-[var(--border-2)] hover:bg-[var(--bg-2)] ${p.nivel === 1 ? 'bg-[var(--bg-2)]' : ''}`}
              >
                <td className="pl-6 pr-2 py-2 font-mono text-[length:var(--fs-2)] text-[var(--text-dim)] tabular-nums">
                  {p.codigo}
                </td>
                <td className="px-2 py-2" style={{ paddingLeft: 12 + (p.nivel - 1) * 18 }}>
                  <span
                    className={
                      p.nivel === 1
                        ? 'font-semibold text-[var(--text)]'
                        : p.nivel === 2
                          ? 'font-medium text-[var(--text)]'
                          : 'text-[var(--text-dim)]'
                    }
                  >
                    {p.nivel > 1 && <span className="text-[var(--text-mute)] mr-1.5" aria-hidden="true">└</span>}
                    {p.nome}
                  </span>
                </td>
                <td className="px-2 py-2">
                  <span className={`inline-flex items-center gap-1 text-[length:var(--fs-2)] font-medium px-2 py-0.5 rounded-full ${TIPO_SELO[p.tipo].chip}`}>
                    <span className={`w-1.5 h-1.5 rounded-full ${TIPO_SELO[p.tipo].ponto}`} aria-hidden="true" />
                    {TIPO_SELO[p.tipo].rotulo}
                  </span>
                </td>
                <MovimentoCelulas mov={movimento === undefined ? undefined : (movimento.contas[p.id] ?? null)} />
                <td className="px-2 py-2 text-[var(--text-dim)] text-[length:var(--fs-2)]">{p.natureza}</td>
                <td className="px-2 py-2 text-center">
                  {p.aceita_lancamento ? (
                    <FileText size={14} className="text-[var(--pos)] inline" aria-label="Aceita lançamento" />
                  ) : (
                    <span className="text-[var(--text-mute)] text-[length:var(--fs-2)]">—</span>
                  )}
                </td>
                <td className="pl-2 pr-6 py-2 text-center">
                  {p.protegido && <Lock size={13} className="text-[var(--warn)] inline" aria-label="Conta protegida" />}
                </td>
              </tr>
            ))}
            {filtered.length === 0 && (
              <tr>
                <td colSpan={8} className="py-12 text-center text-[var(--text-dim)]">
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
        <span className="inline-flex items-center gap-1"><BookOpen className="h-3.5 w-3.5" /> Hierarquia BR · Receita Federal DCASP simplificado</span>
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
