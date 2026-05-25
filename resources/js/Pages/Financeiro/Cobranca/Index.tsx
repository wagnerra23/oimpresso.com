// Financeiro/Cobranca/Index.tsx — F3 PaymentGateway UI Tela 1
// Port literal da pg-cobranca-page.jsx Cowork F1.5 (score 96/100) aprovado [W] 2026-05-19.
//
// Refs:
//  - prototipo-ui/prototipos/payment-gateway-ui/components/pg-cobranca-page.jsx (canonical visual-source)
//  - resources/js/Pages/Financeiro/Cobranca/Index.charter.md (Mission/Goals/Non-Goals/Anti-hooks)
//  - memory/requisitos/Financeiro/RUNBOOK-cobranca.md
//  - ADR 0144 + ADR 0170 PaymentGateway · ADR 0093 Tier 0 multi-tenant
//  - LICOES_F3_FINANCEIRO_REJEITADO.md (pre-flight aplicado)
//
// Cobranca substitui /financeiro/boletos. Persona Eliana[E] (financeiro escritório).
// Bundle CSS: resources/css/cowork-payment-gateway-bundle.css (regra Wagner 2026-05-18 bundle inteiro).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, Deferred } from '@inertiajs/react';
import {
  useCallback, useEffect, useMemo, useRef, useState, type ReactNode,
} from 'react';
import {
  Search, Plus, Download, Upload, Copy, MoreHorizontal, Settings, Webhook,
  Check, AlertCircle, Receipt, Zap, Building,
} from 'lucide-react';
import { Btn, StatusBadge, GatewayTipoChip, OrigemChip, KpiCard} from './_components/atoms';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import FinanceiroPrimaryButton from '@/Pages/Financeiro/_shared/FinanceiroPrimaryButton';
import FunnelStrip from './_components/FunnelStrip';
import DrawerCobranca from './_components/DrawerCobranca';
import SheetNovaCobranca from './_components/SheetNovaCobranca';
import SheetRemessaRetorno from './_components/SheetRemessaRetorno';
import CheatSheet from './_components/CheatSheet';
import AiResumoMes from './_components/AiResumoMes';
import {
  brl, brlNoSign, cn, fmtDate, fmtDateRel, piiMask, lsGet, lsSet,
  DRIVERS, TIPOS, ORIGENS,
  type Cobranca, type Account, type Gateway, type CobrancaKpis, type CobrancaFunil,
  type CobrancaFiltros, type OrigemType,
} from './_lib/cobranca-shared';

interface Props {
  cobrancas: Cobranca[];
  kpis: CobrancaKpis;
  funil: CobrancaFunil;
  accounts: Account[];
  gateways: Gateway[];
  filtros: CobrancaFiltros;
  isSaasBusiness: boolean;
  today: string;
}

const KPI_FALLBACK: CobrancaKpis = {
  pago_mes: { qtd: 0, valor: 0 },
  vencido: { qtd: 0, valor: 0 },
  aberto: { qtd: 0, valor: 0 },
  mandatos_ativos: 0,
  mrr_pago: 0,
};

const FUNIL_FALLBACK: CobrancaFunil = {
  aberto: { qtd: 0, valor: 0 },
  lembrete: { qtd: 0, desc: '3d antes do vcto' },
  cobranca_ativa: { qtd: 0, desc: '1-5d após vcto' },
  vencido_5d: { qtd: 0, valor: 0 },
  protesto: { qtd: 0, desc: '30d+ (Onda 5)' },
  mandatos_cancelados: 0,
};

