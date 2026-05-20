// @memcofre
//   tela: /fiscal
//   module: Fiscal (cockpit unificado)
//   status: em-implementacao
//   stories: US-FISCAL-002 (Cockpit sub-página 1 do design KB-9.75)
//   rules: R-FIN-001 (multi-tenant), R-FISCAL-001 (HasBusinessScope ADR 0093)
//   adrs: 0093, 0094, 0101, 0104, 0114
//   tests: Modules/Fiscal/Tests/Feature/CockpitMultiTenantTest
//
// Origem: design Cowork fiscal-page.jsx §8 FiscalCockpit. Persona: Eliana (contadora).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, router } from '@inertiajs/react';
import { Archive, FileText, Plus, Receipt, RefreshCw, Shield, ShieldAlert } from 'lucide-react';

import FxShell from './_components/FxShell';
import { brl } from './_lib/fiscal-helpers';

import '../../../css/fiscal-cockpit.css';

interface Kpis {
  emitidas: number;
  autorizadas: number;
  autorizadasPct: number;
  rejeitadas: number;
  faturamentoFiscal: number;
  dfeAguardando: number;
  certificadoValidadeDias: number | null;
}

interface Sparklines {
  emitidas: number[];
  autorizadas: number[];
  rejeitadas: number[];
  faturamento: number[];
}

interface Alert {
  level: 'crit' | 'warn' | 'info';
  icon: string;
  title: string;
  sub: string;
  action: string;
  goto: string;
  focus?: string;
}

interface CockpitProps {
  kpis: Kpis;
  sparklines: Sparklines;
  alerts: Alert[];
}

