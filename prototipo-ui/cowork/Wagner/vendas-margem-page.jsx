// vendas-margem-page.jsx — Margem da venda pelo custo médio. Protótipo de 27/10/2026 (PR #10006), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["venda-margem"] = `<title>Margem da venda</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
/* Protótipo US-PROD-024 fase 2 — margem da venda finalizada pelo custo médio do momento da venda.
   Tela viva: resources/js/Pages/Sells/Show.tsx, seção "Itens da venda" (PT-03 Detalhe).
   Imita a faixa do orçamento que já existe (CustoDoOrcamento, UC-VSHOW-08): mesma posição, mesmo
   tamanho de texto; muda só o conteúdo. Tokens = .cockpit (prototipo-ui/design-system/colors_and_type.css).
   Dados de EXEMPLO. Três estados lado a lado para o [W] escolher a copy. */
:root {
  --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  --font-mono: "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace;
  --bg: oklch(0.985 0.003 90); --bg-2: oklch(0.965 0.004 90); --surface: #ffffff;
  --border: oklch(0.90 0.004 90); --border-2: oklch(0.93 0.004 90);
  --text: oklch(0.22 0.01 80); --text-dim: oklch(0.50 0.01 80); --text-mute: oklch(0.555 0.01 80);
  --accent: oklch(0.55 0.15 295); --accent-soft: oklch(0.95 0.04 295);
  --pos: oklch(0.50 0.12 150); --pos-soft: oklch(0.95 0.075 150);
  --neg: oklch(0.55 0.18 25); --neg-soft: oklch(0.955 0.055 25);
  --warn: oklch(0.56 0.12 70); --warn-soft: oklch(0.955 0.072 75);
  --radius: 8px;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.32 0.06 150);
  --neg: oklch(0.74 0.19 25); --neg-soft: oklch(0.32 0.06 25); --warn: oklch(0.82 0.16 75); --warn-soft: oklch(0.33 0.06 75);
  color-scheme: dark; } }
:root[data-theme="dark"] {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.32 0.06 150);
  --neg: oklch(0.74 0.19 25); --neg-soft: oklch(0.32 0.06 25); --warn: oklch(0.82 0.16 75); --warn-soft: oklch(0.33 0.06 75);
  color-scheme: dark; }
body { background: var(--bg); color: var(--text); font-family: var(--font-sans); font-size: 14px; }
.page { max-width: 880px; margin: 0 auto; padding-block: 20px 40px;
  padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px));
  display: grid; gap: 20px; }
