// @memcofre
//   tela: /atendimento/canais/{id}
//   stories: US-WA-068 (Tab "Usuários do canal")
//   adrs: 0135 (omnichannel arquitetura), 0093 (multi-tenant Tier 0)
//   spec: memory/requisitos/Whatsapp/SPEC.md US-WA-068
//   permissao: whatsapp.settings.manage (mesma da Channels CRUD)
//
// Tabs: Config | Usuários | Histórico. Tabs sem componente shadcn dedicado
// (não existe Components/ui/tabs.tsx) — usamos botões accessibility-friendly.

import { Link, Deferred } from '@inertiajs/react';
import { useState } from 'react';
import {
  ArrowLeft, Plug, Smartphone, MessageCircle, CheckCircle2, AlertTriangle,
  Settings, Users, Clock, Loader2,
} from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import ChannelUsersTab from './_components/ChannelUsersTab';

interface ChannelUi {
  id: number;
  channel_uuid: string;
  label: string;
  type: string;
  status: 'active' | 'inactive' | 'setup' | 'disconnected' | 'banned';
  display_identifier: string | null;
  channel_health: 'healthy' | 'degraded' | 'disconnected' | 'banned' | 'never_checked';
  last_health_check_at: string | null;
  last_health_message: string | null;
  has_zapi_credentials: boolean;
  has_meta_credentials: boolean;
  has_baileys_credentials: boolean;
  baileys_phone_e164: string | null;
  zapi_instance_id: string | null;
  meta_phone_number_id: string | null;
  lgpd_acknowledged_at: string | null;
  handles_repair_status: boolean;
  handles_billing: boolean;
  handles_jana_bot: boolean;
  handles_outbound_default: boolean;
  bot_enabled: boolean;
  created_at: string | null;
}

interface AccessRow {
  id: number;
  user_id: number;
  name: string;
  email: string | null;
  granted_at: string | null;
  granted_by_user_id: number;
  granted_by_name: string | null;
}

interface AvailableUser {
  id: number;
  name: string;
  email: string | null;
  username: string | null;
}

interface AuditRow {
  id: number;
  user_id: number;
  user_name: string | null;
  granted_at: string | null;
  granted_by_name: string | null;
  revoked_at: string | null;
  revoked_by_name: string | null;
  is_active: boolean;
}

interface Props {
  channel: ChannelUi;
  // D-14 perf — listas pesadas deferred (users + availableUsers + audit each ~2 queries)
  users?: AccessRow[];
  availableUsers?: AvailableUser[];
  audit?: AuditRow[];
}

type TabKey = 'config' | 'users' | 'history';

export default function ChannelShow({ channel, users, availableUsers, audit }: Props) {
  const [activeTab, setActiveTab] = useState<TabKey>('config');

  const TypeIcon = channel.type.startsWith('whatsapp_') ? MessageCircle : Plug;

  return (
    <div className="p-4 space-y-4">
      <PageHeader
        icon={TypeIcon}
        title={channel.label}
        description={
          <span className="flex items-center gap-2 text-xs">
            <span className="text-muted-foreground">{channel.type}</span>
            <Badge variant="outline" className="text-[10px]">{channel.status}</Badge>
            {channel.display_identifier && (
              <span className="text-muted-foreground inline-flex items-center gap-1">
                <Smartphone size={11} aria-hidden />
                {channel.display_identifier}
              </span>
            )}
          </span>
        }
        action={
          <Button asChild variant="outline" size="sm">
            <Link href={route('atendimento.channels.index')} data-testid="channel-show-back">
              <ArrowLeft size={14} className="mr-1.5" aria-hidden />
              Voltar
            </Link>
          </Button>
        }
      />

      {/* Tabs nav — D-14 perf: contador `users` deferred, mostra '…' até resolver */}
      <div className="flex items-center gap-1 border-b" role="tablist" aria-label="Seções do canal">
        <TabButton
          icon={Settings}
          label="Config"
          active={activeTab === 'config'}
          onClick={() => setActiveTab('config')}
          testid="channel-tab-config"
        />
        <TabButton
          icon={Users}
          label={`Usuários (${users?.length ?? '…'})`}
          active={activeTab === 'users'}
          onClick={() => setActiveTab('users')}
          testid="channel-tab-users"
        />
        <TabButton
          icon={Clock}
          label="Histórico"
          active={activeTab === 'history'}
          onClick={() => setActiveTab('history')}
          testid="channel-tab-history"
        />
      </div>

      {activeTab === 'config' && <ConfigTab channel={channel} />}
      {activeTab === 'users' && (
        <Deferred
          data={['users', 'availableUsers']}
          fallback={(
            <Card className="p-4">
              <div className="space-y-2">
                {Array.from({ length: 3 }).map((_, i) => (
                  <div key={i} className="flex gap-3 items-center py-2">
                    <div className="h-8 w-8 rounded-full bg-muted/40 animate-pulse" />
                    <div className="flex-1 space-y-1.5">
                      <div className="h-3 w-32 bg-muted/40 rounded animate-pulse" />
                      <div className="h-2 w-48 bg-muted/30 rounded animate-pulse" />
                    </div>
                  </div>
                ))}
                <div className="flex items-center justify-center text-muted-foreground text-xs pt-2">
                  <Loader2 size={14} className="animate-spin mr-2" aria-hidden /> Carregando usuários…
                </div>
              </div>
            </Card>
          )}
        >
          <ChannelUsersTab
            channelId={channel.id}
            users={users ?? []}
            availableUsers={availableUsers ?? []}
          />
        </Deferred>
      )}
      {activeTab === 'history' && (
        <Deferred
          data="audit"
          fallback={(
            <Card className="p-4 space-y-2">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="flex gap-3 items-center py-2 border-b border-border/30">
                  <div className="h-3 w-24 bg-muted/40 rounded animate-pulse" />
                  <div className="h-3 w-20 bg-muted/30 rounded animate-pulse" />
                  <div className="h-3 w-24 bg-muted/30 rounded animate-pulse" />
                  <div className="h-3 w-16 bg-muted/30 rounded animate-pulse ml-auto" />
                </div>
              ))}
            </Card>
          )}
        >
          <HistoryTab audit={audit ?? []} />
        </Deferred>
      )}
    </div>
  );
}

