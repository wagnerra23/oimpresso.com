// transaction-payment-page.jsx — Pagamento: Editar (PT-02) + Detalhe (PT-03). Cockpit V2.
// GERADO pelo designer-agente (ADR 0282 §0.1) ancorado no DS canon + no dominio REAL:
//   rotas/props  app/Http/Controllers/TransactionPaymentController.php (editInertia :850, showInertia :886)
//   metodos      app/Utils/Util.php::payment_types :196 — cash·card·cheque·bank_transfer·other + custom_pay_1..7
//   campos       Edit.charter.md §UX (form + condicionais por metodo) · Show.charter.md §UX (3 cards)
//   validacao    Edit.charter.md §Validacao cliente — amount > 0 · method · paid_on
// NAO derivado do .tsx vivo (porte reverso e proibido — §5 2026-06-05 / 2026-08-28).
// Token-driven (claro/escuro pelo host). Expoe window.TransactionPaymentPage.
(() => {
const { useState, useMemo } = React;

const Ic = ({ d, size = 14 }) => (
  <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
       strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">{d}</svg>
);
const TpI = {
  user:  <Ic d={<><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></>} />,
  money: <Ic d={<><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></>} />,
  file:  <Ic d={<><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></>} />,
  print: <Ic size={13} d={<><path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 18H4a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2"/></>} />,
  back:  <Ic size={13} d={<><path d="m15 18-6-6 6-6"/></>} />,
};

// metodos REAIS do Util::payment_types (advance fica de fora no Edit — o controller filtra)
const METODOS = {
  cash: 'Dinheiro', card: 'Cartao', cheque: 'Cheque',
  bank_transfer: 'Transferencia bancaria', other: 'Outro',
  custom_pay_1: 'Personalizado 1', custom_pay_2: 'Personalizado 2',
};

// contas do accountsDropdown
const CONTAS = [
  { id: '', label: 'Sem conta vinculada' },
  { id: 3, label: 'Sicoob PJ — c/c 12.345-6' },
  { id: 7, label: 'Caixa da loja' },
];

// a linha de pagamento e a transacao, no shape que o controller manda
const PAGAMENTO = {
  id: 8421, payment_ref_no: 'SP2026/0841', amount: 1284.5, method: 'card',
  paid_on: '2026-09-08', account_id: 3, note: 'Parcela 2 de 3 — maquininha na loja.',
  card_holder_name: 'L. A. MENEZES', card_number: '5432', card_transaction_number: 'A8842190',
  cheque_number: '', transaction_no: '', document_path: 'recibo-sp2026-0841.pdf',
};
const TRANSACAO = {
  ref_no: 'VD2026/1194', tipo: 'Venda', total: 3850.00, pago: 2565.50,
  contato: { nome: 'Mercado Uniao Ltda', doc: '33.112.001/0001-22',
             fone: '(48) 99812-4477', email: 'financeiro@exemplo.com.br' },
  local: 'Loja Centro',
};

const brl = (v) => 'R$ ' + (v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dataBR = (iso) => { const [a, m, d] = String(iso).split('-'); return d + '/' + m + '/' + a; };

// quais campos extras cada metodo pede — Edit.charter.md §Campos condicionais
const CONDICIONAIS = {
  card:          [['card_holder_name', 'Nome no cartao'], ['card_number', 'Ultimos 4 digitos'], ['card_transaction_number', 'Codigo da transacao']],
  cheque:        [['cheque_number', 'Numero do cheque']],
  bank_transfer: [['bank_account_number', 'Conta de origem']],
  custom_pay_1:  [['transaction_no', 'Numero da transacao']],
  custom_pay_2:  [['transaction_no', 'Numero da transacao']],
};

function Campo({ label, obrigatorio, hint, erro, children }) {
  return (
    <div className="tp-f">
      <label className="tp-lb">{label}{obrigatorio && <span className="tp-req">*</span>}</label>
      {children}
      {erro ? <span className="tp-err" role="alert">{erro}</span>
            : hint ? <span className="tp-hint">{hint}</span> : null}
    </div>
  );
}

// ── TELA 1 — Editar (PT-02) ──
function Editar() {
  const [metodo, setMetodo] = useState(PAGAMENTO.method);
  const [valor, setValor] = useState('1.284,50');
  const [data, setData] = useState(PAGAMENTO.paid_on);

  // validacao do charter: amount > 0 · method · paid_on
  const numero = useMemo(() => {
    const limpo = String(valor).replace(/\./g, '').replace(',', '.');
    return limpo === '' ? NaN : Number(limpo);
  }, [valor]);
  const erroValor = Number.isNaN(numero) ? 'Informe um valor.'
                  : numero <= 0 ? 'O valor precisa ser maior que zero.' : null;
  const erroData = !data ? 'Informe a data do pagamento.' : null;
  const podeSalvar = !erroValor && !erroData && !!metodo;

  const extras = CONDICIONAIS[metodo] || [];

  return (
    <React.Fragment>
      <div className="tp-head">
        <div className="tp-head-l">
          <h1 className="tp-h1">Editar pagamento</h1>
          <div className="tp-sub">{PAGAMENTO.payment_ref_no} · {TRANSACAO.tipo} {TRANSACAO.ref_no}</div>
        </div>
      </div>

      <div className="tp-grid">
        <div>
          <div className="tp-card">
            <div className="tp-card-h"><span style={{ color: 'var(--fg-3)' }}>{TpI.money}</span>
              <span className="tp-card-t">Dados do pagamento</span></div>
            <div className="tp-card-b">
              <div className="tp-fg">
                <Campo label="Forma de pagamento" obrigatorio>
                  <select className="tp-in" value={metodo} onChange={(e) => setMetodo(e.target.value)}>
                    {Object.entries(METODOS).map(([k, v]) => <option value={k} key={k}>{v}</option>)}
                  </select>
                </Campo>
                <Campo label="Data do pagamento" obrigatorio erro={erroData}>
                  <input className="tp-in" type="date" value={data} onChange={(e) => setData(e.target.value)} />
                </Campo>
                <Campo label="Valor" obrigatorio erro={erroValor}
                       hint="Duas casas decimais, virgula como separador.">
                  <input className="tp-in tp-money" value={valor} inputMode="decimal"
                         onChange={(e) => setValor(e.target.value)} aria-invalid={!!erroValor} />
                </Campo>
                <Campo label="Conta" hint="Opcional — vincula a movimentacao a uma conta.">
                  <select className="tp-in" defaultValue={PAGAMENTO.account_id}>
                    {CONTAS.map(c => <option value={c.id} key={String(c.id)}>{c.label}</option>)}
                  </select>
                </Campo>
                <div className="tp-f tp-f-full">
                  <label className="tp-lb">Observacao</label>
                  <textarea className="tp-in" defaultValue={PAGAMENTO.note} />
                </div>
              </div>

              {extras.length > 0 && (
                <div className="tp-cond">
                  <div className="tp-cond-t">Complemento de {METODOS[metodo]}</div>
                  <div className="tp-fg">
                    {extras.map(([campo, rotulo]) => (
                      <Campo label={rotulo} key={campo}>
                        <input className="tp-in" defaultValue={PAGAMENTO[campo] || ''} />
                      </Campo>
                    ))}
                  </div>
                </div>
              )}
            </div>
            <div className="tp-acts">
              <button className="tp-btn">Cancelar</button>
              <button className="tp-btn tp-btn-p" disabled={!podeSalvar}>Salvar</button>
            </div>
          </div>
        </div>

        <aside className="tp-rail">
          <div className="tp-card">
            <div className="tp-card-h"><span style={{ color: 'var(--fg-3)' }}>{TpI.user}</span>
              <span className="tp-card-t">{TRANSACAO.tipo}</span></div>
            <div className="tp-card-b">
              <dl style={{ margin: 0 }}>
                <div className="tp-kv"><dt>Contato</dt><dd>{TRANSACAO.contato.nome}</dd></div>
                <div className="tp-kv"><dt>Referencia</dt><dd>{TRANSACAO.ref_no}</dd></div>
                <div className="tp-kv"><dt>Local</dt><dd>{TRANSACAO.local}</dd></div>
                <div className="tp-kv tp-kv-forte"><dt>Total</dt><dd>{brl(TRANSACAO.total)}</dd></div>
                <div className="tp-kv"><dt>Ja pago</dt><dd>{brl(TRANSACAO.pago)}</dd></div>
                <div className="tp-kv"><dt>Em aberto</dt><dd>{brl(TRANSACAO.total - TRANSACAO.pago)}</dd></div>
              </dl>
            </div>
          </div>
        </aside>
      </div>
    </React.Fragment>
  );
}

// ── TELA 2 — Detalhe (PT-03) ──
function Detalhe() {
  const extras = CONDICIONAIS[PAGAMENTO.method] || [];
  return (
    <React.Fragment>
      <div className="tp-head">
        <div className="tp-head-l">
          <h1 className="tp-h1">Pagamento {PAGAMENTO.payment_ref_no}</h1>
          <div className="tp-sub">{TRANSACAO.tipo} {TRANSACAO.ref_no} · {dataBR(PAGAMENTO.paid_on)} · {TRANSACAO.local}</div>
        </div>
        <div className="tp-head-r no-print">
          <span className="tp-badge tp-badge-ok">Parcial</span>
          <button className="tp-btn">{TpI.back}Voltar</button>
          <button className="tp-btn">{TpI.print}Imprimir</button>
        </div>
      </div>

      <div className="tp-grid">
        <div>
          <div className="tp-card">
            <div className="tp-card-h"><span style={{ color: 'var(--fg-3)' }}>{TpI.money}</span>
              <span className="tp-card-t">Pagamento</span></div>
            <div className="tp-hero">
              <div className="tp-hero-lb">Valor pago</div>
              <div className="tp-hero-v">{brl(PAGAMENTO.amount)}</div>
              <div className="tp-hero-s">{METODOS[PAGAMENTO.method]} · {dataBR(PAGAMENTO.paid_on)}</div>
            </div>
            <div className="tp-card-b" style={{ borderTop: '1px solid var(--border)' }}>
              <dl style={{ margin: 0 }}>
                {extras.map(([campo, rotulo]) => (
                  <div className="tp-kv" key={campo}>
                    <dt>{rotulo}</dt><dd>{PAGAMENTO[campo] || '—'}</dd>
                  </div>
                ))}
                <div className="tp-kv"><dt>Observacao</dt><dd>{PAGAMENTO.note || '—'}</dd></div>
              </dl>
            </div>
          </div>

          <div className="tp-card">
            <div className="tp-card-h"><span style={{ color: 'var(--fg-3)' }}>{TpI.file}</span>
              <span className="tp-card-t">Comprovante anexo</span></div>
            <div className="tp-card-b">
              {PAGAMENTO.document_path ? (
                <div className="tp-doc">
                  <span style={{ color: 'var(--fg-3)' }}>{TpI.file}</span>
                  <span className="tp-doc-n">{PAGAMENTO.document_path}</span>
                  <button className="tp-btn no-print">Baixar</button>
                </div>
              ) : <div className="tp-vazio">Nenhum comprovante anexado a este pagamento.</div>}
            </div>
          </div>
        </div>

        <aside className="tp-rail">
          <div className="tp-card">
            <div className="tp-card-h"><span style={{ color: 'var(--fg-3)' }}>{TpI.user}</span>
              <span className="tp-card-t">Contato</span></div>
            <div className="tp-card-b">
              <dl style={{ margin: 0 }}>
                <div className="tp-kv tp-kv-forte"><dt>Nome</dt><dd>{TRANSACAO.contato.nome}</dd></div>
                <div className="tp-kv"><dt>CNPJ</dt><dd>{TRANSACAO.contato.doc}</dd></div>
                <div className="tp-kv"><dt>Telefone</dt><dd>{TRANSACAO.contato.fone}</dd></div>
                <div className="tp-kv"><dt>E-mail</dt><dd>{TRANSACAO.contato.email}</dd></div>
              </dl>
            </div>
          </div>
          <div className="tp-card">
            <div className="tp-card-h"><span className="tp-card-t">Situacao da {TRANSACAO.tipo.toLowerCase()}</span></div>
            <div className="tp-card-b">
              <dl style={{ margin: 0 }}>
                <div className="tp-kv tp-kv-forte"><dt>Total</dt><dd>{brl(TRANSACAO.total)}</dd></div>
                <div className="tp-kv"><dt>Ja pago</dt><dd>{brl(TRANSACAO.pago)}</dd></div>
                <div className="tp-kv"><dt>Em aberto</dt><dd>{brl(TRANSACAO.total - TRANSACAO.pago)}</dd></div>
              </dl>
            </div>
          </div>
        </aside>
      </div>
    </React.Fragment>
  );
}

function TransactionPaymentPage() {
  const [tela, setTela] = useState('editar');
  return (
    <div className="tp">
      <nav className="tp-nav no-print" aria-label="Telas do prototipo">
        <button aria-current={tela === 'editar'} onClick={() => setTela('editar')}>Editar (PT-02)</button>
        <button aria-current={tela === 'detalhe'} onClick={() => setTela('detalhe')}>Detalhe (PT-03)</button>
      </nav>
      {tela === 'editar' ? <Editar /> : <Detalhe />}
    </div>
  );
}

window.TransactionPaymentPage = TransactionPaymentPage;
})();
