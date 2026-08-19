// @memcofre
//   tela: /superadmin/usuarios/{id}/360
//   module: Superadmin
//   stories: USUARIO-360 (Wagner — não pular galho em galho pra rastrear acesso)
//   permissao: superadmin
//   charter: ./Show.charter.md
//
// Vista única consolidando tudo sobre um user:
//   1) Header com Trancar/Destrancar/Histórico
//   2) Roles Spatie
//   3) Permissions efetivas (via PermissionRegistry — agrupado por módulo + risk)
//   4) Scopes ADS / MCP (mcp_user_scopes)
//   5) Tokens MCP (mascarados)
//   6) Quotas Copiloto
//   7) Sessions ativas
//   8) Auditoria recente (mcp_audit_log)
//   9) Histórico de lockouts
//
// DS: risco mapeado a tokens semânticos (variant Badge + ícone lucide), sem cor
// crua nem emoji; confirmação de destrancar via AlertDialog (não window.confirm).

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Segmented } from '@/Components/ui/segmented';
import PageHeader from '@/Components/shared/PageHeader';
import { toast } from 'sonner';
import {
  Shield, ShieldCheck, ShieldAlert, ShieldX, ShieldQuestion,
  Lock, LockOpen, History, Check, X, Ban, CircleSlash,
  Users, KeySquare, Network, KeyRound, Gauge, MonitorSmartphone, ScrollText,
  type LucideIcon,
} from 'lucide-react';

type Risk = 'low' | 'medium' | 'high' | 'critical';

interface UserData {
  id: number;
  username: string;
  email: string;
  nome: string;
  business_id: number | null;
  business_name: string | null;
  status: string;
  user_type: string;
  is_locked: boolean;
  created_at: string | null;
}

interface PermissionEntry {
  key: string;
  label: string;
  risk: Risk;
  requires: string[];
  granted: boolean;
}

interface ModuleEntry {
  module: string;
  group: string;
  icon: string;
  total: number;
  granted: number;
  has_critical: boolean;
  has_high: boolean;
  permissions: PermissionEntry[];
}

interface ScopeAds {
  slug: string;
  descricao: string | null;
  business_id: number | null;
  granted_at: string | null;
}

interface TokenMcp {
  id: number;
  name: string;
  masked: string;
  last_used_at: string | null;
  last_used_ip: string | null;
  expires_at: string | null;
  revoked_at: string | null;
  created_at: string;
  is_active: boolean;
}

interface QuotaCopiloto {
  period: string;
  kind: string;
  limit: number;
  current_usage: number;
  pct: number;
  reset_at: string;
  block_on_exceed: boolean;
}

interface SessionAtiva {
  id: string;
  ip_address: string | null;
  user_agent: string;
  last_activity: string | null;
}

interface AuditoriaEntry {
  ts: string;
  endpoint: string;
  tool_or_resource: string | null;
  status: string;
  error_code: string | null;
  duration_ms: number | null;
  ip: string | null;
}

interface Lockout {
  id: number;
  locked_at: string;
  locked_by: number;
  reason: string;
  unlocked_at: string | null;
  unlocked_by: number | null;
  unlock_note: string | null;
  is_active: boolean;
}

interface Props {
  user: UserData;
  roles: string[];
  permissions: ModuleEntry[];
  scopes_ads: ScopeAds[];
  tokens_mcp: TokenMcp[];
  quotas_copiloto: QuotaCopiloto[];
  sessions_ativas: SessionAtiva[];
  auditoria: AuditoriaEntry[];
  lockouts: Lockout[];
  tabelas_ausentes: string[];
}

// Risco -> Badge variant DS + ícone lucide. Sem cor crua, sem emoji.
// (substitui RISK_STYLES bg-gray/yellow/orange/red literais)
const RISK_META: Record<Risk, { variant: 'outline' | 'secondary' | 'default' | 'destructive'; icon: LucideIcon; label: string }> = {
  low:      { variant: 'outline',     icon: ShieldCheck, label: 'baixo' },
  medium:   { variant: 'secondary',   icon: Shield,      label: 'médio' },
  high:     { variant: 'default',     icon: ShieldAlert, label: 'alto' },
  critical: { variant: 'destructive', icon: ShieldX,     label: 'crítico' },
};

const RISK_FALLBACK = { variant: 'outline' as const, icon: ShieldQuestion, label: 'desconhecido' };

function riskMeta(risk: Risk) {
  return RISK_META[risk] ?? RISK_FALLBACK;
}

