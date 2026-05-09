// @memcofre
//   tela: /whatsapp/settings
//   stories: US-WA-001 (wizard) + US-WA-022 (UX simplificada Baileys)
//   adrs: 0058 (Centrifugo) + 0093 (multi-tenant) + 0096 (Z-API/Meta/Baileys) + 0107 (visual gate) + 0112 (mwart-exceção)
//   charter: resources/js/Pages/Whatsapp/Settings.charter.md
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   permissao: whatsapp.settings.manage

import { useEffect, useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { Centrifuge } from 'centrifuge';
import { AlertTriangle, Copy, Loader2, QrCode, Smartphone, Wifi, WifiOff } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Switch } from '@/Components/ui/switch';

type Driver = 'zapi' | 'meta_cloud' | 'baileys' | 'null';
type DriverHealth = 'healthy' | 'degraded' | 'disconnected' | 'banned' | 'never_checked';
type LiveState = 'idle' | 'connecting' | 'qr_required' | 'connected' | 'degraded' | 'disconnected' | 'banned';

interface ConfigForUi {
  driver: Driver;
  fallback_driver: Driver;
  display_phone: string | null;
  driver_health: DriverHealth;
  driver_health_consecutive_failures: number;
  last_health_check_at: string | null;
  last_health_message: string | null;
  lgpd_acknowledged_at: string | null;
  has_meta_credentials: boolean;
  has_zapi_credentials: boolean;
  has_baileys_credentials: boolean;
  meta_phone_number_id: string | null;
  meta_webhook_verify_token: string | null;
  zapi_instance_id: string | null;
  baileys_instance_id: string | null;
  baileys_phone_e164: string | null;
  baileys_verified_name: string | null;
  baileys_profile_pic_url: string | null;
  bot_enabled: boolean;
  template_repair_ready_name: string | null;
  template_repair_waiting_parts_name: string | null;
  template_billing_due_name: string | null;
  template_billing_paid_name: string | null;
}

interface CentrifugoConfig {
  wsUrl: string;
  token: string;
  channel: string;
}

interface Props {
  config: ConfigForUi | null;
  webhookUrls: { meta: string; zapi: string; baileys: string } | null;
  forbiddenDrivers: string[];
  mandatoryFallbackFor: string[];
  centrifugoConfig: CentrifugoConfig | null;
}

