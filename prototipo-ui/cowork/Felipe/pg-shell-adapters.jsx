/* @ts-nocheck */
/* eslint-disable */
// pg-shell-adapters.jsx — wrappers que integram os 3 protótipos F1 PaymentGateway
// no shell Cockpit V2 do Chat principal.
//
// Cada wrapper:
//   1) Aplica chrome canon (.os-page)
//   2) Renderiza o componente puro do protótipo (window.PG_*) DENTRO de
//      `.pg-shell-scope` — escopo onde pg-styles.css mapeia Tailwind→tokens do shell
//   3) Expõe window.<NomeDaPagina> que app.jsx consome no router
//
// Não renderizamos .os-page-h aqui — o componente interno do protótipo já tem
// seu próprio Header h-12 (título + breadcrumb + action buttons).
// Não renderizamos meta-bar aqui — limpeza pedida por Wagner (m0065).
(() => {

// Navegação cross-tela: componentes PG (que vivem em IIFEs auto-contidos) usam
// window.PgGotoRoute('payment-gateways') no onClick de action buttons. O shell
// (app.jsx) escuta o CustomEvent pg:goto-route e chama o router.
window.PgGotoRoute = (route) => {
  window.dispatchEvent(new CustomEvent('pg:goto-route', { detail: route }));
};

function CobrancaPage() {
  return (
    <div className="os-page" data-screen-label="01 Cobrança">
      <div className="pg-shell-scope" style={{ flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column', position: 'relative' }}>
        <window.PG_CobrancaPage />
      </div>
    </div>
  );
}

function PaymentGatewaysPage() {
  return (
    <div className="os-page" data-screen-label="02 Settings · Gateways">
      <div className="pg-shell-scope" style={{ flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column', position: 'relative' }}>
        <window.PG_PaymentGatewaysPage />
      </div>
    </div>
  );
}

function SellsCobrancaPreviewPage() {
  return (
    <div className="os-page" data-screen-label="03 Sells · Cobrança vinculada">
      <div className="pg-shell-scope" style={{ flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column', position: 'relative' }}>
        <window.PG_SellsEmitirCobrancaPin />
      </div>
    </div>
  );
}

window.CobrancaPage = CobrancaPage;
window.PaymentGatewaysPage = PaymentGatewaysPage;
window.SellsCobrancaPreviewPage = SellsCobrancaPreviewPage;
})();
