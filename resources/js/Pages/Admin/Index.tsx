// @memcofre tela=/admin module=Admin
// Sprint 1 dia 3-4 (US-ADM-004..008): Centro de Operações Wagner-only.
// Read-mostly aggregator de Brief + Health + Cycles + ADRs Tier 0 violados.
//
// ADR mãe: 0122. Charter ao lado: Index.charter.md.
// NÃO substitui Officeimpresso superadmin nem /copiloto/admin/team — agrega.

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Icon } from '@/Components/Icon';
import WidgetBrief from './_components/WidgetBrief';
import WidgetHealth from './_components/WidgetHealth';
import WidgetCycles from './_components/WidgetCycles';
import WidgetAdrTier0 from './_components/WidgetAdrTier0';
import WidgetCurador from './_components/WidgetCurador';
import WidgetMcpServer from './_components/WidgetMcpServer';
import WidgetVaultwarden from './_components/WidgetVaultwarden';
import WidgetSessions from './_components/WidgetSessions';
import WidgetInfraStatus from './_components/WidgetInfraStatus';
import WidgetBrainBCost from './_components/WidgetBrainBCost';

interface BriefData {
  available: boolean;
  brief_id?: number | null;
  created_at?: string | null;
  markdown?: string;
  reason?: string;
  token_estimate?: number | null;
}

interface HealthCheck {
  name: string;
  status: 'green' | 'yellow' | 'red' | 'unknown';
  message?: string;
  last_run?: string | null;
}

interface HealthData {
  available: boolean;
  generated_at?: string | null;
  checks: HealthCheck[];
  tier_0_failures: HealthCheck[];
  overall_status: 'green' | 'yellow' | 'red' | 'unknown';
  reason?: string;
  instructions?: string;
}

interface CyclesData {
  available: boolean;
  cycles_active: Array<{
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    goal_summary?: string;
  }>;
  tasks_by_dev: Array<{
    owner: string;
    total: number;
    doing: number;
    done: number;
  }>;
  current_cycle?: number | null;
  reason?: string;
}

interface AdrAlert {
  check: string;
  adr: string;
  status: string;
  message?: string;
  last_run?: string | null;
}

interface AdrAlertsData {
  available: boolean;
  tier_0_alerts: AdrAlert[];
  count?: number;
  reason?: string;
}

interface CuradorData {
  available: boolean;
  total_active: number;
  by_bucket: Record<string, number>;
  sensitive_count: number;
  audit_24h: Record<string, number>;
  dedupe_rate_pct: number;
  unique_md5?: number;
  total_occurrences?: number;
  reason?: string;
  instructions?: string;
}

interface McpData {
  available: boolean;
  docs_count: number;
  last_sync?: string | null;
  tokens_total: number;
  tokens_active: number;
  last_token_use?: string | null;
  ping: {
    reachable: boolean;
    status?: number;
    latency_ms?: number | null;
    error?: string;
  };
  reason?: string;
  instructions?: string;
}

interface VaultwardenData {
  available: boolean;
  reachable: boolean;
  latency_ms?: number;
  ciphers_total?: number;
  expiring_30d?: number;
  url?: string;
  reason?: string;
  instructions?: string;
}

interface SessionsData {
  available: boolean;
  latest: any[];
  by_dev: any[];
  reason?: string;
  instructions?: string;
}

interface InfraData {
  hostinger_ssh: { status: 'up' | 'down' | 'degraded'; latency_ms?: number | null; error?: string };
  ct100_tailscale: { status: 'up' | 'down' | 'degraded'; latency_ms?: number | null; http_status?: number };
  centrifugo: { status: 'up' | 'down' | 'degraded'; latency_ms?: number | null; http_status?: number };
  meilisearch: { status: 'up' | 'down' | 'degraded'; latency_ms?: number | null; http_status?: number };
  mysql: { status: 'up' | 'down' | 'degraded'; latency_ms?: number | null; error?: string };
}

interface BrainBCostData {
  available: boolean;
  cost_brl_24h?: number;
  threshold_brl?: number;
  pct_threshold?: number;
  status?: 'green' | 'yellow' | 'red' | 'unknown';
  last_run?: string | null;
  reason?: string;
  instructions?: string;
}

