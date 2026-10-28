// marketplaces-pedidos-page.jsx — Pedidos dos marketplaces com a Nuvemshop (ADR 0443, fatia e).
// Protótipo de 27/10/2026, fonte desta tela no shell. Estende a tela viva
// resources/js/Pages/Marketplaces/Pedidos.tsx (contas no topo + lista de pedidos): o bloco de contas
// ganha a loja Nuvemshop, com botão de conectar e a chave "Enviar estoque para a loja"
// (mkt_account_credentials.enviar_estoque), e a lista ganha a coluna Canal.
// O HTML vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root.
// Para editar: dentro deste texto crase, ${ e barra invertida vão com barra na frente.
(window.PROTO_HTML = window.PROTO_HTML || {})["marketplaces-pedidos"] = `<title>Pedidos dos marketplaces</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
/* Tokens = .cockpit de prototipo-ui/design-system/colors_and_type.css (ADR 0438 · DS no git).
   Layout: PT-01 Lista — cabeçalho, bloco de contas, filtro e tabela. Dados de EXEMPLO. */
:root {
  --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  --font-mono: "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace;
  --bg: oklch(0.985 0.003 90); --bg-2: oklch(0.965 0.004 90); --surface: #ffffff;
  --border: oklch(0.90 0.004 90); --border-2: oklch(0.93 0.004 90);
  --text: oklch(0.22 0.01 80); --text-dim: oklch(0.50 0.01 80); --text-mute: oklch(0.555 0.01 80);
  --accent: oklch(0.55 0.15 295); --accent-soft: oklch(0.95 0.04 295); --accent-fg: #ffffff;
  --pos: oklch(0.50 0.12 150); --pos-soft: oklch(0.95 0.075 150);
  --warn: oklch(0.56 0.12 70); --warn-soft: oklch(0.955 0.072 75);
  --radius: 8px; --radius-sm: 6px;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.32 0.06 150);
  --warn: oklch(0.82 0.16 75); --warn-soft: oklch(0.33 0.06 75); color-scheme: dark; } }
:root[data-theme="dark"] {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.32 0.06 150);
  --warn: oklch(0.82 0.16 75); --warn-soft: oklch(0.33 0.06 75); color-scheme: dark; }
body { background: var(--bg); color: var(--text); font-family: var(--font-sans); font-size: 13px; margin: 0; }
.page { max-width: 1240px; margin: 0 auto; padding-block: 20px 40px;
  padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px));
  display: grid; gap: 16px; }
.num { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }
header.ph { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px;
  border-bottom: 1px solid var(--border); padding-bottom: 12px; }
.crumb { color: var(--text-mute); font-size: 12px; }
h1 { font-size: 20px; font-weight: 600; margin: 2px 0 0; }
h2 { font-size: 14px; font-weight: 600; margin: 0; }
.sub { color: var(--text-dim); font-size: 12px; margin: 2px 0 0; }
.acoes { display: flex; flex-wrap: wrap; gap: 8px; }
.btn { font: inherit; font-size: 12.5px; border: 1px solid var(--border); background: var(--surface); color: var(--text);
  border-radius: var(--radius-sm); padding: 6px 12px; cursor: pointer; min-height: 32px; }
.btn.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); font-weight: 500; }
.btn:focus-visible, select:focus-visible, input:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); min-width: 0; }
.panel > .hd { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px;
  padding: 12px 14px; border-bottom: 1px solid var(--border-2); }
.contas { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; padding: 14px; }
.conta { border: 1px solid var(--border-2); border-radius: var(--radius); padding: 12px; display: grid; gap: 10px; }
.conta .topo { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.canal { font-weight: 600; }
.vend { color: var(--text-dim); font-size: 12px; }
.badge { display: inline-flex; align-items: center; font-size: 11.5px; border-radius: 999px; padding: 2px 8px;
  border: 1px solid var(--border); color: var(--text-dim); }
.badge.ok { background: var(--pos-soft); border-color: transparent; color: var(--pos); }
.badge.warn { background: var(--warn-soft); border-color: transparent; color: var(--warn); }
.linha { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; font-size: 12.5px; }
select { font: inherit; border: 1px solid var(--border); background: var(--surface); color: var(--text);
  border-radius: var(--radius-sm); padding: 5px 8px; min-width: 180px; min-height: 32px; }
.chave { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
.chave input { width: 16px; height: 16px; accent-color: var(--accent); }
.dica { color: var(--text-mute); font-size: 11.5px; }
.filtro { display: flex; flex-wrap: wrap; gap: 6px; padding: 12px 14px; border-bottom: 1px solid var(--border-2); }
.filtro button { font: inherit; font-size: 12px; border: 1px solid var(--border); background: var(--surface); color: var(--text-dim);
  border-radius: 999px; padding: 4px 10px; cursor: pointer; min-height: 28px; }
.filtro button[aria-pressed="true"] { background: var(--accent-soft); border-color: transparent; color: var(--accent); font-weight: 500; }
.tabela { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
th, td { text-align: left; padding: 8px 14px; border-bottom: 1px solid var(--border-2); white-space: nowrap; }
th { color: var(--text-dim); font-weight: 500; font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; background: var(--bg-2); }
td.r, th.r { text-align: right; }
a { color: var(--accent); text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
<div class="page">
  <header class="ph">
    <div>
      <div class="crumb">Vendas · Marketplaces</div>
      <h1>Pedidos dos marketplaces</h1>
      <p class="sub">Pedido pago vira venda no local escolhido em cada conta.</p>
    </div>
    <div class="acoes">
      <button class="btn">Conectar Mercado Livre</button>
      <button class="btn primary">Conectar Nuvemshop</button>
    </div>
  </header>

  <section class="panel" aria-labelledby="t-contas">
    <div class="hd"><h2 id="t-contas">Contas conectadas</h2><span class="dica">2 contas</span></div>
    <div class="contas">
      <article class="conta">
        <div class="topo"><div><div class="canal">Mercado Livre</div><div class="vend">Vendedor 7001</div></div><span class="badge ok">Conectada</span></div>
        <div class="linha"><span>Local de venda</span>
          <select aria-label="Local de venda da conta 7001"><option>Loja Centro</option><option>Depósito</option></select></div>
        <p class="dica">O estoque do Mercado Livre ainda é ajustado no anúncio.</p>
      </article>
      <article class="conta">
        <div class="topo"><div><div class="canal">Nuvemshop</div><div class="vend">Loja 817495</div></div><span class="badge ok">Conectada</span></div>
        <div class="linha"><span>Local de venda</span>
          <select aria-label="Local de venda da loja 817495"><option>Loja Centro</option><option>Depósito</option></select></div>
        <div class="linha">
          <label class="chave"><input type="checkbox" checked> Enviar estoque para a loja</label>
          <span class="dica">Saldo do local de venda, 1 min depois de cada mudança</span>
        </div>
      </article>
    </div>
  </section>

  <section class="panel" aria-labelledby="t-pedidos">
    <div class="hd"><h2 id="t-pedidos">Pedidos</h2></div>
    <div class="filtro" role="group" aria-label="Filtrar pedidos">
      <button aria-pressed="true">Todos</button><button aria-pressed="false">Sem venda</button><button aria-pressed="false">Com venda</button>
      <button aria-pressed="false">Mercado Livre</button><button aria-pressed="false">Nuvemshop</button>
    </div>
    <div class="tabela">
      <table>
        <thead><tr><th>Pedido</th><th>Canal</th><th>Data</th><th>Situação</th><th class="r">Total</th><th>Venda</th></tr></thead>
        <tbody>
          <tr><td class="num">306</td><td>Nuvemshop</td><td class="num">27/10 10:05</td><td><span class="badge ok">Pago</span></td><td class="r num">105,00</td><td><a href="#">#4512</a></td></tr>
          <tr><td class="num">2000009871</td><td>Mercado Livre</td><td class="num">27/10 09:40</td><td><span class="badge ok">Pago</span></td><td class="r num">99,80</td><td><a href="#">#4511</a></td></tr>
          <tr><td class="num">305</td><td>Nuvemshop</td><td class="num">27/10 08:12</td><td><span class="badge warn">Aguardando pagamento</span></td><td class="r num">48,90</td><td class="dica">—</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</div>
`;
