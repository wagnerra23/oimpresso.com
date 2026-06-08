// SaleReciboPrint80mm — Recibo térmico 80mm imprimível.
// Refs: Cowork KB-9.75 bundle 2026-05-26 P2 gap #8 (recibo térmico).
//        prototipo-ui project/vendas-flow.jsx:899 VdReceiptThermal (canon visual)
//        prototipo-ui project/vendas.css:2666-2930 (.vd-receipt-* + .vd-rcp-* + @page 80mm)
//        memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md
//
// Componente print-only: visível só durante window.print() — wrapper recebe className
// `sells-cowork` no Show.tsx, e este componente só renderiza quando `printMode === 'recibo-80mm'`.
// CSS escopa `.sells-cowork .vd-receipt-paper` + `@media print { body.vd-print-receipt … }`
// pra esconder o resto da app (sidebar, header, painéis FSM) durante a impressão.
//
// Fallbacks de empresa (CNPJ/endereço/telefone) são hardcoded — Wagner personaliza depois
// via settings (não-bloqueante pra demo). Multi-tenant Tier 0 herda do Show.tsx.

import { useEffect } from 'react';

interface SaleLine {
  id: number;
  product_name: string;
  product_sku: string;
  quantity: number;
  unit_price: number;
  discount: number;
  subtotal: number;
  tax_amount: number;
  unit: string;
}

interface SalePayment {
  id: number;
  amount: number;
  method: string;
  paid_on: string | null;
  note: string | null;
}

interface Customer {
  id: number;
  name: string;
  mobile: string | null;
  email: string | null;
}

interface Headline {
  id: number;
  invoice_no: string;
  transaction_date: string;
  final_total: number;
  total_paid: number;
  payment_status: 'paid' | 'due' | 'partial' | string;
  customer: Customer | null;
  location: { id: number; name: string } | null;
}

interface Detail {
  lines: SaleLine[];
  payments: SalePayment[];
}

interface CompanyInfo {
  name: string;
  tagline: string;
  cnpj: string;
  address: string;
  phone: string;
}

interface Props {
  headline: Headline;
  detail: Detail;
  /** Callback acionado quando o usuário clica em Fechar ou pressiona Esc */
  onClose: () => void;
  /** Override opcional de dados da empresa — default vem dos fallbacks hardcoded */
  company?: Partial<CompanyInfo>;
}

const DEFAULT_COMPANY: CompanyInfo = {
  name: 'OIMPRESSO',
  tagline: 'Comunicação Visual',
  cnpj: 'CNPJ 12.345.678/0001-90',
  address: 'Rua Exemplo 100 · São Paulo/SP',
  phone: '(11) 4002-8922',
};

const PAYMENT_METHOD_LABEL: Record<string, string> = {
  cash: 'Dinheiro',
  card: 'Cartão',
  bank_transfer: 'Transferência',
  custom_pay_1: 'PIX',
  custom_pay_2: 'Boleto',
};

