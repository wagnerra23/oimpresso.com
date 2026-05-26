// VdNfeEmitModal — Emit NF-e 3-step modal (KB-9.75 Cowork bundle 2026-05-26 P0 gap #2).
// Refs:
//   - prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/vendas-flow.jsx:294 (canon)
//   - memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md gap #2
//
// **UI stub** — mock SEFAZ via setTimeout. Wire backend real (Modules/NfeBrasil)
// fica pro próximo PR. Demo-ready pra mostrar fluxo guiado 3-step.
//
// Steps:
//   1. Review fiscal (CFOP/NCM/CST validações inline + impostos calculados)
//   2. Preview XML (mock RPS XML formatado)
//   3. Transmissão (loader → autorizada/rejeitada/contingência aleatório controlado pra demo)

import { useState } from 'react';
import { X, AlertCircle, CheckCircle2, FileText, Send, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import {
  validaNcm,
  validaCfop,
  validaCst,
  type ValidationResult,
} from '@/Lib/validacoesFiscaisBr';

// Hub canon `oimpressoToast` (PR #1643) opcional — usa sonner direto pra não criar
// dependência cross-PR. Dispatch `oimpresso:venda-emitted-nfe` continua canônico
// (gap #14 namespace events do r4 visual-comparison).
function dispatchEmitted(saleId: number, protocolo: string) {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(
      new CustomEvent('oimpresso:venda-emitted-nfe', { detail: { saleId, protocolo } }),
    );
  }
}

export interface NfeEmitItem {
  id: number;
  produto: string;
  ncm?: string;
  cfop?: string;
  cst?: string;
  qtd: number;
  unit: number;
  subtotal: number;
}

export interface NfeEmitVenda {
  id: number;
  invoice_no: string;
  customer_name: string | null;
  customer_doc?: string | null;
  uf_emitente?: string;
  uf_destinatario?: string;
  itemsList: NfeEmitItem[];
  total: number;
}

interface Props {
  open: boolean;
  venda: NfeEmitVenda | null;
  onClose: () => void;
  /** Callback após autorização — Show.tsx refresh + dispatch oimpresso:venda-emitted-nfe */
  onSuccess?: (protocolo: string) => void;
}

type EmitStatus = 'idle' | 'transmitting' | 'authorized' | 'rejected' | 'contingency';

interface ValidatedItem extends NfeEmitItem {
  ncmValidation: ValidationResult;
  cfopValidation: ValidationResult;
  cstValidation: ValidationResult;
}

function buildPreviewXml(venda: NfeEmitVenda, items: ValidatedItem[]): string {
  // Mock XML formatado — NÃO é XML real SEFAZ, é só pra demo
  const total = items.reduce((s, it) => s + it.subtotal, 0);
  return `<?xml version="1.0" encoding="UTF-8"?>
<NFe xmlns="http://www.portalfiscal.inf.br/nfe">
  <infNFe Id="NFe35260100000000000000550010000000000000000000">
    <ide>
      <cUF>${venda.uf_emitente === 'SP' ? '35' : '42'}</cUF>
      <natOp>Venda de mercadoria</natOp>
      <serie>1</serie>
      <nNF>${venda.invoice_no.replace(/\D/g, '').padStart(9, '0')}</nNF>
    </ide>
    <emit>
      <CNPJ>00000000000000</CNPJ>
      <xNome>EMPRESA EMITENTE LTDA</xNome>
    </emit>
    <dest>
      <CNPJ>${(venda.customer_doc ?? '').replace(/\D/g, '').padEnd(14, '0').slice(0, 14)}</CNPJ>
      <xNome>${venda.customer_name ?? 'Cliente'}</xNome>
    </dest>
    ${items
      .map(
        (it, i) => `<det nItem="${i + 1}">
      <prod>
        <cProd>${it.id}</cProd>
        <xProd>${it.produto}</xProd>
        <NCM>${it.ncm ?? '00000000'}</NCM>
        <CFOP>${it.cfop ?? '5102'}</CFOP>
        <uCom>UN</uCom>
        <qCom>${it.qtd.toFixed(2)}</qCom>
        <vUnCom>${it.unit.toFixed(2)}</vUnCom>
        <vProd>${it.subtotal.toFixed(2)}</vProd>
      </prod>
      <imposto>
        <ICMS>
          <ICMS00>
            <CST>${it.cst ?? '00'}</CST>
          </ICMS00>
        </ICMS>
      </imposto>
    </det>`,
      )
      .join('\n    ')}
    <total>
      <ICMSTot>
        <vNF>${total.toFixed(2)}</vNF>
      </ICMSTot>
    </total>
  </infNFe>
</NFe>`;
}