interface PageProps {
  widgets: {
    brief: BriefData;
    health: HealthData;
    cycles: CyclesData;
    adr_alerts: AdrAlertsData;
    curador: CuradorData;
    mcp: McpData;
    vaultwarden: VaultwardenData;
    sessions: SessionsData;
    infra: InfraData;
    brain_b_cost: BrainBCostData;
  };
  meta: {
    subdomain: string;
    environment: string;
    bypass_local: boolean;
    generated_at: string;
  };
}

export default function AdminIndex({ widgets, meta }: PageProps) {
  const { brief, health, cycles, adr_alerts, curador, mcp, vaultwarden, sessions, infra, brain_b_cost } = widgets;

  return (
    <div className="container mx-auto p-4 space-y-4">
      {/* Top-bar alerta Tier 0 (vermelho se algum violado) */}
      {adr_alerts.available && (adr_alerts.count ?? 0) > 0 && (
        <div className="bg-red-600 text-white rounded-lg px-4 py-3 flex items-center gap-3">
          <Icon name="alert-triangle" />
          <span className="font-semibold">
            {adr_alerts.count} ADR(s) Tier 0 violada(s) — ação imediata
          </span>
        </div>
      )}

      <PageHeader
        icon="shield-check"
        title="Centro de Operações"
        description={`Wagner-only · ${meta.subdomain} · ${meta.environment}${
          meta.bypass_local ? ' (BYPASS LOCAL)' : ''
        }`}
      />

      {meta.bypass_local && (
        <div className="bg-amber-100 border border-amber-300 text-amber-900 rounded px-3 py-2 text-sm">
          ⚠️ ADMIN_BYPASS_LOCAL ativo — middlewares Tailscale + IsWagner
          desabilitados. Apenas dev local.
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="newspaper" /> Brief diário
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetBrief data={brief} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="activity" /> Health Checks
              {health.available && (
                <span
                  className={`ml-auto text-sm px-2 py-0.5 rounded ${
                    health.overall_status === 'green'
                      ? 'bg-green-100 text-green-800'
                      : health.overall_status === 'red'
                        ? 'bg-red-100 text-red-800'
                        : 'bg-amber-100 text-amber-800'
                  }`}
                >
                  {health.overall_status}
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetHealth data={health} />
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="kanban" /> Cycles ativos & Tasks
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetCycles data={cycles} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="archive" /> Arquivos / Curador
              {curador.available && curador.sensitive_count > 0 && (
                <span className="ml-auto text-xs px-2 py-0.5 rounded bg-red-100 text-red-800">
                  {curador.sensitive_count} sensitive
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetCurador data={curador} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="server" /> MCP Server
              <span
                className={`ml-auto text-xs px-2 py-0.5 rounded ${
                  mcp.available && mcp.ping.reachable
                    ? 'bg-green-100 text-green-800'
                    : 'bg-red-100 text-red-800'
                }`}
              >
                {mcp.available && mcp.ping.reachable ? 'online' : 'offline'}
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetMcpServer data={mcp} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="lock" /> Vaultwarden
              {vaultwarden.available && (vaultwarden.expiring_30d ?? 0) > 0 && (
                <span className="ml-auto text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800">
                  {vaultwarden.expiring_30d} vencendo
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetVaultwarden data={vaultwarden} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="dollar-sign" /> Custos Brain B 24h
              {brain_b_cost.available && (
                <span
                  className={`ml-auto text-xs px-2 py-0.5 rounded ${
                    brain_b_cost.status === 'red'
                      ? 'bg-red-100 text-red-800'
                      : brain_b_cost.status === 'yellow'
                        ? 'bg-amber-100 text-amber-800'
                        : 'bg-green-100 text-green-800'
                  }`}
                >
                  {brain_b_cost.status}
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetBrainBCost data={brain_b_cost} />
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="users" /> Sessões Claude (cross-dev 7d)
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetSessions data={sessions} />
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="server" /> Infra status (5 hosts)
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetInfraStatus data={infra} />
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="alert-triangle" /> ADRs Tier 0 violadas
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetAdrTier0 data={adr_alerts} />
          </CardContent>
        </Card>
      </div>

      <div className="text-xs text-gray-500 text-center pt-4">
        Gerado em {new Date(meta.generated_at).toLocaleString('pt-BR')} ·
        ADR 0122 · Read-mostly · Não substitui painéis cliente-side
      </div>
    </div>
  );
}

AdminIndex.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>;
