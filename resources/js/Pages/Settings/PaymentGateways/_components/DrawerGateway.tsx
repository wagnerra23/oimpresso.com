// DrawerGateway.tsx — drawer 4 tabs (Identificação · Credenciais · Webhook · Health)
// Onda 5+ (2026-05-19): wiring backend PUT /settings/payment-gateways/{id}
import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import {
  X, Copy, Shield, RefreshCw, Zap, Check, Trash2, History, Plus, Minus, ExternalLink,
} from 'lucide-react';
import { Btn, KpiCard } from '../../../Financeiro/Cobranca/_components/atoms';
import {
  DriverChip, HealthBadge, Toggle, FileField, Field,
} from './atoms-settings';
import {
  DRIVERS, cn, fmtDate, type GatewayKey,
} from '../_lib/gateway-shared';
import type { SettingsGateway, Account } from '../_lib/gateway-shared';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

type Tab = 'identificacao' | 'credenciais' | 'webhook' | 'health' | 'historico';

interface HistoryEntry {
  id: number;
  when: string;
  when_iso: string;
  who: string;
  action: string;
  event: string;
  diff?: { field: string; from: unknown; to: unknown };
}

interface WebhookEvent {
  id: number;
  when: string;
  when_iso: string;
  evento: string | null;
  gateway_event_id: string | null;
  signature_valid: boolean;
  processed_at: string | null;
  error_message: string | null;
  cobranca_id: number | null;
}

// Onda 4e gap #3 (audit 2026-05-23): quota tracking MVP.
interface QuotaPayload {
  month: string;        // "2026-05"
  counts: Record<string, number>;
  total: number;
  gateway_key: string | null;
}

interface Props {
  gateway: SettingsGateway;
  accounts: Account[];
  onClose: () => void;
  onToggle: (newVal: boolean) => void;
}

