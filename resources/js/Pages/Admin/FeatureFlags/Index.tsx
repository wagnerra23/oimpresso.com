// US-INFRA-008 (2026-05-13) — Painel admin de feature flags GrowthBook.
// Wagner-only (middleware is-wagner + tailscale-only herdado de Routes/web.php).
// Lê via GrowthBookAdminService.listFeatures() + 20 audits recentes.
//
// DS uplift (2026-05-31): cores cruas amber/red -> Alert/EmptyState/Badge tokens.
// `features` + `recent_audits` agora Inertia::defer (controller index) com
// <Deferred fallback={skeleton}> — SPA-feel (RUNBOOK-inertia-defer-pattern).
// Charter ao lado: Index.charter.md (status draft).

import { Head, Link, router, Deferred } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertTitle, AlertDescription } from '@/Components/ui/alert';
import { Skeleton } from '@/Components/ui/skeleton';

interface EnvSummary {
  enabled: boolean;
  rules: Array<{ id?: string; type?: string; value?: string; enabled?: boolean }>;
}

interface FeatureSummary {
  id: string;
  valueType?: string;
  defaultValue?: string;
  environments?: Record<string, EnvSummary>;
}

interface AuditRow {
  id: number;
  created_at: string;
  actor_label: string;
  flag_key: string;
  action: string;
  environment?: string | null;
  diff_summary?: string | null;
}

interface PageProps {
  configured: boolean;
  features?: FeatureSummary[];
  fetch_error?: string | null;
  recent_audits?: AuditRow[];
}

function TableSkeleton({ rows = 4 }: { rows?: number }) {
  return (
    <div className="space-y-2 py-2" aria-hidden="true">
      {Array.from({ length: rows }).map((_, i) => (
        <Skeleton key={i} className="h-8 w-full" />
      ))}
    </div>
  );
}

export default function FeatureFlagsIndex({
  configured,
  features,
  fetch_error,
  recent_audits,
}: PageProps) {
  return (
    <AppShellV2>
      <Head title="Feature Flags · Admin" />

      <div className="container mx-auto p-4 space-y-4">
        <PageHeader
          icon="toggle-left"
          title="Feature Flags"
          description="GrowthBook self-hosted · Wagner-only · audit em feature_flag_audits"
        />

        {!configured && (
          <Alert variant="default">
            <AlertTitle>GrowthBookAdminService não configurado</AlertTitle>
            <AlertDescription>
              <p>
                Defina <code>GROWTHBOOK_ADMIN_API_TOKEN</code> +{' '}
                <code>GROWTHBOOK_ADMIN_API_HOST</code> no <code>.env</code>. Token gerado em{' '}
                <a
                  href="https://growthbook.oimpresso.com"
                  target="_blank"
                  rel="noreferrer"
                  className="font-medium underline underline-offset-2"
                >
                  growthbook.oimpresso.com
                </a>{' '}
                → Settings → Personal Access Tokens.
              </p>
            </AlertDescription>
          </Alert>
        )}

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>
              Features configuradas{' '}
              <span className="text-sm text-muted-foreground">
                ({features?.length ?? 0})
              </span>
            </CardTitle>
            <Button
              size="sm"
              variant="outline"
              onClick={() => router.post(route('admin.feature-flags.cache.clear'))}
            >
              Limpar cache local
            </Button>
          </CardHeader>
          <CardContent>
            {fetch_error ? (
              <Alert variant="destructive">
                <AlertTitle>Falha ao buscar features</AlertTitle>
                <AlertDescription>
                  <code>{fetch_error}</code>
                </AlertDescription>
              </Alert>
            ) : (
              <Deferred data="features" fallback={<TableSkeleton rows={5} />}>
                {(features?.length ?? 0) === 0 ? (
                  <EmptyState
                    icon="flag"
                    title="Nenhuma feature flag configurada"
                    description="Crie via GrowthBook UI ou pela CLI php artisan flag:set."
                    action={
                      <Button asChild size="sm" variant="outline">
                        <a
                          href="https://growthbook.oimpresso.com"
                          target="_blank"
                          rel="noreferrer"
                        >
                          Abrir GrowthBook
                        </a>
                      </Button>
                    }
                  />
                ) : (
                  <table className="w-full text-sm">
                    <thead className="text-left text-muted-foreground border-b border-border">
                      <tr>
                        <th className="py-2">Key</th>
                        <th>Type</th>
                        <th>Default</th>
                        <th>Environments</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(features ?? []).map((f) => (
                        <tr key={f.id} className="border-b border-border last:border-0">
                          <td className="py-2 font-mono">
                            <Link
                              href={route('admin.feature-flags.show', { key: f.id })}
                              className="text-primary hover:underline"
                            >
                              {f.id}
                            </Link>
                          </td>
                          <td>{f.valueType ?? 'boolean'}</td>
                          <td className="font-mono">{String(f.defaultValue ?? '?')}</td>
                          <td>
                            <div className="flex flex-wrap gap-2">
                              {Object.entries(f.environments ?? {}).map(([envName, envData]) => (
                                <Badge
                                  key={envName}
                                  variant={envData.enabled ? 'default' : 'secondary'}
                                  className="text-xs"
                                >
                                  {envName} · {envData.enabled ? 'ON' : 'OFF'} ·{' '}
                                  {envData.rules?.length ?? 0} rule
                                  {(envData.rules?.length ?? 0) === 1 ? '' : 's'}
                                </Badge>
                              ))}
                            </div>
                          </td>
                          <td>
                            <Link
                              href={route('admin.feature-flags.show', { key: f.id })}
                              className="text-primary hover:underline text-xs"
                            >
                              editar →
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </Deferred>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>
              Audit log recente{' '}
              <span className="text-sm text-muted-foreground">
                (últimas {recent_audits?.length ?? 0})
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="recent_audits" fallback={<TableSkeleton rows={4} />}>
              {(recent_audits?.length ?? 0) === 0 ? (
                <EmptyState
                  icon="history"
                  title="Sem mudanças registradas"
                  description="As alterações de flags por painel, CLI ou tool MCP aparecem aqui."
                  variant="default"
                />
              ) : (
                <table className="w-full text-xs">
                  <thead className="text-left text-muted-foreground border-b border-border">
                    <tr>
                      <th className="py-1">Quando</th>
                      <th>Quem</th>
                      <th>Flag</th>
                      <th>Ação</th>
                      <th>Env</th>
                      <th>Resumo</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(recent_audits ?? []).map((a) => (
                      <tr key={a.id} className="border-b border-border last:border-0">
                        <td className="py-1 whitespace-nowrap">
                          {new Date(a.created_at).toLocaleString('pt-BR')}
                        </td>
                        <td className="font-mono">{a.actor_label}</td>
                        <td className="font-mono">
                          <Link
                            href={route('admin.feature-flags.show', { key: a.flag_key })}
                            className="text-primary hover:underline"
                          >
                            {a.flag_key}
                          </Link>
                        </td>
                        <td>
                          <Badge variant="outline" className="text-xs">
                            {a.action}
                          </Badge>
                        </td>
                        <td>{a.environment ?? '—'}</td>
                        <td className="text-muted-foreground">{a.diff_summary ?? '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </Deferred>
          </CardContent>
        </Card>
      </div>
    </AppShellV2>
  );
}
