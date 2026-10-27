// financeiro-pagamento-publico.jsx — protótipo das páginas PÚBLICAS do cliente final do Financeiro:
// /pagar/{token} (link de pagamento do título, US-FIN-081) e /portal-cliente/{token} (portal, US-FIN-082).
// Gerado pelo Code (ADR 0438 D1: a fonte é este arquivo no git; o Artefato é só para ver e comentar),
// ancorado no DS canon (colors_and_type.css) e no padrão das públicas em produção (Pages/ComunicacaoVisual/
// AceitePublico.tsx): fora do AppShellV2, coluna central, card sobre fundo muted, ícone no topo.
// Valores fictícios; nenhum documento, e-mail ou id do cliente aparece. Expõe window.FinPagamentoPublicoPage.
(() => {
const CSS = `.fpp { --fg: var(--color-foreground); --font: var(--font-sans); --radius: 8px;
  --primary-soft: color-mix(in oklch, var(--color-primary) 12%, transparent);
  --destructive-soft: var(--color-destructive-soft); --success-soft: var(--color-success-soft); --success-fg: var(--color-success-fg); }
.fpp .trilho { max-width: 1120px; margin: 0 auto; padding: 24px 16px 48px; }
.fpp .trilho > h1 { font-size: 18px; margin: 0 0 4px; }
.fpp .trilho > p { margin: 0 0 20px; color: var(--muted-fg); }
.fpp .telas { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; align-items: start; }
.fpp .rotulo { font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--muted-fg); margin: 0 0 8px; }
.fpp .tela { background: var(--muted); border: 1px solid var(--border); border-radius: 12px; padding: 24px 16px; }
.fpp .col { max-width: 448px; margin: 0 auto; display: grid; gap: 16px; }
.fpp header.topo { text-align: center; }
.fpp .icone { width: 48px; height: 48px; border-radius: 999px; background: var(--primary-soft); color: var(--primary); display: inline-grid; place-items: center; margin-bottom: 12px; }
.fpp .icone svg { width: 24px; height: 24px; }
.fpp .empresa { margin: 0; color: var(--muted-fg); font-size: 13px; }
.fpp .h1 { margin: 0; font-size: 20px; font-weight: 700; }
.fpp .sub { margin: 4px 0 0; color: var(--muted-fg); font-size: 13px; }
.fpp .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
.fpp .valor { text-align: center; }
.fpp .valor small { color: var(--muted-fg); }
.fpp .valor b { display: block; font-size: 30px; font-variant-numeric: tabular-nums; }
.fpp .acoes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.fpp .btn { font: 500 14px var(--font); border-radius: 6px; padding: 9px 12px; border: 1px solid transparent; display: inline-flex; gap: 6px; align-items: center; justify-content: center; cursor: pointer; min-height: 40px; }
.fpp .btn svg { width: 16px; height: 16px; }
.fpp .btn-p { background: var(--primary); color: var(--primary-fg); }
.fpp .btn-o { background: var(--card); color: var(--fg); border-color: var(--border); }
.fpp .btn-sm { min-height: 32px; padding: 5px 10px; font-size: 13px; }
.fpp .codigo { display: grid; gap: 6px; }
.fpp .codigo p { margin: 0; font-size: 13px; font-weight: 500; }
.fpp .codigo code { display: block; background: var(--muted); border-radius: 4px; padding: 8px; font-size: 12px; word-break: break-all; }
.fpp .codigo .btn { justify-self: start; }
.fpp .alerta { border-radius: var(--radius); padding: 12px 14px; border: 1px solid; font-size: 13px; }
.fpp .alerta b { display: block; margin-bottom: 2px; }
.fpp .alerta.erro { background: var(--destructive-soft); border-color: var(--destructive); }
.fpp .alerta.ok { background: var(--success-soft); border-color: var(--success-fg); }
.fpp .secao h2 { margin: 0 0 12px; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; color: var(--muted-fg); display: flex; justify-content: space-between; }
.fpp .secao h2 span { color: var(--fg); font-size: 15px; letter-spacing: 0; text-transform: none; }
.fpp ul.lista { list-style: none; margin: 0; padding: 0; }
.fpp ul.lista > li { padding: 12px 0; border-top: 1px solid var(--border); display: grid; gap: 8px; }
.fpp ul.lista > li:first-child { border-top: 0; padding-top: 0; }
.fpp .linha { display: flex; justify-content: space-between; gap: 16px; }
.fpp .linha .n { font-weight: 500; }
.fpp .linha small { display: block; color: var(--muted-fg); }
.fpp .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
.fpp .rodape { text-align: center; color: var(--muted-fg); font-size: 12px; margin: 0; }
.fpp a.pdf { color: var(--primary); font-size: 13px; }
`;
function Estilo() { return <style>{CSS}</style>; }
const TELAS = [
  { nome: 'PagamentoPublico · estado aberto', el: (<section>
      <p className="rotulo">1 · /pagar/{"{token}"} — aberto</p>
      <div className="tela"><div className="col">
        <header className="topo">
          <span className="icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8M12 17.5v-11"/></svg></span>
          <p className="empresa">Gráfica Exemplo Ltda</p>
          <p className="h1">Título 000123</p>
          <p className="sub">Vencimento 05/11/2026</p>
        </header>
        <div className="card valor"><small>Valor a pagar</small><b>R$ 150,00</b></div>
        <div className="acoes">
          <button className="btn btn-p"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM18 18h3v3h-3z"/></svg>Pagar com Pix</button>
          <button className="btn btn-o"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 5v14M8 5v14M12 5v14M17 5v14M21 5v14"/></svg>Gerar boleto</button>
        </div>
        <p className="rodape">Pagamento seguro · oimpresso.com</p>
      </div></div>
    </section>) },
  { nome: 'PagamentoPublico · Pix gerado', el: (<section>
      <p className="rotulo">2 · /pagar/{"{token}"} — Pix gerado</p>
      <div className="tela"><div className="col">
        <header className="topo">
          <span className="icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/></svg></span>
          <p className="empresa">Gráfica Exemplo Ltda</p>
          <p className="h1">Título 000123</p>
          <p className="sub">Vencimento 05/11/2026</p>
        </header>
        <div className="card valor"><small>Valor a pagar</small><b>R$ 150,00</b></div>
        <div className="card codigo">
          <p>Pix copia e cola</p>
          <code>00020101021226870014br.gov.bcb.pix2565exemplo.banco/qr/v2/cobv/7f3a9c…5204000053039865406150.00</code>
          <button className="btn btn-o btn-sm">Copiar</button>
        </div>
        <p className="rodape">Pagamento seguro · oimpresso.com</p>
      </div></div>
    </section>) },
  { nome: 'PagamentoPublico · indisponível', el: (<section>
      <p className="rotulo">3 · /pagar/{"{token}"} — já pago / vencido</p>
      <div className="tela"><div className="col">
        <header className="topo">
          <span className="icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/></svg></span>
          <p className="empresa">Gráfica Exemplo Ltda</p>
          <p className="h1">Título 000123</p>
        </header>
        <div className="card valor"><small>Valor a pagar</small><b>R$ 0,00</b></div>
        <div className="alerta ok"><b>Pago</b>Este título já está pago. Obrigado!</div>
        <div className="alerta erro"><b>Não dá para pagar por este link</b>Este link venceu. Peça um novo à empresa.</div>
        <p className="rodape">Pagamento seguro · oimpresso.com</p>
      </div></div>
    </section>) },
  { nome: 'PortalCliente', el: (<section>
      <p className="rotulo">4 · /portal-cliente/{"{token}"} — portal do cliente</p>
      <div className="tela"><div className="col">
        <header className="topo">
          <span className="icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg></span>
          <p className="empresa">Gráfica Exemplo Ltda</p>
          <p className="h1">Olá, Maria Souza</p>
        </header>
        <div className="card secao">
          <h2>Em aberto <span className="num">R$ 140,00</span></h2>
          <ul className="lista">
            <li>
              <div className="linha"><div><span className="n">Título 000123</span><small>Vence 05/11/2026</small></div><span className="num">R$ 100,00</span></div>
              <div style={{display:"flex",gap:8}}><button className="btn btn-p btn-sm">Pix</button><button className="btn btn-o btn-sm">Boleto</button></div>
            </li>
            <li>
              <div className="linha"><div><span className="n">Título 000118</span><small>Vence 28/10/2026</small></div><span className="num">R$ 40,00</span></div>
              <div className="codigo"><p>Linha digitável</p><code>07790.00116 12345.678901 23456.789012 3 98760000004000</code><button className="btn btn-o btn-sm">Copiar</button></div>
              <a className="pdf" href="#">Abrir o boleto em PDF</a>
            </li>
          </ul>
        </div>
        <div className="card secao">
          <h2>Pagos</h2>
          <ul className="lista">
            <li><div className="linha"><span>Título 000101 · 10/10/2026</span><span className="num">R$ 70,00</span></div></li>
            <li><div className="linha"><span>Título 000094 · 10/09/2026</span><span className="num">R$ 70,00</span></div></li>
          </ul>
        </div>
        <p className="rodape">Pagamento seguro · oimpresso.com</p>
      </div></div>
    </section>) },
];
const VIEWS = { pagar: [0, 1, 2], portal: [3] };
function FinPagamentoPublicoPage({ view = "pagar" }) {
  const idx = VIEWS[view] || VIEWS.pagar;
  return (
    <div className="fpp" style={{ padding: 24 }}>
      <Estilo />
      <h1 style={{ fontSize: 18, margin: "0 0 16px" }}>{view === "portal" ? "Financeiro · portal do cliente" : "Financeiro · link de pagamento"}</h1>
      <div className="telas">{idx.map((i) => <React.Fragment key={i}>{TELAS[i].el}</React.Fragment>)}</div>
    </div>
  );
}
window.FinPagamentoPublicoPage = FinPagamentoPublicoPage;
})();