function MiniSparkline({
  data,
  width = 76,
  height = 24,
  color = 'currentColor',
  fill = true,
}: {
  data: number[];
  width?: number;
  height?: number;
  color?: string;
  fill?: boolean;
}) {
  if (!data?.length) return null;
  const max = Math.max(...data, 1);
  const min = Math.min(...data, 0);
  const range = max - min || 1;
  const pad = 2;
  const innerW = width - pad * 2;
  const innerH = height - pad * 2;
  const step = innerW / (data.length - 1);
  const pts = data.map<[number, number]>((v, i) => [pad + i * step, pad + innerH - ((v - min) / range) * innerH]);
  const path = pts.map((p, i) => (i === 0 ? 'M' : 'L') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
  const area = path + ` L${pad + innerW},${pad + innerH} L${pad},${pad + innerH} Z`;
  const last = pts[pts.length - 1];
  return (
    <svg className="fx-spark" viewBox={`0 0 ${width} ${height}`} width={width} height={height} aria-hidden="true">
      {fill && <path d={area} fill={color} fillOpacity={0.12} />}
      <path d={path} stroke={color} strokeWidth={1.5} fill="none" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx={last[0]} cy={last[1]} r={2.2} fill={color} />
    </svg>
  );
}

const ICON: Record<string, React.ComponentType<{ size?: number }>> = {
  audit: ShieldAlert,
  shield: Shield,
  receipt: Receipt,
  refresh: RefreshCw,
};

function AlertRow({ alert }: { alert: Alert }) {
  const Ic = ICON[alert.icon] ?? ShieldAlert;
  const target = alert.goto === 'nfe' ? '/fiscal/nfe'
    : alert.goto === 'dfe' ? '#'
    : alert.goto === 'fiscal_config' ? '#'
    : '#';

  return (
    <div className={`fx-alert ${alert.level}`} onClick={() => target !== '#' && router.visit(target)}>
      <div className="fx-alert-ic"><Ic size={14} /></div>
      <div className="fx-alert-body">
        <b>{alert.title}</b>
        <span className="sub">{alert.sub}</span>
      </div>
      <span className="fx-alert-act">{alert.action} →</span>
    </div>
  );
}

export default function Cockpit({ kpis, sparklines, alerts }: CockpitProps) {
  const goto = (path: string) => router.visit(path);
  const certColor = (kpis.certificadoValidadeDias ?? 999) <= 30 ? 'var(--bad)'
    : (kpis.certificadoValidadeDias ?? 999) <= 60 ? 'var(--warn)' : 'var(--fx-text-dim)';

  return (
    <AppShellV2>
      <Head title="Fiscal · Cockpit" />

      <FxShell
        route="fiscal"
        title="Cockpit fiscal"
        crumb={`${kpis.emitidas} emitidas no mês · ${kpis.rejeitadas} rejeitadas`}
        env={alerts.length === 0 ? 'SEFAZ-SP operacional' : `${alerts.filter((a) => a.level === 'crit').length} críticos`}
        envTone={alerts.some((a) => a.level === 'crit') ? 'bad' : 'ok'}
        cheats={[
          { keys: ['⌘', 'K'], label: 'buscar (em breve)' },
          { keys: ['2'], label: 'NF-e' },
          { keys: ['3'], label: 'NFS-e' },
          { keys: ['?'], label: 'mais atalhos' },
        ]}
        actions={
          <>
            <button className="fx-btn ghost" disabled title="PR seguinte">Exportar SPED</button>
            <button className="fx-btn primary" disabled title="PR seguinte">
              <Plus size={12} /> Emitir <kbd className="fx-kbd-inline">E</kbd>
            </button>
          </>
        }
      >
        {/* KPIs */}
        <div className="fx-kpis-cockpit">
          <div className="fx-kpi hero">
            <div className="fx-kpi-top">
              <small>Emitidas · mês corrente</small>
              <MiniSparkline data={sparklines.emitidas} color="#ffffff" />
            </div>
            <b>{kpis.emitidas}</b>
            <span className="delta">últimos 14 dias</span>
          </div>
          <div className="fx-kpi ok">
            <div className="fx-kpi-top">
              <small>Autorizadas</small>
              <MiniSparkline data={sparklines.autorizadas} color="oklch(0.55 0.13 145)" />
            </div>
            <b>{kpis.autorizadas}</b>
            <span className="delta">{kpis.autorizadasPct}% do total</span>
          </div>
          <div className={`fx-kpi bad${kpis.rejeitadas > 0 ? ' pulse' : ''}`}>
            <div className="fx-kpi-top">
              <small>Rejeitadas</small>
              <MiniSparkline data={sparklines.rejeitadas} color="oklch(0.55 0.18 25)" fill={false} />
            </div>
            <b>{kpis.rejeitadas}</b>
            <span className="delta" style={{ color: 'var(--bad)' }}>
              {kpis.rejeitadas > 0 ? 'requer ação' : 'sem rejeições'}
            </span>
          </div>
          <div className="fx-kpi">
            <div className="fx-kpi-top">
              <small>Faturado fiscal</small>
              <MiniSparkline data={sparklines.faturamento} color="var(--fis)" />
            </div>
            <b>{brl(kpis.faturamentoFiscal)}</b>
            <span className="delta">autorizadas no mês</span>
          </div>
          <div className="fx-kpi warn">
            <small>DF-e p/ manifestar</small>
            <b>{kpis.dfeAguardando}</b>
            <span className="delta">prazo 90d legal</span>
          </div>
          <div className="fx-kpi">
            <small>Certificado A1</small>
            <b>{kpis.certificadoValidadeDias ?? '—'}{kpis.certificadoValidadeDias != null ? 'd' : ''}</b>
            <span className="delta" style={{ color: certColor }}>
              {kpis.certificadoValidadeDias == null ? 'sem cert ativo'
                : kpis.certificadoValidadeDias <= 0 ? 'EXPIRADO'
                : `vence em ${kpis.certificadoValidadeDias}d`}
            </span>
          </div>
        </div>

        {/* Alertas */}
        {alerts.length > 0 && (
          <div className="fx-alerts">
            <div className="fx-alerts-h">
              <ShieldAlert size={14} />
              <h3>Pendências fiscais</h3>
              <span className="count">
                {alerts.length} · {alerts.filter((a) => a.level === 'crit').length} críticos · {alerts.filter((a) => a.level === 'warn').length} atenção · {alerts.filter((a) => a.level === 'info').length} info
              </span>
            </div>
            {alerts.map((a, i) => <AlertRow key={i} alert={a} />)}
          </div>
        )}

        {/* Quick links */}
        <div className="fx-quick">
          <div className="fx-quick-card" onClick={() => goto('/fiscal/nfe')}>
            <div className="top"><Receipt size={13} /> NF-e · NFC-e (saída)</div>
            <b>{kpis.emitidas} no mês</b>
            <small>Modelo 55 + 65 · {kpis.rejeitadas} rejeitadas</small>
            <kbd className="fx-quick-kbd">2</kbd>
          </div>
          <div className="fx-quick-card" onClick={() => goto('/fiscal/nfse')}>
            <div className="top"><FileText size={13} /> NFS-e (serviço)</div>
            <b>Sistema Nacional</b>
            <small>NT 2024-001 · LC 214/2025</small>
            <kbd className="fx-quick-kbd">3</kbd>
          </div>
          <div className="fx-quick-card" onClick={() => goto('/fiscal/dfe')}>
            <div className="top"><ShieldAlert size={13} /> Manifesto DF-e</div>
            <b>{kpis.dfeAguardando} aguardando</b>
            <small>NF-e contra nosso CNPJ · prazo 90d</small>
            <kbd className="fx-quick-kbd">4</kbd>
          </div>
          <div className="fx-quick-card" onClick={() => goto('/fiscal/eventos')}>
            <div className="top"><RefreshCw size={13} /> Eventos</div>
            <b>Timeline</b>
            <small>CC-e · cancelamento · manifestação</small>
            <kbd className="fx-quick-kbd">5</kbd>
          </div>
          <div className="fx-quick-card" onClick={() => goto('/fiscal/config')}>
            <div className="top"><Shield size={13} /> Certif. & cfg.</div>
            <b>{kpis.certificadoValidadeDias != null ? `A1 vence em ${kpis.certificadoValidadeDias}d` : 'Sem cert ativo'}</b>
            <small>Status read-only · edição via NfeBrasil</small>
            <kbd className="fx-quick-kbd">6</kbd>
          </div>
          <div className="fx-quick-card" onClick={() => goto('/fiscal/sped')}>
            <div className="top"><Archive size={13} /> SPED & livros</div>
            <b>EFD ICMS/IPI · PIS/COFINS</b>
            <small>Panorama mensal (gerador em dev)</small>
            <kbd className="fx-quick-kbd">7</kbd>
          </div>
        </div>
      </FxShell>
    </AppShellV2>
  );
}
