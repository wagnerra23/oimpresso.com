// vendas-caixa-pagamento-page.jsx — Pagamento do caixa: maquininha e venda sem internet. Protótipo de 27/10/2026 (US-SELL-075 e US-SELL-054, branch claude/proto-caixa-maquininha-offline), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["venda-pagamento-maquininha"] = `<title>Caixa: maquininha e sem internet</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<!--
  Protótipo — Sells/Create (venda balcão), bloco Pagamento + aviso de conexão.
  Fonte: este arquivo no git (ADR 0438 D1). O Artefato é só para ver e comentar (D2).
  Ancorado no DS: tokens copiados de prototipo-ui/design-system/colors_and_type.css
  (bloco .cockpit claro e [data-theme="dark"]), IBM Plex como no cockpit.
  US-SELL-075 (maquininha/TEF) e US-SELL-054 (venda sem internet). Atrás de flag.
-->
<style>
/* Layout: uma coluna de cards de estado, cada um mostra o bloco Pagamento da venda num momento. */
:root {
  --font-sans: "IBM Plex Sans", system-ui, sans-serif;
  --font-mono: "IBM Plex Mono", ui-monospace, Menlo, monospace;
  --bg: oklch(0.985 0.003 90); --bg-2: oklch(0.965 0.004 90); --surface: #ffffff;
  --border: oklch(0.90 0.004 90); --border-2: oklch(0.93 0.004 90);
  --text: oklch(0.22 0.01 80); --text-dim: oklch(0.50 0.01 80); --text-mute: oklch(0.555 0.01 80);
  --accent: oklch(0.55 0.15 295); --accent-soft: oklch(0.95 0.04 295); --accent-fg: #ffffff;
  --pos: oklch(0.50 0.12 150); --pos-soft: oklch(0.95 0.075 150);
  --neg: oklch(0.55 0.18 25); --neg-soft: oklch(0.955 0.055 25);
  --warn: oklch(0.56 0.12 70); --warn-soft: oklch(0.955 0.072 75);
  --radius: 8px; --radius-sm: 6px;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.32 0.06 150);
  --neg: oklch(0.74 0.19 25); --neg-soft: oklch(0.33 0.07 25); --warn: oklch(0.82 0.16 75); --warn-soft: oklch(0.34 0.06 75);
  color-scheme: dark; } }
:root[data-theme="dark"] {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.32 0.06 150);
  --neg: oklch(0.74 0.19 25); --neg-soft: oklch(0.33 0.07 25); --warn: oklch(0.82 0.16 75); --warn-soft: oklch(0.34 0.06 75);
  color-scheme: dark; }

body { background: var(--bg); color: var(--text); font-family: var(--font-sans); font-size: 13px; }
.wrap { max-width: 760px; margin: 0 auto; padding-block: 24px 48px;
  padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px));
  display: grid; gap: 20px; }
h1 { font-size: 18px; font-weight: 600; margin: 0; text-wrap: balance; }
.lede { color: var(--text-dim); margin: 4px 0 0; max-width: 62ch; line-height: 1.5; }
.estado { display: grid; gap: 8px; }
.estado > .rot { font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: var(--text-mute); font-weight: 500; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; display: grid; gap: 12px; min-width: 0; }
.lbl { font-size: 11px; letter-spacing: .04em; text-transform: uppercase; color: var(--text-dim); font-weight: 500; }
.linha { display: grid; grid-template-columns: minmax(120px, 1fr) minmax(110px, 1fr) auto; gap: 10px; align-items: end; }
.campo { display: grid; gap: 4px; min-width: 0; }
.input { height: 32px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text);
  padding: 0 10px; display: flex; align-items: center; font-variant-numeric: tabular-nums; }
.input.mono { font-family: var(--font-mono); }
button { font: inherit; cursor: pointer; }
.btn { height: 32px; padding: 0 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); color: var(--text); font-weight: 500; white-space: nowrap; }
.btn.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); }
.btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.btn[disabled] { opacity: .55; cursor: default; }
.sub { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.status { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border-radius: var(--radius-sm); line-height: 1.45; }
.status b { font-weight: 600; }
.status.wait { background: var(--accent-soft); }
.status.ok { background: var(--pos-soft); color: var(--text); }
.status.ok .ic { color: var(--pos); }
.status.neg { background: var(--neg-soft); }
.status.neg .ic { color: var(--neg); }
.status.warn { background: var(--warn-soft); }
.status.warn .ic { color: var(--warn); }
.ic { font-weight: 700; flex: none; width: 16px; text-align: center; }
.spin { width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--accent); border-right-color: transparent; animation: g 0.9s linear infinite; flex: none; margin-top: 2px; }
@keyframes g { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .spin { animation: none; } }
.acoes { display: flex; gap: 8px; flex-wrap: wrap; }
.barra { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; align-items: center;
  padding: 8px 12px; border: 1px solid var(--warn); border-radius: var(--radius-sm); background: var(--warn-soft); }
