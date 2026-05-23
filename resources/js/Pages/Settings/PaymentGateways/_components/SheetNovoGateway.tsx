// SheetNovoGateway.tsx — wizard 3 steps (Driver → Credenciais → Vínculo)
// Onda 5 (2026-05-19): wiring backend POST /settings/payment-gateways
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import {
  X, ChevronRight, ChevronLeft, Plus, Check, Shield,
} from 'lucide-react';
import { Btn } from '../../../Financeiro/Cobranca/_components/atoms';
import { DriverChip, FileField, Field } from './atoms-settings';
import { DRIVERS, TIPOS, cn, type GatewayKey } from '../_lib/gateway-shared';
import type { Account } from '../_lib/gateway-shared';

interface Props {
  accounts: Account[];
  onClose: () => void;
}

const STEPS = ['Driver', 'Credenciais', 'Vínculo'];

export default function SheetNovoGateway({ accounts, onClose }: Props) {
  const [step, setStep] = useState(1);
  const [driver, setDriver] = useState<GatewayKey | null>(null);

  // Onda 5: state controlado dos campos
  const [apelido, setApelido] = useState('');
  const [config, setConfig] = useState<Record<string, string>>({});
  const [contaId, setContaId] = useState<string>('');
  const [ambiente, setAmbiente] = useState<'production' | 'sandbox'>('sandbox');
  const [ativo, setAtivo] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Onda 5+: upload cert/key + senha
  const [certFile, setCertFile] = useState<File | null>(null);
  const [keyFile, setKeyFile] = useState<File | null>(null);
  const [certPassword, setCertPassword] = useState('');

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const d = driver ? DRIVERS[driver] : null;
  const canNext = step === 1 ? !!driver : true;
  const isLast = step === STEPS.length;

  function setConfigField(key: string, value: string): void {
    setConfig(prev => ({ ...prev, [key]: value }));
  }

  function handleSubmit(): void {
    if (!driver) return;
    setError(null);
    setSubmitting(true);

    const hasFiles = certFile !== null || keyFile !== null;
    const fd = new FormData();
    fd.append('gateway_key', driver);
    fd.append('ambiente', ambiente);
    if (apelido) fd.append('nome_display', apelido);
    if (contaId) fd.append('conta_bancaria_id', contaId);
    fd.append('ativo', ativo ? '1' : '0');
    Object.entries(config).forEach(([k, v]) => {
      if (v) fd.append(`config_json[${k}]`, v);
    });
    if (certFile) fd.append('cert_file', certFile);
    if (keyFile) fd.append('key_file', keyFile);
    if (certPassword) fd.append('cert_password', certPassword);

    router.post('/settings/payment-gateways', fd, {
      forceFormData: hasFiles,
      preserveScroll: true,
      onSuccess: () => {
        setSubmitting(false);
        onClose();
      },
      onError: (errors) => {
        setSubmitting(false);
        const firstError = Object.values(errors)[0];
        setError(typeof firstError === 'string' ? firstError : 'Erro ao criar credencial');
      },
    });
  }

  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose} role="dialog" aria-modal="true" aria-label="Novo gateway">
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[640px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>

        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Novo gateway</div>
            <div className="text-[15px] font-semibold mt-0.5">passo {step} de {STEPS.length}</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500" aria-label="Fechar (Esc)">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>

        <div className="px-5 py-3 border-b border-stone-200 bg-stone-50/40 flex items-center gap-2 text-[11px]">
          {STEPS.map((s, i) => (
            <div key={i} className="flex items-center gap-1.5">
              <div className={cn(
                'flex items-center gap-1.5',
                step === i + 1 ? 'text-stone-900 font-semibold' : step > i + 1 ? 'text-emerald-700' : 'text-stone-400',
              )}>
                <span className={cn(
                  'w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold',
                  step === i + 1 ? 'bg-stone-900 text-white' :
                  step > i + 1 ? 'bg-emerald-100 text-emerald-700' :
                  'bg-stone-200 text-stone-500',
                )}>
                  {step > i + 1 ? <Check className="h-2.5 w-2.5" /> : i + 1}
                </span>
                {s}
              </div>
              {i < STEPS.length - 1 && <ChevronRight className="h-3 w-3 text-stone-300" />}
            </div>
          ))}
        </div>

        <div className="flex-1 overflow-auto p-5">
          {step === 1 && (
            <div className="space-y-3">
              <div className="text-[11px] text-stone-500">Escolha o driver. Cada um suporta tipos diferentes de cobrança.</div>
              <div className="space-y-2">
                {Object.values(DRIVERS).map(opt => (
                  <button key={opt.key} onClick={() => setDriver(opt.key)} className={cn(
                    'w-full text-left rounded-md border p-3 transition flex items-start gap-3',
                    driver === opt.key ? 'border-stone-900 ring-2 ring-stone-900/10 bg-stone-50' : 'border-stone-200 hover:border-stone-400 hover:bg-stone-50',
                    opt.deprecated && 'opacity-70',
                  )}>
                    <span className={cn('w-9 h-9 rounded-md grid place-items-center text-white text-[13px] font-bold shrink-0', opt.dot)}>{opt.sigla}</span>
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <div className="text-[13px] font-semibold">{opt.nome}</div>
                        {opt.deprecated && <span className="text-[9px] uppercase tracking-widest font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">deprecated</span>}
                      </div>
                      <div className="flex flex-wrap gap-1 mt-1.5">
                        {opt.tipos.map(t => {
                          const tp = TIPOS[t];
                          return <span key={t} className={cn('text-[10px] font-medium px-1.5 py-0.5 rounded', tp?.bg, tp?.fg)}>{tp?.short}</span>;
                        })}
                        <span className="text-[10px] text-stone-400 ml-1">· {opt.ambientes.join(' / ')}</span>
                      </div>
                      <div className="text-[10.5px] text-stone-500 mt-1.5">{opt.cred}</div>
                    </div>
                    {opt.key === 'bcb_pix' && <span className="text-[9px] uppercase tracking-widest font-bold text-violet-700 self-start">novo</span>}
                  </button>
                ))}
              </div>
            </div>
          )}

          {step === 2 && d && (
            <div className="space-y-3">
              <Field label="Apelido">
                <input
                  value={apelido}
                  onChange={e => setApelido(e.target.value)}
                  placeholder={`ex: ${d.nome} · Operacional`}
                  className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]"
                />
              </Field>
              <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[11px] text-stone-700 mb-3">
                <strong>{d.nome}:</strong> {d.cred}
              </div>
              {d.key === 'inter' && <>
                <Field label="Client ID">
                  <input
                    value={config.client_id ?? ''}
                    onChange={e => setConfigField('client_id', e.target.value)}
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>
                <Field label="Client Secret">
                  <input
                    type="password"
                    value={config.client_secret ?? ''}
                    onChange={e => setConfigField('client_secret', e.target.value)}
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>
                <Field label="Webhook secret (opcional)">
                  <input
                    type="password"
                    value={config.webhook_secret ?? ''}
                    onChange={e => setConfigField('webhook_secret', e.target.value)}
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>
                <div className="grid grid-cols-2 gap-3">
                  <FileField
                    label="Certificado .crt"
                    accept=".crt,.pem,.cer"
                    onFile={setCertFile}
                    selectedFileName={certFile?.name}
                    hint="Inter PJ A1 (32KB max)"
                  />
                  <FileField
                    label="Chave .key"
                    accept=".key,.pem"
                    onFile={setKeyFile}
                    selectedFileName={keyFile?.name}
                    hint="Chave privada (32KB max)"
                  />
                </div>
                <div className="bg-stone-50 border border-stone-200 rounded p-2.5 text-[10.5px] text-stone-700">
                  Sandbox aceita sem mTLS. Production exige cert + key ICP-Brasil Inter PJ.
                  Arquivos salvos em <span className="font-mono">storage/app/private/payment-gateway/{'{biz}/{cred_id}'}/</span> com chmod 0600.
                </div>
              </>}
              {d.key === 'asaas' && <>
                <Field label="API Key">
                  <input
                    type="password"
                    value={config.api_key ?? ''}
                    onChange={e => setConfigField('api_key', e.target.value)}
                    placeholder="$aact_..."
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>
                <Field label="Webhook secret">
                  <input
                    type="password"
                    value={config.webhook_secret ?? ''}
                    onChange={e => setConfigField('webhook_secret', e.target.value)}
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>
              </>}
              {d.key === 'c6' && <div className="grid grid-cols-3 gap-3">
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
              </div>}
              {d.key === 'bcb_pix' && <>
                <Field label="CNPJ recebedor">
                  <input
                    value={config.cnpj_recebedor ?? ''}
                    onChange={e => setConfigField('cnpj_recebedor', e.target.value)}
                    placeholder="apenas números"
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono"
                  />
                </Field>
                <div className="grid grid-cols-2 gap-3">
                  <FileField
                    label="Cert mTLS ICP-Brasil .crt"
                    accept=".crt,.pem,.cer"
                    onFile={setCertFile}
                    selectedFileName={certFile?.name}
                  />
                  <FileField
                    label="Chave .key"
                    accept=".key,.pem"
                    onFile={setKeyFile}
                    selectedFileName={keyFile?.name}
                  />
                </div>
                <Field label="Senha do certificado (opcional)">
                  <input
                    type="password"
                    value={certPassword}
                    onChange={e => setCertPassword(e.target.value)}
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono"
                  />
                </Field>
                <div className="bg-stone-50 border border-stone-200 rounded p-2.5 text-[10.5px] text-stone-700">
                  Cert ICP-Brasil ⇄ CNPJ recebedor homologado no BCB. Resolução BCB 380/2024.
                </div>
              </>}
              {d.key === 'pesapal' && <>
                <Field label="Consumer Key">
                  <input
                    type="password"
                    value={config.consumer_key ?? ''}
                    onChange={e => setConfigField('consumer_key', e.target.value)}
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>
                <Field label="Consumer Secret">
                  <input
                    type="password"
                    value={config.consumer_secret ?? ''}
                    onChange={e => setConfigField('consumer_secret', e.target.value)}
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>
              </>}
              {d.key === 'pagarme' && <>
                <Field label="Secret Key">
                  <input
                    type="password"
                    value={config.secret_key ?? ''}
                    onChange={e => setConfigField('secret_key', e.target.value)}
                    placeholder="sk_test_... ou sk_live_..."
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>
                <Field label="Webhook Secret">
                  <input
                    type="password"
                    value={config.webhook_secret ?? ''}
                    onChange={e => setConfigField('webhook_secret', e.target.value)}
                    placeholder="whsec_••• (validação HMAC X-Hub-Signature-256)"
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>
                <div className="bg-stone-50 border border-stone-200 rounded p-2.5 text-[10.5px] text-stone-700">
                  Sandbox via prefixo <span className="font-mono">sk_test_</span> · Production via <span className="font-mono">sk_live_</span>. Webhook secret é configurado no dashboard Pagar.me em Integrações → Webhooks.
                </div>
              </>}

              <div className="pt-2 border-t border-stone-200 mt-3 flex items-center gap-2">
                <Btn variant="outline" disabled><Shield className="h-3 w-3" />Testar conexão</Btn>
                <span className="text-[10.5px] text-stone-500">teste de conexão chega em onda futura — salve e use health check no card</span>
              </div>
            </div>
          )}

          {step === 3 && d && (
            <div className="space-y-3">
              <Field label="Conta destino (FK accounts)">
                <select
                  value={contaId}
                  onChange={e => setContaId(e.target.value)}
                  className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]"
                >
                  <option value="">— sem vínculo —</option>
                  {accounts.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                </select>
              </Field>
              <Field label="Ambiente inicial">
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
              <label className="flex items-center gap-3 py-1.5">
                <input
                  type="checkbox"
                  checked={ativo}
                  onChange={e => setAtivo(e.target.checked)}
                  className="accent-stone-900 w-4 h-4"
                />
                <div>
                  <div className="text-[12.5px] text-stone-800">Ativar imediatamente</div>
                  <div className="text-[10.5px] text-stone-500">se desligado, gateway fica cadastrado mas não emite cobrança</div>
                </div>
              </label>
              <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[11px] text-stone-700">
                Ao confirmar, será criada uma linha em <span className="font-mono">payment_gateway_credentials</span> com <span className="font-mono">business_id</span> do business logado.
                Webhook URL será gerada automaticamente — cole no painel do {d.nome} após criação.
              </div>
              {error && (
                <div className="bg-rose-50 border border-rose-200 rounded p-2.5 text-[11px] text-rose-800">
                  {error}
                </div>
              )}
            </div>
          )}
        </div>

        <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-stone-50/60">
          {step > 1 && <Btn variant="outline" onClick={() => setStep(s => s - 1)}><ChevronLeft className="h-3 w-3" />Voltar</Btn>}
          <div className="flex-1" />
          <Btn variant="outline" onClick={onClose}>Cancelar</Btn>
          {!isLast && (
            <Btn variant="primary" onClick={() => canNext && setStep(s => s + 1)} disabled={!canNext}>
              Avançar<ChevronRight className="h-3 w-3" />
            </Btn>
          )}
          {isLast && (
            <Btn variant="primary" onClick={handleSubmit} disabled={submitting}>
              <Plus className="h-3 w-3" />{submitting ? 'Criando…' : 'Criar gateway'}
            </Btn>
          )}
        </div>
      </div>
    </div>
  );
}
