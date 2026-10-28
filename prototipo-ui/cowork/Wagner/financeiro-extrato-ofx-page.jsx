// financeiro-extrato-ofx-page.jsx — Extrato da conta: importar OFX. Protótipo de 27/10/2026 (US-FIN-084, commit aff79fbb6b), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["fin-extrato-ofx"] = `<title>Extrato · Importar OFX</title>
<meta name="claude:viewport-fit-sides" content="cover">
<style>
/* Protótipo US-FIN-084 · Financeiro/Extrato — tokens do DS oimpresso (prototipo-ui/design-system/colors_and_type.css).
   Layout: a tela do extrato da conta como está hoje + a ação nova "Importar OFX" no PageHeader,
   que abre um diálogo de 3 passos (arquivo → conferência → importado). */
:root {
  --bg: oklch(0.985 0.003 90); --bg-2: oklch(0.965 0.004 90); --surface: #ffffff;
  --border: oklch(0.90 0.004 90); --text: oklch(0.22 0.01 80); --text-dim: oklch(0.50 0.01 80);
  --accent: oklch(0.55 0.15 295); --accent-soft: oklch(0.95 0.04 295); --accent-fg: #ffffff;
  --ok: oklch(0.52 0.12 150); --ok-soft: oklch(0.95 0.05 150);
  --danger: oklch(0.55 0.18 25); --warn: oklch(0.56 0.12 70); --warn-soft: oklch(0.955 0.072 75);
  --sidebar: oklch(0.18 0.01 80); --sidebar-fg: oklch(0.85 0.01 80);
  --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  --font-mono: "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace;
  --radius: 0.5rem;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --bg: oklch(0.17 0.008 80); --bg-2: oklch(0.21 0.008 80); --surface: oklch(0.22 0.008 80);
  --border: oklch(0.32 0.008 80); --text: oklch(0.94 0.005 80); --text-dim: oklch(0.70 0.008 80);
  --accent: oklch(0.68 0.14 295); --accent-soft: oklch(0.30 0.06 295); --accent-fg: oklch(0.15 0.01 80);
  --ok: oklch(0.72 0.13 150); --ok-soft: oklch(0.28 0.05 150); --danger: oklch(0.70 0.16 25);
  --warn: oklch(0.78 0.12 75); --warn-soft: oklch(0.30 0.05 75); color-scheme: dark; } }
:root[data-theme="dark"] {
  --bg: oklch(0.17 0.008 80); --bg-2: oklch(0.21 0.008 80); --surface: oklch(0.22 0.008 80);
  --border: oklch(0.32 0.008 80); --text: oklch(0.94 0.005 80); --text-dim: oklch(0.70 0.008 80);
  --accent: oklch(0.68 0.14 295); --accent-soft: oklch(0.30 0.06 295); --accent-fg: oklch(0.15 0.01 80);
  --ok: oklch(0.72 0.13 150); --ok-soft: oklch(0.28 0.05 150); --danger: oklch(0.70 0.16 25);
  --warn: oklch(0.78 0.12 75); --warn-soft: oklch(0.30 0.05 75); color-scheme: dark; }
