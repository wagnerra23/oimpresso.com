// vendas-orcamento-aprovacao-page.jsx — Aprovação do orçamento (página pública). Protótipo de 27/10/2026 (PR #10000), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["venda-aprovacao-publica"] = `<title>Aprovar orçamento da venda</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
/* Protótipo US-SELL-074 — página PÚBLICA (sem login, fora do AppShellV2) em que o cliente aprova o
   orçamento da venda comum ou pede ajuste. Tokens = .cockpit de prototipo-ui/design-system/colors_and_type.css
   (ADR 0438 · DS no git). Mesma estrutura da página da gráfica (Pages/ComunicacaoVisual/AceitePublico.tsx):
   cabeçalho da empresa, itens com total, ações. Diferença: aprovar pede o NOME de quem aprova (o SPEC registra
   nome, data e IP). Coluna única de 560px, centrada; dados de EXEMPLO (empresa e itens fictícios).
   Os 4 estados ficam lado a lado no seletor do topo — só pra revisão; a página real mostra um. */
:root {
  --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  --font-mono: "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace;
  --bg: oklch(0.985 0.003 90); --bg-2: oklch(0.965 0.004 90); --surface: #ffffff;
  --border: oklch(0.90 0.004 90); --border-2: oklch(0.93 0.004 90);
  --text: oklch(0.22 0.01 80); --text-dim: oklch(0.50 0.01 80); --text-mute: oklch(0.555 0.01 80);
  --accent: oklch(0.55 0.15 295); --accent-soft: oklch(0.95 0.04 295); --accent-fg: #ffffff;
  --pos: oklch(0.50 0.12 150); --pos-soft: oklch(0.95 0.075 150);
  --neg: oklch(0.55 0.18 25); --neg-soft: oklch(0.955 0.055 25);
  --radius: 8px; --radius-sm: 6px;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.32 0.06 150);
  --neg: oklch(0.74 0.19 25); --neg-soft: oklch(0.32 0.06 25);
  color-scheme: dark; } }
:root[data-theme="dark"] {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.32 0.06 150);
  --neg: oklch(0.74 0.19 25); --neg-soft: oklch(0.32 0.06 25);
  color-scheme: dark; }
body { background: var(--bg-2); color: var(--text); font-family: var(--font-sans); font-size: 14px; }
.wrap { max-width: 560px; margin: 0 auto; padding-block: 24px 40px;
  padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px));
  display: grid; gap: 14px; }
.num { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }
.review { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; font-size: 12px; color: var(--text-mute);
  border: 1px dashed var(--border); border-radius: var(--radius); padding: 8px 10px; }
.review button { font: inherit; font-size: 12px; border: 1px solid var(--border); background: var(--surface); color: var(--text-dim);
  border-radius: 999px; padding: 3px 10px; cursor: pointer; }
.review button[aria-pressed="true"] { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); }
header { text-align: center; display: grid; gap: 4px; justify-items: center; }
.mark { width: 44px; height: 44px; border-radius: 50%; background: var(--accent-soft); color: var(--accent);
  display: grid; place-items: center; font-weight: 600; margin-bottom: 4px; }
.emp { color: var(--text-dim); font-size: 13px; margin: 0; }
h1 { font-size: 20px; font-weight: 600; margin: 0; text-wrap: balance; }
.val { color: var(--text-mute); font-size: 12.5px; margin: 0; }
.alert { border-radius: var(--radius); padding: 10px 12px; font-size: 13px; display: grid; gap: 2px; }
.alert b { font-weight: 600; }
.alert.ok { background: var(--pos-soft); color: var(--pos); }
.alert.err { background: var(--neg-soft); color: var(--neg); }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; display: grid; gap: 10px; }
.card h2 { font-size: 11.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--text-mute); margin: 0; font-weight: 600; }
ul.itens { list-style: none; margin: 0; padding: 0; display: grid; }
ul.itens li { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--border-2); }
ul.itens li:last-child { border-bottom: 0; }
.it-n { font-weight: 500; overflow-wrap: anywhere; min-width: 0; }
.it-q { color: var(--text-dim); font-size: 12.5px; }
dl { margin: 0; display: grid; gap: 4px; padding-top: 8px; border-top: 1px solid var(--border); }
dl div { display: flex; justify-content: space-between; gap: 12px; }
dt { color: var(--text-dim); } dd { margin: 0; }
dl .tot { padding-top: 6px; border-top: 1px solid var(--border-2); font-weight: 600; font-size: 15px; }
dl .tot dt { color: var(--text); }
.field { display: grid; gap: 4px; }
.field label { font-size: 12.5px; color: var(--text-dim); }
input, textarea { font: inherit; font-size: 14px; color: var(--text); background: var(--surface);
  border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 9px 10px; min-height: 44px; box-sizing: border-box; width: 100%; }