.num { font-variant-numeric: tabular-nums; }
h1 { font-size: 18px; font-weight: 600; margin: 0; }
.lead { color: var(--text-dim); font-size: 13px; margin: 4px 0 0; max-width: 72ch; line-height: 1.5; }
.estado { font-size: 11.5px; color: var(--text-mute); text-transform: uppercase; letter-spacing: .05em; margin: 0 0 6px; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card .hd { display: flex; align-items: center; gap: 8px; padding: 12px 20px; border-bottom: 1px solid var(--border); background: var(--bg-2); }
.card .hd h2 { font-size: 14px; font-weight: 600; margin: 0; }
.card .hd span { margin-left: auto; font-size: 12px; color: var(--text-mute); }
.faixa { display: flex; flex-wrap: wrap; gap: 4px 16px; padding: 10px 20px; border-bottom: 1px solid var(--border); font-size: 12px; background: var(--bg-2); color: var(--text-dim); }
.faixa b { font-weight: 500; color: var(--text); }
.faixa.aviso { background: var(--warn-soft); color: var(--warn); }
.faixa.aviso b { color: var(--warn); }
.faixa.neg { background: var(--neg-soft); color: var(--neg); }
.faixa.neg b { color: var(--neg); }
.tw { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
th { font-size: 11.5px; color: var(--text-mute); text-transform: uppercase; letter-spacing: .04em; font-weight: 500; padding: 8px 12px; border-bottom: 1px solid var(--border); text-align: right; white-space: nowrap; }
th:first-child, td:first-child { text-align: left; padding-left: 20px; }
th:last-child, td:last-child { padding-right: 20px; }
td { padding: 10px 12px; text-align: right; border-bottom: 1px solid var(--border-2); vertical-align: top; white-space: nowrap; }
td:first-child { white-space: normal; min-width: 180px; }
.prod { font-weight: 500; }
.sku, .cm { font-size: 12px; color: var(--text-mute); }
.cm.neg { color: var(--neg); }
.cm.sem { font-style: italic; }
.sub { font-weight: 600; }
</style>

<div class="page">
  <div>
    <h1>Margem da venda pelo custo médio</h1>
    <p class="lead">Na venda finalizada, para quem vê preço de custo, a seção de itens ganha uma faixa com a receita, o custo médio do momento da venda e a margem. Cada linha mostra o custo médio e a margem do item. Três estados de exemplo abaixo.</p>
  </div>

  <section>
    <p class="estado">1 · Todos os itens com custo médio</p>
    <div class="card">
      <div class="hd"><h2>Itens da venda</h2><span>3 item(s)</span></div>
      <div class="faixa"><b>Margem pelo custo médio na hora da venda</b><span class="num">Receita R$ 1.180,00</span><span class="num">Custo R$ 694,50</span><span class="num">Margem R$ 485,50 · 41,1%</span></div>
      <div class="tw"><table>
        <thead><tr><th>Produto</th><th>Qtd</th><th>Unit</th><th>Desc.</th><th>Subtotal</th></tr></thead>
        <tbody>
          <tr><td><div class="prod">Lona fosca 440 g/m²</div><div class="sku">LON-440</div><div class="cm num">Custo médio R$ 15,00 · margem 25,0%</div></td><td class="num">30 m²</td><td class="num">R$ 20,00</td><td>—</td><td class="num sub">R$ 600,00</td></tr>
          <tr><td><div class="prod">Banner pronto 90 × 120</div><div class="sku">BAN-9012</div><div class="cm num">Custo médio R$ 21,25 · margem 57,5%</div></td><td class="num">10 un</td><td class="num">R$ 50,00</td><td>—</td><td class="num sub">R$ 500,00</td></tr>
          <tr><td><div class="prod">Ilhós latão 10 mm (cento)</div><div class="sku">ILH-10</div><div class="cm num">Custo médio R$ 4,00 · margem 60,0%</div></td><td class="num">8 cento</td><td class="num">R$ 10,00</td><td>—</td><td class="num sub">R$ 80,00</td></tr>
        </tbody>
      </table></div>
    </div>
  </section>

  <section>
    <p class="estado">2 · Um item sem custo médio naquele momento</p>
    <div class="card">
      <div class="hd"><h2>Itens da venda</h2><span>2 item(s)</span></div>
      <div class="faixa aviso" role="status"><b>Margem parcial: 1 item sem custo médio na hora da venda</b><span class="num">Receita com custo R$ 600,00</span><span class="num">Custo R$ 450,00</span><span class="num">Margem R$ 150,00 · 25,0%</span></div>
      <div class="tw"><table>
        <thead><tr><th>Produto</th><th>Qtd</th><th>Unit</th><th>Desc.</th><th>Subtotal</th></tr></thead>
        <tbody>
          <tr><td><div class="prod">Lona fosca 440 g/m²</div><div class="sku">LON-440</div><div class="cm num">Custo médio R$ 15,00 · margem 25,0%</div></td><td class="num">30 m²</td><td class="num">R$ 20,00</td><td>—</td><td class="num sub">R$ 600,00</td></tr>
          <tr><td><div class="prod">Serviço de instalação</div><div class="sku">SRV-INST</div><div class="cm sem">Sem custo médio na data</div></td><td class="num">1 un</td><td class="num">R$ 250,00</td><td>—</td><td class="num sub">R$ 250,00</td></tr>
        </tbody>
      </table></div>
    </div>
  </section>

  <section>
    <p class="estado">3 · Venda abaixo do custo</p>
    <div class="card">
      <div class="hd"><h2>Itens da venda</h2><span>1 item(s)</span></div>
      <div class="faixa neg" role="status"><b>Vendida abaixo do custo médio</b><span class="num">Receita R$ 180,00</span><span class="num">Custo R$ 225,00</span><span class="num">Margem − R$ 45,00 · −25,0%</span></div>
      <div class="tw"><table>
        <thead><tr><th>Produto</th><th>Qtd</th><th>Unit</th><th>Desc.</th><th>Subtotal</th></tr></thead>
        <tbody>
          <tr><td><div class="prod">Lona fosca 440 g/m²</div><div class="sku">LON-440</div><div class="cm neg num">Custo médio R$ 15,00 · margem −25,0%</div></td><td class="num">15 m²</td><td class="num">R$ 12,00</td><td>—</td><td class="num sub">R$ 180,00</td></tr>
        </tbody>
      </table></div>
    </div>
  </section>
</div>
`;