export default function DrawerGateway({ gateway, accounts, onClose, onToggle }: Props) {
  const drawerRef = useRef<HTMLDivElement>(null);
  const d = DRIVERS[gateway.driver as GatewayKey];
  const acct = accounts.find(a => a.id === gateway.account_id);
  const [tab, setTab] = useState<Tab>('identificacao');
  const [revealSecret, setRevealSecret] = useState(false);
  const [testStatus, setTestStatus] = useState<'idle' | 'testando' | 'ok' | 'fail'>('idle');

  // Onda 5+: state controlado pra edit
  const [apelido, setApelido] = useState(gateway.nome);
  const [ambiente, setAmbiente] = useState<'production' | 'sandbox'>(gateway.ambiente);
  const [contaId, setContaId] = useState<string>(gateway.account_id ? String(gateway.account_id) : '');
  const [config, setConfig] = useState<Record<string, string>>({});
  const [certFile, setCertFile] = useState<File | null>(null);
  const [keyFile, setKeyFile] = useState<File | null>(null);
  const [certPassword, setCertPassword] = useState('');
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);

  // Onda 4e.UI (gap P0 estado-da-arte 2026-05-23): histórico de auditoria
  const [historyEntries, setHistoryEntries] = useState<HistoryEntry[] | null>(null);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyError, setHistoryError] = useState<string | null>(null);

  // Onda 4e.UI #2: eventos webhook recebidos (lista read-only — replay em backlog)
  const [webhookEvents, setWebhookEvents] = useState<WebhookEvent[] | null>(null);
  const [webhookEventsLoading, setWebhookEventsLoading] = useState(false);
  const [webhookEventsError, setWebhookEventsError] = useState<string | null>(null);

  // Onda 4e gap #3 (audit 2026-05-23): quota tracking — lazy fetch quando tab Health ativada
  const [quota, setQuota] = useState<QuotaPayload | null>(null);
  const [quotaLoading, setQuotaLoading] = useState(false);

  useEffect(() => {
    if (tab !== 'webhook' || webhookEvents !== null || webhookEventsLoading) return;
    setWebhookEventsLoading(true);
    setWebhookEventsError(null);
    fetch(`/settings/payment-gateways/${gateway.id}/webhook-events`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then(r => r.ok ? r.json() : Promise.reject(`HTTP ${r.status}`))
      .then((data: { events: WebhookEvent[] }) => {
        setWebhookEvents(data.events ?? []);
        setWebhookEventsLoading(false);
      })
      .catch(err => {
        setWebhookEventsError(String(err));
        setWebhookEvents([]);
        setWebhookEventsLoading(false);
      });
  }, [tab, gateway.id, webhookEvents, webhookEventsLoading]);

  // Onda 4e gap #3: lazy fetch quota quando tab Health ativada
  useEffect(() => {
    if (tab !== 'health' || quota !== null || quotaLoading) return;
    setQuotaLoading(true);
    fetch(`/settings/payment-gateways/${gateway.id}/quota`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then(r => r.ok ? r.json() : Promise.reject(`HTTP ${r.status}`))
      .then((data: QuotaPayload) => {
        setQuota(data);
        setQuotaLoading(false);
      })
      .catch(() => {
        setQuota({ month: '', counts: {}, total: 0, gateway_key: null });
        setQuotaLoading(false);
      });
  }, [tab, gateway.id, quota, quotaLoading]);

  useEffect(() => {
    if (tab !== 'historico' || historyEntries !== null || historyLoading) return;
    setHistoryLoading(true);
    setHistoryError(null);
    fetch(`/settings/payment-gateways/${gateway.id}/history`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then(r => r.ok ? r.json() : Promise.reject(`HTTP ${r.status}`))
      .then((data: { entries: HistoryEntry[] }) => {
        setHistoryEntries(data.entries ?? []);
        setHistoryLoading(false);
      })
      .catch(err => {
        setHistoryError(String(err));
        setHistoryEntries([]);
        setHistoryLoading(false);
      });
  }, [tab, gateway.id, historyEntries, historyLoading]);

  function setConfigField(key: string, value: string): void {
    setConfig(prev => ({ ...prev, [key]: value }));
  }

  function handleSave(): void {
    setSaveError(null);
    setSaving(true);

    const hasFiles = certFile !== null || keyFile !== null;
    const fd = new FormData();
    fd.append('_method', 'PUT');
    fd.append('nome_display', apelido);
    fd.append('ambiente', ambiente);
    if (contaId) fd.append('conta_bancaria_id', contaId); else fd.append('conta_bancaria_id', '');
    // Apenas envia config fields preenchidos — backend faz merge com config_json existente
    Object.entries(config).forEach(([k, v]) => {
      if (v) fd.append(`config_json[${k}]`, v);
    });
    if (certFile) fd.append('cert_file', certFile);
    if (keyFile) fd.append('key_file', keyFile);
    if (certPassword) fd.append('cert_password', certPassword);

    router.post(`/settings/payment-gateways/${gateway.id}`, fd, {
      forceFormData: hasFiles,
      preserveScroll: true,
      onSuccess: () => {
        setSaving(false);
        onClose();
      },
      onError: (errors) => {
        setSaving(false);
        const firstError = Object.values(errors)[0];
        setSaveError(typeof firstError === 'string' ? firstError : 'Erro ao salvar');
      },
    });
  }

  function handleDelete(): void {
    setSaveError(null);
    setDeleting(true);
    router.delete(`/settings/payment-gateways/${gateway.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        setDeleting(false);
        onClose();
      },
      onError: (errors) => {
        setDeleting(false);
        setConfirmDelete(false);
        const firstError = Object.values(errors)[0];
        setSaveError(typeof firstError === 'string' ? firstError : 'Erro ao excluir');
      },
    });
  }

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
      const focusable = drawerRef.current?.querySelector<HTMLElement>('button, input, select');
      focusable?.focus();
    }, 100);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [onClose]);

  const testar = () => {
    setTestStatus('testando');
    // POST /settings/payment-gateways/{id}/health-check
    fetch(`/settings/payment-gateways/${gateway.id}/health-check`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '',
        'Accept': 'application/json',
      },
    })
      .then(r => r.json())
      .then(j => setTestStatus(j.status === 'ok' ? 'ok' : 'fail'))
      .catch(() => setTestStatus('fail'));
  };

  if (!d) return null;

  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose} role="dialog" aria-modal="true" aria-label={`Gateway ${gateway.nome}`}>
      <div className="absolute inset-0 bg-stone-900/30" />
      <div ref={drawerRef} className="relative w-[640px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>

        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <span className={cn('w-9 h-9 rounded-md grid place-items-center text-white text-[12px] font-bold shrink-0', d.dot)}>{d.sigla}</span>
          <div className="flex-1 min-w-0">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Gateway #{gateway.id} · {d.nome}</div>
            <div className="text-[15px] font-semibold mt-0.5 truncate">{gateway.nome}</div>
          </div>
          <HealthBadge status={gateway.health} />
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500" aria-label="Fechar (Esc)">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>

        {/* Tabs */}
        <div className="border-b border-stone-200 bg-stone-50/40 px-5">
          <div className="flex gap-1" role="tablist">
            {([
              { id: 'identificacao', label: 'Identificação' },
              { id: 'credenciais',   label: 'Credenciais' },
              { id: 'webhook',       label: 'Webhook' },
              { id: 'health',        label: 'Health' },
              { id: 'historico',     label: 'Histórico' },
            ] as const).map(t => (
              <button key={t.id} onClick={() => setTab(t.id)} role="tab" aria-selected={tab === t.id}
                className={cn(
                  'h-9 px-3 text-[12px] border-b-2 -mb-px transition',
                  tab === t.id ? 'border-stone-900 text-stone-900 font-medium' : 'border-transparent text-stone-500 hover:text-stone-800',
                )}>{t.label}</button>
            ))}
          </div>
        </div>

        <div className="flex-1 overflow-auto px-5 py-4 space-y-4">

          {tab === 'identificacao' && (
            <div className="space-y-3">
              <Field label="Apelido">
                <input
                  value={apelido}
                  onChange={e => setApelido(e.target.value)}
                  className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]"
                />
              </Field>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Driver">
                  <div className="h-8 bg-stone-50 border border-stone-300 rounded px-2 flex items-center gap-2 text-[12.5px]">
                    <span className={cn('w-2 h-2 rounded-sm', d.dot)} />
                    <span>{d.nome}</span>
                    <span className="text-[10.5px] text-stone-400 ml-auto font-mono">{d.key}</span>
                  </div>
                </Field>
                <Field label="Ambiente">
                  <div className="inline-flex bg-stone-100 rounded p-0.5 border border-stone-200">
                    {d.ambientes.map(a => (
                      <button
                        key={a}
                        type="button"
                        onClick={() => setAmbiente(a as 'production' | 'sandbox')}
                        className={cn(
                          'h-7 px-3 rounded text-[11.5px] transition',
                          ambiente === a ? 'bg-white shadow-sm font-medium' : 'text-stone-600',
                        )}
                      >{a === 'production' ? 'produção' : 'sandbox'}</button>
                    ))}
                  </div>
                </Field>
              </div>
              <Field label="Conta destino">
                <Select
                  value={contaId || '__none__'}
                  onValueChange={v => setContaId(v === '__none__' ? '' : v)}
                >
                  <SelectTrigger variant="shadcn" size="sm" aria-label="Conta destino" className="w-full text-[12.5px]">
                    <SelectValue placeholder="— sem vínculo —" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none__">— sem vínculo —</SelectItem>
                    {accounts.map(a => <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>)}
                  </SelectContent>
                </Select>
              </Field>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Status"><Toggle on={gateway.ativo} onConfirm={onToggle} /></Field>
                <Field label="Criado em"><div className="text-[12.5px] py-1">{gateway.created_at ? fmtDate(gateway.created_at) : '—'}</div></Field>
              </div>
              {acct && (
                <div className="text-[11px] text-stone-500 bg-stone-50 border border-stone-200 rounded p-2.5">
                  <strong>{acct.name}</strong>
                  {acct.banco && <> · {acct.banco}{acct.agencia ? ` · Ag ${acct.agencia}` : ''}{acct.conta ? ` · Cc ${acct.conta}` : ''}</>}
                </div>
              )}
            </div>
          )}

          {tab === 'credenciais' && (
            <div className="space-y-3">
              <div className="text-[10.5px] text-stone-500 bg-stone-50 border border-stone-200 rounded px-3 py-2 leading-snug">
                {d.cred} <strong>Deixe em branco pra manter o valor atual</strong> — só campos preenchidos são atualizados.
              </div>

              {/* Onda 4e.UI #5 — deep-link pro painel do PSP onde gerar/rotacionar */}
              {d.credentialSource && !d.deprecated && (
                <a
                  href={d.credentialSource.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-start gap-2 bg-sky-50 border border-sky-200 rounded p-2.5 text-[11px] text-sky-900 hover:bg-sky-100 hover:border-sky-300 transition"
                >
                  <ExternalLink className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                  <div className="flex-1">
                    <div className="font-medium">Onde gerar/rotacionar a credencial</div>
                    <div className="text-sky-700">{d.credentialSource.label}</div>
                  </div>
                </a>
              )}
              {d.key === 'inter' && (
                <>
                  <Field label="Client ID (atualizar)">
                    <input
                      value={config.client_id ?? ''}
                      onChange={e => setConfigField('client_id', e.target.value)}
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                      placeholder="•••••• (digite novo pra trocar)"
                    />
                  </Field>
                  <Field label="Client Secret (atualizar)">
                    <input
                      type="password"
                      value={config.client_secret ?? ''}
                      onChange={e => setConfigField('client_secret', e.target.value)}
                      placeholder="•••••••••••••••• (digite novo pra trocar)"
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                    />
                  </Field>
                  <Field label="Webhook secret (atualizar)">
                    <input
                      type="password"
                      value={config.webhook_secret ?? ''}
                      onChange={e => setConfigField('webhook_secret', e.target.value)}
                      placeholder="•••••• (opcional)"
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                    />
                  </Field>
                  <div className="grid grid-cols-2 gap-3">
                    <FileField
                      label="Certificado .crt (substituir)"
                      accept=".crt,.pem,.cer"
                      onFile={setCertFile}
                      selectedFileName={certFile?.name}
                      hint="Inter PJ A1 (32KB max)"
                    />
                    <FileField
                      label="Chave .key (substituir)"
                      accept=".key,.pem"
                      onFile={setKeyFile}
                      selectedFileName={keyFile?.name}
                      hint="Chave privada (32KB max)"
                    />
                  </div>
                </>
              )}
              {d.key === 'c6' && (
                <div className="grid grid-cols-3 gap-3">
                  <Field label="Agência">
                    <input
                      value={config.agencia ?? ''}
                      onChange={e => setConfigField('agencia', e.target.value)}
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono"
                    />
                  </Field>
                  <Field label="Conta">
                    <input
                      value={config.conta ?? ''}
                      onChange={e => setConfigField('conta', e.target.value)}
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono"
                    />
                  </Field>
                  <Field label="Código cliente">
                    <input
                      value={config.codigo_cliente ?? ''}
                      onChange={e => setConfigField('codigo_cliente', e.target.value)}
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono"
                    />
                  </Field>
                </div>
              )}
              {d.key === 'asaas' && (
                <Field label="API Key (atualizar)">
                  <input
                    type="password"
                    value={config.api_key ?? ''}
                    onChange={e => setConfigField('api_key', e.target.value)}
                    placeholder="$aact_••• (digite novo pra trocar)"
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>
              )}
              {d.key === 'bcb_pix' && (
                <>
                  <div className="bg-violet-50 border border-violet-200 rounded p-3 text-[11px] text-violet-900 mb-2 flex gap-2">
                    <Shield className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                    <div><strong>Resolução BCB 380/2024:</strong> exige homologação prévia do CNPJ recebedor + certificado mTLS válido. Sandbox BCB libera o PSP testar antes da homologação production.</div>
                  </div>
                  <Field label="CNPJ recebedor (atualizar)">
                    <input
                      value={config.cnpj_recebedor ?? ''}
                      onChange={e => setConfigField('cnpj_recebedor', e.target.value)}
                      placeholder="apenas números"
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono"
                    />
                  </Field>
                  <div className="grid grid-cols-2 gap-3">
                    <FileField
                      label="Certificado mTLS .crt (substituir)"
                      accept=".crt,.pem,.cer"
                      onFile={setCertFile}
                      selectedFileName={certFile?.name}
                    />
                    <FileField
                      label="Chave mTLS .key (substituir)"
                      accept=".key,.pem"
                      onFile={setKeyFile}
                      selectedFileName={keyFile?.name}
                    />
                  </div>
                  <Field label="Senha do certificado (atualizar)">
                    <input
                      type="password"
                      value={certPassword}
                      onChange={e => setCertPassword(e.target.value)}
                      placeholder="••••••••••"
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono"
                    />
                  </Field>
                </>
              )}
              {d.key === 'pagarme' && (
                <>
                  <Field label="Secret Key (atualizar)">
                    <input
                      type="password"
                      value={config.secret_key ?? ''}
                      onChange={e => setConfigField('secret_key', e.target.value)}
                      placeholder="sk_test_••• ou sk_live_••• (digite novo pra trocar)"
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                    />
                  </Field>
                  <Field label="Webhook Secret (atualizar)">
                    <input
                      type="password"
                      value={config.webhook_secret ?? ''}
                      onChange={e => setConfigField('webhook_secret', e.target.value)}
                      placeholder="whsec_••• (HMAC X-Hub-Signature-256)"
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                    />
                  </Field>
                </>
              )}
            </div>
          )}

          {tab === 'webhook' && (
            <div className="space-y-3">
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">URL pública</div>
              <div className="flex items-center gap-2 bg-stone-50 border border-stone-200 rounded px-2.5 py-2">
                <div className="font-mono text-[11.5px] flex-1 break-all text-stone-700">
                  {window.location.origin}/paymentgateway/webhooks/{d.key.replace('_', '-')}/{`{businessId}`}
                </div>
                <Btn variant="outline" size="xs"><Copy className="h-3 w-3" />Copiar</Btn>
              </div>
              <div className="text-[10.5px] text-stone-500">
                Cole esta URL no painel {d.nome} → Integrações → Webhooks. Idempotência garantida via <span className="font-mono">gateway_webhook_events.external_id</span>.
              </div>

              <div className="pt-2">
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Assinatura HMAC (verificação)</div>
                <div className="flex gap-2">
                  <input type={revealSecret ? 'text' : 'password'} placeholder="whsec_••••••••" className="flex-1 h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono" />
                  <Btn variant="outline" size="xs" onClick={() => setRevealSecret(s => !s)}>{revealSecret ? 'Ocultar' : 'Rotacionar'}</Btn>
                </div>
              </div>

              <div className="pt-3 border-t border-stone-200">
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-2">Eventos recebidos (últimos 50)</div>

                {webhookEventsLoading && (
                  <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[11.5px] text-stone-600 flex items-center gap-2">
                    <RefreshCw className="h-3 w-3 animate-spin" /> Carregando eventos…
                  </div>
                )}

                {webhookEventsError && (
                  <div className="bg-rose-50 border border-rose-200 rounded p-3 text-[11.5px] text-rose-900">
                    Erro ao carregar eventos: {webhookEventsError}
                  </div>
                )}

                {!webhookEventsLoading && !webhookEventsError && webhookEvents && webhookEvents.length === 0 && (
                  <div className="bg-stone-50 border border-stone-200 rounded p-4 text-center text-[11.5px] text-stone-500">
                    Nenhum evento recebido ainda. Quando o {d.nome} enviar webhook, aparecerá aqui.
                  </div>
                )}

                {!webhookEventsLoading && webhookEvents && webhookEvents.length > 0 && (
                  <ol className="space-y-1.5">
                    {webhookEvents.map((e) => (
                      <li key={e.id} className="bg-white border border-stone-200 rounded px-2.5 py-1.5 text-[11.5px]">
                        <div className="flex items-center gap-2">
                          <span className={cn(
                            'inline-flex items-center justify-center w-4 h-4 rounded-full text-white text-[9px] shrink-0',
                            e.processed_at && !e.error_message ? 'bg-success'
                              : e.error_message ? 'bg-destructive'
                              : 'bg-warning',
                          )} title={e.processed_at && !e.error_message ? 'Processado' : e.error_message ? 'Erro' : 'Pendente'}>
                            {e.processed_at && !e.error_message ? <Check className="h-2.5 w-2.5" /> : e.error_message ? <X className="h-2.5 w-2.5" /> : '·'}
                          </span>
                          <span className="font-mono text-stone-900 truncate flex-1">{e.evento ?? '(unknown)'}</span>
                          {!e.signature_valid && (
                            <span className="text-[10px] bg-amber-50 text-amber-700 px-1 rounded shrink-0" title="HMAC signature inválida ou ausente">!HMAC</span>
                          )}
                          {e.cobranca_id && (
                            <span className="text-[10px] text-stone-400 shrink-0 font-mono">cob #{e.cobranca_id}</span>
                          )}
                          <span className="text-[10px] text-stone-400 tabular-nums shrink-0" title={e.when_iso}>{e.when}</span>
                        </div>
                        {e.error_message && (
                          <div className="mt-1 ml-6 text-[10.5px] text-destructive-fg truncate" title={e.error_message}>
                            {e.error_message}
                          </div>
                        )}
                      </li>
                    ))}
                  </ol>
                )}

                <div className="mt-2 text-[10px] text-stone-400">
                  Replay individual em backlog Onda 4e.UI #3 — necessita orquestração de dispatch CobrancaPaga.
                </div>
              </div>
            </div>
          )}

          {tab === 'health' && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <KpiCard label="Último check" value={gateway.last_check ? gateway.last_check.slice(11, 16) : '—'} sub={gateway.last_check ? fmtDate(gateway.last_check.slice(0, 10)) : 'nunca testado'} icon={<RefreshCw className="h-3 w-3" />} />
                <KpiCard label="Latência" value={gateway.latencia ? `${gateway.latencia}ms` : '—'} sub={gateway.latencia && gateway.latencia > 500 ? 'acima do SLA (<500ms)' : 'dentro do SLA'} tone={gateway.latencia && gateway.latencia > 500 ? 'rose' : 'emerald'} icon={<Zap className="h-3 w-3" />} />
                <KpiCard label="Status" value={gateway.health} sub={gateway.warn || 'sem alertas'} tone={gateway.health === 'ok' ? 'emerald' : gateway.health === 'down' ? 'rose' : 'default'} icon={<Shield className="h-3 w-3" />} />
                {/* Onda 4e gap #3 (audit 2026-05-23): cota mensal — limite per banco fica em follow-up */}
                <KpiCard
                  label="Cota mês"
                  value={quotaLoading ? '…' : String(quota?.total ?? 0)}
                  sub={(() => {
                    if (quotaLoading) return 'carregando…';
                    if (!quota || quota.total === 0) return 'sem cobranças no mês';
                    const c = quota.counts;
                    const parts: string[] = [];
                    const boleto = c.boleto ?? 0;
                    const pix = (c.pix_cob ?? 0) + (c.pix_cobv ?? 0) + (c.pix_recv ?? 0);
                    const card = c.card ?? 0;
                    if (boleto) parts.push(`boleto: ${boleto}`);
                    if (pix) parts.push(`pix: ${pix}`);
                    if (card) parts.push(`card: ${card}`);
                    return parts.join(' · ') || '—';
                  })()}
                  tone="emerald"
                  icon={<Zap className="h-3 w-3" />}
                />
              </div>

              <Btn variant="outline" onClick={testar} disabled={testStatus === 'testando'}>
                {testStatus === 'testando' ? <RefreshCw className="h-3 w-3 animate-spin" /> : <Shield className="h-3 w-3" />}
                {testStatus === 'testando' ? 'Testando…' : 'Rodar health check agora'}
              </Btn>
              {testStatus === 'ok' && (
                <div className="bg-success-soft border border-success/20 rounded p-3 text-[11.5px] text-success-fg flex items-center gap-2">
                  <Check className="h-3.5 w-3.5" /><strong>Conexão OK</strong> · driver respondeu corretamente
                </div>
              )}
              {testStatus === 'fail' && (
                <div className="bg-rose-50 border border-rose-200 rounded p-3 text-[11.5px] text-rose-900">
                  Falha no health check — verifique credenciais e tente novamente.
                </div>
              )}
            </div>
          )}

          {tab === 'historico' && (
            <div className="space-y-3">
              <div className="flex items-center gap-2 text-[10.5px] text-stone-500">
                <History className="h-3 w-3" />
                Últimas 50 mudanças — quem editou o quê e quando (auditoria LGPD/PCI sem segredos).
              </div>

              {historyLoading && (
                <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[12px] text-stone-600 flex items-center gap-2">
                  <RefreshCw className="h-3 w-3 animate-spin" /> Carregando histórico…
                </div>
              )}

              {historyError && (
                <div className="bg-rose-50 border border-rose-200 rounded p-3 text-[11.5px] text-rose-900">
                  Erro ao carregar histórico: {historyError}
                </div>
              )}

              {!historyLoading && !historyError && historyEntries && historyEntries.length === 0 && (
                <div className="bg-stone-50 border border-stone-200 rounded p-4 text-center text-[12px] text-stone-500">
                  Sem mudanças registradas ainda. Auditoria começou em 2026-05 (Spatie LogsActivity).
                </div>
              )}

              {!historyLoading && historyEntries && historyEntries.length > 0 && (
                <ol className="space-y-2">
                  {historyEntries.map((e) => (
                    <li key={e.id} className="bg-white border border-stone-200 rounded p-2.5 text-[12px]">
                      <div className="flex items-center gap-2">
                        <span className={cn(
                          'inline-flex items-center justify-center w-5 h-5 rounded-full text-white text-[10px]',
                          e.event === 'created' ? 'bg-emerald-500'
                            : e.event === 'deleted' ? 'bg-rose-500'
                            : 'bg-stone-500',
                        )}>
                          {e.event === 'created' ? <Plus className="h-3 w-3" />
                            : e.event === 'deleted' ? <Minus className="h-3 w-3" />
                            : <RefreshCw className="h-3 w-3" />}
                        </span>
                        <span className="font-medium text-stone-900">{e.who}</span>
                        <span className="text-stone-500">{e.action}</span>
                        <span className="ml-auto text-[10.5px] text-stone-400 tabular-nums" title={e.when_iso}>{e.when}</span>
                      </div>
                      {e.diff && (
                        <div className="mt-1.5 ml-7 text-[11px] text-stone-600 font-mono">
                          <span className="text-stone-400">{e.diff.field}:</span>{' '}
                          <span className="bg-destructive-soft text-destructive-fg px-1 rounded">{e.diff.from === null || e.diff.from === '' ? '∅' : String(e.diff.from)}</span>
                          {' → '}
                          <span className="bg-success-soft text-success-fg px-1 rounded">{e.diff.to === null || e.diff.to === '' ? '∅' : String(e.diff.to)}</span>
                        </div>
                      )}
                    </li>
                  ))}
                </ol>
              )}
            </div>
          )}
        </div>

        <div className="border-t border-stone-200 p-3 bg-stone-50/60">
          {confirmDelete ? (
            <div className="flex items-center gap-2">
              <div className="text-[11.5px] text-destructive flex-1">
                <strong>Excluir credencial #{gateway.id}?</strong> Apaga arquivos cert/key também. Cobranças vinculadas mantêm histórico (FK vira NULL).
              </div>
              <Btn variant="outline" onClick={() => setConfirmDelete(false)} disabled={deleting}>Não</Btn>
              <button
                onClick={handleDelete}
                disabled={deleting}
                className="inline-flex items-center gap-1.5 h-8 px-3 rounded text-[12px] font-medium bg-destructive text-destructive-foreground hover:bg-destructive/90 disabled:opacity-60"
              >
                <Trash2 className="h-3 w-3" />{deleting ? 'Excluindo…' : 'Sim, excluir'}
              </button>
            </div>
          ) : (
            <div className="flex items-center gap-2">
              {saveError ? (
                <div className="text-[11px] text-destructive flex-1 truncate">{saveError}</div>
              ) : (
                <div className="text-[11px] text-stone-500 flex-1">Apenas campos preenchidos são atualizados.</div>
              )}
              <button
                onClick={() => setConfirmDelete(true)}
                disabled={saving || deleting}
                className="inline-flex items-center gap-1.5 h-8 px-2.5 rounded text-[11.5px] text-destructive hover:bg-destructive-soft disabled:opacity-50"
                title="Excluir credencial"
              >
                <Trash2 className="h-3 w-3" />Excluir
              </button>
              <Btn variant="outline" onClick={onClose} disabled={saving}>Cancelar</Btn>
              <Btn variant="primary" onClick={handleSave} disabled={saving}>
                {saving ? 'Salvando…' : 'Salvar'}
              </Btn>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