textarea { min-height: 96px; resize: vertical; }
.acoes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.btn { font: inherit; font-size: 14px; min-height: 44px; border: 1px solid var(--border); background: var(--surface); color: var(--text);
  border-radius: var(--radius-sm); cursor: pointer; font-weight: 500; }
.btn.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); }
.btn:disabled { opacity: .55; cursor: not-allowed; }
.btn:focus-visible, input:focus-visible, textarea:focus-visible, .review button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.hint { color: var(--text-mute); font-size: 12px; margin: 0; }
footer { text-align: center; color: var(--text-mute); font-size: 12px; }
</style>

<div class="wrap">
  <div class="review" role="group" aria-label="Estados para revisão">
    Revisão — estado da página:
    <button type="button" data-estado="aberto" aria-pressed="true">Aberto</button>
    <button type="button" data-estado="ajuste" aria-pressed="false">Pedindo ajuste</button>
    <button type="button" data-estado="feito" aria-pressed="false">Aprovado</button>
    <button type="button" data-estado="vencido" aria-pressed="false">Link vencido</button>
  </div>

  <header>
    <div class="mark" aria-hidden="true">PM</div>
    <p class="emp">Papelaria Modelo Ltda (exemplo)</p>
    <h1>Orçamento nº 0412</h1>
    <p class="val">Emitido em 27/10/2026 · link válido até 03/11/2026</p>
  </header>

  <div class="alert ok" id="a-feito" hidden><b>Orçamento aprovado.</b>Obrigado, Ana. A loja já recebeu a sua aprovação e vai entrar em contato.</div>
  <div class="alert err" id="a-vencido" hidden><b>Não dá para responder por este link.</b>Este link venceu. Peça um novo à loja.</div>

  <section class="card" aria-labelledby="t-itens">
    <h2 id="t-itens">Itens</h2>
    <ul class="itens">
      <li><div class="it-n">Caderno universitário 10 matérias<div class="it-q num">2 un × R$ 150,00</div></div><span class="num">R$ 300,00</span></li>
      <li><div class="it-n">Estojo escolar duplo<div class="it-q num">1 un × R$ 49,90</div></div><span class="num">R$ 49,90</span></li>
    </ul>
    <dl>
      <div><dt>Frete</dt><dd class="num">R$ 10,00</dd></div>
      <div class="tot"><dt>Total</dt><dd class="num">R$ 359,90</dd></div>
    </dl>
  </section>

  <form class="card" id="f-aprovar" onsubmit="event.preventDefault()">
    <div class="field">
      <label for="nome">Seu nome, para registrar a aprovação</label>
      <input id="nome" name="nome" autocomplete="name" value="Ana Souza" maxlength="120">
    </div>
    <div class="acoes">
      <button type="button" class="btn" data-ir="ajuste">Pedir ajuste</button>
      <button type="button" class="btn primary" data-ir="feito">Aprovar orçamento</button>
    </div>
    <p class="hint">Ao aprovar, a loja registra seu nome, a data e o endereço de rede de onde a aprovação saiu.</p>
  </form>

  <form class="card" id="f-ajuste" hidden onsubmit="event.preventDefault()">
    <div class="field">
      <label for="comentario">O que precisa mudar?</label>
      <textarea id="comentario" name="comentario" maxlength="2000">Trocar o estojo por um de zíper simples e mandar 3 cadernos.</textarea>
    </div>
    <div class="acoes">
      <button type="button" class="btn" data-ir="aberto">Voltar</button>
      <button type="button" class="btn primary" data-ir="feito-ajuste">Enviar pedido</button>
    </div>
  </form>

  <footer>Link válido por 7 dias · oimpresso.com</footer>
</div>

<script>
const $ = (id) => document.getElementById(id);
function mostrar(estado) {
  const aberto = estado === 'aberto', ajuste = estado === 'ajuste';
  $('f-aprovar').hidden = !aberto;
  $('f-ajuste').hidden = !ajuste;
  $('a-feito').hidden = !(estado === 'feito' || estado === 'feito-ajuste');
  $('a-vencido').hidden = estado !== 'vencido';
  if (estado === 'feito-ajuste') {
    $('a-feito').innerHTML = '<b>Pedido de ajuste enviado.</b>A loja vai revisar e mandar um novo orçamento.';
  } else {
    $('a-feito').innerHTML = '<b>Orçamento aprovado.</b>Obrigado, Ana. A loja já recebeu a sua aprovação e vai entrar em contato.';
  }
  const chave = estado === 'feito-ajuste' ? 'feito' : estado;
  document.querySelectorAll('.review button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.estado === chave)));
}
document.querySelectorAll('[data-estado]').forEach((b) => b.addEventListener('click', () => mostrar(b.dataset.estado)));
document.querySelectorAll('[data-ir]').forEach((b) => b.addEventListener('click', () => mostrar(b.dataset.ir)));
$('nome').addEventListener('input', (e) => {
  document.querySelector('[data-ir="feito"]').disabled = e.target.value.trim() === '';
});
mostrar('aberto');
</script>
`;