function TabButton({
  icon: Icon, label, active, onClick, testid,
}: {
  icon: typeof Settings; label: string; active: boolean; onClick: () => void; testid: string;
}) {
  return (
    <button
      type="button"
      role="tab"
      aria-selected={active}
      onClick={onClick}
      data-testid={testid}
      className={
        'inline-flex items-center gap-1.5 px-4 py-2 text-sm border-b-2 transition-colors ' +
        (active
          ? 'border-primary text-primary font-medium'
          : 'border-transparent text-muted-foreground hover:text-foreground')
      }
    >
      <Icon size={14} aria-hidden />
      {label}
    </button>
  );
}

function ConfigTab({ channel }: { channel: ChannelUi }) {
  return (
    <Card className="p-4 space-y-3">
      <h3 className="font-semibold text-sm">Detalhes do canal</h3>
      <dl className="grid gap-2 sm:grid-cols-2 text-sm">
        <DetailRow label="Apelido" value={channel.label} />
        <DetailRow label="Tipo" value={channel.type} />
        <DetailRow label="Status" value={channel.status} />
        <DetailRow label="Health" value={channel.channel_health} />
        <DetailRow label="Identificador" value={channel.display_identifier || '—'} />
        <DetailRow label="UUID" value={channel.channel_uuid} />
        <DetailRow
          label="LGPD aceito"
          value={
            channel.lgpd_acknowledged_at ? (
              <span className="inline-flex items-center gap-1 text-emerald-600">
                <CheckCircle2 size={12} aria-hidden /> Sim
              </span>
            ) : 'Não'
          }
        />
        <DetailRow label="Bot habilitado" value={channel.bot_enabled ? 'Sim' : 'Não'} />
      </dl>

      {channel.last_health_message && (
        <div className="text-xs text-amber-700 dark:text-amber-400 flex items-start gap-1 border-t pt-2">
          <AlertTriangle size={12} className="mt-0.5 shrink-0" aria-hidden />
          {channel.last_health_message}
        </div>
      )}

      <div className="border-t pt-3">
        <p className="text-xs text-muted-foreground">
          Edição completa do canal vem em US futura. Pra remover, voltar pra lista.
        </p>
      </div>
    </Card>
  );
}

function DetailRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="text-sm font-medium truncate">{value}</dd>
    </div>
  );
}

function HistoryTab({ audit }: { audit: AuditRow[] }) {
  if (audit.length === 0) {
    return (
      <Card className="p-6 text-center text-sm text-muted-foreground">
        Sem histórico de grants neste canal ainda.
      </Card>
    );
  }

  return (
    <Card className="p-0 overflow-hidden">
      <table className="w-full text-sm" data-testid="channel-history-table">
        <thead className="bg-muted/30 border-b">
          <tr className="text-xs text-muted-foreground">
            <th className="text-left px-3 py-2">Usuário</th>
            <th className="text-left px-3 py-2">Concedido em</th>
            <th className="text-left px-3 py-2">Por</th>
            <th className="text-left px-3 py-2">Revogado em</th>
            <th className="text-left px-3 py-2">Por</th>
            <th className="text-left px-3 py-2">Status</th>
          </tr>
        </thead>
        <tbody>
          {audit.map((row) => (
            <tr key={row.id} className="border-b last:border-0">
              <td className="px-3 py-2">{row.user_name || `user#${row.user_id}`}</td>
              <td className="px-3 py-2 text-xs">{fmtDate(row.granted_at)}</td>
              <td className="px-3 py-2 text-xs">{row.granted_by_name || '—'}</td>
              <td className="px-3 py-2 text-xs">{fmtDate(row.revoked_at)}</td>
              <td className="px-3 py-2 text-xs">{row.revoked_by_name || '—'}</td>
              <td className="px-3 py-2">
                {row.is_active ? (
                  <Badge variant="outline" className="text-[10px] text-emerald-600 border-emerald-200">ativo</Badge>
                ) : (
                  <Badge variant="outline" className="text-[10px] text-muted-foreground">revogado</Badge>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </Card>
  );
}

function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString('pt-BR');
  } catch {
    return iso;
  }
}

ChannelShow.layout = (page: React.ReactElement) => <AppShellV2>{page}</AppShellV2>;
