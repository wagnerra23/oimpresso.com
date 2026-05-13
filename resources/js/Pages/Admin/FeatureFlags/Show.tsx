// US-INFRA-008 (2026-05-13) — Detalhe + edit de 1 feature flag.
// Permite: adicionar/remover rule biz-{N}, mata-switch do environment.

import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';

interface FeatureRule {
  id?: string;
  type?: string;
  value?: string;
  condition?: string;
  enabled?: boolean;
  description?: string;
}

interface FeatureEnv {
  enabled: boolean;
  rules?: FeatureRule[];
}

interface Feature {
  id: string;
  valueType?: string;
  defaultValue?: string;
  environments?: Record<string, FeatureEnv>;
}

interface AuditRow {
  id: number;
  created_at: string;
  actor_label: string;
  action: string;
  environment?: string | null;
  diff_summary?: string | null;
  payload_before?: Record<string, unknown> | null;
  payload_after?: Record<string, unknown> | null;
}

interface PageProps {
  configured: boolean;
  key: string;
  feature: Feature | null;
  fetch_error?: string | null;
  audits: AuditRow[];
}

export default function FeatureFlagsShow({
  configured,
  key,
  feature,
  fetch_error,
  audits,
}: PageProps) {
  const [env, setEnv] = useState('production');

  const envData = useMemo<FeatureEnv | undefined>(() => {
    return feature?.environments?.[env];
  }, [feature, env]);

  const bizRuleForm = useForm({
    biz_id: '',
    value: 'true',
    remove: false,
    env,
    clear_cache: true,
  });

  const submitBizRule = (e: React.FormEvent) => {
    e.preventDefault();
    bizRuleForm.transform((d) => ({
      ...d,
      env,
      biz_id: Number(d.biz_id),
      value: d.value === 'true',
    }));
    bizRuleForm.post(route('admin.feature-flags.biz-rule', { key }), {
      onSuccess: () => bizRuleForm.reset('biz_id'),
    });
  };

  const toggleEnv = (enabled: boolean) => {
    router.post(
      route('admin.feature-flags.env-enabled', { key }),
      { enabled, env, clear_cache: true },
      { preserveScroll: true }
    );
  };

  return (
    <AppShellV2>
      <Head title={`${key} · Feature Flags`} />

      <div className="container mx-auto p-4 space-y-4">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Link
            href={route('admin.feature-flags.index')}
            className="text-blue-600 hover:underline"
          >
            ← Feature Flags
          </Link>
          <span>/</span>
          <span className="font-mono">{key}</span>
        </div>

        <PageHeader
          icon="toggle-left"
          title={key}
          description={
            feature
              ? `Type=${feature.valueType ?? 'boolean'} · Default=${String(feature.defaultValue ?? '?')}`
              : 'Feature não encontrada'
          }
        />

        {fetch_error && (
          <div className="bg-red-100 border border-red-300 text-red-900 rounded px-4 py-3 text-sm">
            ❌ {fetch_error}
          </div>
        )}

        {feature && (
          <>
            {/* Seletor de environment */}
            <div className="flex gap-2 items-center text-sm">
              <span className="text-muted-foreground">Environment:</span>
              {Object.keys(feature.environments ?? {}).map((e) => (
                <Button
                  key={e}
                  variant={e === env ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => setEnv(e)}
                >
                  {e}
                </Button>
              ))}
            </div>

            {envData && (
              <>
                {/* Mata-switch do env */}
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center justify-between">
                      <span>
                        Environment <code className="text-base">{env}</code>{' '}
                        <Badge variant={envData.enabled ? 'default' : 'destructive'}>
                          {envData.enabled ? '🟢 ON' : '🔴 OFF'}
                        </Badge>
                      </span>
                      <Button
                        size="sm"
                        variant={envData.enabled ? 'destructive' : 'default'}
                        onClick={() => {
                          if (
                            confirm(
                              `Confirma ${envData.enabled ? 'DESLIGAR' : 'LIGAR'} ${key} em ${env}?\n\n` +
                                `Isso é mata-switch global — afeta TODOS os bizs.`
                            )
                          ) {
                            toggleEnv(!envData.enabled);
                          }
                        }}
                      >
                        {envData.enabled ? 'Desligar mata-switch' : 'Ligar environment'}
                      </Button>
                    </CardTitle>
                  </CardHeader>
                </Card>

                {/* Rules */}
                <Card>
                  <CardHeader>
                    <CardTitle>
                      Rules de targeting{' '}
                      <span className="text-sm text-muted-foreground">
                        ({envData.rules?.length ?? 0})
                      </span>
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    {(envData.rules ?? []).length === 0 ? (
                      <div className="text-sm text-muted-foreground py-4 text-center">
                        Sem rules — usa <code>defaultValue</code>.
                      </div>
                    ) : (
                      <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground border-b">
                          <tr>
                            <th className="py-2">ID</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Condition</th>
                            <th>Enabled</th>
                            <th>Ação</th>
                          </tr>
                        </thead>
                        <tbody>
                          {(envData.rules ?? []).map((r) => {
                            const bizMatch = (r.id ?? '').match(/^biz-(\d+)$/);
                            const bizId = bizMatch ? Number(bizMatch[1]) : null;
                            return (
                              <tr key={r.id} className="border-b last:border-0">
                                <td className="py-2 font-mono">{r.id ?? '?'}</td>
                                <td>{r.type ?? '?'}</td>
                                <td className="font-mono">{r.value ?? '?'}</td>
                                <td className="font-mono text-xs">
                                  {r.condition ?? '(sem)'}
                                </td>
                                <td>{r.enabled ? '🟢' : '🔴'}</td>
                                <td>
                                  {bizId !== null && (
                                    <Button
                                      size="sm"
                                      variant="outline"
                                      onClick={() => {
                                        if (
                                          confirm(`Remover rule biz-${bizId}?\n\nBiz=${bizId} volta pra defaultValue.`)
                                        ) {
                                          router.post(
                                            route('admin.feature-flags.biz-rule', { key }),
                                            {
                                              biz_id: bizId,
                                              remove: true,
                                              env,
                                              clear_cache: true,
                                            },
                                            { preserveScroll: true }
                                          );
                                        }
                                      }}
                                    >
                                      Remover
                                    </Button>
                                  )}
                                </td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    )}

                    {/* Form: adicionar rule biz-{N} */}
                    <form onSubmit={submitBizRule} className="mt-6 border-t pt-4 space-y-3">
                      <div className="text-sm font-semibold">
                        Adicionar/atualizar rule por business_id
                      </div>
                      <div className="flex gap-2 items-end flex-wrap">
                        <div>
                          <label className="text-xs text-muted-foreground block mb-1">
                            business_id
                          </label>
                          <Input
                            type="number"
                            min={1}
                            value={bizRuleForm.data.biz_id}
                            onChange={(e) => bizRuleForm.setData('biz_id', e.target.value)}
                            required
                            className="w-32"
                          />
                        </div>
                        <div>
                          <label className="text-xs text-muted-foreground block mb-1">
                            value
                          </label>
                          <select
                            value={bizRuleForm.data.value}
                            onChange={(e) => bizRuleForm.setData('value', e.target.value)}
                            className="border rounded px-2 py-2 text-sm"
                          >
                            <option value="true">true (ligar)</option>
                            <option value="false">false (desligar)</option>
                          </select>
                        </div>
                        <Button type="submit" disabled={bizRuleForm.processing}>
                          Salvar rule
                        </Button>
                      </div>
                      {bizRuleForm.errors.value && (
                        <div className="text-xs text-red-600">{bizRuleForm.errors.value}</div>
                      )}
                      {bizRuleForm.errors.flag && (
                        <div className="text-xs text-red-600">{bizRuleForm.errors.flag}</div>
                      )}
                    </form>
                  </CardContent>
                </Card>
              </>
            )}
          </>
        )}

        {/* Audit history desta flag */}
        <Card>
          <CardHeader>
            <CardTitle>
              Histórico de mudanças{' '}
              <span className="text-sm text-muted-foreground">({audits.length})</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            {audits.length === 0 ? (
              <div className="text-sm text-muted-foreground py-4 text-center">
                Sem mudanças registradas pra <code>{key}</code>.
              </div>
            ) : (
              <table className="w-full text-xs">
                <thead className="text-left text-muted-foreground border-b">
                  <tr>
                    <th className="py-1">Quando</th>
                    <th>Quem</th>
                    <th>Ação</th>
                    <th>Env</th>
                    <th>Resumo</th>
                  </tr>
                </thead>
                <tbody>
                  {audits.map((a) => (
                    <tr key={a.id} className="border-b last:border-0">
                      <td className="py-1 whitespace-nowrap">
                        {new Date(a.created_at).toLocaleString('pt-BR')}
                      </td>
                      <td className="font-mono">{a.actor_label}</td>
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
          </CardContent>
        </Card>
      </div>
    </AppShellV2>
  );
}
