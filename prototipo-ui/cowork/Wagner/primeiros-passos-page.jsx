// primeiros-passos-page.jsx — Primeiros passos. Protótipo de 27/10/2026 (PR #10009), fonte desta tela no shell.
// O HTML da tela vai literal abaixo; o prototipo-html-page.jsx o monta numa shadow root (CSS isolado,
// tema do shell, scripts e onclick funcionando). Para editar: é HTML comum, mas dentro deste texto
// crase, ${ e barra invertida vão com barra na frente (\`  \${  \\).
(window.PROTO_HTML = window.PROTO_HTML || {})["cfg-primeiros-passos"] = `<title>Primeiros passos</title>
<meta name="claude:viewport-fit-sides" content="cover">
<!--
  Protótipo · Onboarding guiado (US-SUPER-012) · card "Primeiros passos" na Visão geral.
  Fonte de design no git (ADR 0438 D1). Tokens: bloco \`.cockpit\` de
  prototipo-ui/design-system/colors_and_type.css (copiados aqui só para a página abrir sozinha
  como Artefato; em produção vêm do cockpit.css). Fica no topo de /dashboard-legacy, acima dos
  KPIs, e só aparece para empresa com passo aberto e que não dispensou.

  Regras que o desenho carrega:
  · cada passo marca FEITO a partir do dado real (CNPJ, certificado ativo, produto, cliente,
    venda final, segundo usuário) — clicar não marca nada;
  · "Pular" tira o passo da conta sem fingir que foi feito (fica "pulado", reversível);
  · "Dispensar" esconde o card para a empresa inteira; volta pelo link "Retomar primeiros passos";
  · o botão do passo leva à tela que já existe; o primeiro passo aberto é o destacado.
-->
<style>
  .cockpit {
    --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
    --font-mono: "IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace;
    --bg: oklch(0.985 0.003 90); --bg-2: oklch(0.965 0.004 90); --surface: #ffffff;
    --border: oklch(0.90 0.004 90); --border-2: oklch(0.93 0.004 90);
    --text: oklch(0.22 0.01 80); --text-dim: oklch(0.50 0.01 80); --text-mute: oklch(0.555 0.01 80);
    --accent: oklch(0.55 0.15 295); --accent-soft: oklch(0.95 0.04 295); --accent-fg: #ffffff;
    --pos: oklch(0.50 0.12 150); --pos-soft: oklch(0.95 0.075 150);
    --radius: 8px; --radius-sm: 6px;
    font-family: var(--font-sans); color: var(--text);
  }
  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) .cockpit {
      --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
      --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
      --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
      --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.33 0.07 150);
    }
  }
  :root[data-theme="dark"] .cockpit {
    --bg: oklch(0.26 0.006 240); --bg-2: oklch(0.23 0.006 240); --surface: oklch(0.30 0.008 240);
    --border: oklch(0.34 0.008 240); --border-2: oklch(0.31 0.008 240);
    --text: oklch(0.94 0.005 90); --text-dim: oklch(0.72 0.005 90); --text-mute: oklch(0.67 0.005 90);
    --accent-soft: oklch(0.33 0.09 295); --pos: oklch(0.76 0.18 150); --pos-soft: oklch(0.33 0.07 150);
  }
  :root { color-scheme: light; }
  @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { color-scheme: dark; } }
  :root[data-theme="dark"] { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin: 0; background: oklch(0.985 0.003 90); }
  @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) body { background: oklch(0.26 0.006 240); } }
  :root[data-theme="dark"] body { background: oklch(0.26 0.006 240); }
  .cockpit { background: var(--bg); min-height: 100%; padding-block: 16px; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); }
  .wrap { max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 10px; }
  .hdr h1 { font-size: 20px; font-weight: 600; margin: 4px 0 2px; }
  .hdr p { font-size: 12.5px; color: var(--text-dim); margin: 0; }
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 16px; }
  .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
  .top h2 { font-size: 13.5px; font-weight: 600; margin: 0; }
  .meta { font-family: var(--font-mono); font-size: 10.5px; color: var(--text-mute); }
  .bar { height: 6px; background: var(--bg-2); border-radius: 99px; overflow: hidden; margin: 10px 0 4px; }
  .bar > i { display: block; height: 100%; width: 50%; background: var(--accent); border-radius: 99px; }
  ol { list-style: none; margin: 8px 0 0; padding: 0; }
  li { display: grid; grid-template-columns: 22px minmax(0,1fr) auto; gap: 10px; align-items: center;
       padding: 9px 0; border-bottom: 1px solid var(--border-2); }
  li:last-child { border-bottom: 0; }
  .dot { width: 20px; height: 20px; border-radius: 99px; border: 1.5px solid var(--border);
         display: grid; place-items: center; font-size: 11px; color: var(--text-mute); }
  .feito .dot { background: var(--pos-soft); border-color: var(--pos); color: var(--pos); }
  .atual .dot { border-color: var(--accent); color: var(--accent); }
  .tit { font-size: 13px; font-weight: 500; }
  .feito .tit { color: var(--text-dim); }
  .pulado .tit { color: var(--text-mute); }
  .sub { font-size: 11.5px; color: var(--text-dim); margin-top: 1px; }
  .acts { display: flex; gap: 6px; align-items: center; }
  .btn { height: 32px; min-width: 44px; padding: 0 12px; border-radius: var(--radius-sm); font: 500 12.5px var(--font-sans);
         border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; }
  .btn.pri { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); }
  .lnk { background: none; border: 0; color: var(--text-dim); font: 12px var(--font-sans); cursor: pointer; padding: 6px; }
  .lnk:hover { color: var(--accent); }
  .tag { font-family: var(--font-mono); font-size: 10.5px; color: var(--pos); }
  .tag.p { color: var(--text-mute); }
  .kpis { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 10px; opacity: .55; }
  .kpis div { height: 92px; }
  @media (max-width: 640px) { .kpis { grid-template-columns: 1fr 1fr; } li { grid-template-columns: 22px minmax(0,1fr); } .acts { grid-column: 2; } }
</style>
<div class="cockpit">
  <div class="wrap">
    <div class="hdr"><h1>Bom dia, Larissa</h1><p>Visão geral · este mês</p></div>

    <section class="card" aria-label="Primeiros passos">
      <div class="top">
        <div>
          <h2>Primeiros passos</h2>
          <span class="meta">3 de 6 feitos · 1 pulado</span>
        </div>
        <button class="lnk" type="button">Dispensar</button>
      </div>
      <div class="bar" aria-hidden="true"><i></i></div>
      <ol>
        <li class="feito"><span class="dot">✓</span>
          <div><div class="tit">Dados da empresa</div><div class="sub">CNPJ e endereço preenchidos.</div></div>
          <div class="acts"><span class="tag">feito</span></div></li>
        <li class="pulado"><span class="dot">–</span>
          <div><div class="tit">Certificado e fiscal</div><div class="sub">Você pulou este passo. A nota fiscal só sai com o certificado A1.</div></div>
          <div class="acts"><span class="tag p">pulado</span><button class="lnk" type="button">Voltar a este passo</button></div></li>
        <li class="feito"><span class="dot">✓</span>
          <div><div class="tit">Primeiro produto</div><div class="sub">1 produto cadastrado.</div></div>
          <div class="acts"><span class="tag">feito</span></div></li>
        <li class="feito"><span class="dot">✓</span>
          <div><div class="tit">Primeiro cliente</div><div class="sub">1 cliente cadastrado.</div></div>
          <div class="acts"><span class="tag">feito</span></div></li>
        <li class="atual"><span class="dot">5</span>
          <div><div class="tit">Primeira venda</div><div class="sub">Lance uma venda finalizada, no PDV ou pela tela de venda.</div></div>
          <div class="acts"><button class="lnk" type="button">Pular</button><button class="btn pri" type="button">Fazer a primeira venda</button></div></li>
        <li><span class="dot">6</span>
          <div><div class="tit">Convidar a equipe</div><div class="sub">Crie o acesso de quem vai usar o sistema com você.</div></div>
          <div class="acts"><button class="lnk" type="button">Pular</button><button class="btn" type="button">Convidar</button></div></li>
      </ol>
    </section>

    <div class="kpis" aria-hidden="true"><div class="card"></div><div class="card"></div><div class="card"></div><div class="card"></div></div>
  </div>
</div>
`;
