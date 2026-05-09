// @memcofre
//   tela: /whatsapp/settings
//   stories: US-WA-001 (wizard 2 passos Z-API + Meta Cloud)
//   adrs: 0096 (Z-API default + Meta Cloud fallback obrigatório)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   status: implementada Lote 2e
//   permissao: whatsapp.settings.manage

import { useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, Copy } from 'lucide-react';

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

interface ConfigForUi {
  driver: Driver;
  fallback_driver: Driver;
  display_phone: string | null;
  driver_health: 'healthy' | 'degraded' | 'disconnected' | 'banned' | 'never_checked';
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
  baileys_daemon_url: string | null;
  bot_enabled: boolean;
  template_repair_ready_name: string | null;
  template_repair_waiting_parts_name: string | null;
  template_billing_due_name: string | null;
  template_billing_paid_name: string | null;
}

interface Props {
  config: ConfigForUi | null;
  webhookUrls: { meta: string; zapi: string; baileys: string } | null;
  forbiddenDrivers: string[];
  mandatoryFallbackFor: string[];
}

export default function WhatsappSettings({ config, webhookUrls, forbiddenDrivers, mandatoryFallbackFor }: Props) {
  const [driver, setDriver] = useState<Driver>(config?.driver ?? 'zapi');
  // Fallback driver é fixo "meta_cloud" no MVP (ADR 0096 §3 fallback obrigatório).
  // Setter mantido pra Sprint 3 quando tela de wizard avançado expor escolha.
  const [fallbackDriver] = useState<Driver>(config?.fallback_driver ?? 'meta_cloud');
  const [lgpdAccepted, setLgpdAccepted] = useState<boolean>(config?.lgpd_acknowledged_at !== null);

  const [metaPhone, setMetaPhone] = useState(config?.meta_phone_number_id ?? '');
  const [metaToken, setMetaToken] = useState(''); // sempre vazio (não vem do server por segurança)
  const [metaSecret, setMetaSecret] = useState('');
  const [metaVerify, setMetaVerify] = useState(config?.meta_webhook_verify_token ?? '');

  const [zapiInstance, setZapiInstance] = useState(config?.zapi_instance_id ?? '');
  const [zapiToken, setZapiToken] = useState('');
  const [zapiClient, setZapiClient] = useState('');

  const [baileysInstance, setBaileysInstance] = useState(config?.baileys_instance_id ?? '');
  const [baileysUrl, setBaileysUrl] = useState(config?.baileys_daemon_url ?? '');
  const [baileysKey, setBaileysKey] = useState('');

  const [botEnabled, setBotEnabled] = useState(config?.bot_enabled ?? false);
  const [tplReady, setTplReady] = useState(config?.template_repair_ready_name ?? '');
  const [tplWaiting, setTplWaiting] = useState(config?.template_repair_waiting_parts_name ?? '');
  const [tplBillingDue, setTplBillingDue] = useState(config?.template_billing_due_name ?? '');
  const [tplBillingPaid, setTplBillingPaid] = useState(config?.template_billing_paid_name ?? '');

  const [submitting, setSubmitting] = useState(false);

  const requiresFallback = mandatoryFallbackFor.includes(driver);
  const isForbidden = forbiddenDrivers.includes(driver);

  function copyToClipboard(text: string) {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
      navigator.clipboard.writeText(text).catch(() => {});
    }
  }

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitting(true);

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
        baileys_instance_id: baileysInstance || null,
        baileys_daemon_url: baileysUrl || null,
        baileys_api_key: baileysKey || null,
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
        description="Wizard 2 passos: Z-API hoje (5 min) + Meta Cloud em paralelo (1-3 dias) como fallback obrigatório"
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
            </CardTitle>
            <CardDescription>
              Driver atual: <strong>{config.driver}</strong>
              {config.display_phone && ` · Número: ${config.display_phone}`}
              {config.last_health_check_at && ` · Último check: ${new Date(config.last_health_check_at).toLocaleString('pt-BR')}`}
            </CardDescription>
          </CardHeader>
          {config.last_health_message && (
            <CardContent>
              <p className="text-sm text-muted-foreground">
                Última mensagem: {config.last_health_message}
              </p>
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
            Driver &quot;{driver}&quot; é PROIBIDO permanente (ADR 0096 emenda 4). Reabrir só via nova ADR explícita
            Wagner-aceita.
          </AlertDescription>
        </Alert>
      )}

      {/* Aviso risco Z-API/Baileys — só mostra antes de aceitar termo LGPD */}
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
            <CardDescription>Recomendação: Z-API (5 min de onboarding) com Meta Cloud como fallback.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <DriverCard
                value="zapi"
                title="Z-API (recomendado)"
                description="SaaS BR. 5 min scan QR. R$ [redacted Tier 0]/mês. Risco ban Meta."
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
                description="Daemon Node CT 100 próprio. Estrutura customizada (schema/logs/métricas). Risco ban."
                selected={driver === 'baileys'}
                onClick={() => setDriver('baileys')}
              />
            </div>
          </CardContent>
        </Card>

        {/* Z-API form (driver=zapi) */}
        {driver === 'zapi' && (
          <Card>
            <CardHeader>
              <CardTitle>Z-API Credenciais</CardTitle>
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

        {/* Baileys form */}
        {driver === 'baileys' && (
          <Card>
            <CardHeader>
              <CardTitle>Baileys custom — credenciais</CardTitle>
              <CardDescription>
                Daemon Node CT 100 próprio. Cadastro deve ser feito após deploy do daemon (ver runbook
                <code className="mx-1 px-1 bg-muted rounded">baileys-daemon-deploy-ct100.md</code>).
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <div>
                <Label htmlFor="baileys_instance_id">Instance ID</Label>
                <Input
                  id="baileys_instance_id"
                  value={baileysInstance}
                  onChange={(e) => setBaileysInstance(e.target.value)}
                  placeholder={`biz${config?.driver ? '<id>' : ''}-main`}
                />
              </div>
              <div>
                <Label htmlFor="baileys_daemon_url">Daemon URL (CT 100)</Label>
                <Input
                  id="baileys_daemon_url"
                  value={baileysUrl}
                  onChange={(e) => setBaileysUrl(e.target.value)}
                  placeholder="https://whatsapp-baileys.oimpresso.local"
                />
              </div>
              <div>
                <Label htmlFor="baileys_api_key">API Key (Bearer — cifrado em DB)</Label>
                <Input
                  id="baileys_api_key"
                  type="password"
                  value={baileysKey}
                  onChange={(e) => setBaileysKey(e.target.value)}
                  placeholder={config?.has_baileys_credentials ? '•••••• (já cadastrado)' : 'Bearer key'}
                />
              </div>
              {webhookUrls && (
                <div>
                  <Label>Webhook URL Baileys (configurado no daemon — só pra referência)</Label>
                  <div className="flex gap-2">
                    <Input readOnly value={webhookUrls.baileys} />
                    <Button type="button" variant="outline" onClick={() => copyToClipboard(webhookUrls.baileys)} className="gap-1.5">
                      <Copy size={14} aria-hidden />
                      Copiar
                    </Button>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* Meta Cloud form (sempre visível — primário ou fallback obrigatório) */}
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

        {/* Termo LGPD (obrigatório quando driver=zapi/baileys) */}
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

        {/* Templates names + Bot */}
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

        <div className="flex justify-end">
          <Button type="submit" disabled={submitting || isForbidden}>
            {submitting ? 'Salvando...' : 'Salvar configuração'}
          </Button>
        </div>
      </form>
    </div>
  );
}

WhatsappSettings.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;

function DriverCard({
  // value é só pra documentar qual Driver enum a card representa — usado nos
  // testes e/ou pra futuro expand do wizard (ADR 0096 Sprint 3).
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
      {disabled && <Badge variant="outline" className="mt-2">Sprint 3</Badge>}
    </button>
  );
}

function DriverHealthBadge({ health }: { health: string }) {
  // Cores fixas semânticas (R-DS-002 exceção: status badges health constantes).
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
