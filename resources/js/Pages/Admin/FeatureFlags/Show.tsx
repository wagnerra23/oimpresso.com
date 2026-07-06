// US-INFRA-008 (2026-05-13) — Detalhe + edit de 1 feature flag.
// Permite: adicionar/remover rule biz-{N}, mata-switch do environment.

import { Deferred, Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Skeleton } from '@/Components/ui/skeleton';
import { CircleDot, Circle, AlertCircle } from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Label } from '@/Components/ui/label';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';

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
  // feature, fetch_error e audits vêm via Inertia::defer — undefined no first render
  feature?: Feature | null;
  fetch_error?: string | null;
  audits?: AuditRow[];
}

export default function FeatureFlagsShow({
  key,
  feature,
  fetch_error,
  audits,
}: PageProps) {
  const [env, setEnv] = useState('production');

  // Guardas defensivas: props deferidas são undefined no first render.
  const auditRows = audits ?? [];

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
            className="text-primary hover:underline"
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
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{fetch_error}</AlertDescription>
          </Alert>
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
                      <span className="flex items-center gap-2">
                        Environment <code className="text-base">{env}</code>{' '}
                        {envData.enabled ? (
                          <Badge variant="default" className="gap-1">
                            <CircleDot className="h-3.5 w-3.5" />
                            ON
                          </Badge>
                        ) : (
                          <Badge variant="secondary" className="gap-1">
                            <Circle className="h-3.5 w-3.5" />
                            OFF
                          </Badge>
                        )}
                      </span>
                      <AlertDialog>
                        <AlertDialogTrigger asChild>
                          <Button
                            size="sm"
                            variant={envData.enabled ? 'destructive' : 'default'}
                          >
                            {envData.enabled ? 'Desligar mata-switch' : 'Ligar environment'}
                          </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                          <AlertDialogHeader>
                            <AlertDialogTitle>
                              {envData.enabled ? 'Desligar' : 'Ligar'} {key} em {env}?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                              Isso é mata-switch global — afeta TODOS os bizs do environment{' '}
                              <code>{env}</code>.
                            </AlertDialogDescription>
                          </AlertDialogHeader>
                          <AlertDialogFooter>
                            <AlertDialogCancel>Cancelar</AlertDialogCancel>
                            <AlertDialogAction onClick={() => toggleEnv(!envData.enabled)}>
                              Confirmar
                            </AlertDialogAction>
                          </AlertDialogFooter>
                        </AlertDialogContent>
                      </AlertDialog>
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
                                <td>
                                  {r.enabled ? (
                                    <Badge variant="default" className="gap-1">
                                      <CircleDot className="h-3.5 w-3.5" />
                                      On
                                    </Badge>
                                  ) : (
                                    <Badge variant="secondary" className="gap-1">
                                      <Circle className="h-3.5 w-3.5" />
                                      Off
                                    </Badge>
                                  )}
                                </td>
                                <td>
                                  {bizId !== null && (
                                    <AlertDialog>
                                      <AlertDialogTrigger asChild>
                                        <Button size="sm" variant="outline">
                                          Remover
                                        </Button>
                                      </AlertDialogTrigger>
                                      <AlertDialogContent>
                                        <AlertDialogHeader>
                                          <AlertDialogTitle>Remover rule biz-{bizId}?</AlertDialogTitle>
                                          <AlertDialogDescription>
                                            O business <code>{bizId}</code> volta pra{' '}
                                            <code>defaultValue</code>.
                                          </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                          <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                          <AlertDialogAction
                                            onClick={() =>
                                              router.post(
                                                route('admin.feature-flags.biz-rule', { key }),
                                                {
                                                  biz_id: bizId,
                                                  remove: true,
                                                  env,
                                                  clear_cache: true,
                                                },
                                                { preserveScroll: true }
                                              )
                                            }
                                          >
                                            Remover
                                          </AlertDialogAction>
                                        </AlertDialogFooter>
                                      </AlertDialogContent>
                                    </AlertDialog>
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
                        <div className="space-y-1">
                          <Label htmlFor="biz_id" className="text-xs text-muted-foreground">
                            business_id
                          </Label>
                          <Input
                            id="biz_id"
                            type="number"
                            min={1}
                            value={bizRuleForm.data.biz_id}
                            onChange={(e) => bizRuleForm.setData('biz_id', e.target.value)}
                            required
                            className="w-32"
                          />
                        </div>
                        <div className="space-y-1">
                          <Label htmlFor="value" className="text-xs text-muted-foreground">
                            value
                          </Label>
                          <Select
                            value={bizRuleForm.data.value}
                            onValueChange={(v) => bizRuleForm.setData('value', v)}
                          >
                            <SelectTrigger id="value" className="w-40">
                              <SelectValue placeholder="Selecione" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="true">true (ligar)</SelectItem>
                              <SelectItem value="false">false (desligar)</SelectItem>
                            </SelectContent>
                          </Select>
                        </div>
                        <Button type="submit" disabled={bizRuleForm.processing}>
                          Salvar rule
                        </Button>
                      </div>
                      {bizRuleForm.errors.value && (
                        <div className="text-xs text-destructive">{bizRuleForm.errors.value}</div>
                      )}
                      {bizRuleForm.errors.biz_id && (
                        <div className="text-xs text-destructive">{bizRuleForm.errors.biz_id}</div>
                      )}
                    </form>
                  </CardContent>
                </Card>
              </>
            )}
          </>
        )}

        {/* Audit history desta flag */}
        <Deferred data="audits" fallback={<Skeleton className="h-32 w-full" />}>
        <Card>
          <CardHeader>
            <CardTitle>
              Histórico de mudanças{' '}
              <span className="text-sm text-muted-foreground">({auditRows.length})</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            {auditRows.length === 0 ? (
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
                  {auditRows.map((a) => (
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
        </Deferred>
      </div>
    </AppShellV2>
  );
}
