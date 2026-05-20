// @memcofre
//   tela: /fiscal/config
//   module: Fiscal
//   stories: US-FISCAL-009 (Cert/Cfg sub-página 6 do design KB-9.75)
//   adrs: 0093, 0094, 0101, 0104

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head } from '@inertiajs/react';
import { Edit3, Shield } from 'lucide-react';

import FxShell from './_components/FxShell';

import '../../../css/fiscal-cockpit.css';

interface Certificado {
  uuid: string;
  cnpjTitular: string | null;
  validoAteIso: string | null;
  validoAteBr: string | null;
  diasRestantes: number | null;
  ativo: boolean;
}

interface Config {
  regime: string;
  autoEmissionEnabled: boolean;
  tributacaoDefault: Record<string, unknown>;
}

interface ConfigProps {
  certificado: Certificado | null;
  config: Config | null;
}

const REGIME_LABEL: Record<string, string> = {
  simples_nacional: 'Simples Nacional',
  lucro_presumido: 'Lucro Presumido',
  lucro_real: 'Lucro Real',
  mei: 'MEI',
};

export default function Config({ certificado, config }: ConfigProps) {
  const certTone = certificado?.diasRestantes == null ? 'bad'
    : certificado.diasRestantes <= 7 ? 'bad'
    : certificado.diasRestantes <= 60 ? 'warn' : 'ok';

  return (
    <AppShellV2>
      <Head title="Fiscal · Certificado & Configuração" />

      <FxShell
        route="fiscal_config"
        title="Certificado & configuração"
        crumb={certificado
          ? `Cert A1 ${certificado.diasRestantes != null && certificado.diasRestantes > 0 ? `vence em ${certificado.diasRestantes}d` : 'EXPIRADO'}`
          : 'Sem certificado ativo'}
        env={certificado ? 'A1 ativo' : 'config pendente'}
        envTone={certTone === 'bad' ? 'bad' : certTone === 'warn' ? 'warn' : 'ok'}
        actions={
          <a href="/nfe-brasil/configuracao/certificado" className="fx-btn primary">
            <Edit3 size={12} /> Editar (NfeBrasil)
          </a>
        }
      >
        {/* Cert section */}
        <section className="fx-drawer-sec" style={{ background: 'white', border: '1px solid var(--fx-border)', borderRadius: 10, padding: 18, marginBottom: 14 }}>
          <h4>
            <Shield size={13} style={{ marginRight: 6, verticalAlign: 'text-bottom' }} />
            Certificado digital A1
          </h4>
          {certificado ? (
            <dl className="fx-kv">
              <dt>CNPJ titular</dt>
              <dd>{certificado.cnpjTitular ?? '—'}</dd>
              <dt>Validade</dt>
              <dd>
                <span className={`fx-sefaz ${certTone}`}>
                  <span className="code">{certificado.validoAteBr ?? '—'}</span>
                  <span className="lbl">
                    {certificado.diasRestantes != null && certificado.diasRestantes > 0
                      ? `${certificado.diasRestantes}d restantes`
                      : 'EXPIRADO'}
                  </span>
                </span>
              </dd>
              <dt>Status</dt>
              <dd>{certificado.ativo ? '✅ Ativo' : '🔒 Inativo'}</dd>
              <dt>UUID</dt>
              <dd className="fx-mono"><small>{certificado.uuid}</small></dd>
            </dl>
          ) : (
            <div className="fx-empty" style={{ background: 'transparent', border: 'none', padding: '12px 0' }}>
              <b>Nenhum certificado A1 ativo</b>
              <small>Upload via /nfe-brasil/configuracao/certificado</small>
            </div>
          )}
        </section>

        {/* Config section */}
        <section className="fx-drawer-sec" style={{ background: 'white', border: '1px solid var(--fx-border)', borderRadius: 10, padding: 18, marginBottom: 14 }}>
          <h4>Regime tributário & emissão</h4>
          {config ? (
            <dl className="fx-kv">
              <dt>Regime</dt>
              <dd>{REGIME_LABEL[config.regime] ?? config.regime}</dd>
              <dt>Emissão auto</dt>
              <dd>{config.autoEmissionEnabled ? '✅ Habilitada (emite NFCe ao finalizar venda)' : '🔒 Manual (emite apenas via botão)'}</dd>
              <dt>Tributação default</dt>
              <dd>
                {Object.keys(config.tributacaoDefault).length > 0
                  ? <code className="fx-mono">{JSON.stringify(config.tributacaoDefault).slice(0, 200)}</code>
                  : <small>nenhum default configurado</small>}
              </dd>
            </dl>
          ) : (
            <div className="fx-empty" style={{ background: 'transparent', border: 'none', padding: '12px 0' }}>
              <b>Configuração não inicializada</b>
              <small>Edite via /nfe-brasil/tributacao</small>
            </div>
          )}
        </section>

        <div className="fx-empty" style={{ background: 'var(--fx-bg-2)', border: 'none' }}>
          <small>
            Edição completa de certificado + tributação cascata em
            {' '}<a href="/nfe-brasil/configuracao/certificado" className="fx-link">Configuração NfeBrasil</a>{' '}
            (módulo emissor). Esta tela do Fiscal Cockpit mostra status read-only consolidado.
          </small>
        </div>
      </FxShell>
    </AppShellV2>
  );
}