.chip { font-size: 11px; padding: 2px 8px; border-radius: 999px; background: var(--bg-2); border: 1px solid var(--border); color: var(--text-dim); font-variant-numeric: tabular-nums; }
.fila { display: grid; gap: 0; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.fila div { display: grid; grid-template-columns: 1fr auto auto; gap: 12px; padding: 8px 10px; border-top: 1px solid var(--border-2); font-variant-numeric: tabular-nums; }
.fila div:first-child { border-top: 0; }
.nota { color: var(--text-dim); font-size: 12px; line-height: 1.5; max-width: 66ch; }
@media (max-width: 520px) { .linha { grid-template-columns: 1fr 1fr; } .linha .btn { grid-column: 1 / -1; } .sub { grid-template-columns: 1fr 1fr; } }
</style>

<div class="wrap">
  <header>
    <h1>Caixa: maquininha e venda sem internet</h1>
    <p class="lede">Bloco Pagamento da venda balcão. O botão manda o valor para a maquininha; a resposta preenche bandeira e autorização. Sem internet, a venda fica guardada no navegador e sobe sozinha quando a conexão volta. As duas coisas nascem desligadas por empresa.</p>
  </header>

  <section class="estado" aria-labelledby="e1">
    <span class="rot" id="e1">1 · Cartão escolhido, antes de cobrar</span>
    <div class="card">
      <span class="lbl">Pagamento</span>
      <div class="linha">
        <div class="campo"><span class="lbl">Método</span><div class="input">Cartão</div></div>
        <div class="campo"><span class="lbl">Valor</span><div class="input">R$ 204,90</div></div>
        <button class="btn primary" type="button">Cobrar na maquininha</button>
      </div>
      <div class="sub">
        <div class="campo"><span class="lbl">Modalidade</span><div class="input">Crédito</div></div>
        <div class="campo"><span class="lbl">Parcelas</span><div class="input">3x</div></div>
        <div class="campo"><span class="lbl">Autorização</span><div class="input mono" style="color:var(--text-mute)">digite ou use a maquininha</div></div>
      </div>
    </div>
  </section>

  <section class="estado" aria-labelledby="e2">
    <span class="rot" id="e2">2 · Valor enviado, cliente na máquina</span>
    <div class="card">
      <div class="status wait" role="status"><span class="spin" aria-hidden="true"></span>
        <span><b>Aguardando o cliente na maquininha.</b> R$ 204,90 no crédito em 3x. Os campos ficam travados até a resposta.</span></div>
      <div class="acoes"><button class="btn" type="button">Cancelar cobrança</button></div>
    </div>
  </section>

  <section class="estado" aria-labelledby="e3">
    <span class="rot" id="e3">3 · Aprovada: a linha nasce preenchida</span>
    <div class="card">
      <div class="status ok" role="status"><span class="ic" aria-hidden="true">✓</span>
        <span><b>Aprovada.</b> Visa · NSU 000004182 · autorização A04182.</span></div>
      <div class="sub">
        <div class="campo"><span class="lbl">Bandeira</span><div class="input">Visa</div></div>
        <div class="campo"><span class="lbl">Parcelas</span><div class="input">3x</div></div>
        <div class="campo"><span class="lbl">Autorização</span><div class="input mono">A04182</div></div>
      </div>
    </div>
  </section>

  <section class="estado" aria-labelledby="e4">
    <span class="rot" id="e4">4 · Recusada ou sem resposta</span>
    <div class="card">
      <div class="status neg" role="alert"><span class="ic" aria-hidden="true">!</span>
        <span><b>Transação negada pelo emissor.</b> Peça outro cartão ou troque a forma de pagamento. Nada foi cobrado.</span></div>
      <div class="acoes"><button class="btn primary" type="button">Tentar de novo</button><button class="btn" type="button">Digitar à mão</button></div>
    </div>
  </section>

  <section class="estado" aria-labelledby="e5">
    <span class="rot" id="e5">5 · Sem internet</span>
    <div class="barra" role="status">
      <span><b>Sem internet.</b> As vendas ficam guardadas neste computador e sobem sozinhas quando a conexão voltar.</span>
      <span class="chip">2 aguardando envio</span>
    </div>
    <div class="card">
      <span class="lbl">Aguardando envio</span>
      <div class="fila">
        <div><span>Venda 14:32 · 3 itens</span><span>R$ 89,70</span><span class="chip">dinheiro</span></div>
        <div><span>Venda 14:41 · 1 item</span><span>R$ 45,00</span><span class="chip">Pix</span></div>
      </div>
      <p class="nota">Sem internet a maquininha integrada fica desligada: o cartão é digitado à mão. Cada venda leva uma chave própria, então reenviar não grava duas vezes nem baixa o estoque duas vezes. Se o servidor recusar uma venda ao sincronizar, ela fica aqui com o motivo, para o caixa resolver.</p>
    </div>
  </section>
</div>
`;
