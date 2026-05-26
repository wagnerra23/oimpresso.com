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
                    <b>{certificado.cnpjTitular ?? 'CNPJ não informado'}</b>
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

                <div className="fx-cert-actions">
                  <a href="/nfe-brasil/configuracao/certificado" className="fx-btn warn">Renovar certificado</a>
                  <a href="/nfe-brasil/configuracao/certificado" className="fx-btn ghost">Trocar para A3 (token)</a>
                </div>
              </>
            ) : (
              <div className="fx-empty" style={{ background: 'transparent', border: 'none', padding: 0 }}>
                <b>Nenhum certificado A1 ativo</b>
                <small>Upload via NfeBrasil para começar a emitir.</small>
                <a href="/nfe-brasil/configuracao/certificado" className="fx-btn primary" style={{ marginTop: 12 }}>Importar certificado</a>
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

        {/* Regime tributário (mantém visual list-style canônico) */}
        <section className="fx-cert-card" style={{ marginBottom: 14 }}>
          <h3>Regime tributário &amp; emissão</h3>
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
