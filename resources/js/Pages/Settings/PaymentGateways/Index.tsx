// Settings/PaymentGateways/Index.tsx — F3 PaymentGateway UI Tela 2
// Port literal pg-payment-gateways-page.jsx Cowork F1.5 (score 93/100) aprovado [W] 2026-05-19.
//
// Refs:
//  - prototipo-ui/prototipos/payment-gateway-ui/components/pg-payment-gateways-page.jsx
//  - resources/js/Pages/Settings/PaymentGateways/Index.charter.md
//  - memory/requisitos/PaymentGateway/RUNBOOK-settings-gateways.md
//  - ADR 0144 + 0170 PaymentGateway · ADR 0093 Tier 0 multi-tenant
//
// Persona: Wagner (superadmin / dono). Bundle CSS:
// resources/css/cowork-payment-gateway-bundle.css (compartilhado com Cobrança).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, Deferred } from '@inertiajs/react';
import {
  useCallback, useEffect, useMemo, useState, type ReactNode,
} from 'react';
import {
  Plus, Shield, ArrowLeft, RefreshCw, Settings, MoreHorizontal,
  AlertCircle, Webhook, Receipt,
} from 'lucide-react';
import { Btn, KpiCard, PageHeader } from '../../Financeiro/Cobranca/_components/atoms';
import { DriverChip, HealthBadge, Toggle } from './_components/atoms-settings';
import DrawerGateway from './_components/DrawerGateway';
import SheetNovoGateway from './_components/SheetNovoGateway';
import ConfirmToggleModal from './_components/ConfirmToggleModal';
import CheatSheetSettings from './_components/CheatSheetSettings';
import {
  DRIVERS, TIPOS, cn, fmtDate, fmtDateRel, lsGetSettings, lsSetSettings,
  type SettingsGateway, type SettingsKpis, type Account, type GatewayKey,
} from './_lib/gateway-shared';

interface Props {
  gateways: SettingsGateway[];
  accounts: Account[];
  kpis: SettingsKpis;
  today: string;
}

const KPI_FALLBACK: SettingsKpis = { ativos: 0, total: 0, fail: 0, cobs_hoje: 0 };

