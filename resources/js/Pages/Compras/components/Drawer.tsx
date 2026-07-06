// Drawer.tsx — DrawerView 5 tabs sobre grid (US-COM-001 Wave 5).
// Pin literal do protótipo prototipo-ui/cowork/compras-page.jsx
// (DrawerView linhas 195-431). Substitui DrawerSimple do Index.tsx.
//
// Tabs: Resumo / Itens / Documentos / Pagamentos / Histórico
// FSM stepper 6 estágios highlight current.

import { useMemo, useState } from 'react';

type Stage = 'rascunho' | 'pedido' | 'transito' | 'recebido' | 'conferido' | 'pago' | 'cancelada';

const STAGES: { id: Stage; l: string; ic: string }[] = [
  { id: 'rascunho', l: 'Rascunho', ic: '○' },
  { id: 'pedido', l: 'Pedido', ic: '✎' },
  { id: 'transito', l: 'Em trânsito', ic: '⇨' },
  { id: 'recebido', l: 'Recebido', ic: '⊞' },
  { id: 'conferido', l: 'Conferido', ic: '✓' },
  { id: 'pago', l: 'Pago', ic: '$' },
];

const TABS = [
  { id: 'resumo', l: 'Resumo' },
  { id: 'itens', l: 'Itens' },
  { id: 'documentos', l: 'Documentos' },
  { id: 'pagamentos', l: 'Pagamentos' },
  { id: 'historico', l: 'Histórico' },
] as const;

type TabId = (typeof TABS)[number]['id'];

export interface CompraDetalhe {
  id: number;
  ref_no: string | null;
  document: string | null;
  transaction_date: string | null;
  type: string;
  status: Stage | string;
  payment_status: string;
  final_total: number;
  total_before_tax: number;
  tax_amount: number;
  discount_amount: number;
  shipping_charges: number;
  pay_term_number: number | null;
  pay_term_type: string | null;
  additional_notes: string | null;
  amount_paid: number;
  amount_due: number;
  contact: {
    id: number;
    name: string | null;
    supplier_business_name: string | null;
    tax_number: string | null;
    city: string | null;
    mobile: string | null;
    email: string | null;
  } | null;
  location: { id: number; name: string } | null;
  lines: Array<{
    id: number;
    product_name: string;
    product_sku: string | null;
    variation_name: string | null;
    quantity: number;
    unit_name: string | null;
    purchase_price: number;
    purchase_price_inc_tax: number;
    item_tax: number;
    line_total: number;
    lot_number: string | null;
  }>;
  payments: Array<{
    id: number;
    paid_on: string | null;
    amount: number;
    method: string | null;
    card_transaction_number: string | null;
    cheque_number: string | null;
    bank_account_number: string | null;
    note: string | null;
    is_return: boolean;
  }>;
  timeline: Array<{
    id: number;
    description: string;
    causer_name: string;
    created_at: string | null;
    properties: Record<string, unknown> | null;
  }>;
}

interface DrawerProps {
  compra: CompraDetalhe;
  onClose: () => void;
  /** Tab inicial. Default 'resumo'. Usado por "Ver pagamentos" pra abrir já no tab certo. */
  initialTab?: TabId;
}

