// DrawerGateway.tsx — drawer 4 tabs (Identificação · Credenciais · Webhook · Health)
// Onda 5+ (2026-05-19): wiring backend PUT /settings/payment-gateways/{id}
import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import {
  X, Copy, Shield, RefreshCw, Zap, Check, Trash2,
} from 'lucide-react';
import { Btn, KpiCard } from '../../../Financeiro/Cobranca/_components/atoms';
import {
  DriverChip, HealthBadge, Toggle, FileField, Field,
} from './atoms-settings';
import {
  DRIVERS, cn, fmtDate, type GatewayKey,
} from '../_lib/gateway-shared';
import type { SettingsGateway, Account } from '../_lib/gateway-shared';

type Tab = 'identificacao' | 'credenciais' | 'webhook' | 'health';

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
                <select
                  value={contaId}
                  onChange={e => setContaId(e.target.value)}
                  className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]"
                >
                  <option value="">— sem vínculo —</option>
                  {accounts.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                </select>
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
              {d.key === 'pesapal' && (
                <div className="bg-amber-50 border border-amber-200 rounded p-3 text-[11.5px] text-amber-900">
                  <div className="font-medium mb-1">Driver deprecated</div>
                  <p>PesaPal foi UltimatePOS legacy pra cartão internacional. Hoje recomenda-se <strong>Asaas</strong> (BR nativo + 3DS + PIX). Migração: criar Asaas → desativar PesaPal → backfill subscriptions ativas.</p>
                  <Btn variant="outline" size="xs" className="mt-2 !border-amber-300 !text-amber-800">Iniciar migração</Btn>
                </div>
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

              <div className="pt-2 text-[10.5px] text-stone-500">Estatísticas de eventos recebidos em backlog (Onda 5).</div>
            </div>
          )}

          {tab === 'health' && (
            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-3">
                <KpiCard label="Último check" value={gateway.last_check ? gateway.last_check.slice(11, 16) : '—'} sub={gateway.last_check ? fmtDate(gateway.last_check.slice(0, 10)) : 'nunca testado'} icon={<RefreshCw className="h-3 w-3" />} />
                <KpiCard label="Latência" value={gateway.latencia ? `${gateway.latencia}ms` : '—'} sub={gateway.latencia && gateway.latencia > 500 ? 'acima do SLA (<500ms)' : 'dentro do SLA'} tone={gateway.latencia && gateway.latencia > 500 ? 'rose' : 'emerald'} icon={<Zap className="h-3 w-3" />} />
                <KpiCard label="Status" value={gateway.health} sub={gateway.warn || 'sem alertas'} tone={gateway.health === 'ok' ? 'emerald' : gateway.health === 'down' ? 'rose' : 'default'} icon={<Shield className="h-3 w-3" />} />
              </div>

              <Btn variant="outline" onClick={testar} disabled={testStatus === 'testando'}>
                {testStatus === 'testando' ? <RefreshCw className="h-3 w-3 animate-spin" /> : <Shield className="h-3 w-3" />}
                {testStatus === 'testando' ? 'Testando…' : 'Rodar health check agora'}
              </Btn>
              {testStatus === 'ok' && (
                <div className="bg-emerald-50 border border-emerald-200 rounded p-3 text-[11.5px] text-emerald-900 flex items-center gap-2">
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
        </div>

        <div className="border-t border-stone-200 p-3 bg-stone-50/60">
          {confirmDelete ? (
            <div className="flex items-center gap-2">
              <div className="text-[11.5px] text-rose-700 flex-1">
                <strong>Excluir credencial #{gateway.id}?</strong> Apaga arquivos cert/key também. Cobranças vinculadas mantêm histórico (FK vira NULL).
              </div>
              <Btn variant="outline" onClick={() => setConfirmDelete(false)} disabled={deleting}>Não</Btn>
              <button
                onClick={handleDelete}
                disabled={deleting}
                className="inline-flex items-center gap-1.5 h-8 px-3 rounded text-[12px] font-medium bg-rose-600 text-white hover:bg-rose-700 disabled:opacity-60"
              >
                <Trash2 className="h-3 w-3" />{deleting ? 'Excluindo…' : 'Sim, excluir'}
              </button>
            </div>
          ) : (
            <div className="flex items-center gap-2">
              {saveError ? (
                <div className="text-[11px] text-rose-700 flex-1 truncate">{saveError}</div>
              ) : (
                <div className="text-[11px] text-stone-500 flex-1">Apenas campos preenchidos são atualizados.</div>
              )}
              <button
                onClick={() => setConfirmDelete(true)}
                disabled={saving || deleting}
                className="inline-flex items-center gap-1.5 h-8 px-2.5 rounded text-[11.5px] text-rose-600 hover:bg-rose-50 disabled:opacity-50"
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
