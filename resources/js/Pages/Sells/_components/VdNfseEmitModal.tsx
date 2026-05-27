// VdNfseEmitModal — Emit NFS-e 3-step modal (KB-9.75 Cowork bundle 2026-05-26 P0 gap #3).
// Refs:
//   - prototipo-ui/cowork-2026-05-26-comunicacao-visual/project/vendas-flow.jsx:294 (canon, mesma estrutura NF-e)
//   - memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md gap #3
//
// Diferenças vs NF-e:
//   - SEM CFOP/NCM/CST (impostos NFS-e são via código serviço + ISS)
//   - código serviço (LC 116/2003 Lista de Serviços)
//   - alíquota ISS 2-5% (LC 116/2003 art. 8º-A)
//   - prefeitura webservice (não SEFAZ) — mock idêntico pra demo
//
// **UI stub** — mock prefeitura via setTimeout. Demo-ready.

import { useState } from 'react';
import { X, AlertCircle, CheckCircle2, FileText, Send, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { validaIss, type ValidationResult } from '@/Lib/validacoesFiscaisBr';

function dispatchEmittedNfse(saleId: number, rps: string) {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(
      new CustomEvent('oimpresso:venda-emitted-nfse', { detail: { saleId, rps } }),
    );
  }
}

export interface NfseEmitItem {
  id: number;
  servico: string;
  codigoServico?: string; // Lista LC 116/2003
  aliquotaIss?: number; // 2-5%
  qtd: number;
  unit: number;
  subtotal: number;
}

export interface NfseEmitVenda {
  id: number;
  invoice_no: string;
  customer_name: string | null;
  customer_doc?: string | null;
  itemsList: NfseEmitItem[];
  total: number;
}

interface Props {
  open: boolean;
  venda: NfseEmitVenda | null;
  onClose: () => void;
  onSuccess?: (rps: string) => void;
}

type EmitStatus = 'idle' | 'transmitting' | 'authorized' | 'rejected' | 'contingency';

interface ValidatedItem extends NfseEmitItem {
  issValidation: ValidationResult;
}

function buildPreviewRps(venda: NfseEmitVenda, items: ValidatedItem[]): string {
  const total = items.reduce((s, it) => s + it.subtotal, 0);
  const issTotal = items.reduce(
    (s, it) => s + it.subtotal * ((it.aliquotaIss ?? 5) / 100),
    0,
  );
  return `<?xml version="1.0" encoding="UTF-8"?>
<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">
  <LoteRps Id="lote1" versao="2.04">
    <Cnpj>00000000000000</Cnpj>
    <InscricaoMunicipal>123456</InscricaoMunicipal>
    <QuantidadeRps>1</QuantidadeRps>
    <ListaRps>
      <Rps>
        <InfDeclaracaoPrestacaoServico>
          <Rps>
            <IdentificacaoRps>
              <Numero>${venda.invoice_no.replace(/\D/g, '').padStart(9, '0')}</Numero>
              <Serie>RPS</Serie>
              <Tipo>1</Tipo>
            </IdentificacaoRps>
            <Status>1</Status>
          </Rps>
          <DataEmissao>${new Date().toISOString()}</DataEmissao>
          <CompetenciaServico>${new Date().toISOString().slice(0, 7)}</CompetenciaServico>
          <Servico>
            ${items
              .map(
                (it) => `<ItemListaServico>${it.codigoServico ?? '1.01'}</ItemListaServico>
            <Discriminacao>${it.servico}</Discriminacao>
            <Aliquota>${((it.aliquotaIss ?? 5) / 100).toFixed(4)}</Aliquota>`,
              )
              .join('\n            ')}
            <Valores>
              <ValorServicos>${total.toFixed(2)}</ValorServicos>
              <ValorIss>${issTotal.toFixed(2)}</ValorIss>
            </Valores>
          </Servico>
          <Prestador>
            <CpfCnpj>
              <Cnpj>00000000000000</Cnpj>
            </CpfCnpj>
          </Prestador>
          <Tomador>
            <RazaoSocial>${venda.customer_name ?? 'Cliente'}</RazaoSocial>
          </Tomador>
        </InfDeclaracaoPrestacaoServico>
      </Rps>
    </ListaRps>
  </LoteRps>
</EnviarLoteRpsEnvio>`;
}

