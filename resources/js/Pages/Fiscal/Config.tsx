// @memcofre
//   tela: /fiscal/config
//   module: Fiscal
//   stories: US-FISCAL-009 (Cert/Cfg sub-página 6 do design KB-9.75)
//   adrs: 0093, 0094, 0101, 0104
//
// Tela UNIFICADA — cert + ambiente + testar + upload no mesmo lugar.
// Forms apontam pros endpoints existentes /nfe-brasil/configuracao/certificado/*
// (NfeBrasil CertificadoController) — zero duplicação de lógica backend.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, useForm, usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import { Archive, CheckCircle2, FileText, KeyRound, Loader2, Lock, PlugZap, Settings, Shield, Upload, XCircle } from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';
import { toast } from 'sonner';

import FxShell from './_components/FxShell';
import FiscalModuleTopNav from './_components/FiscalModuleTopNav';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';

import '../../../css/fiscal-cockpit.css';

type ConfigTab = 'cert' | 'series' | 'ambiente' | 'sped';

export interface SerieFiscal {
  modelo: 55 | 65;
  serie: string;
  proximo: number;
  filial: string;
  ativo: boolean;
  obs?: string | null;
}

interface Certificado {
  uuid: string;
  cnpjTitular: string | null;
  cnpjTitularFallback: string | null;
  validoAteIso: string | null;
  validoAteBr: string | null;
  diasRestantes: number | null;
  alerta: 'ok' | 'proximo_vencimento' | 'vencido' | null;
  ativo: boolean;
}

interface Config {
  regime: string;
  autoEmissionEnabled: boolean;
  tributacaoDefault: Record<string, unknown>;
}

interface Painel {
  cnpjBusiness: string | null;
  razaoSocial: string | null;
  regime: string | null;
  ncmPadrao: string | null;
  serieNfe: string;
  ultimoNumero: number;
  proximoNumero: number;
  cfopDefault: string | null;
  csosnDefault: string | null;
  cstDefault: string | null;
  uf: string | null;
  cidade: string | null;
  ambiente: 1 | 2;
}

interface ConfigProps {
  certificado: Certificado | null;
  config: Config | null;
  painel: Painel;
  // Onda 2 I — séries fiscais (modelo 55 NF-e + 65 NFC-e)
  seriesMock?: SerieFiscal[];
}

interface FlashProps extends PageProps {
  flash?: { success?: string };
}

type SefazTesteResultado = {
  ok: boolean;
  cstat: string;
  xMotivo: string;
  tempoResposta: number;
  ambiente: number;
  uf: string;
  versao?: string | null;
  error?: string;
};

const REGIME_LABEL: Record<string, string> = {
  simples_nacional: 'Simples Nacional',
  simples: 'Simples Nacional',
  lucro_presumido: 'Lucro Presumido',
  lucro_real: 'Lucro Real',
  mei: 'MEI',
};

function formatCnpj(raw: string | null): string {
  if (!raw) return '—';
  const digits = raw.replace(/\D/g, '').padStart(14, '0').slice(-14);
  return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
}

