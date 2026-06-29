// SheetNovoGateway.tsx — wizard 3 steps (Driver → Credenciais → Vínculo)
// Onda 5 (2026-05-19): wiring backend POST /settings/payment-gateways
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import {
  X, ChevronRight, ChevronLeft, Plus, Check, Shield, ExternalLink,
} from 'lucide-react';
import { Btn } from '../../../Financeiro/Cobranca/_components/atoms';
import { DriverChip, FileField, Field } from './atoms-settings';
import { DRIVERS, TIPOS, cn, type GatewayKey } from '../_lib/gateway-shared';
import type { Account } from '../_lib/gateway-shared';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Checkbox } from '@/Components/ui/checkbox';

interface NfeCertificadoAtivo {
  cnpjTitular: string;
  validoAteBr: string;
  diasRestantes: number;
  vencido: boolean;
  proximoVencer: boolean;
}

interface Props {
  accounts: Account[];
  nfeCertificadoAtivo?: NfeCertificadoAtivo | null;
  onClose: () => void;
}

const STEPS = ['Driver', 'Credenciais', 'Vínculo'];

export default function SheetNovoGateway({ accounts, nfeCertificadoAtivo, onClose }: Props) {
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

  // Onda 5+: upload cert/key + senha (Inter PJ · BCB PIX)
  const [certFile, setCertFile] = useState<File | null>(null);
  const [keyFile, setKeyFile] = useState<File | null>(null);
  const [certPassword, setCertPassword] = useState('');

  // US-FIN-046 (2026-05-27): state pfxFile/pfxPassword REMOVIDO.
  // Sicoob API reusa NfeCertificado canon via /fiscal/configuracao/certificado.

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
    // US-FIN-046 (2026-05-27): upload pfx_file/pfx_password REMOVIDOS.
    // Sicoob API reusa NfeCertificado canon (single source of truth).

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
                step === i + 1 ? 'text-stone-900 font-semibold' : step > i + 1 ? 'text-success' : 'text-stone-400',
              )}>
                <span className={cn(
                  'w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold',
                  step === i + 1 ? 'bg-stone-900 text-white' :
                  step > i + 1 ? 'bg-success-soft text-success-fg' :
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
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <div className="text-[13px] font-semibold">{opt.nome}</div>
                        {opt.deprecated && <span className="text-[9px] uppercase tracking-widest font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">deprecated</span>}
                        {opt.key === 'bcb_pix' && <span className="text-[9px] uppercase tracking-widest font-bold text-violet-700">novo</span>}
                        {opt.key === 'pagarme' && <span className="text-[9px] uppercase tracking-widest font-bold text-violet-700">Onda 4e</span>}
                      </div>

                      <div className="flex flex-wrap gap-1 mt-1.5">
                        {opt.tipos.map(t => {
                          const tp = TIPOS[t];
                          return <span key={t} className={cn('text-[10px] font-medium px-1.5 py-0.5 rounded', tp?.bg, tp?.fg)}>{tp?.short}</span>;
                        })}
                        <span className="text-[10px] text-stone-400 ml-1">· {opt.ambientes.join(' / ')}</span>
                      </div>

                      {/* Onda 4e.UI #3: comparativo drivers (taxa/settlement/requirements) */}
                      {opt.pricing && !opt.deprecated && (
                        <div className="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-0.5 text-[10.5px]">
                          {opt.pricing.boleto && (
                            <div className="flex gap-1"><span className="text-stone-400 shrink-0">Boleto:</span><span className="text-stone-700 truncate" title={opt.pricing.boleto}>{opt.pricing.boleto}</span></div>
                          )}
                          {opt.pricing.pix && (
                            <div className="flex gap-1"><span className="text-stone-400 shrink-0">PIX:</span><span className="text-stone-700 truncate" title={opt.pricing.pix}>{opt.pricing.pix}</span></div>
                          )}
                          {opt.pricing.card && (
                            <div className="flex gap-1"><span className="text-stone-400 shrink-0">Cartão:</span><span className="text-stone-700 truncate" title={opt.pricing.card}>{opt.pricing.card}</span></div>
                          )}
                          {opt.pricing.settlement && (
                            <div className="flex gap-1"><span className="text-stone-400 shrink-0">Liquidação:</span><span className="text-stone-700 truncate" title={opt.pricing.settlement}>{opt.pricing.settlement}</span></div>
                          )}
                        </div>
                      )}

                      {opt.recommendedFor && !opt.deprecated && (
                        <div className="mt-1.5 text-[10px] text-stone-500 italic">
                          ↳ {opt.recommendedFor}
                        </div>
                      )}

                      <div className="text-[10px] text-stone-400 mt-1.5 font-mono truncate" title={opt.cred}>{opt.cred}</div>
                    </div>
                  </button>
                ))}

                <div className="text-[10px] text-stone-400 italic pt-1">
                  Valores REFERENCE 2026 (sites oficiais + docs públicas) — negociáveis com PSP em volume alto.
                </div>

                {/* Onda 4e.UI #6 (estado-da-arte 2026-05-23) — pointer pros 21 bancos eduardokum/CNAB.
                    Backend já funciona via Modules/Financeiro/Strategies/CnabDirectStrategy.
                    Cadastro vive em /financeiro/contas-bancarias (fluxo legado UPOS validado). */}
                <a
                  href="/financeiro/contas-bancarias"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-full text-left rounded-md border border-dashed border-stone-300 p-3 transition flex items-start gap-3 hover:border-stone-500 hover:bg-stone-50"
                >
                  <span className="w-9 h-9 rounded-md grid place-items-center text-stone-700 text-[12px] font-bold shrink-0 bg-stone-100 border border-stone-300">BC</span>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <div className="text-[13px] font-semibold">Boleto bancário tradicional (CNAB)</div>
                      <span className="text-[9px] uppercase tracking-widest font-bold px-1.5 py-0.5 rounded bg-stone-100 text-stone-600">21 bancos</span>
                    </div>
                    <div className="text-[10.5px] text-stone-600 mt-1.5 leading-snug">
                      BB · Bradesco · Itaú · Sicredi · Sicoob · Cresol · Ailos · Caixa · Santander · Banrisul · BNB · BTG · Fibra · HSBC · Delbank · Rendimento · Pine · Ourinvest · Unicred · Inter · C6
                    </div>

                    {/* Onda 4e.UI #7 — destaque bancos com API moderna disponível (futuros drivers nativos PaymentGateway Onda 4f).
                        Tier S de maturidade Open Finance + Pix Cobrança REST, dados de WebSearch oficial 2026-05-23. */}
                    <div className="mt-2 bg-warning-soft border border-warning/20 rounded px-2 py-1.5 text-[10px] text-warning-fg leading-snug">
                      <span className="font-medium">Bancos com API moderna disponível</span> (futuros drivers nativos):
                      <div className="font-semibold mt-0.5">🔥 Bradesco · Itaú · BB · Sicredi · Sicoob · Santander · Caixa · BTG</div>
                      <div className="text-[9.5px] text-warning-fg mt-0.5 italic">(hoje via CNAB — driver nativo + webhook em backlog Onda 4f)</div>
                    </div>

                    <div className="text-[10px] text-stone-500 mt-1.5">
                      Cadastrar via <span className="font-mono">/financeiro/contas-bancarias</span> (CNAB 240/400 · sem webhook real-time · settlement T+1 mín · upload/download arquivo)
                    </div>
                  </div>
                  <ExternalLink className="h-3.5 w-3.5 text-stone-400 self-start mt-1" />
                </a>
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

              {/* Onda 4e.UI #5 — deep-link pro painel do PSP onde gerar credencial */}
              {d.credentialSource && (
                <a
                  href={d.credentialSource.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-start gap-2 bg-sky-50 border border-sky-200 rounded p-2.5 text-[11px] text-sky-900 hover:bg-sky-100 hover:border-sky-300 transition mb-3"
                >
                  <ExternalLink className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                  <div className="flex-1">
                    <div className="font-medium">Onde gerar a credencial</div>
                    <div className="text-sky-700">{d.credentialSource.label}</div>
                  </div>
                </a>
              )}
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
              {d.key === 'sicoob_api' && <>
                <Field label="Client ID">
                  <input
                    value={config.client_id ?? ''}
                    onChange={e => setConfigField('client_id', e.target.value)}
                    placeholder="9b5e0aac-..."
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
                <div className="grid grid-cols-3 gap-3">
                  <Field label="Convênio (numero_cliente)">
                    <input
                      value={config.numero_cliente ?? ''}
                      onChange={e => setConfigField('numero_cliente', e.target.value)}
                      placeholder="código cedente"
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono"
                    />
                  </Field>
                  <Field label="Carteira">
                    <Select
                      value={config.codigo_modalidade ?? '1'}
                      onValueChange={v => setConfigField('codigo_modalidade', v)}
                    >
                      <SelectTrigger variant="shadcn" size="sm" aria-label="Carteira" className="w-full text-[12.5px]">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="1">1 — Simples</SelectItem>
                        <SelectItem value="3">3 — Caucionada</SelectItem>
                      </SelectContent>
                    </Select>
                  </Field>
                  <Field label="Conta corrente">
                    <input
                      value={config.numero_conta ?? ''}
                      onChange={e => setConfigField('numero_conta', e.target.value)}
                      className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono"
                    />
                  </Field>
                </div>
                <Field label="Webhook secret">
                  <input
                    type="password"
                    value={config.webhook_secret ?? ''}
                    onChange={e => setConfigField('webhook_secret', e.target.value)}
                    placeholder="HMAC-SHA256 raw body (header x-sicoob-signature)"
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[11.5px] font-mono"
                  />
                </Field>

                {/* US-FIN-046 (2026-05-27) — Sicoob reusa NfeCertificado A1 (single source).
                    Mostra status do cert ativo OU pede pra cadastrar em /fiscal. */}
                {nfeCertificadoAtivo ? (
                  <div className={cn(
                    'border rounded p-2.5 text-[10.5px]',
                    nfeCertificadoAtivo.vencido
                      ? 'bg-rose-50 border-rose-200 text-rose-900'
                      : nfeCertificadoAtivo.proximoVencer
                        ? 'bg-amber-50 border-amber-200 text-amber-900'
                        : 'bg-emerald-50 border-emerald-200 text-emerald-900',
                  )}>
                    <div className="font-semibold mb-0.5">
                      {nfeCertificadoAtivo.vencido
                        ? '⚠️ Cert A1 VENCIDO em ' + nfeCertificadoAtivo.validoAteBr
                        : nfeCertificadoAtivo.proximoVencer
                          ? `⚠️ Cert A1 vence em ${nfeCertificadoAtivo.diasRestantes}d (${nfeCertificadoAtivo.validoAteBr})`
                          : '✅ Certificado A1 ICP-Brasil ativo'}
                    </div>
                    CNPJ titular <span className="font-mono">{nfeCertificadoAtivo.cnpjTitular}</span> · válido até{' '}
                    <span className="font-mono">{nfeCertificadoAtivo.validoAteBr}</span> ({nfeCertificadoAtivo.diasRestantes}d).
                    Sicoob API reusa este certificado — mesmo usado pra NFe SEFAZ.
                    {(nfeCertificadoAtivo.vencido || nfeCertificadoAtivo.proximoVencer) && (
                      <>
                        {' · '}
                        <a href="/fiscal/configuracao/certificado" target="_blank" rel="noopener noreferrer" className="underline">
                          Renovar em Fiscal
                        </a>
                      </>
                    )}
                  </div>
                ) : (
                  <div className="bg-warning-soft border border-warning/20 rounded p-2.5 text-[10.5px] text-warning-fg">
                    <div className="font-semibold mb-0.5">⚠️ Cadastre o certificado A1 da empresa em Fiscal</div>
                    Sicoob API exige cert ICP-Brasil A1 do CNPJ — mesmo que NFe SEFAZ usa.{' '}
                    <a href="/fiscal/configuracao/certificado" target="_blank" rel="noopener noreferrer" className="underline font-medium">
                      Ir pra /fiscal/configuracao/certificado
                    </a>
                    . Depois volta aqui pra finalizar o cadastro Sicoob.
                  </div>
                )}
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
                <Select
                  value={contaId || '__none__'}
                  onValueChange={v => setContaId(v === '__none__' ? '' : v)}
                >
                  <SelectTrigger variant="shadcn" size="sm" aria-label="Conta destino (FK accounts)" className="w-full text-[12.5px]">
                    <SelectValue placeholder="— sem vínculo —" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none__">— sem vínculo —</SelectItem>
                    {accounts.map(a => <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>)}
                  </SelectContent>
                </Select>
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
              <div className="flex items-center gap-3 py-1.5">
                <Checkbox
                  id="gw-ativo"
                  checked={ativo}
                  onCheckedChange={v => setAtivo(v === true)}
                />
                <label htmlFor="gw-ativo" className="cursor-pointer">
                  <div className="text-[12.5px] text-stone-800">Ativar imediatamente</div>
                  <div className="text-[10.5px] text-stone-500">se desligado, gateway fica cadastrado mas não emite cobrança</div>
                </label>
              </div>
              <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[11px] text-stone-700">
                Ao confirmar, será criada uma linha em <span className="font-mono">payment_gateway_credentials</span> com <span className="font-mono">business_id</span> do business logado.
                Webhook URL será gerada automaticamente — cole no painel do {d.nome} após criação.
              </div>
              {error && (
                <div className="bg-destructive-soft border border-destructive/20 rounded p-2.5 text-[11px] text-destructive-fg">
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
