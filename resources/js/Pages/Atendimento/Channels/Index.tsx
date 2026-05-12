// @memcofre
//   tela: /atendimento/canais
//   stories: US-WA-057 (Omnichannel Fase 0 UI)
//   adrs: 0135 (omnichannel arquitetura)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   status: implementada Fase 0 — CRUD basico (list/add/delete), edit em PR seguinte
//   permissao: whatsapp.settings.manage (reusada)
//
// Coexiste com /whatsapp/settings legacy durante PR B. Refactor drivers/jobs
// pra consumir Channel direto vai num PR seguinte.

import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
// QR vem como data URL PNG do daemon (string Baileys excede limite QR v40 23KB).
import {
  Plus, Trash2, AlertTriangle, CheckCircle2, Circle, Loader2,
  MessageCircle, Plug, Smartphone, Zap,
} from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription,
} from '@/Components/ui/dialog';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';

interface Channel {
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
}

interface TypeOption {
  value: string;
  label: string;
  description: string;
  enabled: boolean;
}

interface Props {
  channels: Channel[];
  businessId: number;
  availableTypes: TypeOption[];
  forbiddenDrivers: string[];
}

export default function ChannelsIndex({ channels, availableTypes }: Props) {
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState<Channel | null>(null);
  const [connecting, setConnecting] = useState<Channel | null>(null);
  const [qrImage, setQrImage] = useState<string | null>(null);
  const [pairingCode, setPairingCode] = useState<string | null>(null);
  const [qrState, setQrState] = useState<string | null>(null);
  const [qrError, setQrError] = useState<string | null>(null);
  const [qrLoading, setQrLoading] = useState(false);

  // Form state
  const [type, setType] = useState<string>('whatsapp_baileys');
  const [label, setLabel] = useState('');
  const [config, setConfig] = useState<Record<string, string>>({});
  const [lgpdOk, setLgpdOk] = useState(false);

  function resetForm() {
    setType('whatsapp_baileys');
    setLabel('');
    setConfig({});
    setLgpdOk(false);
  }

  function submitCreate(e: React.FormEvent) {
    e.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    router.post(
      route('atendimento.channels.store'),
      {
        type,
        label,
        config,
        lgpd_acknowledged: lgpdOk,
        handles_jana_bot: true,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setShowAddDialog(false);
          resetForm();
        },
        onFinish: () => setSubmitting(false),
      },
    );
  }

  function doDelete(channel: Channel) {
    router.delete(route('atendimento.channels.destroy', channel.id), {
      preserveScroll: true,
      onSuccess: () => setConfirmDelete(null),
    });
  }

  async function startConnect(channel: Channel) {
    setConnecting(channel);
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
        if (!data.qr_png_data_url && !data.pairing_code) {
          setQrError(data.message || 'Daemon respondeu sem QR nem código.');
        }
      }
    } catch (e: any) {
      setQrError('Erro de rede: ' + (e?.message || 'desconhecido'));
    } finally {
      setQrLoading(false);
    }
  }

  // Poll status enquanto modal connect aberto
  useEffect(() => {
    if (!connecting) return;
    const interval = setInterval(async () => {
      try {
        const r = await fetch(route('atendimento.channels.status', connecting.id), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        const data = await r.json();
        setQrState(data.state);
        if (data.state === 'connected') {
          setTimeout(() => {
            setConnecting(null);
            router.reload({ only: ['channels'] });
          }, 1500);
        }
      } catch { /* swallow */ }
    }, 3000);
    return () => clearInterval(interval);
  }, [connecting?.id]);

  return (
    <div className="p-4 space-y-4">
      <PageHeader
        icon={Plug}
        title="Canais de Atendimento"
        description="Cadastre números WhatsApp, e (em breve) Instagram, Email e Mercado Livre. Inbox unificada em /atendimento/inbox."
        action={
          <Button onClick={() => setShowAddDialog(true)} size="sm">
            <Plus size={14} className="mr-1.5" aria-hidden />
            Adicionar canal
          </Button>
        }
      />

      {channels.length === 0 ? (
        <Card className="p-8">
          <EmptyState
            icon="message-circle"
            title="Nenhum canal cadastrado"
            description="Comece adicionando um número WhatsApp (Meta Cloud, Z-API ou Baileys). Você pode ter N canais por business."
          />
        </Card>
      ) : (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {channels.map((ch) => (
            <ChannelCard
              key={ch.id}
              channel={ch}
              onDelete={() => setConfirmDelete(ch)}
              onConnect={() => startConnect(ch)}
            />
          ))}
        </div>
      )}

      {/* Modal connect Baileys via QR (preferred) ou pairing code (fallback) */}
      <Dialog open={!!connecting} onOpenChange={(o) => { if (!o) { setConnecting(null); setQrImage(null); setPairingCode(null); } }}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Conectar {connecting?.label}</DialogTitle>
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
              <div className="text-sm text-red-700 dark:text-red-400 text-center px-4">
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
                  {qrState === 'connected' && <span className="text-emerald-600 ml-1">✓ conectado!</span>}
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
            <Button variant="outline" onClick={() => { setConnecting(null); setQrImage(null); setPairingCode(null); }}>
              Fechar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Add channel dialog */}
      <Dialog open={showAddDialog} onOpenChange={(o) => { setShowAddDialog(o); if (!o) resetForm(); }}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Adicionar canal</DialogTitle>
            <DialogDescription>
              Escolha o tipo + credenciais. Channel.config_json é cifrado em DB (encrypted:array Laravel).
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={submitCreate} className="space-y-3">
            <div className="space-y-1">
              <Label htmlFor="type">Tipo</Label>
              <Select value={type} onValueChange={setType}>
                <SelectTrigger id="type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {availableTypes.map((t) => (
                    <SelectItem key={t.value} value={t.value} disabled={!t.enabled}>
                      <span className="flex items-center gap-2">
                        {t.label}
                        {!t.enabled && <Badge variant="outline" className="text-[10px]">em breve</Badge>}
                      </span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                {availableTypes.find((t) => t.value === type)?.description}
              </p>
            </div>

            <div className="space-y-1">
              <Label htmlFor="label">Apelido</Label>
              <Input
                id="label"
                value={label}
                onChange={(e) => setLabel(e.target.value)}
                placeholder="Comercial · Suporte · Pós-venda"
                required
                maxLength={80}
              />
              <p className="text-xs text-muted-foreground">
                Identifica o canal pra atendente (livre).
              </p>
            </div>

            {/* Campos per-type */}
            {type === 'whatsapp_baileys' && (
              <BaileysFields config={config} setConfig={setConfig} lgpdOk={lgpdOk} setLgpdOk={setLgpdOk} />
            )}
            {type === 'whatsapp_zapi' && (
              <ZapiFields config={config} setConfig={setConfig} />
            )}
            {type === 'whatsapp_meta' && (
              <MetaFields config={config} setConfig={setConfig} />
            )}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setShowAddDialog(false)}>
                Cancelar
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting && <Loader2 size={14} className="mr-1.5 animate-spin" aria-hidden />}
                Salvar canal
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Confirm delete */}
      <Dialog open={!!confirmDelete} onOpenChange={(o) => !o && setConfirmDelete(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Remover canal?</DialogTitle>
            <DialogDescription>
              Canal <strong>{confirmDelete?.label}</strong> ({confirmDelete?.type}) será removido.
              Conversas e mensagens NÃO são apagadas (preservadas pra audit LGPD).
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setConfirmDelete(null)}>Cancelar</Button>
            <Button
              variant="destructive"
              onClick={() => confirmDelete && doDelete(confirmDelete)}
            >
              <Trash2 size={14} className="mr-1.5" aria-hidden />
              Remover
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function ChannelCard({
  channel, onDelete, onConnect,
}: { channel: Channel; onDelete: () => void; onConnect: () => void }) {
  const TypeIcon = channel.type.startsWith('whatsapp_') ? MessageCircle : Plug;
  const healthColor = {
    healthy: 'text-emerald-600 dark:text-emerald-400',
    degraded: 'text-amber-600 dark:text-amber-400',
    disconnected: 'text-red-600 dark:text-red-400',
    banned: 'text-red-700 dark:text-red-500',
    never_checked: 'text-muted-foreground',
  }[channel.channel_health];

  const showConnect = channel.type === 'whatsapp_baileys'
    && channel.status !== 'active'
    && channel.channel_health !== 'healthy';

  return (
    <Card className="p-4 flex flex-col gap-2">
      <div className="flex items-start justify-between gap-2">
        <div className="flex items-center gap-2 min-w-0">
          <TypeIcon size={20} className="text-primary shrink-0" aria-hidden />
          <div className="min-w-0">
            {/* US-WA-068: nome do canal vira link pro detail (tabs Config | Usuários | Histórico) */}
            <Link
              href={route('atendimento.channels.show', channel.id)}
              className="font-semibold truncate block hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded"
              data-testid={`channel-card-link-${channel.id}`}
            >
              {channel.label}
            </Link>
            <div className="text-xs text-muted-foreground truncate">{channel.type}</div>
          </div>
        </div>
        <div className="flex items-center gap-1 shrink-0">
          {showConnect && (
            <Button
              variant="default"
              size="sm"
              onClick={onConnect}
              title="Gerar QR Code pra conectar este número ao WhatsApp"
              className="h-7 gap-1.5"
            >
              <Zap size={14} aria-hidden />
              Conectar
            </Button>
          )}
          <Button variant="ghost" size="icon" onClick={onDelete} title="Remover canal" className="h-7 w-7">
            <Trash2 size={14} className="text-muted-foreground hover:text-destructive" aria-hidden />
          </Button>
        </div>
      </div>

      {channel.display_identifier && (
        <div className="text-xs text-muted-foreground flex items-center gap-1.5">
          <Smartphone size={12} aria-hidden />
          <span className="truncate">{channel.display_identifier}</span>
        </div>
      )}

      <div className="flex items-center gap-2 text-xs">
        <Circle size={8} className={`fill-current ${healthColor}`} aria-hidden />
        <span className="text-muted-foreground">
          {channel.channel_health === 'never_checked' ? 'Setup pendente' : channel.channel_health}
        </span>
        <Badge variant="outline" className="text-[10px] ml-auto">
          {channel.status}
        </Badge>
      </div>

      {channel.last_health_message && (
        <div className="text-[11px] text-amber-700 dark:text-amber-400 flex items-start gap-1 mt-1">
          <AlertTriangle size={11} className="mt-0.5 shrink-0" aria-hidden />
          <span className="line-clamp-2">{channel.last_health_message}</span>
        </div>
      )}

      {channel.lgpd_acknowledged_at && (
        <div className="text-[10px] text-muted-foreground flex items-center gap-1">
          <CheckCircle2 size={10} className="text-emerald-500" aria-hidden />
          LGPD aceito
        </div>
      )}
    </Card>
  );
}

function BaileysFields({
  config, setConfig, lgpdOk, setLgpdOk,
}: {
  config: Record<string, string>; setConfig: (c: Record<string, string>) => void;
  lgpdOk: boolean; setLgpdOk: (v: boolean) => void;
}) {
  return (
    <>
      <div className="space-y-1">
        <Label htmlFor="baileys_phone">Telefone WhatsApp (E.164)</Label>
        <Input
          id="baileys_phone"
          value={config.baileys_phone_e164 || ''}
          onChange={(e) => setConfig({ ...config, baileys_phone_e164: e.target.value })}
          placeholder="+5511987654321"
          required
        />
        <p className="text-xs text-amber-700 dark:text-amber-400 inline-flex items-start gap-1">
          <AlertTriangle size={11} className="mt-0.5 shrink-0" aria-hidden />
          Chip dedicado pra empresa — NUNCA número pessoal. Risco ban Meta.
        </p>
      </div>
      <div className="flex items-start gap-2 rounded-md border bg-muted/30 p-3 text-xs">
        <Checkbox id="lgpd" checked={lgpdOk} onCheckedChange={(v) => setLgpdOk(v === true)} />
        <Label htmlFor="lgpd" className="leading-relaxed text-xs cursor-pointer">
          Aceito que Baileys é driver não-oficial (WhatsApp Web reverse-engineered).
          Meta pode banir o número a qualquer momento. Fallback Meta Cloud
          obrigatório quando driver_health degrada.
        </Label>
      </div>
    </>
  );
}

function ZapiFields({
  config, setConfig,
}: { config: Record<string, string>; setConfig: (c: Record<string, string>) => void }) {
  return (
    <>
      <div className="space-y-1">
        <Label htmlFor="zapi_instance_id">Z-API Instance ID</Label>
        <Input
          id="zapi_instance_id"
          value={config.zapi_instance_id || ''}
          onChange={(e) => setConfig({ ...config, zapi_instance_id: e.target.value })}
          required
        />
      </div>
      <div className="space-y-1">
        <Label htmlFor="zapi_instance_token">Z-API Instance Token</Label>
        <Input
          id="zapi_instance_token"
          type="password"
          value={config.zapi_instance_token || ''}
          onChange={(e) => setConfig({ ...config, zapi_instance_token: e.target.value })}
          required
        />
      </div>
      <div className="space-y-1">
        <Label htmlFor="zapi_client_token">Z-API Client Token (opcional)</Label>
        <Input
          id="zapi_client_token"
          type="password"
          value={config.zapi_client_token || ''}
          onChange={(e) => setConfig({ ...config, zapi_client_token: e.target.value })}
        />
      </div>
    </>
  );
}

function MetaFields({
  config, setConfig,
}: { config: Record<string, string>; setConfig: (c: Record<string, string>) => void }) {
  return (
    <>
      <div className="space-y-1">
        <Label htmlFor="meta_phone_number_id">Phone Number ID (Meta)</Label>
        <Input
          id="meta_phone_number_id"
          value={config.meta_phone_number_id || ''}
          onChange={(e) => setConfig({ ...config, meta_phone_number_id: e.target.value })}
          required
        />
      </div>
      <div className="space-y-1">
        <Label htmlFor="meta_access_token">Access Token (Meta)</Label>
        <Input
          id="meta_access_token"
          type="password"
          value={config.meta_access_token || ''}
          onChange={(e) => setConfig({ ...config, meta_access_token: e.target.value })}
          required
        />
      </div>
      <div className="space-y-1">
        <Label htmlFor="meta_webhook_verify_token">Webhook Verify Token</Label>
        <Input
          id="meta_webhook_verify_token"
          value={config.meta_webhook_verify_token || ''}
          onChange={(e) => setConfig({ ...config, meta_webhook_verify_token: e.target.value })}
        />
      </div>
    </>
  );
}

ChannelsIndex.layout = (page: React.ReactElement) => <AppShellV2>{page}</AppShellV2>;