function CobrancaPage({ cobrancas, kpis, funil, accounts = [], gateways = [], filtros, isSaasBusiness, today }: Props) {
  // Hotfix Inertia::defer first paint: kpis/funil podem ser undefined até resolver.
  kpis = kpis ?? KPI_FALLBACK;
  funil = funil ?? FUNIL_FALLBACK;
  // Persistência localStorage namespace oimpresso.financeiro.cobranca.*
  const [tabStatus, setTabStatus] = useState(() => lsGet<string>('tab', filtros.status || 'all'));
  const [tipoFilter, setTipoFilter] = useState(() => lsGet<string>('tipo', filtros.tipo || 'all'));
  const [gatewayFilter, setGatewayFilter] = useState(() => lsGet<string>('gateway', filtros.gateway || 'all'));
  const [accountFilter, setAccountFilter] = useState(() => lsGet<string>('account', filtros.account_id ? String(filtros.account_id) : 'all'));
  const [origemFilter, setOrigemFilter] = useState(() => lsGet<string>('origem', filtros.origem || 'all'));
  const [busca, setBusca] = useState(filtros.busca || '');

  const setTabStatusLs = useCallback((v: string) => { setTabStatus(v); lsSet('tab', v); }, []);
  const setTipoFilterLs = useCallback((v: string) => { setTipoFilter(v); lsSet('tipo', v); }, []);
  const setGatewayFilterLs = useCallback((v: string) => { setGatewayFilter(v); lsSet('gateway', v); }, []);
  const setAccountFilterLs = useCallback((v: string) => { setAccountFilter(v); lsSet('account', v); }, []);
  const setOrigemFilterLs = useCallback((v: string) => { setOrigemFilter(v); lsSet('origem', v); }, []);

  const [drawer, setDrawer] = useState<Cobranca | null>(null);
  const [novaOpen, setNovaOpen] = useState(false);
  const [remessaOpen, setRemessaOpen] = useState(false);
  const [cheatOpen, setCheatOpen] = useState(false);
  const [aiOpen, setAiOpen] = useState(false);
  const [focusIdx, setFocusIdx] = useState(-1);
  const buscaRef = useRef<HTMLInputElement>(null);

  const tipoMatch = useCallback((t: string) => {
    if (tipoFilter === 'all') return true;
    if (tipoFilter === 'pix') return t === 'pix_cob' || t === 'pix_cobv';
    return t === tipoFilter;
  }, [tipoFilter]);

  // Hotfix: cobrancas é Inertia::defer — undefined no primeiro paint até resolver.
  // Sem o ?? [] o useMemo crasha com "Cannot read properties of undefined (reading 'filter')".
  const filtered = useMemo(() => {
    return (cobrancas ?? []).filter(c => {
      if (tabStatus !== 'all' && c.status !== tabStatus) return false;
      if (!tipoMatch(c.tipo)) return false;
      if (gatewayFilter !== 'all' && c.gateway !== gatewayFilter) return false;
      if (accountFilter !== 'all' && c.account_id !== parseInt(accountFilter, 10)) return false;
      if (origemFilter !== 'all' && c.origem_type !== origemFilter) return false;
      if (busca) {
        const q = busca.toLowerCase();
        const hay = `${c.contato} ${c.contato_doc || ''} ${c.nosso_numero || ''} ${c.origem_label || ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });
  }, [cobrancas, tabStatus, tipoMatch, gatewayFilter, accountFilter, origemFilter, busca]);

  const statusCounts = useMemo(() => {
    const list = cobrancas ?? [];
    const out: Record<string, number> = { all: list.length };
    (['emitida', 'paga', 'vencida', 'cancelada', 'erro'] as const).forEach(s => {
      out[s] = list.filter(c => c.status === s).length;
    });
    return out;
  }, [cobrancas]);

  // KB-9.75 atalhos teclado
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement | null;
      const inField = !!target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
      if (e.key === 'Escape') {
        if (drawer) { setDrawer(null); e.preventDefault(); }
        else if (novaOpen) { setNovaOpen(false); e.preventDefault(); }
        else if (remessaOpen) { setRemessaOpen(false); e.preventDefault(); }
        else if (cheatOpen) { setCheatOpen(false); e.preventDefault(); }
        else if (aiOpen) { setAiOpen(false); e.preventDefault(); }
        return;
      }
      if (inField || drawer || novaOpen || remessaOpen || cheatOpen || aiOpen) return;
      if (e.key === '/') { e.preventDefault(); buscaRef.current?.focus(); }
      else if (e.key === '?') { e.preventDefault(); setCheatOpen(true); }
      else if (e.key === 'j' || e.key === 'ArrowDown') { e.preventDefault(); setFocusIdx(i => Math.min(filtered.length - 1, i + 1)); }
      else if (e.key === 'k' || e.key === 'ArrowUp') { e.preventDefault(); setFocusIdx(i => Math.max(0, i - 1)); }
      else if (e.key === 'Enter' && focusIdx >= 0) {
        e.preventDefault();
        const c = filtered[focusIdx];
        if (c) setDrawer(c);
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [drawer, novaOpen, remessaOpen, cheatOpen, aiOpen, focusIdx, filtered]);

  // breadcrumb dinâmico
  const breadcrumb = useMemo(() => {
    const parts = ['Financeiro', 'Cobrança'];
    if (gatewayFilter !== 'all' && DRIVERS[gatewayFilter as keyof typeof DRIVERS]) {
      parts.push(DRIVERS[gatewayFilter as keyof typeof DRIVERS].nome);
    }
    if (tipoFilter !== 'all') {
      const tp = tipoFilter === 'pix' ? TIPOS.pix_cob : TIPOS[tipoFilter as keyof typeof TIPOS];
      if (tp) parts.push(tp.label);
    }
    return parts.join(' · ');
  }, [gatewayFilter, tipoFilter]);

  // KPI #4 contextual
  const kpiContextual = useMemo(() => {
    if (tipoFilter === 'pix_recv') {
      return { label: 'Mandatos ativos', value: kpis.mandatos_ativos, sub: 'contratos PIX Automático vigentes', tone: 'violet' as const, icon: <Zap className="h-3 w-3" /> };
    }
    if (origemFilter === 'subscription_license') {
      return { label: 'MRR cobrado este mês', value: brl(kpis.mrr_pago), sub: 'tenants pagos', tone: 'fuchsia' as const, icon: <Building className="h-3 w-3" /> };
    }
    return null;
  }, [tipoFilter, origemFilter, kpis]);

  return (
    <div className="fin-cowork h-full bg-stone-50 flex flex-col font-sans pg-shell-scope">
      {/* BLOCO 1 — Header card canon v3.2 (LEARNINGS Decisao #4 · 2026-05-25)
          rounded-t-lg + pt-6 px-6 pb-3.5 + h1 22/700 espelha Vendas canon Cowork.
          Substitui header Cowork legacy (os-page-h fin-page-h) por canon Tailwind.
          FinanceiroSubNav + FinanceiroPrimaryButton mantidos como wrappers legacy
          até Wave 2 refactor pra <PageHeaderPrimary> universal (ADR 0190). */}
      <div className="fin-curadoria vendas-aplus">
        <header
          className="bg-background border border-border rounded-t-lg overflow-visible"
          role="banner"
        >
          <div className="flex items-center gap-4 pt-6 px-6 pb-3.5 min-h-[60px]">
            <div className="flex-1 min-w-0">
              <h1 className="text-[22px] font-bold tracking-tight text-foreground leading-snug">
                Cobrança<span className="font-semibold text-muted-foreground"> · Boletos e PIX</span>
              </h1>
              <p className="text-xs text-muted-foreground mt-0.5 tabular-nums">
                {kpis.aberto.qtd} em aberto · gestão de remessa/retorno + gateways
              </p>
            </div>
            <div className="flex-shrink-0 flex items-center gap-1.5">
              {/* ADR 0180 Fase 5 — SubNav com 12 ghosts Financeiro + extraOverflow contextual */}
              <FinanceiroSubNav
                active="cobranca"
                hidePrimary
                extraOverflowItems={[
                  { key: 'resumir',  label: 'Resumir mês',     icon: <span>✦</span>,         onClick: () => setAiOpen(true),                            title: 'Resumir cobranças deste mês — IA' },
                  { key: 'gateways', label: 'Gateways',        icon: <Settings size={13} />, onClick: () => router.visit('/settings/payment-gateways'), title: 'Configurar gateways' },
                  { key: 'remessa',  label: 'Remessa/Retorno', icon: <Upload size={13} />,   onClick: () => setRemessaOpen(true) },
                ]}
              />
              {/* FinanceiroPrimaryButton ja eh shim DEPRECATED -> roxo 295 universal (ADR 0190 + PR #1462) */}
              <FinanceiroPrimaryButton onClick={() => setNovaOpen(true)}>
                Nova cobrança
              </FinanceiroPrimaryButton>
            </div>
          </div>
        </header>
      </div>

      {/* FUNIL */}
      <div className="px-6 pt-5">
        <Deferred data="funil" fallback={<div className="h-[100px] bg-white border border-stone-200 rounded-md pg-skel" />}>
          <FunnelStrip funil={funil ?? FUNIL_FALLBACK} />
        </Deferred>
      </div>

      {/* KPIs: 3 fixos + 1 contextual */}
      <div className="px-6 pt-4 grid grid-cols-4 gap-3">
        <Deferred data="kpis" fallback={<KpiSkeleton count={4} />}>
          <KpiCard tone="emerald" label="Pago no mês" value={brl(kpis.pago_mes.valor)}
            sub={`${kpis.pago_mes.qtd} liquidações${kpis.pago_mes.qtd > 0 ? ` · ticket ${brl(kpis.pago_mes.valor / kpis.pago_mes.qtd)}` : ''}`}
            icon={<Check className="h-3 w-3" />} />
          <KpiCard tone="rose" label="Vencido" value={brl(kpis.vencido.valor)}
            sub={`${kpis.vencido.qtd} cobranças`}
            icon={<AlertCircle className="h-3 w-3" />} />
          <KpiCard label="Em aberto" value={brl(kpis.aberto.valor)}
            sub={`${kpis.aberto.qtd} cobranças`}
            icon={<Receipt className="h-3 w-3" />} />
          {kpiContextual ? (
            <KpiCard tone={kpiContextual.tone} label={kpiContextual.label} value={kpiContextual.value} sub={kpiContextual.sub} icon={kpiContextual.icon} contextual />
          ) : (
            <KpiCard tone="dark" label="Próx. janela remessa" value="hoje 18:30"
              sub="C6 CNAB diário"
              icon={<Webhook className="h-3 w-3" />} />
          )}
        </Deferred>
      </div>

      {/* FILTROS linha 1 */}
      <div className="px-6 pt-4 pb-2 flex items-center gap-2 flex-wrap">
        <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
          {([
            { id: 'all',       label: 'Todos' },
            { id: 'emitida',   label: 'Em aberto' },
            { id: 'paga',      label: 'Pagas' },
            { id: 'vencida',   label: 'Vencidas' },
            { id: 'cancelada', label: 'Canceladas' },
            { id: 'erro',      label: 'Erro' },
          ] as const).map(t => (
            <button key={t.id} onClick={() => setTabStatusLs(t.id)} className={cn(
              'h-7 px-3 rounded text-[12px] flex items-center gap-1.5 transition tabular-nums',
              tabStatus === t.id ? 'bg-white shadow-sm font-medium text-stone-900' : 'text-stone-600 hover:text-stone-800',
            )}>
              <span>{t.label}</span>
              <span className={cn(
                'text-[10px] tabular-nums px-1 rounded',
                tabStatus === t.id ? 'bg-stone-200 text-stone-700' : 'text-stone-400',
              )}>
                {statusCounts[t.id] ?? 0}
              </span>
            </button>
          ))}
        </div>

        <div className="ml-auto relative">
          <Search className="absolute left-2 top-1/2 -translate-y-1/2 text-stone-400 h-3.5 w-3.5" />
          <input ref={buscaRef} value={busca} onChange={e => setBusca(e.target.value)}
            placeholder="cliente, doc, nosso nº, origem… (/ pra focar)"
            className="h-7 w-[280px] pl-7 pr-7 bg-white border border-stone-300 rounded-md text-[12px] focus:outline-none focus:border-stone-500 focus-visible:ring-2 focus-visible:ring-stone-400"
            aria-label="Buscar cobranças" />
          <kbd className="absolute right-2 top-1/2 -translate-y-1/2 text-[9.5px] font-mono text-stone-400 px-1 border border-stone-200 rounded">/</kbd>
        </div>
        <Btn variant="ghost"><Download className="h-3 w-3" />Exportar</Btn>
      </div>

      {/* FILTROS linha 2 */}
      <div className="px-6 pb-3 flex items-center gap-2 flex-wrap">
        <div className="text-[10px] uppercase tracking-widest font-medium text-stone-400 mr-1">Tipo</div>
        <div className="inline-flex gap-1">
          {([
            { id: 'all',      label: 'Todos' },
            { id: 'boleto',   label: 'Boleto' },
            { id: 'pix',      label: 'PIX' },
            { id: 'pix_recv', label: 'PIX Aut.' },
            { id: 'card',     label: 'Cartão' },
          ] as const).map(t => (
            <button key={t.id} onClick={() => setTipoFilterLs(t.id)} className={cn(
              'h-6 px-2 rounded text-[11px] font-medium border transition',
              tipoFilter === t.id ? 'bg-stone-900 text-white border-stone-900' : 'bg-white text-stone-700 border-stone-300 hover:bg-stone-50',
            )}>{t.label}</button>
          ))}
        </div>

        <div className="w-px h-5 bg-stone-200 mx-1" />

        <select value={gatewayFilter} onChange={e => setGatewayFilterLs(e.target.value)}
          className="h-6 text-[11.5px] bg-white border border-stone-300 rounded px-2 text-stone-700 focus-visible:ring-2 focus-visible:ring-stone-400"
          aria-label="Filtrar por gateway">
          <option value="all">Todos gateways</option>
          {Object.values(DRIVERS).filter(d => !d.deprecated).map(d => <option key={d.key} value={d.key}>{d.nome}</option>)}
        </select>

        <select value={accountFilter} onChange={e => setAccountFilterLs(e.target.value)}
          className="h-6 text-[11.5px] bg-white border border-stone-300 rounded px-2 text-stone-700"
          aria-label="Filtrar por conta destino">
          <option value="all">Todas contas destino</option>
          {accounts.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
        </select>

        <div className="w-px h-5 bg-stone-200 mx-1" />

        <div className="text-[10px] uppercase tracking-widest font-medium text-stone-400 mr-1">Origem</div>
        <div className="inline-flex gap-1">
          {([
            { id: 'all',     label: 'Todas' },
            { id: 'sale',    label: 'Venda' },
            { id: 'invoice', label: 'Recorrente' },
            isSaasBusiness ? { id: 'subscription_license', label: 'SaaS Oimpresso' } : null,
          ].filter((x): x is NonNullable<typeof x> => x !== null)).map(o => (
            <button key={o.id} onClick={() => setOrigemFilterLs(o.id)} className={cn(
              'h-6 px-2 rounded text-[11px] font-medium border transition',
              origemFilter === o.id ? 'bg-stone-700 text-white border-stone-700' : 'bg-white text-stone-600 border-stone-200 hover:bg-stone-50',
            )}>{o.label}</button>
          ))}
        </div>
      </div>

      {/* TABELA */}
      <div className="px-6 pb-6 flex-1 overflow-auto">
        <Deferred data="cobrancas" fallback={<TableSkeleton />}>
          <div className="bg-white border border-stone-200 rounded-md overflow-hidden">
            <table className="w-full text-[12.5px] tabular-nums">
              <thead>
                <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/60">
                  <th className="pl-5 pr-2 py-2 text-left font-medium w-[100px]">Vencimento</th>
                  <th className="px-2 py-2 text-left font-medium">Pagador / Origem</th>
                  <th className="px-2 py-2 text-left font-medium w-[110px]">Tipo · Gateway</th>
                  <th className="px-2 py-2 text-left font-medium w-[180px]">Conta destino</th>
                  <th className="px-2 py-2 text-left font-medium w-[120px]">Nosso nº</th>
                  <th className="px-2 py-2 text-right font-medium w-[110px]">Valor</th>
                  <th className="px-2 py-2 text-left font-medium w-[110px]">Status</th>
                  <th className="pl-2 pr-5 py-2 text-right font-medium w-[100px]"></th>
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0 && <EmptyRow setNovaOpen={() => setNovaOpen(true)} cause={gatewayFilter !== 'all' ? 'gateway' : 'filter'} />}
                {filtered.map((c, idx) => {
                  const acct = accounts.find(a => a.id === c.account_id);
                  const overdue = c.vencimento < today && c.status === 'emitida';
                  const isFocus = idx === focusIdx;
                  return (
                    <tr key={c.id} onClick={() => setDrawer(c)} className={cn(
                      'border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer',
                      isFocus && 'bg-blue-50/40 ring-1 ring-inset ring-blue-300',
                    )}>
                      <td className="pl-5 pr-2 py-2.5 text-stone-700">
                        <div className="font-medium">{fmtDate(c.vencimento)}</div>
                        <div className={cn('text-[10.5px]', overdue ? 'text-rose-600' : 'text-stone-400')}>
                          {overdue
                            ? `${Math.round((new Date(today).getTime() - new Date(c.vencimento).getTime()) / 86400000)}d atraso`
                            : fmtDateRel(c.vencimento, today)}
                        </div>
                      </td>
                      <td className="px-2 py-2.5">
                        <div className="font-medium text-stone-900 truncate max-w-[300px]">{c.contato}</div>
                        <div className="flex items-center gap-1.5 mt-0.5">
                          {c.contato_doc && <span className="text-[10.5px] text-stone-400 font-mono">{piiMask(c.contato_doc)}</span>}
                          {c.origem_type && <span className="text-stone-300 text-[10px]">·</span>}
                          {c.origem_type && <OrigemChip tipo={c.origem_type as OrigemType} label={c.origem_label?.replace(/^[^·]+·\s*/, '') || ORIGENS[c.origem_type as OrigemType]?.label} />}
                        </div>
                      </td>
                      <td className="px-2 py-2.5"><GatewayTipoChip gateway={c.gateway} tipo={c.tipo} /></td>
                      <td className="px-2 py-2.5 text-stone-600">
                        <div className="text-[11.5px] truncate">{acct?.name || <span className="text-stone-400">—</span>}</div>
                        {acct?.banco && <div className="text-[10px] text-stone-400">{acct.banco}{acct.agencia ? ` · Ag ${acct.agencia}` : ''}</div>}
                      </td>
                      <td className="px-2 py-2.5 font-mono text-[11px] text-stone-600 truncate">
                        {c.nosso_numero || (
                          c.tipo === 'pix_recv' ? <span className="text-stone-400">mandato #{c.id}</span>
                            : c.tipo?.startsWith('pix') ? <span className="text-stone-400">cob_{c.id}</span>
                            : <span className="text-stone-400">—</span>
                        )}
                      </td>
                      <td className="px-2 py-2.5 text-right font-semibold tabular-nums">{brlNoSign(c.valor)}</td>
                      <td className="px-2 py-2.5">
                        <StatusBadge status={c.status} />
                        {c.tipo === 'pix_recv' && c.status === 'emitida' && (
                          <div className="text-[10px] text-violet-700 mt-0.5">mandato ativo</div>
                        )}
                      </td>
                      <td className="pl-2 pr-5 py-2.5 text-right" onClick={e => e.stopPropagation()}>
                        <div className="pg-row-actions inline-flex items-center gap-0.5">
                          <button title="Copiar identificador" className="pg-action-btn" aria-label="Copiar"><Copy className="h-3 w-3" /></button>
                          {c.tipo === 'boleto' && <button title="Baixar PDF" className="pg-action-btn" aria-label="Baixar PDF"><Download className="h-3 w-3" /></button>}
                          <button title="Mais ações" className="pg-action-btn" aria-label="Mais"><MoreHorizontal className="h-3 w-3" /></button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Deferred>
      </div>

      {drawer && <DrawerCobranca cob={drawer} accounts={accounts} today={today} onClose={() => setDrawer(null)} />}
      {novaOpen && <SheetNovaCobranca accounts={accounts} onClose={() => setNovaOpen(false)} />}
      {remessaOpen && <SheetRemessaRetorno onClose={() => setRemessaOpen(false)} />}
      {cheatOpen && <CheatSheet onClose={() => setCheatOpen(false)} />}
      {aiOpen && <AiResumoMes kpis={kpis} cobs={cobrancas ?? []} onClose={() => setAiOpen(false)} />}
    </div>
  );
}

function KpiSkeleton({ count = 4 }: { count?: number }) {
  return <>{Array.from({ length: count }).map((_, i) => <div key={i} className="h-[90px] pg-skel rounded-md" />)}</>;
}

function TableSkeleton() {
  return (
    <div className="bg-white border border-stone-200 rounded-md overflow-hidden">
      {Array.from({ length: 8 }).map((_, i) => (
        <div key={i} className="h-10 border-b border-stone-100 last:border-b-0 px-4 flex items-center gap-3">
          <div className="w-[100px] h-3 pg-skel" />
          <div className="flex-1 h-3 pg-skel" />
          <div className="w-[80px] h-3 pg-skel" />
          <div className="w-[100px] h-3 pg-skel" />
        </div>
      ))}
    </div>
  );
}

function EmptyRow({ setNovaOpen, cause }: { setNovaOpen: () => void; cause: 'gateway' | 'filter' }) {
  return (
    <tr>
      <td colSpan={8} className="py-12 text-center">
        <div className="inline-flex flex-col items-center gap-3">
          <Receipt className="h-6 w-6 text-stone-400" />
          <div>
            <div className="text-[13px] font-medium text-stone-700">Nenhuma cobrança encontrada</div>
            <div className="text-[11.5px] text-stone-500 mt-0.5">
              {cause === 'gateway' ? 'Talvez este gateway não tenha credencial ativa.' : 'Tente outro filtro ou crie uma nova cobrança.'}
            </div>
          </div>
          <div className="flex gap-2 mt-1">
            {cause === 'gateway' && (
              <Btn variant="outline" onClick={() => router.visit('/settings/payment-gateways')}>
                <Settings className="h-3 w-3" />Configurar gateway
              </Btn>
            )}
            <Btn variant="primary" onClick={setNovaOpen}><Plus className="h-3 w-3" />Nova cobrança</Btn>
          </div>
        </div>
      </td>
    </tr>
  );
}

CobrancaPage.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro — Cobrança"
    breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Cobrança' }]}
  >
    {page}
  </AppShellV2>
);

export default CobrancaPage;
