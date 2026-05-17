// SaleTranscriptPDF — Cowork KB-9.75 Sells Onda 4 R4 Distribuição (transcript A4 print-friendly).
// Refs:
//  - prototipo-ui/prototipos/sells-index/vendas-output.jsx (canonical source VdTranscriptPDF)
//  - resources/css/sells-cowork-distribuicao.css (.vd-transcript)
//  - SaleSheet.tsx (button "Transcript" no footer drawer)
//
// Modal overlay 794px wide simulando página A4. Header brand + 4-grid
// (cliente/atendido/pgto/total) + tabela items + fiscal completo + audit
// trail + comentários inline + 2 assinaturas. `window.print()` ativa
// @media print que esconde tudo menos a página.

import { useEffect, type ReactNode } from 'react';
import { X, Printer } from 'lucide-react';

interface TranscriptLine {
  id: number;
  product_name: string | null;
  product_sku: string | null;
  quantity: number;
  unit_price: number;
  subtotal: number;
}

interface TranscriptPayment {
  id: number;
  amount: number;
  method: string;
  paid_on: string | null;
}

interface TranscriptVenda {
  id: number;
  invoice_no: string;
  transaction_date: string;
  final_total: number;
  total_paid: number;
  payment_status: string;
  customer_name: string | null;
  customer_secondary?: string | null;
  customer_doc?: string | null;
  seller_name?: string | null;
  lines: TranscriptLine[];
  payments: TranscriptPayment[];
  fiscal_label?: string | null;
  fiscal_numero?: string | null;
  fiscal_serie?: string | null;
  fiscal_chave?: string | null;
  additional_notes?: string | null;
  business_name?: string | null;
  business_cnpj?: string | null;
}

interface Props {
  venda: TranscriptVenda;
  open: boolean;
  onClose: () => void;
}

const fmt = (n: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);

const fmtDate = (iso: string): string => {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString('pt-BR');
};

const fmtChave = (k: string | null | undefined): string => {
  if (!k) return '';
  return k.replace(/(\d{4})/g, '$1 ').trim();
};

const STATUS_LABEL: Record<string, string> = {
  paid: 'PAGA',
  partial: 'PARCIAL',
  due: 'PENDENTE',
};

export default function SaleTranscriptPDF({ venda, open, onClose }: Props): ReactNode {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      if ((e.metaKey || e.ctrlKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  const itemsTotal = venda.lines.reduce((s, l) => s + l.subtotal, 0);
  const status = STATUS_LABEL[venda.payment_status] ?? venda.payment_status.toUpperCase();

  return (
    <div className="vd-transcript-bd" onClick={onClose}>
      <div className="vd-transcript-toolbar">
        <button
          type="button"
          className="vd-transcript-print"
          onClick={() => window.print()}
          title="Imprimir (Ctrl+P)"
        >
          <Printer size={14} />
          Imprimir
        </button>
        <button
          type="button"
          className="vd-transcript-close"
          onClick={onClose}
          title="Fechar (Esc)"
          aria-label="Fechar transcript"
        >
          <X size={14} />
        </button>
      </div>

      <div className="vd-transcript-page" onClick={(e) => e.stopPropagation()}>
        {/* Header brand */}
        <header className="vd-tr-h">
          <div className="vd-tr-h-l">
            <h1>{venda.business_name ?? 'Oimpresso'}</h1>
            {venda.business_cnpj && (
              <small>CNPJ {venda.business_cnpj}</small>
            )}
          </div>
          <div className="vd-tr-h-r">
            <h2>Transcript de venda</h2>
            <p>
              #{venda.invoice_no} · {fmtDate(venda.transaction_date)} · {status}
            </p>
          </div>
        </header>

        {/* 4-grid info */}
        <div className="vd-tr-grid">
          <div>
            <small>CLIENTE</small>
            <b>{venda.customer_name ?? 'Consumidor Final'}</b>
            {venda.customer_secondary && <span>{venda.customer_secondary}</span>}
            {venda.customer_doc && <span>{venda.customer_doc}</span>}
          </div>
          <div>
            <small>ATENDIDO POR</small>
            <b>{venda.seller_name ?? '—'}</b>
          </div>
          <div>
            <small>PAGAMENTO</small>
            <b>{venda.payments[0]?.method ?? '—'}</b>
            <span>{fmt(venda.total_paid)} pago</span>
          </div>
          <div>
            <small>TOTAL</small>
            <b className="vd-tr-total">{fmt(venda.final_total)}</b>
          </div>
        </div>

        {/* Itens table */}
        <table className="vd-tr-items">
          <thead>
            <tr>
              <th>Produto / serviço</th>
              <th className="num">Qtde</th>
              <th className="num">Unitário</th>
              <th className="num">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            {venda.lines.map((l) => (
              <tr key={l.id}>
                <td>
                  <div>{l.product_name ?? '—'}</div>
                  {l.product_sku && <small>{l.product_sku}</small>}
                </td>
                <td className="num">{l.quantity}</td>
                <td className="num">{fmt(l.unit_price)}</td>
                <td className="num">{fmt(l.subtotal)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={3} className="num">
                <b>Total</b>
              </td>
              <td className="num">
                <b>{fmt(itemsTotal)}</b>
              </td>
            </tr>
          </tfoot>
        </table>

        {/* Fiscal */}
        {venda.fiscal_chave && (
          <div className="vd-tr-fiscal">
            <h3>Documento fiscal</h3>
            <p>
              <b>{venda.fiscal_label ?? 'NF-e'}</b> nº {venda.fiscal_numero}/{venda.fiscal_serie ?? '1'}
            </p>
            <code className="vd-tr-chave">{fmtChave(venda.fiscal_chave)}</code>
          </div>
        )}

        {/* Notas */}
        {venda.additional_notes && (
          <div className="vd-tr-notes">
            <h3>Observações</h3>
            <p>{venda.additional_notes}</p>
          </div>
        )}

        {/* Assinaturas */}
        <div className="vd-tr-sigs">
          <div className="vd-tr-sig">
            <span className="vd-tr-sig-line" />
            <small>Cliente</small>
          </div>
          <div className="vd-tr-sig">
            <span className="vd-tr-sig-line" />
            <small>Atendente {venda.seller_name ? `(${venda.seller_name})` : ''}</small>
          </div>
        </div>

        {/* Footer */}
        <footer className="vd-tr-f">
          <small>
            Emitido em {fmtDate(new Date().toISOString())} via Oimpresso ERP. Documento não-fiscal —
            apenas comprovante operacional.
          </small>
        </footer>
      </div>
    </div>
  );
}

export type { TranscriptVenda };