export default function VdNfeEmitModal({ open, venda, onClose, onSuccess }: Props) {
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [status, setStatus] = useState<EmitStatus>('idle');
  const [protocolo, setProtocolo] = useState<string | null>(null);

  if (!open || !venda) return null;

  const items: ValidatedItem[] = venda.itemsList.map((it) => ({
    ...it,
    ncmValidation: validaNcm(it.ncm ?? ''),
    cfopValidation: validaCfop(it.cfop ?? '', {
      ufEmitente: venda.uf_emitente,
      ufDestinatario: venda.uf_destinatario,
    }),
    cstValidation: validaCst(it.cst ?? ''),
  }));

  const allValid = items.every(
    (it) => it.ncmValidation.ok && it.cfopValidation.ok && it.cstValidation.ok,
  );

  const xml = buildPreviewXml(venda, items);

  const handleTransmit = () => {
    setStatus('transmitting');
    // Mock SEFAZ — 85% autorizada, 10% rejeitada, 5% contingência (demo-friendly)
    setTimeout(() => {
      const roll = Math.random();
      if (roll < 0.85) {
        const prot = `35260100000${Math.floor(Math.random() * 1_000_000_000)
          .toString()
          .padStart(9, '0')}`;
        setProtocolo(prot);
        setStatus('authorized');
        toast.success(`NF-e autorizada · protocolo ${prot}`);
        dispatchEmitted(venda.id, prot);
        onSuccess?.(prot);
      } else if (roll < 0.95) {
        setStatus('rejected');
        toast.error('NF-e rejeitada · verifique CFOP/NCM');
      } else {
        setStatus('contingency');
        toast.info('SEFAZ indisponível · contingência ativa');
      }
    }, 1800);
  };

  const close = () => {
    setStep(1);
    setStatus('idle');
    setProtocolo(null);
    onClose();
  };

  return (
    <div
      className="vd-emit-bd"
      role="dialog"
      aria-modal="true"
      aria-labelledby="vd-emit-title"
      onClick={(e) => {
        if (e.target === e.currentTarget) close();
      }}
    >
      <div className="vd-emit-modal">
        <header className="vd-emit-h">
          <div className="vd-emit-h-l">
            <h2 id="vd-emit-title">Emitir NF-e · #{venda.invoice_no}</h2>
            <small>Cliente: {venda.customer_name ?? '—'}</small>
          </div>
          <button
            type="button"
            className="vd-emit-close"
            onClick={close}
            aria-label="Fechar"
          >
            <X size={18} />
          </button>
        </header>

        <div className="vd-emit-steps">
          <div className={`vd-emit-step ${step >= 1 ? 'active' : ''} ${step > 1 ? 'done' : ''}`}>
            <span className="vd-emit-step-num">1</span>
            <span>Revisar fiscal</span>
          </div>
          <div className="vd-emit-step-sep" />
          <div className={`vd-emit-step ${step >= 2 ? 'active' : ''} ${step > 2 ? 'done' : ''}`}>
            <span className="vd-emit-step-num">2</span>
            <span>Preview XML</span>
          </div>
          <div className="vd-emit-step-sep" />
          <div className={`vd-emit-step ${step >= 3 ? 'active' : ''}`}>
            <span className="vd-emit-step-num">3</span>
            <span>Transmissão</span>
          </div>
        </div>

        <div className="vd-emit-body">
          {step === 1 && (
            <div className="vd-emit-step1">
              <p className="vd-emit-hint">
                Conferência fiscal por item. Corrija valores em vermelho antes de prosseguir.
              </p>
              <table className="vd-emit-table">
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th>NCM</th>
                    <th>CFOP</th>
                    <th>CST</th>
                    <th>Qtd</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((it) => (
                    <tr key={it.id}>
                      <td>{it.produto}</td>
                      <td className={it.ncmValidation.ok ? '' : 'has-error'}>
                        <span className="vd-emit-tag">{it.ncm ?? '—'}</span>
                        {!it.ncmValidation.ok && (
                          <span className="vd-emit-err">{it.ncmValidation.motivo}</span>
                        )}
                      </td>
                      <td className={it.cfopValidation.ok ? '' : 'has-error'}>
                        <span className="vd-emit-tag">{it.cfop ?? '—'}</span>
                        {!it.cfopValidation.ok && (
                          <span className="vd-emit-err">{it.cfopValidation.motivo}</span>
                        )}
                      </td>
                      <td className={it.cstValidation.ok ? '' : 'has-error'}>
                        <span className="vd-emit-tag">{it.cst ?? '—'}</span>
                        {!it.cstValidation.ok && (
                          <span className="vd-emit-err">{it.cstValidation.motivo}</span>
                        )}
                      </td>
                      <td className="vd-emit-tab-num">{it.qtd}</td>
                      <td className="vd-emit-tab-num">
                        {new Intl.NumberFormat('pt-BR', {
                          style: 'currency',
                          currency: 'BRL',
                        }).format(it.subtotal)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {!allValid && (
                <div className="vd-emit-warn">
                  <AlertCircle size={14} />
                  Corrija os campos em vermelho antes de avançar
                </div>
              )}
            </div>
          )}

          {step === 2 && (
            <div className="vd-emit-step2">
              <p className="vd-emit-hint">
                <FileText size={13} /> XML que será transmitido pra SEFAZ:
              </p>
              <pre className="vd-emit-xml">{xml}</pre>
            </div>
          )}

          {step === 3 && (
            <div className="vd-emit-step3">
              {status === 'idle' && (
                <div className="vd-emit-confirm">
                  <Send size={32} />
                  <h3>Pronto pra transmitir</h3>
                  <p>Total: {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(venda.total)}</p>
                  <p className="vd-emit-hint">A NF será enviada à SEFAZ. Após autorização, título entra no contas a receber.</p>
                </div>
              )}
              {status === 'transmitting' && (
                <div className="vd-emit-loading">
                  <Loader2 size={32} className="vd-emit-spin" />
                  <h3>Transmitindo à SEFAZ…</h3>
                  <p className="vd-emit-hint">Aguardando resposta do webservice</p>
                </div>
              )}
              {status === 'authorized' && (
                <div className="vd-emit-result vd-emit-ok">
                  <CheckCircle2 size={32} />
                  <h3>NF-e autorizada</h3>
                  <p>Protocolo: <code>{protocolo}</code></p>
                  <p className="vd-emit-hint">Título lançado no contas a receber.</p>
                </div>
              )}
              {status === 'rejected' && (
                <div className="vd-emit-result vd-emit-bad">
                  <AlertCircle size={32} />
                  <h3>NF-e rejeitada</h3>
                  <p>SEFAZ retornou erro de validação fiscal. Revise CFOP/NCM/CST.</p>
                </div>
              )}
              {status === 'contingency' && (
                <div className="vd-emit-result vd-emit-warn-r">
                  <AlertCircle size={32} />
                  <h3>SEFAZ indisponível</h3>
                  <p>Modo contingência ativado — XML armazenado pra transmissão posterior.</p>
                </div>
              )}
            </div>
          )}
        </div>

        <footer className="vd-emit-f">
          {step > 1 && status === 'idle' && (
            <button
              type="button"
              className="vd-emit-btn-ghost"
              onClick={() => setStep((s) => (s - 1) as 1 | 2 | 3)}
            >
              ← Voltar
            </button>
          )}
          <div className="vd-emit-f-spacer" />
          {step === 1 && (
            <button
              type="button"
              className="vd-emit-btn"
              onClick={() => setStep(2)}
              disabled={!allValid}
            >
              Avançar → Preview
            </button>
          )}
          {step === 2 && (
            <button
              type="button"
              className="vd-emit-btn"
              onClick={() => setStep(3)}
            >
              Avançar → Transmitir
            </button>
          )}
          {step === 3 && status === 'idle' && (
            <button type="button" className="vd-emit-btn primary" onClick={handleTransmit}>
              <Send size={14} /> Transmitir SEFAZ
            </button>
          )}
          {step === 3 && (status === 'authorized' || status === 'rejected' || status === 'contingency') && (
            <button type="button" className="vd-emit-btn" onClick={close}>
              Fechar
            </button>
          )}
        </footer>
      </div>
    </div>
  );
}