function fmtBRL(n: number): string {
  return (n || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function fmtCompact(n: number): string {
  return (n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDateBR(input: string): string {
  if (!input) return '—';
  const d = new Date(input);
  if (Number.isNaN(d.getTime())) return input;
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yy = d.getFullYear();
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  return `${dd}/${mm}/${yy} ${hh}:${mi}`;
}

export default function SaleReciboPrint80mm({ headline, detail, onClose, company }: Props) {
  const empresa: CompanyInfo = { ...DEFAULT_COMPANY, ...(company ?? {}) };

  // Escape fecha + adiciona classe no body pra @media print esconder o resto
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
      }
    };
    window.addEventListener('keydown', onKey);
    document.body.classList.add('vd-print-receipt');
    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.classList.remove('vd-print-receipt');
    };
  }, [onClose]);

  const lines = detail.lines ?? [];
  const subtotal = lines.reduce((s, l) => s + l.quantity * l.unit_price, 0);
  const desconto = lines.reduce((s, l) => s + (l.discount || 0), 0);
  const total = headline.final_total ?? subtotal - desconto;

  // primeiro payment como "forma" exibida (caso múltiplos → usa rótulo do primeiro)
  const primaryPayment = detail.payments?.[0];
  const formaPagamento = primaryPayment
    ? PAYMENT_METHOD_LABEL[primaryPayment.method] ?? primaryPayment.method
    : '—';

  return (
    <div className="vd-receipt-bd" onClick={onClose}>
      <div className="vd-receipt-toolbar">
        <button type="button" className="os-btn ghost" onClick={onClose}>
          ← Fechar
        </button>
        <span className="vd-receipt-tag">Recibo térmico · #{headline.invoice_no}</span>
        <button type="button" className="os-btn primary" onClick={() => window.print()}>
          ⎙ Imprimir
        </button>
      </div>

      <div className="vd-receipt-paper" onClick={(e) => e.stopPropagation()}>
        {/* HEADER */}
        <div className="vd-rcp-h">
          <div className="vd-rcp-brand">{empresa.name}</div>
          <div className="vd-rcp-sub">{empresa.tagline}</div>
          <div className="vd-rcp-meta">
            {empresa.cnpj}
            <br />
            {empresa.address}
            <br />
            {empresa.phone}
          </div>
        </div>

        <div className="vd-rcp-sep" />

        {/* INFO VENDA */}
        <div className="vd-rcp-info">
          <div className="vd-rcp-row">
            <span>VENDA</span>
            <b>#{headline.invoice_no}</b>
          </div>
          <div className="vd-rcp-row">
            <span>DATA</span>
            <b>{fmtDateBR(headline.transaction_date)}</b>
          </div>
          {headline.location && (
            <div className="vd-rcp-row">
              <span>LOJA</span>
              <b>{headline.location.name}</b>
            </div>
          )}
          {headline.customer && (
            <div className="vd-rcp-row">
              <span>CLIENTE</span>
              <b>{headline.customer.name}</b>
            </div>
          )}
        </div>

        <div className="vd-rcp-sep" />

        {/* ITENS */}
        <div className="vd-rcp-itens">
          <div className="vd-rcp-itens-h">
            <span>ITEM</span>
            <span>VL.UNIT</span>
            <span>TOTAL</span>
          </div>
          {lines.map((l) => (
            <div key={l.id} className="vd-rcp-item">
              <div className="vd-rcp-item-name">{l.product_name}</div>
              <div className="vd-rcp-item-row">
                <span className="vd-rcp-item-qty">
                  {l.quantity}× {fmtCompact(l.unit_price)}
                </span>
                <span className="vd-rcp-item-tot">{fmtCompact(l.quantity * l.unit_price)}</span>
              </div>
            </div>
          ))}
        </div>

        <div className="vd-rcp-sep" />

        {/* TOTAIS */}
        <div className="vd-rcp-totais">
          <div className="vd-rcp-row">
            <span>SUBTOTAL</span>
            <b>{fmtBRL(subtotal)}</b>
          </div>
          {desconto > 0 && (
            <div className="vd-rcp-row">
              <span>DESCONTO</span>
              <b>-{fmtBRL(desconto)}</b>
            </div>
          )}
          <div className="vd-rcp-row vd-rcp-row-total">
            <span>TOTAL</span>
            <b>{fmtBRL(total)}</b>
          </div>
        </div>

        <div className="vd-rcp-sep" />

        {/* PAGAMENTO */}
        <div className="vd-rcp-pgto">
          <div className="vd-rcp-row">
            <span>PAGAMENTO</span>
            <b>{formaPagamento}</b>
          </div>
          {detail.payments && detail.payments.length > 1 && (
            <div className="vd-rcp-row">
              <span>LANÇAMENTOS</span>
              <b>{detail.payments.length}</b>
            </div>
          )}
          <div className="vd-rcp-row">
            <span>PAGO</span>
            <b>{fmtBRL(headline.total_paid)}</b>
          </div>
        </div>

        <div className="vd-rcp-sep" />

        {/* FOOTER */}
        <div className="vd-rcp-foot">
          <div className="vd-rcp-thanks">Obrigado pela preferência!</div>
          <small className="vd-rcp-disclaimer">
            Este documento NÃO é um documento fiscal.
          </small>
          <div className="vd-rcp-stamp">
            Impresso em{' '}
            {new Date().toLocaleDateString('pt-BR')}{' '}
            {new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
          </div>
        </div>
      </div>
    </div>
  );
}
