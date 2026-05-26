// SendToContabilDrawer.tsx — Slide-in 760px com fluxo guiado "Enviar p/ contabilidade".
//
// Port do fiscal-page.jsx §SendToContabilDrawer (Onda 2 D). Multi-seção:
//  1. Validações (rejeições pendentes? cert vencendo? DF-e pendente?)
//  2. Período + método (email anexo / SFTP / download manual)
//  3. Pacote (selecionar: XML autorizadas + DANFE + NFS-e + relatório summary)
//  4. Histórico (últimos envios)
//
// Wire-up POST real fica TODO[CL] — drawer renderiza fluxo + botão disabled.
//
// Refactor onda 1 (2026-05-26): shell migrado pra <DrawerBase> compartilhado.

import { useState } from 'react';
import { Archive, CheckCircle2, ExternalLink, FileText, Mail, ShieldAlert } from 'lucide-react';

import DrawerBase from './_shared/DrawerBase';

export interface ContabilValidacao {
  ok: boolean | 'warn';
  label: string;
  action?: string;
  goto?: string;
}

export interface ContabilHistoryEntry {
  id: string | number;
  periodo: string; // ex "abril/2026"
  enviadoEm: string; // ex "03/05 09:23"
  metodo: 'email' | 'sftp' | 'download';
  destino: string; // ex "contador@example.br" ou "sftp://..."
  pacote: number; // bytes
  status: 'enviado' | 'falha' | 'pendente';
}

export interface SendToContabilData {
  periodoCorrente: string; // ex "maio/2026"
  validacoes: ContabilValidacao[];
  destinatarioPadrao: string;
  contadorNome: string;
  history: ContabilHistoryEntry[];
  totalsByPeriodo: { autorizadas: number; nfse: number; eventos: number };
}

interface SendToContabilDrawerProps {
  open: boolean;
  data: SendToContabilData | null;
  onClose: () => void;
}

type Metodo = 'email' | 'sftp' | 'download';

interface PacoteSelecao {
  xmlAutorizadas: boolean;
  danfeAutorizadas: boolean;
  xmlNfse: boolean;
  relatorioSummary: boolean;
  eventos: boolean;
}

function formatBytes(b: number): string {
  if (b < 1024) return `${b} B`;
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
  return `${(b / (1024 * 1024)).toFixed(1)} MB`;
}