export default function VdNfseEmitModal({ open, venda, onClose, onSuccess }: Props) {
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [status, setStatus] = useState<EmitStatus>('idle');
  const [rps, setRps] = useState<string | null>(null);

  if (!open || !venda) return null;

  const items: ValidatedItem[] = venda.itemsList.map((it) => ({
    ...it,
    issValidation: validaIss(it.aliquotaIss ?? 5),
  }));

  const allValid = items.every((it) => it.issValidation.ok);
  const xml = buildPreviewRps(venda, items);

  const handleTransmit = () => {
    setStatus('transmitting');
    setTimeout(() => {
      const roll = Math.random();
      if (roll < 0.85) {
        const rpsNum = `RPS-${Math.floor(Math.random() * 1_000_000_000).toString().padStart(9, '0')}`;
        setRps(rpsNum);
        setStatus('authorized');
        toast.success(`NFS-e autorizada · RPS ${rpsNum}`);
        dispatchEmittedNfse(venda.id, rpsNum);
        onSuccess?.(rpsNum);
      } else if (roll < 0.95) {
        setStatus('rejected');
        toast.error('NFS-e rejeitada · verifique código serviço/ISS');
      } else {
        setStatus('contingency');
        toast.info('Prefeitura indisponível · contingência ativa');
      }
    }, 1800);
  };

  const close = () => {
    setStep(1);
    setStatus('idle');
    setRps(null);
    onClose();
  };

  return (
    <div
      className="vd-emit-bd"
      role="dialog"
      aria-modal="true"
      aria-labelledby="vd-emit-nfse-title"
      onClick={(e) => {
        if (e.target === e.currentTarget) close();
      }}
    >
      <div className="vd-emit-modal">
        <header className="vd-emit-h">
          <div className="vd-emit-h-l">
            <h2 id="vd-emit-nfse-title">Emitir NFS-e · #{venda.invoice_no}</h2>
            <small>Tomador: {venda.customer_name ?? '—'}</small>
          </div>
          <button type="button" className="vd-emit-close" onClick={close} aria-label="Fechar">
            <X size={18} />
          </button>
        </header>

        <div className="vd-emit-steps">
          <div className={`vd-emit-step ${step >= 1 ? 'active' : ''} ${step > 1 ? 'done' : ''}`}>
            <span className="vd-emit-step-num">1</span>
            <span>Revisar serviços</span>
          </div>
          <div className="vd-emit-step-sep" />
          <div className={`vd-emit-step ${step >= 2 ? 'active' : ''} ${step > 2 ? 'done' : ''}`}>
            <span className="vd-emit-step-num">2</span>
            <span>Preview RPS</span>
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
                Conferência por serviço. Alíquota ISS deve estar entre 2% e 5% (LC 116/2003).
              </p>
              <table className="vd-emit-table">
                <thead>
                  <tr>
                    <th>Serviço</th>
                    <th>Código LC 116</th>
                    <th>ISS (%)</th>
                    <th>Qtd</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((it) => (
                    <tr key={it.id}>
                      <td>{it.servico}</td>
                      <td>
                        <span className="vd-emit-tag">{it.codigoServico ?? '1.01'}</span>
                      </td>
                      <td className={it.issValidation.ok ? '' : 'has-error'}>
                        <span className="vd-emit-tag">
                          {(it.aliquotaIss ?? 5).toFixed(2)}%
                        </span>
                        {!it.issValidation.ok && (
                          <span className="vd-emit-err">{it.issValidation.motivo}</span>
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
                  Corrija alíquotas ISS fora do range 2-5% antes de avançar
                </div>
              )}
            </div>
          )}

          {step === 2 && (
            <div className="vd-emit-step2">
              <p className="vd-emit-hint">
                <FileText size={13} /> RPS (Recibo Provisório de Serviço) — enviado à Prefeitura:
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
                  <p className="vd-emit-hint">RPS será enviado à Prefeitura. Após autorização, NFS-e é gerada e título entra no contas a receber.</p>
                </div>
              )}
              {status === 'transmitting' && (
                <div className="vd-emit-loading">
                  <Loader2 size={32} className="vd-emit-spin" />
                  <h3>Transmitindo à Prefeitura…</h3>
                  <p className="vd-emit-hint">Aguardando resposta do webservice municipal</p>
                </div>
              )}
              {status === 'authorized' && (
                <div className="vd-emit-result vd-emit-ok">
                  <CheckCircle2 size={32} />
                  <h3>NFS-e autorizada</h3>
                  <p>RPS: <code>{rps}</code></p>
                  <p className="vd-emit-hint">Título lançado no contas a receber.</p>
                </div>
              )}
              {status === 'rejected' && (
                <div className="vd-emit-result vd-emit-bad">
                  <AlertCircle size={32} />
                  <h3>NFS-e rejeitada</h3>
                  <p>Prefeitura retornou erro de validação. Revise código serviço LC 116 ou alíquota ISS.</p>
                </div>
              )}
              {status === 'contingency' && (
                <div className="vd-emit-result vd-emit-warn-r">
                  <AlertCircle size={32} />
                  <h3>Prefeitura indisponível</h3>
                  <p>Modo contingência ativado — RPS armazenado pra transmissão posterior.</p>
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
            <button type="button" className="vd-emit-btn" onClick={() => setStep(3)}>
              Avançar → Transmitir
            </button>
          )}
          {step === 3 && status === 'idle' && (
            <button type="button" className="vd-emit-btn primary" onClick={handleTransmit}>
              <Send size={14} /> Transmitir Prefeitura
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