function PaymentGatewaysPage({ gateways = [], accounts = [], kpis = KPI_FALLBACK, today }: Props) {
  const [drawer, setDrawer] = useState<SettingsGateway | null>(null);
  const [novoOpen, setNovoOpen] = useState(false);
  const [confirmToggle, setConfirmToggle] = useState<{ gateway: SettingsGateway; newValue: boolean } | null>(null);
  const [cheatOpen, setCheatOpen] = useState(false);
  const [focusIdx, setFocusIdx] = useState(-1);
  const [filtroDriver, setFiltroDriver] = useState(() => lsGetSettings<string>('driver', 'all'));

  const setFiltroDriverLs = useCallback((v: string) => { setFiltroDriver(v); lsSetSettings('driver', v); }, []);

  const gatewaysFiltered = useMemo(() => (
    gateways.filter(g => filtroDriver === 'all' || g.driver === filtroDriver)
  ), [gateways, filtroDriver]);

  const configuredDrivers = useMemo(() => new Set(gateways.map(g => g.driver)), [gateways]);
  const availableDrivers = useMemo(() => (
    Object.values(DRIVERS).filter(d => !configuredDrivers.has(d.key))
  ), [configuredDrivers]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement | null;
      const inField = !!target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
      if (e.key === 'Escape') {
        if (drawer) { setDrawer(null); e.preventDefault(); }
        else if (novoOpen) { setNovoOpen(false); e.preventDefault(); }
        else if (confirmToggle) { setConfirmToggle(null); e.preventDefault(); }
        else if (cheatOpen) { setCheatOpen(false); e.preventDefault(); }
        return;
      }
      if (inField || drawer || novoOpen || confirmToggle || cheatOpen) return;
      if (e.key === '?') { e.preventDefault(); setCheatOpen(true); }
      else if (e.key === 'n' || e.key === 'N') { e.preventDefault(); setNovoOpen(true); }
      else if (e.key === 'j' || e.key === 'ArrowDown') { e.preventDefault(); setFocusIdx(i => Math.min(gatewaysFiltered.length - 1, i + 1)); }
      else if (e.key === 'k' || e.key === 'ArrowUp') { e.preventDefault(); setFocusIdx(i => Math.max(0, i - 1)); }
      else if (e.key === 'Enter' && focusIdx >= 0) {
        e.preventDefault();
        const g = gatewaysFiltered[focusIdx];
        if (g) setDrawer(g);
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [drawer, novoOpen, confirmToggle, cheatOpen, focusIdx, gatewaysFiltered]);

  const hasWarn = gateways.some(g => g.warn && g.health !== 'down');

  const handleToggle = (g: SettingsGateway, newValue: boolean) => {
    setConfirmToggle({ gateway: g, newValue });
  };

  const doToggle = (g: SettingsGateway) => {
    fetch(`/settings/payment-gateways/${g.id}/toggle`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '',
        'Accept': 'application/json',
      },
    }).then(() => router.reload({ only: ['gateways', 'kpis'] }));
  };

  return (
    <div className="h-full bg-stone-50 flex flex-col font-sans pg-shell-scope">
      <PageHeader
        title="Gateways de Pagamento"
        breadcrumb="Configurações · Pagamento"
        right={<>
          <Btn variant="ghost" onClick={() => router.visit('/financeiro/cobranca')} title="Voltar pra Cobrança">
            <ArrowLeft className="h-3 w-3" />Voltar
          </Btn>
          <Btn variant="outline" onClick={() => fetch('/settings/payment-gateways/health-check', { method: 'POST', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '' } }).then(() => router.reload({ only: ['gateways'] }))}>
            <Shield className="h-3 w-3" />Testar todos
          </Btn>
          <Btn variant="primary" onClick={() => setNovoOpen(true)}>
            <Plus className="h-3 w-3" />Novo gateway
          </Btn>
        </>}
      />

      {/* KPIs */}
      <div className="px-6 pt-5 grid grid-cols-3 gap-3">
        <Deferred data="kpis" fallback={<><div className="h-[90px] pg-skel rounded-md" /><div className="h-[90px] pg-skel rounded-md" /><div className="h-[90px] pg-skel rounded-md" /></>}>
          <KpiCard
            label="Credenciais ativas"
            value={kpis.ativos}
            sub={`de ${kpis.total} configuradas · ${kpis.total - kpis.ativos} inativas/legacy`}
            icon={<Shield className="h-3 w-3" />}
          />
          <KpiCard
            tone={kpis.fail > 0 ? 'rose' : 'emerald'}
            label="Health check"
            value={kpis.fail > 0 ? `${kpis.fail} fail` : '100% OK'}
            sub={kpis.fail > 0 ? 'ver detalhes no drawer' : 'todos drivers respondendo'}
            icon={<Webhook className="h-3 w-3" />}
          />
          <button onClick={() => router.visit('/financeiro/cobranca')} className="text-left appearance-none p-0 m-0 bg-transparent border-0" title="Abrir Financeiro · Cobrança">
            <KpiCard
              label="Cobranças hoje ↗"
              value={kpis.cobs_hoje}
              sub="emitidas em todos drivers · clique pra ver"
              icon={<Receipt className="h-3 w-3" />}
            />
          </button>
        </Deferred>
      </div>

      {/* Aviso warn */}
      {hasWarn && (
        <div className="px-6 pt-3">
          <div className="bg-amber-50 border border-amber-200 rounded-md p-3 flex items-start gap-3 text-[11.5px]">
            <AlertCircle className="h-4 w-4 text-amber-700 mt-0.5" />
            <div className="flex-1 text-amber-900">
              <strong>Credencial(is) precisam de atenção</strong> — verifique no drawer de cada gateway abaixo (warn label).
            </div>
          </div>
        </div>
      )}

      {/* TABELA */}
      <div className="px-6 pt-4 pb-3 flex items-center gap-2">
        <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Gateways configurados · {gateways.length}</div>
        <div className="flex-1" />
        <div className="text-[11px] text-stone-500 tabular-nums">hoje {today}</div>
      </div>

      <div className="px-6 pb-6 flex-1 overflow-auto">
        <Deferred data="gateways" fallback={<TableSkeleton />}>
          <div className="bg-white border border-stone-200 rounded-md overflow-hidden">
            <table className="w-full text-[12.5px] tabular-nums">
              <thead>
                <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/60">
                  <th className="pl-5 pr-2 py-2 text-left font-medium">Apelido</th>
                  <th className="px-2 py-2 text-left font-medium w-[200px]">Driver</th>
                  <th className="px-2 py-2 text-left font-medium w-[120px]">Ambiente</th>
                  <th className="px-2 py-2 text-left font-medium w-[200px]">Conta destino</th>
                  <th className="px-2 py-2 text-left font-medium w-[140px]">Health</th>
                  <th className="px-2 py-2 text-left font-medium w-[100px]">Ativo</th>
                  <th className="pl-2 pr-5 py-2 text-right font-medium w-[100px]"></th>
                </tr>
              </thead>
              <tbody>
                {gatewaysFiltered.length === 0 && (
                  <tr><td colSpan={7} className="py-12 text-center">
                    <div className="inline-flex flex-col items-center gap-3">
                      <Settings className="h-6 w-6 text-stone-400" />
                      <div>
                        <div className="text-[13px] font-medium text-stone-700">Nenhum gateway configurado ainda</div>
                        <div className="text-[11.5px] text-stone-500 mt-0.5">Configure pelo menos 1 driver pra emitir cobranças.</div>
                      </div>
                      <Btn variant="primary" onClick={() => setNovoOpen(true)}><Plus className="h-3 w-3" />Configurar primeiro gateway</Btn>
                    </div>
                  </td></tr>
                )}
                {gatewaysFiltered.map((g, idx) => {
                  const acct = accounts.find(a => a.id === g.account_id);
                  const isFocus = idx === focusIdx;
                  return (
                    <tr key={g.id} onClick={() => setDrawer(g)} className={cn(
                      'border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer',
                      isFocus && 'bg-blue-50/40 ring-1 ring-inset ring-blue-300',
                    )}>
                      <td className="pl-5 pr-2 py-2.5">
                        <div className="font-medium text-stone-900">{g.nome}</div>
                        {g.warn && (
                          <div className="text-[10.5px] text-amber-700 mt-0.5 flex items-center gap-1">
                            <AlertCircle className="h-2.5 w-2.5" />{g.warn}
                          </div>
                        )}
                      </td>
                      <td className="px-2 py-2.5"><DriverChip driver={g.driver} /></td>
                      <td className="px-2 py-2.5">
                        <span className={cn(
                          'inline-flex items-center gap-1 text-[10.5px] font-medium px-1.5 py-0.5 rounded border',
                          g.ambiente === 'production' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200',
                        )}>
                          <span className={cn('w-1 h-1 rounded-full', g.ambiente === 'production' ? 'bg-emerald-500' : 'bg-amber-500')} />
                          {g.ambiente === 'production' ? 'Produção' : 'Sandbox'}
                        </span>
                      </td>
                      <td className="px-2 py-2.5 text-stone-700">
                        {acct ? acct.name : <span className="text-stone-400 italic">não vinculado</span>}
                      </td>
                      <td className="px-2 py-2.5">
                        <HealthBadge status={g.health} />
                        <div className="text-[10.5px] text-stone-400 tabular-nums mt-0.5">
                          {g.latencia ? `${g.latencia}ms · ` : ''}{g.last_check ? fmtDateRel(g.last_check.slice(0, 10), today) : 'nunca testado'}
                        </div>
                      </td>
                      <td className="px-2 py-2.5">
                        <Toggle on={g.ativo} onConfirm={(newVal) => handleToggle(g, newVal)} />
                      </td>
                      <td className="pl-2 pr-5 py-2.5 text-right" onClick={e => e.stopPropagation()}>
                        <button title="Rodar health check" className="pg-action-btn" aria-label="Testar conexão"><RefreshCw className="h-3 w-3" /></button>
                        <button title="Configurar" className="pg-action-btn" aria-label="Configurar" onClick={() => setDrawer(g)}><Settings className="h-3 w-3" /></button>
                        <button title="Mais ações" className="pg-action-btn" aria-label="Mais"><MoreHorizontal className="h-3 w-3" /></button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Deferred>

        {availableDrivers.length > 0 && (
          <div className="mt-6">
            <div className="flex items-center gap-2 mb-2">
              <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Drivers disponíveis</div>
              <div className="text-[10px] text-stone-400">({availableDrivers.length} não configurado{availableDrivers.length === 1 ? '' : 's'})</div>
            </div>
            <div className="grid grid-cols-5 gap-3">
              {availableDrivers.map(d => (
                <button key={d.key} onClick={() => setNovoOpen(true)} className={cn(
                  'text-left bg-white border rounded-md p-3 transition',
                  d.deprecated ? 'border-amber-200 opacity-70' : 'border-stone-200 hover:shadow-sm hover:border-stone-400 cursor-pointer',
                )}>
                  <div className="flex items-start gap-2">
                    <span className={cn('w-7 h-7 rounded-sm grid place-items-center text-white text-[12px] font-bold', d.dot)}>{d.sigla}</span>
                    <div className="flex-1 min-w-0">
                      <div className="text-[12.5px] font-semibold truncate">{d.nome}</div>
                      {d.deprecated && <div className="text-[9.5px] uppercase tracking-widest font-bold text-amber-700">deprecated</div>}
                    </div>
                  </div>
                  <div className="mt-2 flex flex-wrap gap-1">
                    {d.tipos.map(t => {
                      const tp = TIPOS[t];
                      return <span key={t} className={cn('text-[9.5px] font-medium px-1 py-0.5 rounded', tp?.bg, tp?.fg)}>{tp?.short}</span>;
                    })}
                  </div>
                  <div className="text-[10.5px] text-stone-500 mt-2 leading-snug line-clamp-2">{d.cred}</div>
                  <div className="mt-2 text-[11px] font-medium text-stone-900 inline-flex items-center gap-1">
                    <Plus className="h-3 w-3" />Configurar
                  </div>
                </button>
              ))}
            </div>
          </div>
        )}
      </div>

      {drawer && <DrawerGateway gateway={drawer} accounts={accounts} onClose={() => setDrawer(null)} onToggle={(newVal) => handleToggle(drawer, newVal)} />}
      {novoOpen && <SheetNovoGateway accounts={accounts} onClose={() => setNovoOpen(false)} />}
      {confirmToggle && (
        <ConfirmToggleModal
          gateway={confirmToggle.gateway}
          newValue={confirmToggle.newValue}
          onConfirm={() => doToggle(confirmToggle.gateway)}
          onClose={() => setConfirmToggle(null)}
        />
      )}
      {cheatOpen && <CheatSheetSettings onClose={() => setCheatOpen(false)} />}
    </div>
  );
}

function TableSkeleton() {
  return (
    <div className="bg-white border border-stone-200 rounded-md overflow-hidden">
      {Array.from({ length: 5 }).map((_, i) => (
        <div key={i} className="h-12 border-b border-stone-100 last:border-b-0 px-4 flex items-center gap-3">
          <div className="flex-1 h-3 pg-skel" />
          <div className="w-[120px] h-3 pg-skel" />
          <div className="w-[80px] h-3 pg-skel" />
          <div className="w-[50px] h-3 pg-skel" />
        </div>
      ))}
    </div>
  );
}

PaymentGatewaysPage.layout = (page: ReactNode) => (
  <AppShellV2
    title="Configurações — Gateways de Pagamento"
    breadcrumbItems={[{ label: 'Configurações', href: '/settings' }, { label: 'Gateways' }]}
  >
    {page}
  </AppShellV2>
);

export default PaymentGatewaysPage;