export default function WhatsappSettings({
  config, webhookUrls, forbiddenDrivers, mandatoryFallbackFor, centrifugoConfig,
}: Props) {
  const [driver, setDriver] = useState<Driver>(config?.driver ?? 'zapi');
  const [fallbackDriver] = useState<Driver>(config?.fallback_driver ?? 'meta_cloud');
  const [lgpdAccepted, setLgpdAccepted] = useState<boolean>(config?.lgpd_acknowledged_at !== null);

  const [metaPhone, setMetaPhone] = useState(config?.meta_phone_number_id ?? '');
  const [metaToken, setMetaToken] = useState('');
  const [metaSecret, setMetaSecret] = useState('');
  const [metaVerify, setMetaVerify] = useState(config?.meta_webhook_verify_token ?? '');

  const [zapiInstance, setZapiInstance] = useState(config?.zapi_instance_id ?? '');
  const [zapiToken, setZapiToken] = useState('');
  const [zapiClient, setZapiClient] = useState('');

  // US-WA-022: Baileys agora é só telefone E.164.
  const [baileysPhone, setBaileysPhone] = useState(config?.baileys_phone_e164 ?? '');

  const [botEnabled, setBotEnabled] = useState(config?.bot_enabled ?? false);
  const [tplReady, setTplReady] = useState(config?.template_repair_ready_name ?? '');
  const [tplWaiting, setTplWaiting] = useState(config?.template_repair_waiting_parts_name ?? '');
  const [tplBillingDue, setTplBillingDue] = useState(config?.template_billing_due_name ?? '');
  const [tplBillingPaid, setTplBillingPaid] = useState(config?.template_billing_paid_name ?? '');

  const [submitting, setSubmitting] = useState(false);

  // US-WA-022 estado reativo Baileys via Centrifugo
  const [liveState, setLiveState] = useState<LiveState>(deriveInitialLiveState(config));
  const [liveQr, setLiveQr] = useState<string | null>(null);
  const [liveQrExpiresIn, setLiveQrExpiresIn] = useState<number>(0);
  const [liveDisplayPhone, setLiveDisplayPhone] = useState<string | null>(config?.display_phone ?? null);
  const [liveVerifiedName, setLiveVerifiedName] = useState<string | null>(config?.baileys_verified_name ?? null);
  const [liveProfilePicUrl, setLiveProfilePicUrl] = useState<string | null>(config?.baileys_profile_pic_url ?? null);
  const [liveBanReason, setLiveBanReason] = useState<string | null>(null);
  const [centrifugoConnected, setCentrifugoConnected] = useState(false);

  const requiresFallback = mandatoryFallbackFor.includes(driver);
  const isForbidden = forbiddenDrivers.includes(driver);

  // ------- Centrifugo subscribe (driver=baileys) -------
  useEffect(() => {
    if (!centrifugoConfig || driver !== 'baileys') return;

    const c = new Centrifuge(centrifugoConfig.wsUrl, { token: centrifugoConfig.token });
    c.on('connected', () => setCentrifugoConnected(true));
    c.on('disconnected', () => setCentrifugoConnected(false));

    const sub = c.newSubscription(centrifugoConfig.channel);
    sub.on('publication', (ctx: { data: Record<string, unknown> }) => {
      const event = (ctx.data?.event as string | undefined) ?? '';
      if (!event.startsWith('baileys.')) return;
      const state = ctx.data?.state as LiveState | undefined;
      if (state) setLiveState(state);

      switch (event) {
        case 'baileys.qr_updated':
          setLiveQr((ctx.data?.qr as string | null) ?? null);
          setLiveQrExpiresIn((ctx.data?.expires_in_seconds as number | undefined) ?? 60);
          break;
        case 'baileys.connected':
          setLiveQr(null);
          setLiveQrExpiresIn(0);
          setLiveDisplayPhone((ctx.data?.display_phone as string | null) ?? null);
          setLiveVerifiedName((ctx.data?.verified_name as string | null) ?? null);
          setLiveProfilePicUrl((ctx.data?.profile_pic_url as string | null) ?? null);
          setLiveBanReason(null);
          break;
        case 'baileys.ban_detected':
          setLiveBanReason((ctx.data?.reason as string | null) ?? 'unknown');
          setLiveQr(null);
          break;
        case 'baileys.session_lost':
        case 'baileys.disconnected':
          setLiveQr(null);
          break;
      }
    });
    sub.subscribe();
    c.connect();

    return () => {
      try { sub.unsubscribe(); } catch { /* ignore */ }
      c.disconnect();
    };
  }, [centrifugoConfig?.token, centrifugoConfig?.channel, centrifugoConfig?.wsUrl, driver]);

  // Countdown QR
  useEffect(() => {
    if (liveState !== 'qr_required' || liveQrExpiresIn <= 0) return;
    const interval = setInterval(() => {
      setLiveQrExpiresIn((s) => Math.max(0, s - 1));
    }, 1000);
    return () => clearInterval(interval);
  }, [liveState, liveQr]);

  function copyToClipboard(text: string) {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
      navigator.clipboard.writeText(text).catch(() => {});
    }
  }

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitting(true);

    if (driver === 'baileys') {
      setLiveState('connecting');
    }

    router.put(
      route('whatsapp.settings.update'),
      {
        driver,
        fallback_driver: fallbackDriver,
        meta_phone_number_id: metaPhone || null,
        meta_access_token: metaToken || null,
        meta_app_secret: metaSecret || null,
        meta_webhook_verify_token: metaVerify || null,
        zapi_instance_id: zapiInstance || null,
        zapi_instance_token: zapiToken || null,
        zapi_client_token: zapiClient || null,
        baileys_phone_e164: baileysPhone || null,
        lgpd_acknowledged: lgpdAccepted,
        bot_enabled: botEnabled,
        template_repair_ready_name: tplReady || null,
        template_repair_waiting_parts_name: tplWaiting || null,
        template_billing_due_name: tplBillingDue || null,
        template_billing_paid_name: tplBillingPaid || null,
      },
      {
        preserveScroll: true,
        onFinish: () => setSubmitting(false),
      },
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        icon="settings"
        title="Configurações Whatsapp"
        description="Wizard provedores: Z-API · Meta Cloud · Baileys custom (todos com fallback obrigatório quando aplicável)"
      />

      {/* Status atual */}
      {config && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-3">
              Status do driver
              <DriverHealthBadge health={config.driver_health} />
              {config.driver_health !== 'healthy' && config.driver_health !== 'never_checked' && (
                <Badge variant="outline" className="border-amber-500 text-amber-700 dark:text-amber-400 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30">
                  Fallback {config.fallback_driver} ativo
                </Badge>
              )}
              {driver === 'baileys' && centrifugoConfig && (
                <Badge variant="outline" className={centrifugoConnected ? 'border-green-500 text-green-700' : 'border-slate-400 text-slate-600'}>
                  {centrifugoConnected ? <Wifi size={12} className="inline mr-1" /> : <WifiOff size={12} className="inline mr-1" />}
                  {centrifugoConnected ? 'real-time' : 'sem real-time'}
                </Badge>
              )}
            </CardTitle>
            <CardDescription>
              Driver atual: <strong>{config.driver}</strong>
              {config.display_phone && ` · Número: ${config.display_phone}`}
              {config.last_health_check_at && ` · Último check: ${new Date(config.last_health_check_at).toLocaleString('pt-BR')}`}
            </CardDescription>
          </CardHeader>
          {config.last_health_message && (
            <CardContent>
              <p className="text-sm text-muted-foreground">Última mensagem: {config.last_health_message}</p>
            </CardContent>
          )}
        </Card>
      )}

      {/* Aviso forbidden */}
      {isForbidden && (
        <Alert variant="destructive">
          <AlertTriangle className="h-4 w-4" aria-hidden />
          <AlertTitle>Driver proibido</AlertTitle>
          <AlertDescription>
            Driver &quot;{driver}&quot; é PROIBIDO permanente (ADR 0096 emenda 4). Reabrir só via nova ADR explícita Wagner-aceita.
          </AlertDescription>
        </Alert>
      )}

      {/* Aviso risco — só antes de aceitar LGPD */}
      {requiresFallback && !lgpdAccepted && (
        <Alert variant="destructive">
          <AlertTriangle className="h-4 w-4" aria-hidden />
          <AlertTitle>Provedor não-oficial — risco ban Meta</AlertTitle>
          <AlertDescription>
            Driver <strong>{driver}</strong> é baseado em Whatsapp Web (não-oficial). Existe risco de bloqueio
            arbitrário pela Meta. Meta Cloud cadastrado como fallback é <strong>obrigatório</strong> e o termo LGPD
            precisa ser aceito.
          </AlertDescription>
        </Alert>
      )}

      <form onSubmit={onSubmit} className="space-y-6">
        {/* Seletor driver */}
        <Card>
          <CardHeader>
            <CardTitle>Passo 1 — Driver primário</CardTitle>
            <CardDescription>Escolha o provedor Whatsapp pra este negócio.</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <DriverCard
                value="zapi"
                title="Z-API"
                description="SaaS BR. 5 min scan QR. R$ 99/mês. Risco ban Meta."
                selected={driver === 'zapi'}
                onClick={() => setDriver('zapi')}
              />
              <DriverCard
                value="meta_cloud"
                title="Meta Cloud (oficial)"
                description="Onboarding 1-3 dias. Free 1k conv/mês. Sem risco ban."
                selected={driver === 'meta_cloud'}
                onClick={() => setDriver('meta_cloud')}
              />
              <DriverCard
                value="baileys"
                title="Baileys custom"
                description="Daemon Node próprio oimpresso. Estrutura customizada. Risco ban."
                selected={driver === 'baileys'}
                onClick={() => setDriver('baileys')}
              />
            </div>
          </CardContent>
        </Card>

        {/* Z-API form */}
        {driver === 'zapi' && (
          <Card>
            <CardHeader>
              <CardTitle>Z-API — credenciais</CardTitle>
              <CardDescription>Cadastre conta em app.z-api.io e cole as credenciais aqui.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <div>
                <Label htmlFor="zapi_instance_id">Instance ID</Label>
                <Input id="zapi_instance_id" value={zapiInstance} onChange={(e) => setZapiInstance(e.target.value)} placeholder="3ABC..." />
              </div>
              <div>
                <Label htmlFor="zapi_instance_token">Instance Token (cifrado em DB)</Label>
                <Input id="zapi_instance_token" type="password" value={zapiToken} onChange={(e) => setZapiToken(e.target.value)} placeholder={config?.has_zapi_credentials ? '•••••• (já cadastrado)' : 'Cole o token'} />
              </div>
              <div>
                <Label htmlFor="zapi_client_token">Client Token (cifrado, segurança webhook)</Label>
                <Input id="zapi_client_token" type="password" value={zapiClient} onChange={(e) => setZapiClient(e.target.value)} placeholder={config?.has_zapi_credentials ? '•••••• (já cadastrado)' : 'Cole o client_token'} />
              </div>
              {webhookUrls && (
                <div>
                  <Label>Webhook URL Z-API (cole no painel Z-API)</Label>
                  <div className="flex gap-2">
                    <Input readOnly value={webhookUrls.zapi} />
                    <Button type="button" variant="outline" onClick={() => copyToClipboard(webhookUrls.zapi)} className="gap-1.5">
                      <Copy size={14} aria-hidden />
                      Copiar
                    </Button>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* Baileys — UX simplificada US-WA-022 */}
        {driver === 'baileys' && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                Baileys — telefone WhatsApp Business
                <BaileysLiveStateBadge state={liveState} />
              </CardTitle>
              <CardDescription>
                Cadastre o telefone no formato E.164 (ex: <code>+5511987654321</code>). O sistema cuida do resto:
                provisiona instance, gera QR Code, sincroniza perfil. Você só precisa escanear.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <Label htmlFor="baileys_phone_e164">Telefone (E.164)</Label>
                <Input
                  id="baileys_phone_e164"
                  type="tel"
                  inputMode="tel"
                  value={baileysPhone}
                  onChange={(e) => setBaileysPhone(e.target.value)}
                  placeholder="+5511987654321"
                  pattern="^\+[1-9][0-9]{8,14}$"
                />
                <p className="text-xs text-muted-foreground mt-1">
                  Use chip dedicado pra business — nunca número pessoal. Risco ban Meta.
                </p>
              </div>

              <BaileysLiveStatusPanel
                state={liveState}
                qr={liveQr}
                qrExpiresIn={liveQrExpiresIn}
                displayPhone={liveDisplayPhone}
                verifiedName={liveVerifiedName}
                profilePicUrl={liveProfilePicUrl}
                banReason={liveBanReason}
              />
            </CardContent>
          </Card>
        )}

        {/* Meta Cloud — primário ou fallback obrigatório */}
        <Card>
          <CardHeader>
            <CardTitle>
              Passo 2 — Meta Cloud {requiresFallback ? '(fallback obrigatório)' : '(driver primário)'}
            </CardTitle>
            <CardDescription>
              Cadastre em business.facebook.com → WhatsApp Business Account. Aprovação 1-3 dias.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div>
              <Label htmlFor="meta_phone_number_id">Phone Number ID (Meta)</Label>
              <Input id="meta_phone_number_id" value={metaPhone} onChange={(e) => setMetaPhone(e.target.value)} placeholder="123456789012345" />
            </div>
            <div>
              <Label htmlFor="meta_access_token">Access Token (cifrado em DB)</Label>
              <Input id="meta_access_token" type="password" value={metaToken} onChange={(e) => setMetaToken(e.target.value)} placeholder={config?.has_meta_credentials ? '•••••• (já cadastrado)' : 'EAAB...'} />
            </div>
            <div>
              <Label htmlFor="meta_app_secret">App Secret (cifrado, usado pra HMAC webhook)</Label>
              <Input id="meta_app_secret" type="password" value={metaSecret} onChange={(e) => setMetaSecret(e.target.value)} placeholder={config?.has_meta_credentials ? '•••••• (já cadastrado)' : 'app secret'} />
            </div>
            <div>
              <Label htmlFor="meta_webhook_verify_token">Webhook Verify Token</Label>
              <Input id="meta_webhook_verify_token" value={metaVerify} onChange={(e) => setMetaVerify(e.target.value)} placeholder="random-32-bytes" />
            </div>
            {webhookUrls && (
              <div>
                <Label>Webhook URL Meta (cole na Meta App → Webhooks → Whatsapp)</Label>
                <div className="flex gap-2">
                  <Input readOnly value={webhookUrls.meta} />
                  <Button type="button" variant="outline" onClick={() => copyToClipboard(webhookUrls.meta)}>Copiar</Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Termo LGPD */}
        {requiresFallback && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                Termo LGPD
                {lgpdAccepted && config?.lgpd_acknowledged_at ? (
                  <Badge variant="outline" className="border-green-500 text-green-700 dark:text-green-400 dark:border-green-700 bg-green-50 dark:bg-green-950/30">
                    ✓ aceito em {new Date(config.lgpd_acknowledged_at).toLocaleDateString('pt-BR')}
                  </Badge>
                ) : (
                  <Badge variant="outline" className="border-amber-500 text-amber-700 dark:text-amber-400 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30">
                    obrigatório
                  </Badge>
                )}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <label className="flex items-start gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={lgpdAccepted}
                  onChange={(e) => setLgpdAccepted(e.target.checked)}
                  className="mt-1"
                />
                <span className="text-sm">
                  Estou ciente que <strong>{driver}</strong> é provedor não-oficial baseado em Whatsapp Web e que existe
                  risco de bloqueio Meta. Configurei Meta Cloud como fallback pra mitigar interrupção do meu serviço.
                </span>
              </label>
            </CardContent>
          </Card>
        )}

        {/* Templates + Bot */}
        <Card>
          <CardHeader>
            <CardTitle>Templates + Bot</CardTitle>
            <CardDescription>Nomes dos templates HSM Meta (ou locais Z-API/Baileys) que disparam automaticamente.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-center gap-3">
              <Switch checked={botEnabled} onCheckedChange={setBotEnabled} />
              <Label>Bot Jana (HITL — handoff humano via PolicyEngine ADS)</Label>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <Label htmlFor="tpl_ready">Repair: status ready</Label>
                <Input id="tpl_ready" value={tplReady} onChange={(e) => setTplReady(e.target.value)} placeholder="repair_status_ready" />
              </div>
              <div>
                <Label htmlFor="tpl_waiting">Repair: aguardando peças</Label>
                <Input id="tpl_waiting" value={tplWaiting} onChange={(e) => setTplWaiting(e.target.value)} placeholder="repair_status_waiting_parts" />
              </div>
              <div>
                <Label htmlFor="tpl_due">Cobrança: vencimento próximo</Label>
                <Input id="tpl_due" value={tplBillingDue} onChange={(e) => setTplBillingDue(e.target.value)} placeholder="billing_due_reminder" />
              </div>
              <div>
                <Label htmlFor="tpl_paid">Cobrança: pagamento confirmado</Label>
                <Input id="tpl_paid" value={tplBillingPaid} onChange={(e) => setTplBillingPaid(e.target.value)} placeholder="billing_paid_thank_you" />
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="flex justify-end gap-2">
          <Button type="submit" disabled={submitting || isForbidden}>
            {submitting ? <><Loader2 className="h-4 w-4 animate-spin mr-1" /> Salvando...</> : driver === 'baileys' ? 'Salvar e Conectar' : 'Salvar configuração'}
          </Button>
        </div>
      </form>
    </div>
  );
}