function RiskBadge({ risk }: { risk: Risk }) {
  const m = riskMeta(risk);
  const Icon = m.icon;
  return (
    <Badge variant={m.variant} className="gap-1">
      <Icon className="h-3 w-3" aria-hidden />
      {m.label}
    </Badge>
  );
}

function fmt(iso: string | null): string {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString('pt-BR');
  } catch { return iso; }
}

function NotInstalled({ msg }: { msg: string }) {
  return (
    <p className="text-sm italic text-muted-foreground">{msg}</p>
  );
}

function Usuario360Show(props: Props) {
  const { user, roles, permissions, scopes_ads, tokens_mcp, quotas_copiloto,
          sessions_ativas, auditoria, lockouts, tabelas_ausentes } = props;

  const [lockOpen, setLockOpen] = useState(false);
  const [historyOpen, setHistoryOpen] = useState(false);
  const [unlockOpen, setUnlockOpen] = useState(false);
  // Âncora dos 9 blocos 360 via Segmented DS (não há componente Tabs no DS).
  const [tab, setTab] = useState<'acesso' | 'ads' | 'atividade' | 'seguranca'>('acesso');

  const lockForm = useForm<{ reason: string }>({ reason: '' });
  const unlockForm = useForm<{ note: string }>({ note: '' });

  function submitLock(e: FormEvent) {
    e.preventDefault();
    if (lockForm.data.reason.trim().length < 5) {
      toast.error('Motivo precisa ter ao menos 5 caracteres.');
      return;
    }
    lockForm.post(`/superadmin/usuarios/${user.id}/lock`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Usuário trancado.');
        setLockOpen(false);
      },
      onError: () => toast.error('Erro ao trancar.'),
      onFinish: () => lockForm.reset(),
    });
  }

  // Confirmação de destrancar via AlertDialog DS (substitui window.confirm nativo).
  function submitUnlock() {
    unlockForm.post(`/superadmin/usuarios/${user.id}/unlock`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Usuário destrancado.');
        setUnlockOpen(false);
      },
      onError: () => toast.error('Erro ao destrancar.'),
    });
  }

  const StatusIcon = user.is_locked ? Lock : user.status === 'active' ? Check : CircleSlash;

  return (
    <>
      <PageHeader
        title={user.nome}
        description={`${user.email} · biz=${user.business_id ?? '—'} · #${user.id}`}
        action={
          <div className="flex items-center gap-2">
            <Badge variant={user.status === 'active' ? 'default' : 'destructive'} className="gap-1">
              <StatusIcon className="h-3 w-3" aria-hidden />
              {user.is_locked ? 'TRANCADO' : user.status === 'active' ? 'ATIVO' : 'INATIVO'}
            </Badge>
            {user.is_locked ? (
              <Button variant="outline" onClick={() => setUnlockOpen(true)}>
                <LockOpen className="h-4 w-4" aria-hidden /> Destrancar
              </Button>
            ) : (
              <Button variant="destructive" onClick={() => setLockOpen(true)}>
                <Lock className="h-4 w-4" aria-hidden /> Trancar
              </Button>
            )}
            <Button variant="outline" onClick={() => setHistoryOpen(true)}>
              <History className="h-4 w-4" aria-hidden /> Histórico
            </Button>
          </div>
        }
      />

      {tabelas_ausentes.length > 0 && (
        <Card className="mb-4 border-destructive/30 bg-destructive/5">
          <CardContent className="flex items-start gap-2 pt-6 text-sm text-foreground">
            <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0 text-destructive" aria-hidden />
            <span>
              <strong>Aviso:</strong> as seguintes tabelas não existem nesta instalação —
              cards relacionados aparecem vazios:{' '}
              <code className="text-xs">{tabelas_ausentes.join(', ')}</code>
            </span>
          </CardContent>
        </Card>
      )}

      {/* Âncoras pros 9 blocos 360 — Segmented DS (atalho sem scroll longo) */}
      <div className="space-y-4">
        <Segmented
          value={tab}
          onValueChange={(v) => setTab(v as typeof tab)}
          className="flex-wrap"
          options={[
            { value: 'acesso', label: <><Users className="h-3.5 w-3.5" aria-hidden /> Acesso</> },
            { value: 'ads', label: <><Network className="h-3.5 w-3.5" aria-hidden /> ADS / MCP</> },
            { value: 'atividade', label: <><MonitorSmartphone className="h-3.5 w-3.5" aria-hidden /> Atividade</> },
            { value: 'seguranca', label: <><Lock className="h-3.5 w-3.5" aria-hidden /> Segurança ({lockouts.length})</> },
          ]}
        />

        {/* ── ACESSO: roles + quotas + permissions ── */}
        {tab === 'acesso' && (
        <div className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            {/* Roles Spatie */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <Users className="h-4 w-4" aria-hidden /> Roles Spatie
                </CardTitle>
                <CardDescription>{roles.length} atribuída(s)</CardDescription>
              </CardHeader>
              <CardContent>
                {roles.length === 0 ? (
                  <NotInstalled msg="Nenhuma role atribuída." />
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {roles.map((r) => (
                      <Badge key={r} variant="secondary">{r}</Badge>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Quotas Copiloto */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <Gauge className="h-4 w-4" aria-hidden /> Quotas Copiloto / MCP
                </CardTitle>
                <CardDescription>Consumo do mês corrente</CardDescription>
              </CardHeader>
              <CardContent>
                {quotas_copiloto.length === 0 ? (
                  <NotInstalled msg="Sem quotas configuradas (ou tabela mcp_quotas ausente)." />
                ) : (
                  <ul className="space-y-2 text-sm">
                    {quotas_copiloto.map((q, i) => (
                      <li key={i} className="flex items-center justify-between border-b pb-2 last:border-b-0">
                        <span><strong>{q.period}</strong> · {q.kind}</span>
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                          {q.current_usage} / {q.limit} ({q.pct}%)
                          {q.block_on_exceed && q.pct >= 100 && (
                            <Ban className="h-3.5 w-3.5 text-destructive" aria-label="bloqueado por exceder" />
                          )}
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>
          </div>

          {/* Permissions efetivas — bloco grande */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <KeySquare className="h-4 w-4" aria-hidden />
                Permissions efetivas (via Permission Registry)
              </CardTitle>
              <CardDescription className="flex flex-wrap items-center gap-2">
                Agrupadas por módulo. Risco por permissão:
                <RiskBadge risk="low" />
                <RiskBadge risk="medium" />
                <RiskBadge risk="high" />
                <RiskBadge risk="critical" />
              </CardDescription>
            </CardHeader>
            <CardContent>
              {permissions.length === 0 ? (
                <NotInstalled msg="Nenhum módulo declarou permissions.php (ou user sem nenhuma permission)." />
              ) : (
                <div className="space-y-4">
                  {permissions.map((mod) => (
                    <div key={mod.module} className="rounded border p-3">
                      <div className="mb-2 flex items-center justify-between">
                        <div>
                          <strong className="text-sm">{mod.group}</strong>
                          <span className="ml-2 text-xs text-muted-foreground">
                            {mod.granted}/{mod.total} concedida(s)
                          </span>
                        </div>
                        <div className="flex gap-1">
                          {mod.has_critical && <RiskBadge risk="critical" />}
                          {mod.has_high && <RiskBadge risk="high" />}
                        </div>
                      </div>
                      <ul className="grid gap-1 text-xs sm:grid-cols-2">
                        {mod.permissions.map((p) => {
                          const GrantIcon = p.granted ? Check : X;
                          return (
                            <li key={p.key} className="flex items-center gap-2">
                              <GrantIcon
                                className={p.granted ? 'h-3.5 w-3.5 text-primary' : 'h-3.5 w-3.5 text-muted-foreground'}
                                aria-label={p.granted ? 'concedida' : 'não concedida'}
                              />
                              <code className="font-mono">{p.key}</code>
                              <RiskBadge risk={p.risk} />
                              <span className="text-muted-foreground">{p.label}</span>
                            </li>
                          );
                        })}
                      </ul>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
        )}

        {/* ── ADS / MCP: scopes + tokens ── */}
        {tab === 'ads' && (
        <div className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            {/* Scopes ADS / MCP */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <Network className="h-4 w-4" aria-hidden /> Scopes ADS / MCP
                </CardTitle>
                <CardDescription>Per-user × módulo (mcp_user_scopes)</CardDescription>
              </CardHeader>
              <CardContent>
                {scopes_ads.length === 0 ? (
                  <NotInstalled msg="Sem scopes (ou tabela ausente)." />
                ) : (
                  <ul className="space-y-1 text-xs">
                    {scopes_ads.map((s, i) => (
                      <li key={i} className="flex items-center justify-between border-b py-1">
                        <code className="font-mono">{s.slug}</code>
                        <span className="text-muted-foreground">
                          biz={s.business_id ?? '*'} · {fmt(s.granted_at)}
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>

            {/* Tokens MCP */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <KeyRound className="h-4 w-4" aria-hidden /> Tokens MCP
                </CardTitle>
                <CardDescription>{tokens_mcp.length} token(s)</CardDescription>
              </CardHeader>
              <CardContent>
                {tokens_mcp.length === 0 ? (
                  <NotInstalled msg="Nenhum token MCP (ou tabela ausente)." />
                ) : (
                  <ul className="space-y-2 text-xs">
                    {tokens_mcp.map((t) => (
                      <li key={t.id} className="border-b pb-2 last:border-b-0">
                        <div className="flex items-center justify-between">
                          <strong>{t.name}</strong>
                          <Badge variant={t.is_active ? 'default' : 'secondary'}>
                            {t.is_active ? 'ativo' : t.revoked_at ? 'revogado' : 'expirado'}
                          </Badge>
                        </div>
                        <div className="text-muted-foreground">
                          <code>{t.masked}</code> · último uso {fmt(t.last_used_at)} ({t.last_used_ip ?? '—'})
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
        )}

        {/* ── ATIVIDADE: sessions + auditoria ── */}
        {tab === 'atividade' && (
        <div className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            {/* Sessions ativas */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <MonitorSmartphone className="h-4 w-4" aria-hidden /> Sessions ativas
                </CardTitle>
              </CardHeader>
              <CardContent>
                {sessions_ativas.length === 0 ? (
                  <NotInstalled msg="Sem sessions DB (driver pode não ser 'database')." />
                ) : (
                  <ul className="space-y-1 text-xs">
                    {sessions_ativas.map((s) => (
                      <li key={s.id} className="border-b py-1">
                        <div className="flex items-center justify-between">
                          <code>{s.id}</code>
                          <span className="text-muted-foreground">{s.ip_address}</span>
                        </div>
                        <div className="text-muted-foreground">
                          {s.user_agent} · last={s.last_activity}
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>

            {/* Auditoria recente */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <ScrollText className="h-4 w-4" aria-hidden /> Auditoria recente (MCP)
                </CardTitle>
                <CardDescription>Últimas {auditoria.length} entradas em mcp_audit_log</CardDescription>
              </CardHeader>
              <CardContent>
                {auditoria.length === 0 ? (
                  <NotInstalled msg="Sem entradas (ou tabela mcp_audit_log ausente)." />
                ) : (
                  <ul className="max-h-64 space-y-1 overflow-y-auto text-xs">
                    {auditoria.map((a, i) => (
                      <li key={i} className="border-b py-1">
                        <div className="flex items-center justify-between">
                          <span>
                            <code>{a.endpoint}</code>{' '}
                            <strong>{a.tool_or_resource ?? '—'}</strong>
                          </span>
                          <Badge variant={a.status === 'ok' ? 'default' : 'destructive'}>
                            {a.status}
                          </Badge>
                        </div>
                        <div className="text-muted-foreground">
                          {fmt(a.ts)} · {a.duration_ms ?? '—'}ms · {a.ip ?? '—'}
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
        )}

        {/* ── SEGURANÇA: histórico de lockouts ── */}
        {tab === 'seguranca' && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Lock className="h-4 w-4" aria-hidden /> Histórico de lockouts
              </CardTitle>
            </CardHeader>
            <CardContent>
              {lockouts.length === 0 ? (
                <NotInstalled msg="Nunca foi trancado." />
              ) : (
                <ul className="space-y-2 text-sm">
                  {lockouts.map((l) => (
                    <li key={l.id} className="border-b pb-2 last:border-b-0">
                      <div className="flex items-center justify-between">
                        <strong>#{l.id}</strong>
                        <Badge variant={l.is_active ? 'destructive' : 'secondary'} className="gap-1">
                          {l.is_active
                            ? <><Lock className="h-3 w-3" aria-hidden /> ATIVO</>
                            : <><LockOpen className="h-3 w-3" aria-hidden /> destrancado</>}
                        </Badge>
                      </div>
                      <div className="text-xs text-muted-foreground">
                        Trancado em {fmt(l.locked_at)} por user #{l.locked_by}
                        {l.unlocked_at && <> · destrancado em {fmt(l.unlocked_at)} por user #{l.unlocked_by}</>}
                      </div>
                      <div className="mt-1 italic">&quot;{l.reason}&quot;</div>
                      {l.unlock_note && <div className="mt-1 text-xs text-muted-foreground">Nota: {l.unlock_note}</div>}
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        )}
      </div>

      {/* Dialog Trancar */}
      <Dialog open={lockOpen} onOpenChange={setLockOpen}>
        <DialogContent>
          <form onSubmit={submitLock}>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <Lock className="h-5 w-5" aria-hidden /> Trancar usuário
              </DialogTitle>
              <DialogDescription>
                Vai revogar tokens MCP, matar sessions e bloquear login. Snapshot
                completo (roles + permissions + scopes + tokens) é salvo em{' '}
                <code>user_lockouts.snapshot</code> pra rastreabilidade.
              </DialogDescription>
            </DialogHeader>
            <div className="my-4">
              <label className="text-sm font-medium" htmlFor="lock-reason">Motivo (obrigatório)</label>
              <Textarea
                id="lock-reason"
                value={lockForm.data.reason}
                onChange={(e) => lockForm.setData('reason', e.target.value)}
                placeholder="Ex.: Suspeita de desvio de NFSe — investigação em curso"
                rows={4}
                required
                minLength={5}
                maxLength={500}
              />
              {lockForm.errors.reason && (
                <p className="mt-1 text-xs text-destructive">{lockForm.errors.reason}</p>
              )}
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setLockOpen(false)}>
                Cancelar
              </Button>
              <Button type="submit" variant="destructive" disabled={lockForm.processing}>
                Sim, trancar
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* AlertDialog Destrancar — substitui window.confirm nativo, com motivo/nota */}
      <AlertDialog open={unlockOpen} onOpenChange={(open) => { if (!open && !unlockForm.processing) setUnlockOpen(false); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <LockOpen className="h-5 w-5" aria-hidden /> Destrancar usuário?
            </AlertDialogTitle>
            <AlertDialogDescription>
              Reativa o login de <strong>{user.nome}</strong>. Os tokens MCP{' '}
              <strong>NÃO</strong> serão restaurados — gere novos manualmente.
              A ação fica registrada na trilha de auditoria.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="my-1">
            <label className="text-sm font-medium" htmlFor="unlock-note">Nota (opcional)</label>
            <Textarea
              id="unlock-note"
              value={unlockForm.data.note}
              onChange={(e) => unlockForm.setData('note', e.target.value)}
              placeholder="Ex.: Investigação concluída, sem indício de desvio."
              rows={3}
              maxLength={500}
            />
            {unlockForm.errors.note && (
              <p className="mt-1 text-xs text-destructive">{unlockForm.errors.note}</p>
            )}
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={unlockForm.processing}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={(e) => { e.preventDefault(); submitUnlock(); }}
              disabled={unlockForm.processing}
            >
              {unlockForm.processing ? 'Destrancando…' : 'Sim, destrancar'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Dialog Histórico (mesmo conteúdo do bloco, em modal) */}
      <Dialog open={historyOpen} onOpenChange={setHistoryOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <History className="h-5 w-5" aria-hidden /> Histórico de mudanças
            </DialogTitle>
            <DialogDescription>Lockouts e auditoria MCP recente</DialogDescription>
          </DialogHeader>
          <div className="max-h-96 space-y-3 overflow-y-auto text-sm">
            {lockouts.length === 0 && auditoria.length === 0 ? (
              <p className="italic text-muted-foreground">Sem histórico.</p>
            ) : (
              <>
                <div>
                  <strong className="text-xs uppercase">Lockouts</strong>
                  <ul className="mt-1 space-y-1">
                    {lockouts.map((l) => (
                      <li key={l.id} className="flex items-center gap-1.5 text-xs">
                        #{l.id} · {fmt(l.locked_at)} ·{' '}
                        {l.is_active
                          ? <span className="inline-flex items-center gap-1"><Lock className="h-3 w-3" aria-hidden /> ativo</span>
                          : <span className="inline-flex items-center gap-1"><LockOpen className="h-3 w-3" aria-hidden /> destrancado</span>}
                        {' '}· &quot;{l.reason}&quot;
                      </li>
                    ))}
                  </ul>
                </div>
                <div>
                  <strong className="text-xs uppercase">Auditoria MCP</strong>
                  <ul className="mt-1 space-y-1">
                    {auditoria.slice(0, 15).map((a, i) => (
                      <li key={i} className="text-xs">
                        {fmt(a.ts)} · {a.tool_or_resource} · {a.status}
                      </li>
                    ))}
                  </ul>
                </div>
              </>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}

Usuario360Show.layout = (page: ReactNode) => (
  <AppShellV2 title="Usuário 360°">{page}</AppShellV2>
);

export default Usuario360Show;
