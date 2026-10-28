// superadmin-pagar-page.jsx — Pagar assinatura (/subscription/{id}/pay e /register-pay). Protótipo de 27/10/2026, fonte desta tela no shell.
// Decisão [W] 2026-10-27: só Pix automático e pagamento offline (os outros 6 gateways saem). Antes→depois de valor aprovado pelo [W].
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente.
(window.PROTO_HTML = window.PROTO_HTML || {})["superadmin-pagar"] = `<title>Pagar assinatura</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
/* Tela do NEGÓCIO pagando um pacote. Tokens = .cockpit de prototipo-ui/design-system/colors_and_type.css
   (ADR 0438 · DS no git). Layout: cartão do pacote + formas de pagamento. Dados de EXEMPLO. */
:root {
  --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  --font-mono: "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace;
  --bg: oklch(0.985 0.003 90); --bg-2: oklch(0.965 0.004 90); --surface: #ffffff;
  --border: oklch(0.90 0.004 90); --border-2: oklch(0.93 0.004 90);
  --text: oklch(0.22 0.01 80); --text-dim: oklch(0.50 0.01 80); --text-mute: oklch(0.555 0.01 80);
  --accent: oklch(0.55 0.15 295); --accent-soft: oklch(0.95 0.04 295); --accent-fg: #ffffff;
  --pos: oklch(0.50 0.12 150); --pos-soft: oklch(0.95 0.075 150);
  --neg: oklch(0.55 0.18 25); --neg-soft: oklch(0.955 0.055 25);
  --warn: oklch(0.56 0.12 70); --warn-soft: oklch(0.955 0.072 75);
  --radius: 8px; --radius-sm: 6px; --row-h: 30px;
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
body { background: var(--bg); color: var(--text); font-family: var(--font-sans); font-size: 13px; }
.page { max-width: 1100px; margin: 0 auto; padding-block: 20px 40px;
  padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px));
  display: grid; gap: 16px; }
.num { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }
header.ph { border-bottom: 1px solid var(--border); padding-bottom: 12px; }
.crumb { color: var(--text-mute); font-size: 12px; text-decoration: none; }
.crumb:hover { color: var(--text); }
h1 { font-size: 20px; font-weight: 600; margin: 2px 0 0; }
h2 { font-size: 14px; font-weight: 600; margin: 0; }
.sub { color: var(--text-dim); font-size: 12px; margin: 2px 0 0; }
.cols { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px;
  display: flex; flex-direction: column; gap: 10px; }
.pacote { flex: 1 1 280px; max-width: 360px; }
.preco { display: flex; align-items: baseline; gap: 6px; }
.preco .v { font-size: 22px; font-weight: 600; }
.preco .c { color: var(--text-dim); font-size: 12px; }
ul.lim { margin: 0; padding-left: 16px; color: var(--text-dim); font-size: 12px; display: grid; gap: 2px; }
.nota { border-top: 1px solid var(--border-2); padding-top: 10px; color: var(--text-mute); font-size: 11px; margin: 0; }
.formas { flex: 999 1 420px; min-width: 0; display: grid; gap: 12px; }
.rot { color: var(--text-mute); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin: 0; }
.forma-h { display: flex; align-items: center; gap: 8px; }
.forma p { margin: 0; color: var(--text-dim); }
.selo { font-size: 11px; padding: 1px 8px; border-radius: 999px; background: var(--pos-soft); color: var(--pos); }
.dados { margin: 0; padding: 10px 12px; border-radius: var(--radius-sm); background: var(--bg-2); font: inherit; font-size: 12px;
  white-space: pre-wrap; color: var(--text); }
.btn { font: inherit; font-size: 12.5px; border: 1px solid var(--border); background: var(--surface); color: var(--text);
  border-radius: var(--radius-sm); padding: 0 14px; min-height: 36px; cursor: pointer; justify-self: start; }
.btn:hover { background: var(--bg-2); }
.btn.pri { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); }
.btn.pri:hover { filter: brightness(1.06); }
.btn:disabled { opacity: .55; cursor: default; }
.aviso { margin: 0; padding: 10px 12px; border-radius: var(--radius-sm); background: var(--accent-soft); color: var(--text); }
.aviso[hidden] { display: none; }
</style>
<div class="page">
  <header class="ph">
    <a class="crumb" href="#">← Minha assinatura</a>
    <h1>Pagar assinatura</h1>
    <p class="sub">Confira o pacote e escolha como pagar</p>
  </header>

  <div class="cols">
    <section class="card pacote" aria-label="Pacote">
      <h2>Pacote Profissional</h2>
      <div class="preco"><span class="v num">[preço do pacote]</span><span class="c">/ mês</span></div>
      <ul class="lim">
        <li>2 locais</li>
        <li>usuários ilimitados</li>
        <li>produtos ilimitados</li>
        <li>faturas ilimitadas</li>
        <li>15 dias de teste</li>
      </ul>
      <p class="nota">O preço aparece no mesmo formato da tela antiga. O valor cobrado é o do pacote, lido de novo no servidor.</p>
    </section>

    <section class="formas" aria-label="Formas de pagamento">
      <p class="rot">Como pagar</p>

      <article class="card forma">
        <div class="forma-h"><h2>Pix automático</h2><span class="selo">recomendado</span></div>
        <p>Você autoriza uma vez no app do seu banco e a mensalidade é debitada todo mês. Depois de confirmar, tem 7 dias para autorizar.</p>
        <button type="button" class="btn pri" data-msg="Cobrança enviada. Autorize o mandato no app do seu banco em até 7 dias.">Pagar com Pix automático</button>
      </article>

      <article class="card forma">
        <div class="forma-h"><h2>Pagamento offline</h2></div>
        <p>Pague por transferência ou depósito e avise aqui. A assinatura fica aguardando até o pagamento ser aprovado.</p>
        <pre class="dados">Banco de exemplo · agência 0000 · conta 00000-0
Envie o comprovante para o suporte.</pre>
        <button type="button" class="btn" data-msg="Aviso enviado. A assinatura fica aguardando aprovação.">Avisar pagamento offline</button>
      </article>

      <p class="aviso" role="status" hidden></p>
    </section>
  </div>
</div>
<script>
document.addEventListener('click', (e) => {
  const b = e.target.closest('button[data-msg]'); if (!b) return;
  const aviso = document.querySelector('.aviso');
  aviso.textContent = b.dataset.msg; aviso.hidden = false;
  document.querySelectorAll('button[data-msg]').forEach((x) => { x.disabled = true; });
});
</script>
`;