function fmtMoney(v: number | null | undefined): string {
  return 'R$ ' + Number(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d: string | null): string {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function fmtDateTime(d: string | null): string {
  if (!d) return '—';
  return new Date(d).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function stageIdx(s: string): number {
  return STAGES.findIndex((x) => x.id === s);
}

function methodLabel(m: string | null): string {
  if (!m) return '—';
  const map: Record<string, string> = {
    cash: 'Dinheiro',
    card: 'Cartão',
    cheque: 'Cheque',
    bank_transfer: 'Transferência',
    other: 'Outro',
    pix: 'PIX',
    advance: 'Adiantamento',
    custom_pay_1: 'Boleto',
    custom_pay_2: 'Crediário',
    custom_pay_3: 'Outro',
  };
  return map[m.toLowerCase()] ?? m;
}

function methodAbbr(m: string | null): string {
  if (!m) return '?';
  const map: Record<string, string> = {
    cash: '$',
    pix: 'PIX',
    card: 'C',
    cheque: 'CHQ',
    bank_transfer: 'TED',
    custom_pay_1: 'BL',
    custom_pay_2: 'CR',
  };
  return map[m.toLowerCase()] ?? m.slice(0, 3).toUpperCase();
}

export default function Drawer({ compra, onClose, initialTab = 'resumo' }: DrawerProps) {
  const [tab, setTab] = useState<TabId>(initialTab);
  const idx = stageIdx(compra.status);

  const supplierName = compra.contact?.supplier_business_name || compra.contact?.name || 'Sem fornecedor';
  const itemCount = compra.lines.length;
  const paid = compra.amount_paid;
  const due = compra.amount_due;
  const subtotal = compra.total_before_tax || compra.final_total;
  const payTermLabel = compra.pay_term_number
    ? `${compra.pay_term_number} ${compra.pay_term_type === 'months' ? 'meses' : 'dias'}`
    : null;

  const itemsTabs = useMemo(
    () => [
      { id: 'resumo' as TabId, l: 'Resumo', ct: null },
      { id: 'itens' as TabId, l: 'Itens', ct: itemCount },
      { id: 'documentos' as TabId, l: 'Documentos', ct: null },
      { id: 'pagamentos' as TabId, l: 'Pagamentos', ct: compra.payments.length },
      { id: 'historico' as TabId, l: 'Histórico', ct: compra.timeline.length },
    ],
    [itemCount, compra.payments.length, compra.timeline.length]
  );

  return (
    <aside className="drawer">
      <div className="drw-head">
        <div>
          <h2>{supplierName}</h2>
          <div style={{ fontSize: 11.5, color: 'var(--cmp-ink-3)', marginTop: 2 }}>
            {itemCount} ite{itemCount === 1 ? 'm' : 'ns'} · {compra.location?.name ?? '—'}
            {payTermLabel ? ` · ${payTermLabel}` : ''}
          </div>
          <span className="mono">
            #{compra.id}
            {compra.ref_no ? ` · ${compra.ref_no}` : ''}
          </span>
        </div>
        <button className="x" onClick={onClose} aria-label="Fechar drawer">
          ✕
        </button>
      </div>

      <div className="fsm">
        <div className="fsm-track">
          {STAGES.map((st, i) => (
            <div
              key={st.id}
              className={`fsm-step ${i < idx ? 'done' : i === idx ? 'now' : ''}`}
              title={st.l}
            >
              <span className="ic">{st.ic}</span>
              {st.l}
            </div>
          ))}
        </div>
      </div>

      <div className="drw-tabs">
        {itemsTabs.map((t) => (
          <button
            key={t.id}
            className={tab === t.id ? 'active' : ''}
            onClick={() => setTab(t.id)}
          >
            {t.l}
            {t.ct != null && t.ct > 0 && <span className="ct">{t.ct}</span>}
          </button>
        ))}
      </div>

      <div className="drw-body">
        {tab === 'resumo' && <ResumoTab compra={compra} subtotal={subtotal} paid={paid} due={due} />}
        {tab === 'itens' && <ItensTab compra={compra} />}
        {tab === 'documentos' && <DocumentosTab compra={compra} />}
        {tab === 'pagamentos' && <PagamentosTab compra={compra} due={due} />}
        {tab === 'historico' && <HistoricoTab compra={compra} />}
      </div>

      <div className="drw-foot">
        <div className="total">
          Total da compra
          <b>{fmtMoney(compra.final_total)}</b>
        </div>
        <button className="btn ghost" onClick={onClose}>
          Fechar
        </button>
      </div>
    </aside>
  );
}

function ResumoTab({
  compra,
  subtotal,
  paid,
  due,
}: {
  compra: CompraDetalhe;
  subtotal: number;
  paid: number;
  due: number;
}) {
  return (
    <>
      <div className="sec">
        <h4>Fornecedor</h4>
        <div className="card">
          {compra.contact ? (
            <div className="field-grid">
              <div className="f">
                <label>CNPJ/CPF</label>
                <span className="mono">{compra.contact.tax_number || '—'}</span>
              </div>
              <div className="f">
                <label>Cidade</label>
                <span>{compra.contact.city || '—'}</span>
              </div>
              {compra.contact.mobile && (
                <div className="f">
                  <label>Telefone</label>
                  <span className="mono">{compra.contact.mobile}</span>
                </div>
              )}
              {compra.contact.email && (
                <div className="f">
                  <label>E-mail</label>
                  <span>{compra.contact.email}</span>
                </div>
              )}
            </div>
          ) : (
            <div style={{ color: 'var(--cmp-ink-3)', fontSize: 12 }}>Sem fornecedor associado</div>
          )}
        </div>
      </div>

      <div className="sec">
        <h4>Dados da compra</h4>
        <div className="card">
          <div className="field-grid">
            <div className="f">
              <label>Ref. interna</label>
              <span className="mono">{compra.ref_no || '—'}</span>
            </div>
            <div className="f">
              <label>Data emissão</label>
              <span className="mono">{fmtDate(compra.transaction_date)}</span>
            </div>
            <div className="f">
              <label>Local</label>
              <span>{compra.location?.name ?? '—'}</span>
            </div>
            <div className="f">
              <label>Cond. pagamento</label>
              <span>
                {compra.pay_term_number
                  ? `${compra.pay_term_number} ${compra.pay_term_type === 'months' ? 'meses' : 'dias'}`
                  : '—'}
              </span>
            </div>
            <div className="f">
              <label>Desconto</label>
              <span className="mono">{fmtMoney(compra.discount_amount)}</span>
            </div>
            <div className="f">
              <label>Frete</label>
              <span className="mono">{fmtMoney(compra.shipping_charges)}</span>
            </div>
          </div>
        </div>
      </div>

      {compra.additional_notes && (
        <div className="sec">
          <h4>Observações</h4>
          <div
            className="card"
            style={{ fontSize: 12, color: 'var(--cmp-ink-2)', lineHeight: 1.5, background: '#fff' }}
          >
            {compra.additional_notes}
          </div>
        </div>
      )}

      <div className="sec">
        <h4>Resumo financeiro</h4>
        <div className="card" style={{ padding: 0 }}>
          <table style={{ width: '100%', fontSize: 12 }}>
            <tbody>
              <tr>
                <td style={{ padding: '6px 13px', color: 'var(--cmp-ink-3)' }}>Subtotal</td>
                <td style={{ padding: '6px 13px', textAlign: 'right', fontFamily: 'var(--cmp-mono)' }}>
                  {fmtMoney(subtotal)}
                </td>
              </tr>
              {compra.discount_amount > 0 && (
                <tr>
                  <td style={{ padding: '6px 13px', color: 'var(--cmp-ink-3)' }}>Desconto</td>
                  <td
                    style={{
                      padding: '6px 13px',
                      textAlign: 'right',
                      fontFamily: 'var(--cmp-mono)',
                      color: 'var(--cmp-err)',
                    }}
                  >
                    −{fmtMoney(compra.discount_amount)}
                  </td>
                </tr>
              )}
              {compra.shipping_charges > 0 && (
                <tr>
                  <td style={{ padding: '6px 13px', color: 'var(--cmp-ink-3)' }}>Frete</td>
                  <td style={{ padding: '6px 13px', textAlign: 'right', fontFamily: 'var(--cmp-mono)' }}>
                    +{fmtMoney(compra.shipping_charges)}
                  </td>
                </tr>
              )}
              {compra.tax_amount > 0 && (
                <tr>
                  <td style={{ padding: '6px 13px', color: 'var(--cmp-ink-3)' }}>Impostos</td>
                  <td style={{ padding: '6px 13px', textAlign: 'right', fontFamily: 'var(--cmp-mono)' }}>
                    {fmtMoney(compra.tax_amount)}
                  </td>
                </tr>
              )}
              <tr style={{ borderTop: '1px solid var(--cmp-line)' }}>
                <td style={{ padding: '8px 13px', fontWeight: 700 }}>Total da compra</td>
                <td
                  style={{
                    padding: '8px 13px',
                    textAlign: 'right',
                    fontFamily: 'var(--cmp-mono)',
                    fontWeight: 700,
                    fontSize: 14,
                  }}
                >
                  {fmtMoney(compra.final_total)}
                </td>
              </tr>
              <tr>
                <td style={{ padding: '6px 13px', color: 'var(--cmp-ok)' }}>Pago</td>
                <td
                  style={{
                    padding: '6px 13px',
                    textAlign: 'right',
                    fontFamily: 'var(--cmp-mono)',
                    color: 'var(--cmp-ok)',
                  }}
                >
                  {fmtMoney(paid)}
                </td>
              </tr>
              <tr>
                <td style={{ padding: '6px 13px', color: 'var(--cmp-warn)', fontWeight: 600 }}>A pagar</td>
                <td
                  style={{
                    padding: '6px 13px',
                    textAlign: 'right',
                    fontFamily: 'var(--cmp-mono)',
                    fontWeight: 700,
                    color: 'var(--cmp-warn)',
                  }}
                >
                  {fmtMoney(due)}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}

function ItensTab({ compra }: { compra: CompraDetalhe }) {
  if (compra.lines.length === 0) {
    return (
      <div className="sec">
        <h4>Itens recebidos</h4>
        <div
          className="card"
          style={{ textAlign: 'center', color: 'var(--cmp-ink-3)', fontSize: 11.5, padding: 18 }}
        >
          Esta compra não tem linhas cadastradas.
        </div>
      </div>
    );
  }

  return (
    <div className="sec">
      <h4>
        Itens recebidos <span className="badge">{compra.lines.length}</span>
      </h4>
      <table className="items-tbl">
        <thead>
          <tr>
            <th>Produto</th>
            <th className="num">Qtd</th>
            <th className="num">Custo unit.</th>
            <th className="num">Total</th>
          </tr>
        </thead>
        <tbody>
          {compra.lines.map((it) => (
            <tr key={it.id}>
              <td>
                <b>{it.product_name}</b>
                <small>
                  {it.product_sku ? it.product_sku : '—'}
                  {it.variation_name ? ` · ${it.variation_name}` : ''}
                  {it.lot_number ? ` · lote ${it.lot_number}` : ''}
                </small>
              </td>
              <td className="num">
                {it.quantity}
                {it.unit_name && (
                  <small style={{ textAlign: 'right' }}>{it.unit_name}</small>
                )}
              </td>
              <td className="num">{fmtMoney(it.purchase_price_inc_tax)}</td>
              <td className="num">
                <b>{fmtMoney(it.line_total)}</b>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function DocumentosTab({ compra }: { compra: CompraDetalhe }) {
  const xmlChave = compra.document; // UPos guarda chave NF-e em `document` quando há

  if (!xmlChave) {
    return (
      <div
        className="card"
        style={{ textAlign: 'center', padding: 24, color: 'var(--cmp-ink-3)' }}
      >
        <div style={{ fontSize: 24, marginBottom: 8 }}>⊠</div>
        <b style={{ color: 'var(--cmp-ink-2)', fontSize: 13, display: 'block', marginBottom: 3 }}>
          Nenhuma NF-e vinculada
        </b>
        <small style={{ fontSize: 11 }}>Importe o XML pela tela Fiscal (Wave 6 vai trazer atalho aqui)</small>
      </div>
    );
  }

  return (
    <div className="sec">
      <h4>NF-e de entrada</h4>
      <div className="xml-badge">
        <span style={{ fontSize: 18 }}>⊞</span>
        <div style={{ flex: 1 }}>
          <b>{compra.ref_no ? `NF-e ${compra.ref_no}` : 'NF-e vinculada'}</b>
          <div style={{ fontSize: 10.5, marginTop: 3 }}>chave de acesso</div>
          <div className="key">{xmlChave}</div>
        </div>
      </div>
      <div
        style={{ fontSize: 11, color: 'var(--cmp-ink-3)', marginTop: 8, lineHeight: 1.5 }}
      >
        Download de XML / DANFE e manifestação fiscal ficam na tela Fiscal. Wave 6 traz atalhos integrados aqui.
      </div>
    </div>
  );
}

function PagamentosTab({ compra, due }: { compra: CompraDetalhe; due: number }) {
  if (compra.payments.length === 0) {
    return (
      <div className="sec">
        <h4>Pagamentos</h4>
        <div className="card">
          <div style={{ textAlign: 'center', color: 'var(--cmp-ink-3)', fontSize: 12, padding: 14 }}>
            {due > 0 ? `Sem pagamentos lançados · resta ${fmtMoney(due)} a pagar` : 'Sem pagamentos lançados'}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="sec">
      <h4>Pagamentos</h4>
      <div className="card">
        {compra.payments.map((p) => (
          <div key={p.id} className="pay-row">
            <div
              className="pay-icon"
              style={{
                background: p.is_return ? 'var(--cmp-err-soft)' : 'var(--cmp-ok-soft)',
                color: p.is_return ? 'var(--cmp-err)' : 'var(--cmp-ok)',
              }}
            >
              {methodAbbr(p.method)}
            </div>
            <div>
              <b>
                {p.is_return ? 'Estorno ' : ''}
                {methodLabel(p.method)}
              </b>
              <small>
                {fmtDateTime(p.paid_on)}
                {p.cheque_number ? ` · cheque ${p.cheque_number}` : ''}
                {p.bank_account_number ? ` · conta ${p.bank_account_number}` : ''}
                {p.card_transaction_number ? ` · doc ${p.card_transaction_number}` : ''}
              </small>
              {p.note && (
                <small style={{ marginTop: 2, color: 'var(--cmp-ink-2)' }}>{p.note}</small>
              )}
            </div>
            <span className={`val ${p.is_return ? 'due' : 'paid'}`}>{fmtMoney(p.amount)}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function HistoricoTab({ compra }: { compra: CompraDetalhe }) {
  if (compra.timeline.length === 0) {
    return (
      <div className="sec">
        <h4>Linha do tempo</h4>
        <div
          className="card"
          style={{ textAlign: 'center', color: 'var(--cmp-ink-3)', fontSize: 12, padding: 18 }}
        >
          Sem eventos registrados
        </div>
      </div>
    );
  }

  return (
    <div className="sec">
      <h4>Linha do tempo</h4>
      <div className="tl">
        {compra.timeline.map((e) => (
          <div key={e.id} className="tl-item ok">
            <b>{e.description}</b>
            <div className="when">
              {fmtDateTime(e.created_at)} · {e.causer_name}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
