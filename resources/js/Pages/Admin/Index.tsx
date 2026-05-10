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

interface PageProps {
  widgets: {
    brief: BriefData;
    health: HealthData;
    cycles: CyclesData;
    adr_alerts: AdrAlertsData;
  };
  meta: {
    subdomain: string;
    environment: string;
    bypass_local: boolean;
    generated_at: string;
  };
}

export default function AdminIndex({ widgets, meta }: PageProps) {
  const { brief, health, cycles, adr_alerts } = widgets;

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
