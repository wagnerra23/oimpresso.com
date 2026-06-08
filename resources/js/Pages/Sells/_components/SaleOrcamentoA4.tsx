// SaleOrcamentoA4 — Proposta comercial A4 imprimível.
// Refs: Cowork KB-9.75 bundle 2026-05-26 P2 gap #9 (orçamento A4 formal).
//        prototipo-ui project/vendas-flow.jsx:1228 VdOrcamentoPrint (canon visual)
//        prototipo-ui project/vendas.css:3016-3347 (.vd-orc-* + @page A4)
//        memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md
//
// Diferente do recibo térmico (80mm) e da NF-e fiscal — é a proposta comercial enviada ao
// cliente ANTES da venda virar pedido. Layout A4 (210mm × 297mm), validade 7 dias padrão.
// Número Q-XXXX deriva do invoice_no da venda.
//
// Componente print-only: visível só durante window.print() — wrapper recebe className
// `sells-cowork` no Show.tsx, e este componente só renderiza quando `printMode === 'orcamento-a4'`.
// CSS escopa `.sells-cowork .vd-orc-page` + `@media print { body.vd-print-orc … }`.
//
// Fallbacks de empresa/destinatário são hardcoded — Wagner personaliza depois via settings.
// Multi-tenant Tier 0 herda do Show.tsx.

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
  customer: Customer | null;
  location: { id: number; name: string } | null;
}

interface Detail {
  lines: SaleLine[];
}

interface CompanyInfo {
  name: string;
  tagline: string;
  cnpj: string;
  ie: string;
  address: string;
  phone: string;
  email: string;
  website: string;
}

interface Props {
  headline: Headline;
  detail: Detail;
  /** Callback acionado quando o usuário clica em Fechar ou pressiona Esc */
  onClose: () => void;
  /** Override opcional de dados da empresa */
  company?: Partial<CompanyInfo>;
  /** Validade em dias da proposta (default 7) */
  validadeDias?: number;
}

const DEFAULT_COMPANY: CompanyInfo = {
  name: 'OIMPRESSO',
  tagline: 'Comunicação Visual',
  cnpj: 'CNPJ 12.345.678/0001-90',
  ie: 'IE 123.456.789.012',
  address: 'Rua Exemplo 100, São Paulo/SP · 01310-100',
  phone: '(11) 4002-8922',
  email: 'contato@oimpresso.com.br',
  website: 'oimpresso.com.br',
};

