// financeiro-contas-bancarias-page.jsx — Contas bancárias, tela única de contas. Protótipo de 27/10/2026, fonte desta tela no shell.
// Decisão [W] 27/10 (opção A): o cadastro das contas do núcleo (/account/account) passa para esta tela do Financeiro.
// Lista, filtro Ativa/Encerrada e menu da linha seguem telaContas() de financeiro-legado.jsx; o painel "Nova conta" e o
// painel "Tipos de conta" são novos, ancorados no DS (tokens do bloco .cockpit). Dados de exemplo, fictícios.
// O HTML vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root. Dentro deste texto, crase, cifrão-chave
// e barra invertida precisariam de escape — este arquivo não usa nenhum dos três.
(window.PROTO_HTML = window.PROTO_HTML || {})["fin-contas-bancarias"] = `<title>Contas bancárias</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
<style>
:root {
  --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  --font-mono: "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace;
  --fs-2: 11.5px; --fs-3: 12.5px; --fs-4: 13.5px; --fs-5: 15px;
  --bg: oklch(0.985 0.003 90); --bg-2: oklch(0.965 0.004 90); --surface: #ffffff;
  --border: oklch(0.90 0.004 90); --border-2: oklch(0.93 0.004 90);
  --text: oklch(0.22 0.01 80); --text-dim: oklch(0.50 0.01 80);
  --accent: oklch(0.55 0.15 295); --accent-soft: oklch(0.95 0.04 295); --accent-fg: #ffffff;
  --pos: oklch(0.50 0.12 150); --pos-soft: oklch(0.95 0.075 150);
  --neg: oklch(0.55 0.18 25); --warn: oklch(0.56 0.12 70); --warn-soft: oklch(0.955 0.072 75);
  --radius: 8px; --radius-sm: 6px; --shadow-pop: 0 6px 24px -8px rgba(0,0,0,.18), 0 2px 6px -2px rgba(0,0,0,.10);
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.34 0.06 150);
  --neg: oklch(0.74 0.19 25); --warn: oklch(0.82 0.16 75); --warn-soft: oklch(0.35 0.06 75);
  color-scheme: dark; } }
:root[data-theme="dark"] {
  --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
  --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
  --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90);
  --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.34 0.06 150);
  --neg: oklch(0.74 0.19 25); --warn: oklch(0.82 0.16 75); --warn-soft: oklch(0.35 0.06 75);
  color-scheme: dark;
}
body { background: var(--bg); color: var(--text); font-family: var(--font-sans); font-size: var(--fs-4); margin: 0; }
.wrap { max-width: 1180px; margin: 0 auto; padding: 20px 16px 48px; display: flex; flex-direction: column; gap: 16px; }
.head { display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.head h1 { margin: 0; font-size: 20px; font-weight: 600; }
.head p { margin: 4px 0 0; color: var(--text-dim); }
.grow { flex: 1 1 320px; }
.btn { height: 36px; padding: 0 14px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--surface); color: var(--text); font: inherit; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.btn.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); font-weight: 500; }
.btn.icon { width: 36px; padding: 0; justify-content: center; border-color: transparent; background: transparent; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); }
.bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 10px 12px; border-bottom: 1px solid var(--border-2); }
.seg { display: inline-flex; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.seg button { height: 32px; padding: 0 12px; border: 0; background: var(--surface); color: var(--text-dim); font: inherit; cursor: pointer; }
.seg button + button { border-left: 1px solid var(--border); }
.seg button[aria-pressed="true"] { background: var(--accent-soft); color: var(--text); font-weight: 500; }
.muted { color: var(--text-dim); }
.scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; font-weight: 500; font-size: var(--fs-3); color: var(--text-dim); padding: 8px 12px; }
td { padding: 10px 12px; border-top: 1px solid var(--border-2); vertical-align: middle; }
.num { text-align: right; font-family: var(--font-mono); font-variant-numeric: tabular-nums; white-space: nowrap; }
.mono { font-family: var(--font-mono); }
.sub { font-size: var(--fs-3); color: var(--text-dim); }
.pill { display: inline-block; padding: 2px 8px; border-radius: var(--radius-sm); font-size: var(--fs-3); }
.pill.ok { background: var(--pos-soft); color: var(--pos); }
.pill.warn { background: var(--warn-soft); color: var(--warn); }
.pill.off { background: var(--bg-2); color: var(--text-dim); }
.menu { position: relative; display: inline-block; }
.menu ul { display: none; position: absolute; right: 0; top: 38px; z-index: 5; min-width: 190px; margin: 0; padding: 4px; list-style: none; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-pop); }
.menu.open ul { display: block; }
.menu li button { width: 100%; text-align: left; height: 34px; padding: 0 10px; border: 0; background: transparent; color: var(--text); font: inherit; border-radius: var(--radius-sm); cursor: pointer; }
.menu li button:hover { background: var(--bg-2); }
.menu li.sep { border-top: 1px solid var(--border-2); margin: 4px 0; }
.menu li button.danger { color: var(--neg); }
.nota { font-size: var(--fs-3); color: var(--text-dim); padding: 10px 12px; border-top: 1px solid var(--border-2); margin: 0; }
.scrim { display: none; position: fixed; inset: 0; background: rgba(20,20,19,.28); z-index: 10; }
.drawer { display: none; position: fixed; top: 0; right: 0; bottom: 0; width: min(480px, 100%); z-index: 11; background: var(--surface); border-left: 1px solid var(--border); box-shadow: var(--shadow-pop); flex-direction: column; }
.aberto .scrim, .drawer.aberto { display: flex; }
.aberto .scrim { display: block; }
.dhead { display: flex; align-items: center; gap: 8px; padding: 16px 20px; border-bottom: 1px solid var(--border-2); }
.dhead h2 { margin: 0; font-size: var(--fs-5); font-weight: 600; flex: 1; }
.dbody { flex: 1; overflow: auto; padding: 16px 20px; display: flex; flex-direction: column; gap: 14px; }
.dfoot { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 20px; border-top: 1px solid var(--border-2); }
label.f { display: flex; flex-direction: column; gap: 6px; font-weight: 500; }
label.f small { font-weight: 400; color: var(--text-dim); font-size: var(--fs-3); }
input, select, textarea { height: 36px; box-sizing: border-box; padding: 0 10px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text); font: inherit; font-weight: 400; }
textarea { height: auto; padding: 8px 10px; resize: vertical; }
.par { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr) 36px; gap: 8px; }
.req { color: var(--neg); }
ul.tipos { list-style: none; margin: 0; padding: 0; }
ul.tipos li { display: flex; align-items: center; gap: 6px; padding: 6px 0; border-top: 1px solid var(--border-2); }
ul.tipos li.subtipo { padding-left: 20px; color: var(--text-dim); }
ul.tipos li span { flex: 1; }
.box { border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; display: flex; flex-direction: column; gap: 10px; }
</style>

<div class="wrap" id="tela">
  <div class="head">
    <div class="grow">
      <h1>Contas bancárias</h1>
      <p>Cadastro, saldo e dados de boleto de cada conta. Credenciais de gateway ficam em Configurações › Gateways.</p>
    </div>
    <button class="btn" onclick="abrir('d-tipos')">Tipos de conta</button>
    <button class="btn primary" onclick="abrir('d-conta')">+ Nova conta</button>
  </div>

  <div class="card">
    <div class="bar">
      <div class="seg" role="group" aria-label="Situação da conta">
        <button aria-pressed="true" onclick="situacao(this)">Ativa</button>
        <button aria-pressed="false" onclick="situacao(this)">Encerrada</button>
      </div>
      <span class="muted" style="margin-left:auto">3 contas ativas</span>
    </div>
    <div class="scroll">
      <table>
        <thead><tr><th>Conta</th><th>Tipo · subtipo</th><th>Banco</th><th>Boleto</th><th class="num">Saldo</th><th><span style="position:absolute;left:-9999px">Ações</span></th></tr></thead>
        <tbody>
          <tr>
            <td><div style="font-weight:500">Caixa Loja</div><div class="sub">—</div></td>
            <td>Ativo · Caixa</td><td class="muted">—</td><td><span class="pill off">Sem boleto</span></td>
            <td class="num">R$ 1.240,00</td>
            <td><div class="menu"><button class="btn icon" aria-label="Ações da conta Caixa Loja" onclick="menu(this)">⋮</button>
              <ul><li><button onclick="abrir('d-conta')">Editar conta</button></li><li><button>Configurar boleto</button></li><li class="sep"></li><li><button class="danger">Encerrar conta</button></li></ul></div></td>
          </tr>
          <tr>
            <td><div style="font-weight:500">Sicoob PJ</div><div class="sub mono">Ag 3001 · Cc 45678-9</div></td>
            <td>Ativo · Conta corrente</td><td class="mono">756</td><td><span class="pill ok">Ativo · Cart. 1</span></td>
            <td class="num">R$ 18.906,35</td>
            <td><div class="menu"><button class="btn icon" aria-label="Ações da conta Sicoob PJ" onclick="menu(this)">⋮</button>
              <ul><li><button onclick="abrir('d-conta')">Editar conta</button></li><li><button>Editar boleto</button></li><li class="sep"></li><li><button class="danger">Encerrar conta</button></li></ul></div></td>
          </tr>
          <tr>
            <td><div style="font-weight:500">Asaas</div><div class="sub">—</div></td>
            <td>Ativo · Conta virtual</td><td class="mono">274</td><td><span class="pill warn">Faltam dados</span></td>
            <td class="num">R$ 2.115,80</td>
            <td><div class="menu"><button class="btn icon" aria-label="Ações da conta Asaas" onclick="menu(this)">⋮</button>
              <ul><li><button onclick="abrir('d-conta')">Editar conta</button></li><li><button>Configurar boleto</button></li><li class="sep"></li><li><button class="danger">Encerrar conta</button></li></ul></div></td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="nota">O saldo vem do mesmo cálculo de hoje (crédito − débito). A tela não soma saldos: total e KPIs ficam para decisão [W].</p>
  </div>
</div>

<div class="scrim" onclick="fechar()"></div>

<aside class="drawer" id="d-conta" aria-label="Nova conta">
  <div class="dhead"><h2>Nova conta</h2><button class="btn icon" aria-label="Fechar" onclick="fechar()">✕</button></div>
  <div class="dbody">
    <label class="f">Nome <span class="req">*</span><input value="Banco do Brasil PJ"></label>
    <label class="f">Tipo de conta<select><option>Sem tipo</option><option selected>Ativo · Conta corrente</option><option>Ativo · Caixa</option></select>
      <small>Subtipos aparecem dentro do tipo. Tipos novos no botão "Tipos de conta".</small></label>
    <label class="f">Número da conta<input class="mono" value="12345-6"></label>
    <label class="f">Saldo de abertura<input class="mono" style="text-align:right" placeholder="0,00">
      <small>Só na criação. Entra como crédito "saldo de abertura" na data de hoje.</small></label>
    <fieldset style="border:0;margin:0;padding:0;display:flex;flex-direction:column;gap:8px">
      <legend style="font-weight:500;margin-bottom:6px">Detalhes da conta</legend>
      <div class="par"><input aria-label="Rótulo 1" value="Agência"><input aria-label="Valor 1" value="0001"><button class="btn icon" aria-label="Remover detalhe 1">✕</button></div>
      <button class="btn" style="align-self:flex-start">+ Adicionar detalhe</button>
    </fieldset>
    <label class="f">Observação<textarea rows="3"></textarea></label>
  </div>
  <div class="dfoot"><button class="btn" onclick="fechar()">Cancelar</button><button class="btn primary" onclick="fechar()">Salvar conta</button></div>
</aside>

<aside class="drawer" id="d-tipos" aria-label="Tipos de conta">
  <div class="dhead"><h2>Tipos de conta</h2><button class="btn icon" aria-label="Fechar" onclick="fechar()">✕</button></div>
  <div class="dbody">
    <p class="muted" style="margin:0">Agrupam as contas na lista. Um subtipo fica dentro de um tipo.</p>
    <ul class="tipos">
      <li><span style="font-weight:500">Ativo</span><button class="btn" style="height:30px" aria-label="Editar tipo Ativo">Editar</button><button class="btn" style="height:30px" aria-label="Remover tipo Ativo">Remover</button></li>
      <li class="subtipo"><span>Caixa</span><button class="btn" style="height:30px" aria-label="Editar subtipo Caixa">Editar</button><button class="btn" style="height:30px" aria-label="Remover subtipo Caixa">Remover</button></li>
      <li class="subtipo"><span>Conta corrente</span><button class="btn" style="height:30px" aria-label="Editar subtipo Conta corrente">Editar</button><button class="btn" style="height:30px" aria-label="Remover subtipo Conta corrente">Remover</button></li>
      <li class="subtipo"><span>Conta virtual</span><button class="btn" style="height:30px" aria-label="Editar subtipo Conta virtual">Editar</button><button class="btn" style="height:30px" aria-label="Remover subtipo Conta virtual">Remover</button></li>
    </ul>
    <div class="box">
      <strong>Novo tipo</strong>
      <label class="f">Nome <span class="req">*</span><input></label>
      <label class="f">Dentro de<select><option>Nenhum (é um tipo)</option><option>Ativo</option></select></label>
      <div style="display:flex;justify-content:flex-end"><button class="btn primary">Adicionar tipo</button></div>
    </div>
  </div>
</aside>

<script>
  var raiz = document.getElementById('tela').getRootNode();
  function q(s) { return raiz.querySelector(s); }
  function qa(s) { return raiz.querySelectorAll(s); }
  function fechar() {
    qa('.drawer').forEach(function (d) { d.classList.remove('aberto'); });
    q('.scrim').style.display = 'none';
  }
  function abrir(id) {
    fechar();
    qa('.menu').forEach(function (m) { m.classList.remove('open'); });
    q('#' + id).classList.add('aberto');
    q('.scrim').style.display = 'block';
  }
  function menu(b) {
    var m = b.parentNode, ja = m.classList.contains('open');
    qa('.menu').forEach(function (x) { x.classList.remove('open'); });
    if (!ja) m.classList.add('open');
  }
  function situacao(b) {
    b.parentNode.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
  }
</script>
`;