export default function Config({ certificado, config, painel, seriesMock = [] }: ConfigProps) {
  const [tab, setTab] = useState<ConfigTab>('cert');

  const { props: pageProps } = usePage<FlashProps>();
  const flashSuccess = pageProps.flash?.success;

  const certTone: 'ok' | 'warn' | 'bad' = certificado?.diasRestantes == null
    ? 'bad'
    : certificado.diasRestantes <= 7
      ? 'bad'
      : certificado.diasRestantes <= 60
        ? 'warn'
        : 'ok';

  // Upload form (Inertia) → POST /nfe-brasil/configuracao/certificado
  const fileRef = useRef<HTMLInputElement>(null);
  const uploadForm = useForm<{ certificado: File | null; senha: string }>({
    certificado: null,
    senha: '',
  });
  const submitUpload = (e: FormEvent) => {
    e.preventDefault();
    uploadForm.post('/nfe-brasil/configuracao/certificado', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        if (fileRef.current) fileRef.current.value = '';
        toast.success('Certificado A1 cadastrado.');
      },
      onError: () => toast.error('Verifique o arquivo e a senha.'),
      onFinish: () => uploadForm.reset('certificado', 'senha'),
    });
  };

  // Ambiente form (Inertia) → POST /nfe-brasil/configuracao/certificado/ambiente
  const ambienteForm = useForm<{ ambiente: 1 | 2 }>({ ambiente: painel.ambiente });
  const submitAmbiente = (e: FormEvent) => {
    e.preventDefault();
    if (ambienteForm.data.ambiente === painel.ambiente) return;
    ambienteForm.post('/nfe-brasil/configuracao/certificado/ambiente', {
      preserveScroll: true,
      onSuccess: () => toast.success(
        `Ambiente alterado para ${ambienteForm.data.ambiente === 1 ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO'}.`,
      ),
      onError: () => toast.error('Falha ao salvar ambiente.'),
    });
  };

  // Testar SEFAZ (fetch local, não Inertia) → POST /nfe-brasil/configuracao/certificado/testar
  const [testando, setTestando] = useState(false);
  const [resultadoTeste, setResultadoTeste] = useState<SefazTesteResultado | null>(null);
  const testarSefaz = async () => {
    setTestando(true);
    setResultadoTeste(null);
    try {
      const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
      const res = await fetch('/nfe-brasil/configuracao/certificado/testar', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const payload: SefazTesteResultado = await res.json();
      setResultadoTeste(payload);
      if (payload.ok) {
        toast.success(`SEFAZ-${payload.uf} online (cstat ${payload.cstat})`);
      } else {
        toast.error(`SEFAZ retornou cstat ${payload.cstat}: ${payload.xMotivo}`);
      }
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Erro desconhecido';
      setResultadoTeste({
        ok: false, cstat: '—',
        xMotivo: `Falha de rede: ${msg}`,
        tempoResposta: 0, ambiente: 0, uf: '—',
        error: 'network',
      });
      toast.error('Falha de rede ao chamar endpoint.');
    } finally {
      setTestando(false);
    }
  };

  const crumb = certificado
    ? (certificado.diasRestantes != null && certificado.diasRestantes > 0
        ? `Cert A1 vence em ${certificado.diasRestantes}d · ambiente ${painel.ambiente === 1 ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO'}`
        : `Cert A1 EXPIRADO · ambiente ${painel.ambiente === 1 ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO'}`)
    : `Sem certificado A1 ativo · ambiente ${painel.ambiente === 1 ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO'}`;

  return (
    <AppShellV2>
      <Head title="Fiscal · Certificado & Configuração" />

      <FxShell
        route="fiscal_config"
        title="Certificado & configuração"
        crumb={crumb}
        env={certificado ? `A1 ativo · ${painel.ambiente === 1 ? 'prod' : 'homolog'}` : 'sem cert ativo'}
        envTone={certTone === 'bad' ? 'bad' : certTone === 'warn' ? 'warn' : 'ok'}
        actions={
          certificado ? (
            <button
              type="button"
              className="fx-btn ghost"
              onClick={testarSefaz}
              disabled={testando}
              title="Pingar SEFAZ (cstat 107 esperado) — não emite NFe"
            >
              {testando ? <Loader2 size={12} className="animate-spin" /> : <PlugZap size={12} />}
              {testando ? 'Testando…' : 'Testar SEFAZ'}
            </button>
          ) : undefined
        }
      >
        {flashSuccess && (
          <div className="fx-callout" role="status" style={{ background: 'var(--ok-soft)', borderColor: 'var(--ok)' }}>
            <CheckCircle2 size={16} />
            <div>
              <small style={{ color: 'var(--ok)' }}>{flashSuccess}</small>
            </div>
          </div>
        )}

        {resultadoTeste && (
          <div
            role="status"
            aria-live="polite"
            className="fx-callout"
            style={{
              background: resultadoTeste.ok ? 'var(--ok-soft)' : 'var(--bad-soft)',
              borderColor: resultadoTeste.ok ? 'var(--ok)' : 'var(--bad)',
            }}
          >
            {resultadoTeste.ok ? <CheckCircle2 size={16} /> : <XCircle size={16} />}
            <div>
              <b style={{ color: resultadoTeste.ok ? 'var(--ok)' : 'var(--bad)' }}>
                {resultadoTeste.ok
                  ? `SEFAZ-${resultadoTeste.uf} online`
                  : resultadoTeste.uf && resultadoTeste.uf !== '—'
                    ? `Erro consultando SEFAZ-${resultadoTeste.uf}`
                    : 'Erro consultando SEFAZ'}
                {resultadoTeste.cstat && resultadoTeste.cstat !== '—' && ` · cstat ${resultadoTeste.cstat}`}
              </b>
              <small>
                {resultadoTeste.xMotivo}
                {resultadoTeste.tempoResposta > 0 && ` · ${resultadoTeste.tempoResposta}s`}
                {resultadoTeste.versao && ` · ${resultadoTeste.versao}`}
              </small>
            </div>
          </div>
        )}

        {/* Onda 2 H — ModuleTopNav 4 tabs */}
        <FiscalModuleTopNav
          items={[
            { id: 'cert',     label: 'Certificado A1', icon: <Shield size={12} />,    count: certificado ? undefined : 0, tone: certificado ? null : 'bad' },
            { id: 'series',   label: 'Séries',         icon: <FileText size={12} />,  count: seriesMock.filter((s) => s.ativo).length },
            { id: 'ambiente', label: 'Ambiente',       icon: <Settings size={12} /> },
            { id: 'sped',     label: 'SPED & Livros',  icon: <Archive size={12} /> },
          ]}
          value={tab}
          onChange={(id) => setTab(id as ConfigTab)}
        />

        {tab === 'cert' && (<>
        {/* Cert + Help (2-col grid — port fiscal-page.jsx §12 CertificadoTab) */}
        <div className="fx-cert-grid">
          <section className="fx-cert-card">
            <h3>Certificado digital A1</h3>
            <p className="lead">Instalado em MemCofre · SEFAZ exige renovação anual.</p>

            {certificado ? (
              <>
                <div className="fx-cert-head">
                  <span className="fx-cert-ic"><Shield size={20} /></span>
                  <div>
                    <b>
                      {certificado.cnpjTitular
                        ? formatCnpj(certificado.cnpjTitular)
                        : certificado.cnpjTitularFallback
                          ? <>{formatCnpj(certificado.cnpjTitularFallback)} <small style={{ opacity: 0.7 }}>(business)</small></>
                          : 'CNPJ não informado'}
                    </b>
                    <small>A1 (arquivo .pfx) · MemCofre · senha protegida</small>
                    <small>UUID {certificado.uuid.slice(0, 8)}…</small>
                  </div>
                </div>

                <dl className="fx-cert-validade">
                  <div>
                    <dt>Válido até</dt>
                    <dd>{certificado.validoAteBr ?? '—'}</dd>
                  </div>
                  <div>
                    <dt>Restam</dt>
                    <dd style={{ color: certTone === 'bad' ? 'var(--bad)' : certTone === 'warn' ? 'var(--warn)' : 'var(--ok)' }}>
                      {certificado.diasRestantes != null && certificado.diasRestantes > 0
                        ? `${certificado.diasRestantes}d`
                        : 'EXPIRADO'}
                    </dd>
                  </div>
                  <div>
                    <dt>Status</dt>
                    <dd>{certificado.ativo ? 'Ativo' : 'Inativo'}</dd>
                  </div>
                </dl>

                <div className={`fx-cert-bar${certTone === 'bad' ? ' crit' : ''}`}>
                  <div style={{ width: `${Math.max(2, Math.min(100, ((certificado.diasRestantes ?? 0) / 365) * 100))}%` }} />
                </div>
              </>
            ) : (
              <div className="fx-empty" style={{ background: 'transparent', border: 'none', padding: 0 }}>
                <b>Nenhum certificado A1 ativo</b>
                <small>Faça upload abaixo pra começar a emitir.</small>
              </div>
            )}
          </section>

          <section className="fx-cert-card">
            <h3>Como funciona o A1</h3>
            <p className="lead">Resumo pra contadora externa.</p>
            <ul className="fx-help-list">
              <li><b>A1 (arquivo):</b> certificado em <code>.pfx</code> + senha. Fica no servidor (MemCofre). Renova a cada 12 meses.</li>
              <li><b>A3 (token/cartão):</b> exige hardware presente no servidor. Mais seguro mas dificulta automação.</li>
              <li><b>Vence em &lt; 30 dias?</b> SEFAZ continua aceitando até a data final, mas a emissão deve ser pausada se renovação atrasar.</li>
              <li><b>Onde fica a senha?</b> sempre em <code>MemCofre</code>, nunca em variável de ambiente.</li>
            </ul>
          </section>
        </div>
        </>)}

        {tab === 'ambiente' && (
        <>
        {/* Ambiente SEFAZ — radio + submit */}
        <section className="fx-cert-card" style={{ marginBottom: 14 }}>
          <h3>Ambiente SEFAZ</h3>
          <p className="lead">
            <b>Homologação</b> = teste, NF-e gerada não tem valor fiscal.{' '}
            <b>Produção</b> = valor fiscal real, vai pra contabilidade.
          </p>
          <form onSubmit={submitAmbiente} style={{ marginTop: 12 }}>
            <RadioGroup
              value={String(ambienteForm.data.ambiente)}
              onValueChange={(v) => ambienteForm.setData('ambiente', Number(v) as 1 | 2)}
              style={{ display: 'flex', gap: 16, alignItems: 'center', marginBottom: 12 }}
            >
              {/* htmlFor → clicar no label inteiro ativa o RadioGroupItem (botão labelable) */}
              <label htmlFor="ambiente-2" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, cursor: 'pointer', fontSize: 13 }}>
                <RadioGroupItem id="ambiente-2" value="2" />
                <b>Homologação</b>
                <small style={{ color: 'var(--fx-text-mute)' }}>(teste)</small>
              </label>
              <label htmlFor="ambiente-1" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, cursor: 'pointer', fontSize: 13 }}>
                <RadioGroupItem id="ambiente-1" value="1" />
                <b>Produção</b>
                <small style={{ color: 'var(--warn)' }}>(valor fiscal real)</small>
              </label>
            </RadioGroup>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
              <small style={{ color: 'var(--fx-text-mute)' }}>
                Atual: <code className="fx-mono">{painel.ambiente === 1 ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO'}</code>
              </small>
              <button
                type="submit"
                className="fx-btn ghost"
                disabled={ambienteForm.processing || ambienteForm.data.ambiente === painel.ambiente}
              >
                {ambienteForm.processing ? 'Salvando…' : 'Salvar ambiente'}
              </button>
            </div>
            {ambienteForm.errors.ambiente && (
              <small style={{ color: 'var(--bad)' }}>{ambienteForm.errors.ambiente}</small>
            )}
          </form>
        </section>
        </>
        )}

        {tab === 'cert' && (<>
        {/* Upload .pfx — Inertia useForm → POST /nfe-brasil/configuracao/certificado */}
        <section className="fx-cert-card" style={{ marginBottom: 14 }}>
          <h3>
            <Upload size={14} style={{ verticalAlign: 'text-bottom', marginRight: 6 }} />
            {certificado ? 'Substituir certificado' : 'Upload do certificado A1'}
          </h3>
          <p className="lead">
            {certificado
              ? 'Subir um certificado novo desativa o atual automaticamente (rotação cega).'
              : 'Sobe o .pfx ou .p12 + senha. Valida CNPJ, criptografa em disco e habilita emissão NF-e/NFC-e.'}
          </p>
          <form onSubmit={submitUpload} style={{ marginTop: 12 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 12 }}>
              <div>
                <label htmlFor="certificado-file" style={{ display: 'block', fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.04em', color: 'var(--fx-text-mute)', marginBottom: 6 }}>
                  Arquivo .pfx / .p12 *
                </label>
                <input
                  id="certificado-file"
                  ref={fileRef}
                  type="file"
                  accept=".pfx,.p12"
                  onChange={(e) => uploadForm.setData('certificado', e.target.files?.[0] ?? null)}
                  style={{ width: '100%', padding: '6px 8px', fontSize: 12, border: '1px solid var(--fx-border)', borderRadius: 6, background: 'white' }}
                />
                <small style={{ color: 'var(--fx-text-mute)', fontSize: 11 }}>Máximo 100 KB. A3 (token) não é suportado.</small>
                {uploadForm.errors.certificado && (
                  <small style={{ color: 'var(--bad)', display: 'block', fontSize: 11 }}>{uploadForm.errors.certificado}</small>
                )}
              </div>
              <div>
                <label htmlFor="certificado-senha" style={{ display: 'block', fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.04em', color: 'var(--fx-text-mute)', marginBottom: 6 }}>
                  <KeyRound size={11} style={{ verticalAlign: 'text-bottom', marginRight: 4 }} />
                  Senha do certificado *
                </label>
                <input
                  id="certificado-senha"
                  type="password"
                  value={uploadForm.data.senha}
                  onChange={(e) => uploadForm.setData('senha', e.target.value)}
                  autoComplete="off"
                  maxLength={80}
                  style={{ width: '100%', padding: '6px 8px', fontSize: 12, border: '1px solid var(--fx-border)', borderRadius: 6 }}
                />
                <small style={{ color: 'var(--fx-text-mute)', fontSize: 11 }}>Encrypted-at-rest (Laravel encrypt) · nunca em log.</small>
                {uploadForm.errors.senha && (
                  <small style={{ color: 'var(--bad)', display: 'block', fontSize: 11 }}>{uploadForm.errors.senha}</small>
                )}
              </div>
            </div>
            <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
              <button
                type="submit"
                className="fx-btn primary"
                disabled={uploadForm.processing || !uploadForm.data.certificado || !uploadForm.data.senha}
              >
                {uploadForm.processing
                  ? 'Enviando…'
                  : certificado
                    ? 'Substituir certificado'
                    : 'Enviar certificado'}
              </button>
            </div>
          </form>
        </section>

        {/* Identificação + Numeração (info read-only consolidada do painel fiscal) */}
        <section className="fx-cert-card" style={{ marginBottom: 14 }}>
          <h3>Identificação fiscal &amp; numeração</h3>
          <dl className="fx-kv" style={{ gridTemplateColumns: '140px 1fr 140px 1fr' }}>
            <dt>CNPJ business</dt>
            <dd className="fx-mono">{formatCnpj(painel.cnpjBusiness)}</dd>
            <dt>Razão social</dt>
            <dd>{painel.razaoSocial ?? '—'}</dd>
            <dt>Regime</dt>
            <dd>{painel.regime ? (REGIME_LABEL[painel.regime] ?? painel.regime) : (config ? (REGIME_LABEL[config.regime] ?? config.regime) : '—')}</dd>
            <dt>Localização</dt>
            <dd>{painel.cidade && painel.uf ? `${painel.cidade} / ${painel.uf}` : (painel.uf ?? '—')}</dd>
            <dt>NCM padrão</dt>
            <dd className="fx-mono">{painel.ncmPadrao ?? '—'}</dd>
            <dt>CFOP / CSOSN</dt>
            <dd className="fx-mono">{painel.cfopDefault ?? '—'} / {painel.csosnDefault ?? painel.cstDefault ?? '—'}</dd>
            <dt>Série NFe</dt>
            <dd className="fx-mono">{painel.serieNfe}</dd>
            <dt>Próximo número</dt>
            <dd className="fx-mono">{painel.proximoNumero}</dd>
            <dt>Emissão auto</dt>
            <dd>{config?.autoEmissionEnabled ? '✅ Habilitada' : <><Lock className="h-3.5 w-3.5 mr-1 inline align-text-bottom" />Manual</>}</dd>
            <dt>Tributação default</dt>
            <dd>
              {config && Object.keys(config.tributacaoDefault).length > 0
                ? <code className="fx-mono" style={{ fontSize: 11 }}>{JSON.stringify(config.tributacaoDefault).slice(0, 120)}</code>
                : <small>nenhum default</small>}
            </dd>
          </dl>
          <small style={{ display: 'block', marginTop: 12, color: 'var(--fx-text-mute)' }}>
            Cascade NCM/CFOP/CSOSN avançado vive em{' '}
            <a href="/nfe-brasil/tributacao" className="fx-link">/nfe-brasil/tributacao</a>.
          </small>
        </section>
        </>)}

        {tab === 'series' && (
          seriesMock.length === 0 ? (
            <div className="fx-empty">
              <FileText size={20} />
              <b>Nenhuma série fiscal cadastrada</b>
              <small>Séries NFe (modelo 55) e NFCe (modelo 65) são configuradas em NfeBrasil/business.numero_serie_nfe.</small>
              <a href="/nfe-brasil/configuracao/series" className="fx-btn ghost" style={{ marginTop: 12 }}>Configurar em NfeBrasil</a>
            </div>
          ) : (
            <section className="fx-cert-card">
              <h3>Séries fiscais</h3>
              <p className="lead">
                Numeração ativa por modelo + filial. Read-only no Fiscal Cockpit —
                edição em <a href="/nfe-brasil/configuracao/series" className="fx-link">NfeBrasil/Series</a>.
              </p>
              <div className="fx-table" style={{ marginTop: 12 }}>
                <table>
                  <thead>
                    <tr>
                      <th style={{ width: 140 }}>Modelo</th>
                      <th style={{ width: 100 }}>Série</th>
                      <th style={{ width: 130, textAlign: 'right' }}>Próximo nº</th>
                      <th>Filial</th>
                      <th style={{ width: 140 }}>Estado</th>
                    </tr>
                  </thead>
                  <tbody>
                    {seriesMock.map((s, i) => (
                      <tr key={`${s.modelo}-${s.serie}-${i}`}>
                        <td><b>{s.modelo}</b> <small className="fx-mut">({s.modelo === 65 ? 'NFC-e' : 'NF-e'})</small></td>
                        <td className="fx-mono">série {s.serie}</td>
                        <td className="fx-mono fx-strong" style={{ textAlign: 'right' }}>{s.proximo.toLocaleString('pt-BR')}</td>
                        <td><small>{s.filial}</small></td>
                        <td>
                          <span className={`fx-sefaz ${s.ativo ? 'ok' : 'warn'}`}>
                            <span className="lbl">{s.ativo ? 'ativa' : 'inativa'}</span>
                          </span>
                          {s.obs && <small style={{ display: 'block', color: 'var(--fx-text-mute)', marginTop: 3 }}>{s.obs}</small>}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )
        )}

        {tab === 'sped' && (
          <section className="fx-cert-card">
            <h3>SPED &amp; Livros fiscais</h3>
            <p className="lead">
              EFD ICMS/IPI · PIS/COFINS · ECF · Conciliação SEFAZ.
              Gerador completo vive em <a href="/fiscal/sped" className="fx-link">/fiscal/sped</a>.
            </p>
            <div className="fx-callout" role="region" style={{ marginTop: 12 }}>
              <Archive size={16} />
              <div>
                <b>Próxima entrega: maio/2026 — prazo 15/06</b>
                <small>
                  Última: 03/2026 protocolada 14/04 (SPED EFD ICMS/IPI).
                  <br />
                  ECF anual: prazo julho.
                </small>
              </div>
            </div>
            <div style={{ marginTop: 14 }}>
              <a href="/fiscal/sped" className="fx-btn primary">
                <Archive size={12} /> Abrir gerador SPED
              </a>
            </div>
          </section>
        )}
      </FxShell>
    </AppShellV2>
  );
}