function fmtBRL(n: number): string {
  return (n || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function fmtDt(d: Date): string {
  return d.toLocaleDateString('pt-BR');
}

export default function SaleOrcamentoA4({
  headline,
  detail,
  onClose,
  company,
  validadeDias = 7,
}: Props) {
  const empresa: CompanyInfo = { ...DEFAULT_COMPANY, ...(company ?? {}) };

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
      }
    };
    window.addEventListener('keydown', onKey);
    document.body.classList.add('vd-print-orc');
    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.classList.remove('vd-print-orc');
    };
  }, [onClose]);

  const lines = detail.lines ?? [];
  const subtotal = lines.reduce((s, l) => s + l.quantity * l.unit_price, 0);
  const desconto = lines.reduce((s, l) => s + (l.discount || 0), 0);
  const total = headline.final_total ?? subtotal - desconto;

  const today = new Date();
  const validade = new Date(today.getTime() + validadeDias * 86400000);

  // V-XXXX → Q-XXXX; sem prefixo V- usa invoice_no direto
  const orcNum = headline.invoice_no.startsWith('V-')
    ? headline.invoice_no.replace(/^V-/, 'Q-')
    : `Q-${headline.invoice_no}`;

  const clientName = headline.customer?.name ?? 'Consumidor Final';
  const clientPhone = headline.customer?.mobile ?? '—';
  const clientEmail = headline.customer?.email ?? '—';

  // Brand initials (2 letras) do nome da empresa pra logo
  const brandInitials = empresa.name.slice(0, 2);

  return (
    <div className="vd-orc-bd" onClick={onClose}>
      <div className="vd-orc-toolbar">
        <button type="button" className="os-btn ghost" onClick={onClose}>
          ← Fechar
        </button>
        <span className="vd-orc-tag">
          Orçamento {orcNum} · {clientName}
        </span>
        <button type="button" className="os-btn primary" onClick={() => window.print()}>
          ⎙ Imprimir / Salvar PDF
        </button>
      </div>

      <div className="vd-orc-page" onClick={(e) => e.stopPropagation()}>
        {/* HEADER */}
        <header className="vd-orc-h">
          <div className="vd-orc-brand-block">
            <div className="vd-orc-logo">{brandInitials}</div>
            <div>
              <div className="vd-orc-brand">{empresa.name}</div>
              <div className="vd-orc-brand-sub">{empresa.tagline}</div>
              <div className="vd-orc-brand-meta">
                {empresa.cnpj} · {empresa.ie}
                <br />
                {empresa.address}
                <br />
                {empresa.phone} · {empresa.email}
              </div>
            </div>
          </div>
          <div className="vd-orc-num-block">
            <div className="vd-orc-num-label">ORÇAMENTO</div>
            <div className="vd-orc-num">{orcNum}</div>
            <dl className="vd-orc-num-meta">
              <dt>Data emissão</dt>
              <dd>{fmtDt(today)}</dd>
              <dt>Válido até</dt>
              <dd className="vd-orc-validade">{fmtDt(validade)}</dd>
              {headline.location && (
                <>
                  <dt>Loja</dt>
                  <dd>{headline.location.name}</dd>
                </>
              )}
            </dl>
          </div>
        </header>

        {/* DESTINATÁRIO */}
        <section className="vd-orc-dest">
          <h3>PROPOSTA PARA</h3>
          <div className="vd-orc-dest-grid">
            <div>
              <span className="vd-orc-dest-lbl">Razão social</span>
              <b>{clientName}</b>
            </div>
            <div>
              <span className="vd-orc-dest-lbl">Telefone</span>
              <b>{clientPhone}</b>
            </div>
            <div>
              <span className="vd-orc-dest-lbl">E-mail</span>
              <b>{clientEmail}</b>
            </div>
            <div>
              <span className="vd-orc-dest-lbl">Venda ref.</span>
              <b>#{headline.invoice_no}</b>
            </div>
          </div>
        </section>

        {/* ITENS */}
        <section className="vd-orc-itens">
          <h3>ITENS DA PROPOSTA</h3>
          <table className="vd-orc-tbl">
            <thead>
              <tr>
                <th style={{ width: 32 }}>#</th>
                <th>Descrição</th>
                <th style={{ width: 60, textAlign: 'right' }}>Qtd</th>
                <th style={{ width: 90, textAlign: 'right' }}>Vl. unit.</th>
                <th style={{ width: 110, textAlign: 'right' }}>Total</th>
              </tr>
            </thead>
            <tbody>
              {lines.map((l, i) => (
                <tr key={l.id}>
                  <td className="vd-orc-tbl-n">{String(i + 1).padStart(2, '0')}</td>
                  <td>
                    <b>{l.product_name}</b>
                    {l.product_sku && (
                      <small className="vd-orc-tbl-sku">SKU {l.product_sku}</small>
                    )}
                  </td>
                  <td style={{ textAlign: 'right' }}>
                    {l.quantity} {l.unit}
                  </td>
                  <td style={{ textAlign: 'right' }}>{fmtBRL(l.unit_price)}</td>
                  <td style={{ textAlign: 'right' }} className="vd-orc-tbl-tot">
                    {fmtBRL(l.quantity * l.unit_price)}
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={4} className="vd-orc-tbl-foot-lbl">
                  Subtotal
                </td>
                <td style={{ textAlign: 'right' }}>{fmtBRL(subtotal)}</td>
              </tr>
              {desconto > 0 && (
                <tr>
                  <td colSpan={4} className="vd-orc-tbl-foot-lbl">
                    Desconto comercial
                  </td>
                  <td style={{ textAlign: 'right' }}>-{fmtBRL(desconto)}</td>
                </tr>
              )}
              <tr className="vd-orc-tbl-total-row">
                <td colSpan={4} className="vd-orc-tbl-foot-lbl">
                  TOTAL
                </td>
                <td style={{ textAlign: 'right' }}>{fmtBRL(total)}</td>
              </tr>
            </tfoot>
          </table>
        </section>

        {/* CONDIÇÕES */}
        <section className="vd-orc-cond">
          <h3>CONDIÇÕES COMERCIAIS</h3>
          <ul>
            <li>
              <b>Prazo de entrega:</b> 5 dias úteis após confirmação do pedido e aprovação
              da arte.
            </li>
            <li>
              <b>Forma de pagamento:</b> a combinar (PIX, cartão, boleto ou transferência).
            </li>
            <li>
              <b>Validade desta proposta:</b> {validadeDias} dias corridos a contar da data de
              emissão.
            </li>
            <li>
              <b>Arte e revisão:</b> 1 (uma) revisão inclusa. Revisões adicionais R$ 80,00
              cada.
            </li>
            <li>
              <b>Tributação:</b> valores já incluem impostos. Emissão de NF-e/NFS-e conforme
              natureza do item.
            </li>
            <li>
              <b>Cancelamento:</b> em caso de cancelamento após início da produção, será
              cobrado proporcional ao executado.
            </li>
          </ul>
        </section>

        {/* ASSINATURAS */}
        <section className="vd-orc-sign">
          <div className="vd-orc-sign-col">
            <div className="vd-orc-sign-line" />
            <div className="vd-orc-sign-lbl">
              <b>Aprovação do cliente</b>
              <small>{clientName}</small>
              <small>Data: ____ / ____ / ________</small>
            </div>
          </div>
          <div className="vd-orc-sign-col">
            <div className="vd-orc-sign-line" />
            <div className="vd-orc-sign-lbl">
              <b>{empresa.name} {empresa.tagline}</b>
              <small>Vendedor</small>
              <small>Data: {fmtDt(today)}</small>
            </div>
          </div>
        </section>

        {/* FOOTER */}
        <footer className="vd-orc-ft">
          <span>
            Orçamento {orcNum} · Emitido em {fmtDt(today)} · Página 1 de 1
          </span>
          <span>{empresa.website}</span>
        </footer>
      </div>
    </div>
  );
}