* { box-sizing: border-box; }
body { background: var(--bg); color: var(--text); font: 14px/1.45 var(--font-sans); }
.wrap { max-width: 1180px; margin: 0 auto; padding-block: 24px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); display: grid; gap: 16px; }
.nota { background: var(--accent-soft); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px 14px; font-size: 13px; }
.ph { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; justify-content: space-between; }
.ph h1 { margin: 0; font-size: 20px; font-weight: 600; text-wrap: balance; }
.ph p { margin: 2px 0 0; color: var(--text-dim); font-size: 13px; }
.acts { display: flex; gap: 8px; flex-wrap: wrap; }
.btn { font: 500 13px var(--font-sans); border-radius: var(--radius); padding: 7px 12px; border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; display: inline-flex; gap: 6px; align-items: center; }
.btn.primary { background: var(--accent); color: var(--accent-fg); border-color: var(--accent); }
.btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; min-width: 0; }
.card .l { font-size: 12px; color: var(--text-dim); }
.card .v { font-size: 22px; font-weight: 600; font-variant-numeric: tabular-nums; }
.c { color: var(--ok); } .d { color: var(--danger); }
.tbl { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); white-space: nowrap; }
th { font-size: 12px; color: var(--text-dim); font-weight: 500; }
td.num { text-align: right; }
.chip { font-size: 11px; padding: 1px 7px; border-radius: 999px; background: var(--bg-2); color: var(--text-dim); border: 1px solid var(--border); }
.chip.ofx { background: var(--warn-soft); color: var(--text); }
/* Diálogo */
.dlg { background: var(--surface); border: 1px solid var(--border); border-radius: calc(var(--radius) + 4px); max-width: 560px; width: 100%; justify-self: center; box-shadow: 0 12px 40px oklch(0 0 0 / 0.18); }
.dlg header { padding: 16px 18px 8px; } .dlg header h2 { margin: 0; font-size: 16px; } .dlg header p { margin: 4px 0 0; color: var(--text-dim); font-size: 13px; }
.passos { display: flex; gap: 6px; padding: 0 18px 10px; flex-wrap: wrap; }
.passo { font-size: 12px; padding: 3px 9px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface); color: var(--text-dim); cursor: pointer; }
.passo[aria-current="step"] { background: var(--accent-soft); color: var(--text); border-color: var(--accent); }
.dlg section { padding: 4px 18px 14px; display: grid; gap: 12px; }
.drop { border: 1.5px dashed var(--border); border-radius: var(--radius); padding: 22px; text-align: center; background: var(--bg-2); }
.drop strong { display: block; } .drop span { color: var(--text-dim); font-size: 12px; }
.resumo { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.resumo div { background: var(--bg-2); border-radius: var(--radius); padding: 8px 10px; min-width: 0; }
.resumo b { display: block; font-size: 16px; font-variant-numeric: tabular-nums; }
.aviso { background: var(--warn-soft); border-radius: var(--radius); padding: 8px 10px; font-size: 13px; }
.ok-box { background: var(--ok-soft); border-radius: var(--radius); padding: 12px; }
.dlg footer { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 18px 16px; border-top: 1px solid var(--border); }
.mono { font-family: var(--font-mono); font-size: 12px; }
@media (max-width: 720px) { .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .resumo { grid-template-columns: 1fr; } }
</style>

<div class="wrap">
  <div class="nota"><strong>Proposta US-FIN-084.</strong> Ação nova "Importar OFX" na tela do extrato da conta, para bancos sem integração (Bradesco, Caixa, BB, C6, Cora). Só aparece com a flag ligada. Os valores são exemplo.</div>

  <div class="ph">
    <div>
      <h1>Extrato · Bradesco</h1>
      <p>Conta movimento · Ag. 1234 · Conta 56789-0</p>
    </div>
    <div class="acts">
      <button class="btn primary" id="abrir" type="button">Importar OFX</button>
      <button class="btn" type="button">Voltar pra contas</button>
    </div>
  </div>

  <div class="kpis">
    <div class="card"><div class="l">Saldo atual</div><div class="v">—</div><div class="l">Sem sync (banco sem integração)</div></div>
    <div class="card"><div class="l">Créditos</div><div class="v c">R$ 18.420,00</div></div>
    <div class="card"><div class="l">Débitos</div><div class="v d">R$ 9.315,60</div></div>
    <div class="card"><div class="l">Lançamentos</div><div class="v">31</div><div class="l">no período</div></div>
  </div>

  <div class="dlg" role="dialog" aria-labelledby="dlg-t" id="dlg">
    <header>
      <h2 id="dlg-t">Importar extrato OFX</h2>
      <p>Exporte o OFX no internet banking do Bradesco e envie aqui. Lançamentos que já estão no extrato não entram de novo.</p>
    </header>
    <nav class="passos" aria-label="Passos">
      <button class="passo" data-p="1" type="button">1 · Arquivo</button>
      <button class="passo" data-p="2" type="button" aria-current="step">2 · Conferir</button>
      <button class="passo" data-p="3" type="button">3 · Importado</button>
    </nav>

    <section data-s="1" hidden>
      <label class="drop" for="arquivo">
        <strong>Arraste o arquivo .ofx ou clique para escolher</strong>
        <span>Até 10 MB · um arquivo por conta</span>
      </label>
      <input id="arquivo" type="file" accept=".ofx" hidden>
    </section>

    <section data-s="2">
      <div class="mono">extrato-bradesco-outubro.ofx · 01/10/2026 a 26/10/2026</div>
      <div class="resumo">
        <div>Lançamentos<b>14</b></div>
        <div>Créditos<b class="c">R$ 6.240,00</b></div>
        <div>Débitos<b class="d">R$ 3.118,45</b></div>
      </div>
      <div class="aviso">3 lançamentos já estão no extrato e serão ignorados. Entram <strong>11</strong>.</div>
      <div class="tbl">
        <table>
          <thead><tr><th>Data</th><th>Descrição</th><th>Valor</th><th></th></tr></thead>
          <tbody>
            <tr><td>24/10</td><td>PIX RECEBIDO · GRAFICA SOL</td><td class="num c">+ 1.200,00</td><td><span class="chip">novo</span></td></tr>
            <tr><td>23/10</td><td>PAGTO BOLETO · PAPEL E CIA</td><td class="num d">− 842,30</td><td><span class="chip">novo</span></td></tr>
            <tr><td>22/10</td><td>TARIFA PACOTE SERVICOS</td><td class="num d">− 89,90</td><td><span class="chip">novo</span></td></tr>
            <tr><td>21/10</td><td>TED RECEBIDA · CLIENTE 0412</td><td class="num c">+ 2.500,00</td><td><span class="chip">já no extrato</span></td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section data-s="3" hidden>
      <div class="ok-box"><strong>11 lançamentos importados.</strong> Eles já aparecem no extrato e na Conciliação para casar com os títulos.</div>
    </section>

    <footer>
      <button class="btn" type="button" id="cancelar">Cancelar</button>
      <button class="btn primary" type="button" id="seguir">Importar 11 lançamentos</button>
    </footer>
  </div>

  <div class="card tbl">
    <table>
      <thead><tr><th>Data</th><th>Descrição</th><th>Origem</th><th>Valor</th></tr></thead>
      <tbody>
        <tr><td>20/10/2026</td><td>PIX RECEBIDO · ESTUDIO NORTE</td><td><span class="chip ofx">OFX</span></td><td class="num c">+ 640,00</td></tr>
        <tr><td>19/10/2026</td><td>PAGTO BOLETO · ENERGIA</td><td><span class="chip ofx">OFX</span></td><td class="num d">− 412,77</td></tr>
        <tr><td>18/10/2026</td><td>DEP DINHEIRO</td><td><span class="chip ofx">OFX</span></td><td class="num c">+ 1.050,00</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
const passos = [...document.querySelectorAll('.passo')];
const secs = [...document.querySelectorAll('[data-s]')];
const seguir = document.getElementById('seguir');
const rotulo = { 1: 'Conferir arquivo', 2: 'Importar 11 lançamentos', 3: 'Fechar' };
function ir(p) {
  passos.forEach(b => b.toggleAttribute('aria-current', false));
  passos[p - 1].setAttribute('aria-current', 'step');
  secs.forEach(s => { s.hidden = s.dataset.s !== String(p); });
  seguir.textContent = rotulo[p];
  seguir.dataset.p = p;
}
passos.forEach(b => b.addEventListener('click', () => ir(+b.dataset.p)));
seguir.addEventListener('click', () => ir(Math.min(3, (+seguir.dataset.p || 2) + 1)));
document.getElementById('abrir').addEventListener('click', () => { ir(1); document.getElementById('dlg').scrollIntoView({ behavior: 'smooth' }); });
document.getElementById('cancelar').addEventListener('click', () => ir(1));
ir(2);
</script>
`;
