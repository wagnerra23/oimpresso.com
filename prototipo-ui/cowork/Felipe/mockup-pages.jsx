// mockup-pages.jsx — Renderiza os mockups das 16 telas faltantes dentro do shell unificado.
// Cada rota usa um único componente `MockupPage` que carrega o body do mockup correspondente
// de window.MOCKUP_BODIES (gerado por mockup-bodies.js).
// IIFE para não vazar nomes no escopo global.
(() => {
  function MockupPage({ route }) {
    const body = (window.MOCKUP_BODIES && window.MOCKUP_BODIES[route]) || "";
    if (!body) {
      return (
        <div className="mockup-page" style={{ padding: 40, color: "var(--text-mute)" }}>
          <p>Mockup não disponível para a rota <code>{route}</code>.</p>
        </div>
      );
    }
    return (
      <div className={"mockup-page mp-" + route}
           data-screen-label={"01 " + route}
           dangerouslySetInnerHTML={{ __html: body }}/>
    );
  }

  window.MockupPage = MockupPage;
})();