WhatsappSettings.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;

// ============================================================================
// Helpers
// ============================================================================

function deriveInitialLiveState(config: ConfigForUi | null): LiveState {
  if (!config || config.driver !== 'baileys') return 'idle';
  switch (config.driver_health) {
    case 'healthy': return 'connected';
    case 'banned': return 'banned';
    case 'degraded': return 'degraded';
    case 'disconnected': return 'disconnected';
    default: return 'idle';
  }
}

function DriverCard({
  value: _value,
  title,
  description,
  selected,
  onClick,
  disabled = false,
}: {
  value: string;
  title: string;
  description: string;
  selected: boolean;
  onClick: () => void;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      onClick={disabled ? undefined : onClick}
      disabled={disabled}
      className={`text-left rounded-lg border p-4 transition ${
        selected
          ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
          : 'border-border hover:border-primary/50'
      } ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
    >
      <div className="font-semibold">{title}</div>
      <div className="text-sm text-muted-foreground mt-1">{description}</div>
    </button>
  );
}

function DriverHealthBadge({ health }: { health: string }) {
  const map: Record<string, { label: string; className: string }> = {
    healthy:       { label: 'Conectado',       className: 'bg-green-100 text-green-800 border-green-300 dark:bg-green-950/40 dark:text-green-300 dark:border-green-800' },
    degraded:      { label: 'Degradado',       className: 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800' },
    disconnected:  { label: 'Desconectado',    className: 'bg-red-100 text-red-800 border-red-300 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800' },
    banned:        { label: 'Banido pela Meta', className: 'bg-red-100 text-red-900 border-red-500 dark:bg-red-950/60 dark:text-red-200 dark:border-red-700' },
    never_checked: { label: 'Aguardando teste', className: 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-900/40 dark:text-slate-300 dark:border-slate-700' },
  };
  const conf = map[health] ?? map.never_checked!;
  return <Badge variant="outline" className={conf.className}>{conf.label}</Badge>;
}

function BaileysLiveStateBadge({ state }: { state: LiveState }) {
  const map: Record<LiveState, { label: string; cls: string }> = {
    idle:          { label: 'aguardando',     cls: 'border-slate-300 text-slate-700' },
    connecting:    { label: 'conectando…',    cls: 'border-amber-500 text-amber-700' },
    qr_required:   { label: 'aguarda QR',     cls: 'border-amber-500 text-amber-700' },
    connected:     { label: '✓ conectado',    cls: 'border-green-500 text-green-700' },
    degraded:      { label: 'degradado',      cls: 'border-amber-500 text-amber-700' },
    disconnected:  { label: 'desconectado',   cls: 'border-red-500 text-red-700' },
    banned:        { label: 'banido pela Meta', cls: 'border-red-600 text-red-800 bg-red-50' },
  };
  const conf = map[state];
  return <Badge variant="outline" className={conf.cls}>{conf.label}</Badge>;
}

function BaileysLiveStatusPanel({
  state, qr, qrExpiresIn, displayPhone, verifiedName, profilePicUrl, banReason,
}: {
  state: LiveState;
  qr: string | null;
  qrExpiresIn: number;
  displayPhone: string | null;
  verifiedName: string | null;
  profilePicUrl: string | null;
  banReason: string | null;
}) {
  if (state === 'idle') {
    return (
      <div className="text-sm text-muted-foreground border rounded-lg p-4 bg-muted/40">
        <Smartphone className="inline mr-1 h-4 w-4 align-text-bottom" />
        Salve a configuração e o sistema vai gerar o QR Code automaticamente.
      </div>
    );
  }

  if (state === 'connecting') {
    return (
      <div className="text-sm border rounded-lg p-4 bg-amber-50 dark:bg-amber-950/30 border-amber-300 dark:border-amber-700">
        <Loader2 className="inline mr-2 h-4 w-4 animate-spin align-text-bottom" />
        Provisionando instance no daemon… aguarde alguns segundos.
      </div>
    );
  }

  if (state === 'qr_required') {
    return (
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 border rounded-lg p-4 bg-amber-50 dark:bg-amber-950/30 border-amber-300 dark:border-amber-700">
        <div className="flex items-center justify-center">
          {qr ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={qr} alt="QR Code Whatsapp" className="w-56 h-56 border rounded" />
          ) : (
            <div className="w-56 h-56 flex items-center justify-center border rounded bg-white">
              <QrCode className="h-12 w-12 text-muted-foreground" />
            </div>
          )}
        </div>
        <div className="text-sm space-y-2">
          <p className="font-semibold">Escaneie com WhatsApp Business:</p>
          <ol className="list-decimal pl-5 space-y-0.5">
            <li>Abra o app no celular</li>
            <li>Toque em <strong>⋮ → Aparelhos conectados</strong></li>
            <li>Toque em <strong>Conectar aparelho</strong></li>
            <li>Aponte a câmera pro QR ↑</li>
          </ol>
          <p className="text-xs text-muted-foreground pt-1">
            ⏱ Expira em {qrExpiresIn}s · novo QR gerado automaticamente
          </p>
        </div>
      </div>
    );
  }

  if (state === 'connected') {
    return (
      <div className="flex items-center gap-3 border rounded-lg p-4 bg-green-50 dark:bg-green-950/30 border-green-300 dark:border-green-700">
        {profilePicUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={profilePicUrl} alt="" className="w-12 h-12 rounded-full border" />
        ) : (
          <Smartphone className="h-12 w-12 text-green-700" />
        )}
        <div className="flex-1">
          <p className="font-semibold">✓ Conectado</p>
          {displayPhone && <p className="text-sm">{formatPhone(displayPhone)}</p>}
          {verifiedName && <p className="text-xs text-muted-foreground">{verifiedName}</p>}
        </div>
      </div>
    );
  }

  if (state === 'banned') {
    return (
      <Alert variant="destructive">
        <AlertTriangle className="h-4 w-4" aria-hidden />
        <AlertTitle>Número banido pela Meta</AlertTitle>
        <AlertDescription>
          Motivo: <strong>{banReason ?? 'desconhecido'}</strong>. Use chip novo dedicado e configure novo telefone E.164.
          Veja{' '}
          <a
            className="underline"
            href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/requisitos/Whatsapp/runbooks/baileys-troubleshoot-ban.md"
            target="_blank"
            rel="noreferrer noopener"
          >
            runbook troubleshoot-ban
          </a>{' '}
          pra recuperação.
        </AlertDescription>
      </Alert>
    );
  }

  // degraded / disconnected
  return (
    <Alert variant="destructive">
      <AlertTriangle className="h-4 w-4" aria-hidden />
      <AlertTitle>{state === 'degraded' ? 'Sessão degradada' : 'Desconectado'}</AlertTitle>
      <AlertDescription>
        Sistema vai tentar reconectar automaticamente. Se persistir, salve novamente esta configuração pra forçar
        reconexão.
      </AlertDescription>
    </Alert>
  );
}

function formatPhone(e164: string): string {
  // +5511987654321 → +55 11 9 8765-4321
  const digits = e164.replace(/\D+/g, '');
  if (digits.length === 13 && digits.startsWith('55')) {
    return `+${digits.slice(0, 2)} ${digits.slice(2, 4)} ${digits.slice(4, 5)} ${digits.slice(5, 9)}-${digits.slice(9)}`;
  }
  return e164;
}
