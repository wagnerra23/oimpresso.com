// @memcofre
//   tela: /atendimento/canais/{id}
//   stories: US-WA-068 (Tab "Usuários do canal")
//   adrs: 0135 (omnichannel arquitetura), 0093 (multi-tenant Tier 0)
//   spec: memory/requisitos/Whatsapp/SPEC.md US-WA-068
//   permissao: whatsapp.settings.manage (mesma da Channels CRUD)
//
// Tabs: Config | Usuários | Histórico. Tabs sem componente shadcn dedicado
// (não existe Components/ui/tabs.tsx) — usamos botões accessibility-friendly.

import { Link, Deferred, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
  ArrowLeft, Plug, Smartphone, MessageCircle, CheckCircle2, AlertTriangle,
  Settings, Users, Clock, Loader2, Zap,
} from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription,
} from '@/Components/ui/dialog';
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
  // Re-parear: sempre disponível pra whatsapp_baileys.
  // Wagner request 2026-05-15 (D-15) — quando WhatsApp do device desconecta
  // unilateralmente (cliente desvincula via "Aparelhos conectados"), DB
  // Laravel ainda mostra status=active + channel_health=healthy até
  // whatsapp:channels-reconcile rodar (cron 5min). Botão "Conectar" do
  // Index só aparece se status!=active && health!=healthy, então Wagner
  // não vê opção. Botão "Re-parear" aqui é SEMPRE visível pra Baileys —
  // reusa endpoint atendimento.channels.connect (auto-purge banned).
  const isBaileys = channel.type === 'whatsapp_baileys';
  const [repairOpen, setRepairOpen] = useState(false);
  const [qrImage, setQrImage] = useState<string | null>(null);
  const [pairingCode, setPairingCode] = useState<string | null>(null);
  const [qrState, setQrState] = useState<string | null>(null);
  const [qrError, setQrError] = useState<string | null>(null);
  const [qrLoading, setQrLoading] = useState(false);

  async function startRepair() {
    if (!confirm(
      'Re-parear gera novo QR e invalida sessão atual. Continuar?\n\n' +
      '• Se o canal estava conectado, a sessão Baileys vai cair durante o pareamento.\n' +
      '• Se já estava desconectado (cliente desvinculou em "Aparelhos conectados"), só vai parear de novo.\n' +
      '• Mensagens em andamento podem atrasar até reconectar.'
    )) {
      return;
    }
    setRepairOpen(true);
    setQrImage(null);
    setPairingCode(null);
    setQrState(null);
    setQrError(null);
    setQrLoading(true);
    try {
      const r = await fetch(route('atendimento.channels.connect', channel.id), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content || '',
        },
        credentials: 'same-origin',
      });
      const data = await r.json();
      if (!r.ok || !data.ok) {
        setQrError(data.error || 'Falha desconhecida ao chamar daemon.');
      } else {
        setQrImage(data.qr_png_data_url || null);
        setPairingCode(data.pairing_code || null);
        setQrState(data.state || null);
        if (!data.qr_png_data_url && !data.pairing_code && data.state !== 'connected') {
          setQrError(data.message || 'Daemon respondeu sem QR nem código.');
        }
      }
    } catch (e: any) {
      setQrError('Erro de rede: ' + (e?.message || 'desconhecido'));
    } finally {
      setQrLoading(false);
    }
  }

  // Poll status enquanto modal aberto (a cada 3s)
  useEffect(() => {
    if (!repairOpen) return;
    const interval = setInterval(async () => {
      try {
        const r = await fetch(route('atendimento.channels.status', channel.id), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        const data = await r.json();
        setQrState(data.state);
        if (data.state === 'connected') {
          setTimeout(() => {
            setRepairOpen(false);
            router.reload({ only: ['channel'] });
          }, 1500);
        }
      } catch { /* swallow */ }
    }, 3000);
    return () => clearInterval(interval);
  }, [repairOpen, channel.id]);

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
              <span className="inline-flex items-center gap-1 text-success">
                <CheckCircle2 size={12} aria-hidden /> Sim
              </span>
            ) : 'Não'
          }
        />
        <DetailRow label="Bot habilitado" value={channel.bot_enabled ? 'Sim' : 'Não'} />
      </dl>

      {channel.last_health_message && (
        <div className="text-xs text-warning-fg flex items-start gap-1 border-t pt-2">
          <AlertTriangle size={12} className="mt-0.5 shrink-0" aria-hidden />
          {channel.last_health_message}
        </div>
      )}

      {isBaileys && (
        <div className="border-t pt-3 flex items-center justify-between gap-2">
          <p className="text-xs text-muted-foreground flex-1">
            Sessão WhatsApp caiu ou cliente desvinculou em "Aparelhos conectados"? Use re-parear.
          </p>
          <Button
            variant="outline"
            size="sm"
            onClick={startRepair}
            data-testid="channel-show-repair-btn"
          >
            <Zap size={14} className="mr-1.5" aria-hidden />
            Re-parear
          </Button>
        </div>
      )}

      <div className="border-t pt-3">
        <p className="text-xs text-muted-foreground">
          Edição completa do canal vem em US futura. Pra remover, voltar pra lista.
        </p>
      </div>

      {/* Modal re-parear — reusa visual do Index.tsx (QR PNG data URL + fallback pairing code) */}
      {isBaileys && (
        <Dialog
          open={repairOpen}
          onOpenChange={(o) => {
            if (!o) {
              setRepairOpen(false);
              setQrImage(null);
              setPairingCode(null);
            }
          }}
        >
          <DialogContent className="max-w-md" data-testid="channel-show-repair-modal">
            <DialogHeader>
              <DialogTitle>Re-parear {channel.label}</DialogTitle>
              <DialogDescription>
                No celular: WhatsApp → Configurações → Dispositivos vinculados → <strong>Vincular dispositivo</strong> → aponta a câmera no QR abaixo.
              </DialogDescription>
            </DialogHeader>

            <div className="flex flex-col items-center justify-center py-4 gap-3 min-h-[280px]">
              {qrLoading && (
                <>
                  <Loader2 size={32} className="animate-spin text-muted-foreground" aria-hidden />
                  <p className="text-sm text-muted-foreground">Gerando QR no daemon CT 100…</p>
                </>
              )}
              {!qrLoading && qrError && (
                <div className="text-sm text-destructive-fg text-center px-4">
                  <AlertTriangle size={20} className="inline mr-2" aria-hidden />
                  {qrError}
                </div>
              )}
              {!qrLoading && qrImage && (
                <>
                  <div className="bg-white p-2 rounded-lg shadow-sm">
                    <img src={qrImage} alt="QR Code WhatsApp" width={280} height={280} />
                  </div>
                  <p className="text-xs text-muted-foreground text-center">
                    Válido ~20s (renova automaticamente). State: <strong>{qrState || 'qr_required'}</strong>
                    {qrState === 'connected' && <span className="text-success ml-1">✓ conectado!</span>}
                  </p>
                </>
              )}
              {!qrLoading && !qrImage && pairingCode && (
                <>
                  <p className="text-xs text-muted-foreground">QR indisponível — use código numérico via "Vincular com número de telefone":</p>
                  <div className="bg-muted/50 rounded-lg px-6 py-4 text-center">
                    <div className="text-4xl font-mono font-bold tracking-[0.3em] text-primary">
                      {pairingCode.replace(/(.{4})/, '$1-')}
                    </div>
                  </div>
                </>
              )}
              {!qrLoading && !qrImage && !pairingCode && !qrError && qrState && (
                <p className="text-sm text-muted-foreground">State: <strong>{qrState}</strong></p>
              )}
            </div>

            <DialogFooter>
              <Button
                variant="outline"
                onClick={() => {
                  setRepairOpen(false);
                  setQrImage(null);
                  setPairingCode(null);
                }}
              >
                Fechar
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}
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
                  <Badge variant="outline" className="text-[10px] text-success border-success/20">ativo</Badge>
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