export default function SendToContabilDrawer({ open, data, onClose }: SendToContabilDrawerProps) {
  const [metodo, setMetodo] = useState<Metodo>('email');
  const [pacote, setPacote] = useState<PacoteSelecao>({
    xmlAutorizadas: true,
    danfeAutorizadas: false,
    xmlNfse: true,
    relatorioSummary: true,
    eventos: true,
  });

  if (!open || !data) return null;

  const bloqueios = data.validacoes.filter((v) => v.ok === false);
  const warnings = data.validacoes.filter((v) => v.ok === 'warn');
  const podeEnviar = bloqueios.length === 0 && (pacote.xmlAutorizadas || pacote.xmlNfse || pacote.relatorioSummary);

  return (
    <DrawerBase
      open={open}
      onClose={onClose}
      ariaLabel="Enviar para contabilidade"
      width={760}
      header={
        <div>
          <small>Fechamento mensal</small>
          <h2>Enviar p/ contabilidade — {data.periodoCorrente}</h2>
          <small style={{ color: 'var(--fx-text-mute)' }}>
            Contador: <b>{data.contadorNome}</b> · {data.destinatarioPadrao}
          </small>
        </div>
      }
      footer={
        <>
          {warnings.length > 0 && (
            <small style={{ color: 'var(--warn)' }}>
              ⚠ {warnings.length} aviso{warnings.length > 1 ? 's' : ''} (não bloqueia)
            </small>
          )}
          <div className="fx-drawer-f-r">
            <button type="button" className="fx-btn ghost" onClick={onClose}>Cancelar</button>
            <button
              type="button"
              className="fx-btn primary"
              disabled={!podeEnviar}
              title={!podeEnviar ? 'Resolver bloqueios + selecionar pelo menos 1 item do pacote' : 'TODO[CL]: wire-up real no PR seguinte'}
            >
              <Archive size={12} /> Gerar e enviar pacote
            </button>
          </div>
        </>
      }
    >
      {/* 1. Validações */}
      <section className="fx-drawer-sec">
        <h4>Validações pré-envio</h4>
        <ul className="fx-validacoes">
          {data.validacoes.map((v, i) => (
            <li key={i} className={`fx-validacao ${v.ok === true ? 'ok' : v.ok === false ? 'bad' : 'warn'}`}>
              {v.ok === true ? <CheckCircle2 size={14} /> : <ShieldAlert size={14} />}
              <span>{v.label}</span>
              {v.action && v.goto && (
                <a
                  href={v.goto}
                  className="fx-link"
                  style={{ marginLeft: 'auto', fontSize: 11 }}
                  onClick={(e) => { e.preventDefault(); /* TODO[CL] router.visit(v.goto) */ }}
                >
                  {v.action} <ExternalLink size={10} />
                </a>
              )}
            </li>
          ))}
        </ul>
        {bloqueios.length > 0 && (
          <div className="fx-drawer-rej" style={{ marginTop: 10 }}>
            ↳ Resolver bloqueios antes de enviar ({bloqueios.length} críticos)
          </div>
        )}
      </section>

      {/* 2. Método */}
      <section className="fx-drawer-sec">
        <h4>Método de entrega</h4>
        <div className="fx-metodo-grid">
          {(['email', 'sftp', 'download'] as Metodo[]).map((m) => (
            <label key={m} className={`fx-metodo-card ${metodo === m ? 'active' : ''}`}>
              <input
                type="radio"
                name="metodo"
                value={m}
                checked={metodo === m}
                onChange={() => setMetodo(m)}
              />
              <div>
                {m === 'email' && <><Mail size={14} /> <b>E-mail anexo</b><small>Envia ZIP pro {data.destinatarioPadrao}</small></>}
                {m === 'sftp' && <><Archive size={14} /> <b>SFTP do contador</b><small>Configurar credencial em /fiscal/config</small></>}
                {m === 'download' && <><FileText size={14} /> <b>Download manual</b><small>Baixa ZIP local pra você compartilhar</small></>}
              </div>
            </label>
          ))}
        </div>
      </section>

      {/* 3. Pacote */}
      <section className="fx-drawer-sec">
        <h4>O que entra no pacote</h4>
        <ul className="fx-pacote-list">
          <li>
            <label>
              <input
                type="checkbox"
                checked={pacote.xmlAutorizadas}
                onChange={(e) => setPacote({ ...pacote, xmlAutorizadas: e.target.checked })}
              />
              <span><b>{data.totalsByPeriodo.autorizadas}</b> XMLs NF-e/NFC-e autorizadas</span>
            </label>
          </li>
          <li>
            <label>
              <input
                type="checkbox"
                checked={pacote.danfeAutorizadas}
                onChange={(e) => setPacote({ ...pacote, danfeAutorizadas: e.target.checked })}
              />
              <span>{data.totalsByPeriodo.autorizadas} DANFEs PDF <small className="fx-mut">(pesado · normalmente contador não precisa)</small></span>
            </label>
          </li>
          <li>
            <label>
              <input
                type="checkbox"
                checked={pacote.xmlNfse}
                onChange={(e) => setPacote({ ...pacote, xmlNfse: e.target.checked })}
              />
              <span><b>{data.totalsByPeriodo.nfse}</b> XMLs NFS-e</span>
            </label>
          </li>
          <li>
            <label>
              <input
                type="checkbox"
                checked={pacote.eventos}
                onChange={(e) => setPacote({ ...pacote, eventos: e.target.checked })}
              />
              <span><b>{data.totalsByPeriodo.eventos}</b> Eventos (CC-e · cancelamentos · inutilizações)</span>
            </label>
          </li>
          <li>
            <label>
              <input
                type="checkbox"
                checked={pacote.relatorioSummary}
                onChange={(e) => setPacote({ ...pacote, relatorioSummary: e.target.checked })}
              />
              <span>Relatório summary PDF (totais + rejeições + alertas certificado)</span>
            </label>
          </li>
        </ul>
      </section>

      {/* 4. Histórico */}
      {data.history.length > 0 && (
        <section className="fx-drawer-sec">
          <h4>Histórico de envios <span className="fx-sec-count">{data.history.length}</span></h4>
          <ul className="fx-emails">
            {data.history.map((h) => (
              <li key={h.id}>
                <div>
                  <b>{h.periodo}</b>
                  <small>{h.metodo === 'email' ? 'e-mail' : h.metodo === 'sftp' ? 'SFTP' : 'download'} · {h.destino} · {formatBytes(h.pacote)}</small>
                </div>
                <div className="fx-email-meta">
                  <span className="fx-mut">{h.enviadoEm}</span>
                  <span className={`fx-sefaz ${h.status === 'enviado' ? 'ok' : h.status === 'falha' ? 'bad' : 'warn'} compact`}>
                    <span className="lbl">{h.status}</span>
                  </span>
                </div>
              </li>
            ))}
          </ul>
        </section>
      )}
    </DrawerBase>
  );
}
