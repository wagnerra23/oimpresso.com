// @memcofre tela=/admin module=Admin
// Sprint 1 dia 3-4 (US-ADM-004..008): Centro de Operações Wagner-only.
// Read-mostly aggregator de Brief + Health + Cycles + ADRs Tier 0 violados.
//
// ADR mãe: 0122. Charter ao lado: Index.charter.md.
// NÃO substitui Officeimpresso superadmin nem /copiloto/admin/team — agrega.

import { Deferred } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import StatusBadge from '@/Components/shared/StatusBadge';
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
import WidgetMutations from './_components/WidgetMutations';

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

// Fallback skeleton enquanto cada widget caro resolve via <Deferred>.
// Controller mantém eager load (rollback PR #963, test-locked) — <Deferred>
// lê a prop eager no render inicial e só re-busca em partial reload `only:[]`,
// dando SPA-feel sem quebrar initial render. Cada widget tem skeleton próprio,
// então infra ping/mcp/vault lentos não bloqueiam os de governança.
function WidgetSkeleton({ rows = 3 }: { rows?: number }) {
  return (
    <div className="space-y-2" aria-busy="true">
      {Array.from({ length: rows }).map((_, i) => (
        <Skeleton key={i} className="h-4 w-full" />
      ))}
    </div>
  );
}

export default function AdminIndex({ widgets, meta }: PageProps) {
  const { brief, health, cycles, adr_alerts, curador, mcp, vaultwarden, sessions, infra, brain_b_cost } = widgets;

  return (
    <div className="container mx-auto p-4 space-y-4">
      {/* Top-bar alerta Tier 0 (vermelho se algum violado) */}
      {adr_alerts.available && (adr_alerts.count ?? 0) > 0 && (
        <div className="bg-destructive text-destructive-foreground rounded-lg px-4 py-3 flex items-center gap-3">
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
        <div className="bg-muted border border-border text-muted-foreground rounded px-3 py-2 text-sm">
          ⚠️ ADMIN_BYPASS_LOCAL ativo — middlewares Tailscale + IsWagner
          desabilitados. Apenas dev local.
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Saúde crítica promovida ao topo: Health + ADRs Tier 0 (P1). */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="activity" /> Health Checks
              {health.available && (
                <span className="ml-auto">
                  <StatusBadge kind="admin_health" value={health.overall_status} />
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="widgets" fallback={<WidgetSkeleton rows={5} />}>
              <WidgetHealth data={health} />
            </Deferred>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="alert-triangle" /> ADRs Tier 0 violadas
              {adr_alerts.available && (adr_alerts.count ?? 0) > 0 && (
                <span className="ml-auto">
                  <StatusBadge kind="admin_health" value="red" label={`${adr_alerts.count} violada(s)`} />
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="widgets" fallback={<WidgetSkeleton rows={3} />}>
              <WidgetAdrTier0 data={adr_alerts} />
            </Deferred>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="newspaper" /> Brief diário
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="widgets" fallback={<WidgetSkeleton rows={4} />}>
              <WidgetBrief data={brief} />
            </Deferred>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="archive" /> Arquivos / Curador
              {curador.available && curador.sensitive_count > 0 && (
                <span className="ml-auto">
                  <StatusBadge kind="admin_health" value="red" label={`${curador.sensitive_count} sensitive`} />
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="widgets" fallback={<WidgetSkeleton rows={3} />}>
              <WidgetCurador data={curador} />
            </Deferred>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="kanban" /> Cycles ativos & Tasks
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="widgets" fallback={<WidgetSkeleton rows={4} />}>
              <WidgetCycles data={cycles} />
            </Deferred>
          </CardContent>
        </Card>

        {/* Infra cara (ping/mcp/vault) — defer com skeleton independente (P7). */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="server" /> MCP Server
              <span className="ml-auto">
                <StatusBadge
                  kind="admin_reachable"
                  value={mcp.available && mcp.ping.reachable ? 'online' : 'offline'}
                />
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="widgets" fallback={<WidgetSkeleton rows={3} />}>
              <WidgetMcpServer data={mcp} />
            </Deferred>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="lock" /> Vaultwarden
              {vaultwarden.available && (vaultwarden.expiring_30d ?? 0) > 0 && (
                <span className="ml-auto">
                  <StatusBadge kind="admin_health" value="yellow" label={`${vaultwarden.expiring_30d} vencendo`} />
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="widgets" fallback={<WidgetSkeleton rows={3} />}>
              <WidgetVaultwarden data={vaultwarden} />
            </Deferred>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="server" /> Infra status (5 hosts)
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="widgets" fallback={<WidgetSkeleton rows={5} />}>
              <WidgetInfraStatus data={infra} />
            </Deferred>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="dollar-sign" /> Custos Brain B 24h
              {brain_b_cost.available && (
                <span className="ml-auto">
                  <StatusBadge kind="admin_health" value={brain_b_cost.status ?? 'unknown'} />
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="widgets" fallback={<WidgetSkeleton rows={2} />}>
              <WidgetBrainBCost data={brain_b_cost} />
            </Deferred>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="users" /> Sessões Claude (cross-dev 7d)
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="widgets" fallback={<WidgetSkeleton rows={4} />}>
              <WidgetSessions data={sessions} />
            </Deferred>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2 border-2 border-border">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Icon name="zap" /> Ações
              <span className="ml-auto">
                <StatusBadge kind="admin_health" value="yellow" label="double-confirmation" />
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <WidgetMutations />
          </CardContent>
        </Card>
      </div>

      <div className="text-xs text-muted-foreground text-center pt-4">
        Gerado em {new Date(meta.generated_at).toLocaleString('pt-BR')} ·
        ADR 0122 · Read-mostly · Não substitui painéis cliente-side
      </div>
    </div>
  );
}

AdminIndex.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>;
