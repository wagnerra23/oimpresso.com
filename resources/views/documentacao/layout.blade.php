<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('titulo', 'Documentação') — oimpresso</title>
<meta name="color-scheme" content="light dark">
<style>
  /* Acento = roxo canônico (ADR 0190). Neutros com viés violeta, escolhidos —
     cinza puro lê como não-considerado. CSS mora AQUI, num lugar só: três views
     com cópia do mesmo CSS seria o drift que este projeto combate. */
  :root {
    --paper:#FBFAFC; --surface:#F3F1F7; --ink:#17151E; --ink-soft:#4A4655;
    --ink-mute:#736E80; --rule:#DEDAE6; --rule-soft:#EAE7F0;
    --accent:#6D4FD1; --accent:oklch(0.55 0.15 295); --accent-bg:#F0EBFC;
    --serif:"Iowan Old Style","Palatino Linotype",Palatino,"Book Antiqua",Georgia,serif;
    --sans:"Segoe UI",-apple-system,BlinkMacSystemFont,"Helvetica Neue",Arial,sans-serif;
    --mono:ui-monospace,"SF Mono","Cascadia Mono",Consolas,"Liberation Mono",monospace;
  }
  @media (prefers-color-scheme: dark) {
    :root { --paper:#131118; --surface:#1C1926; --ink:#E9E6F0; --ink-soft:#B6B1C4;
      --ink-mute:#8B8598; --rule:#2E2A3A; --rule-soft:#241F2E;
      --accent:oklch(0.74 0.13 295); --accent-bg:#241C3D; }
  }
  :root[data-theme="dark"]{--paper:#131118;--surface:#1C1926;--ink:#E9E6F0;--ink-soft:#B6B1C4;
    --ink-mute:#8B8598;--rule:#2E2A3A;--rule-soft:#241F2E;--accent:oklch(0.74 0.13 295);--accent-bg:#241C3D}
  :root[data-theme="light"]{--paper:#FBFAFC;--surface:#F3F1F7;--ink:#17151E;--ink-soft:#4A4655;
    --ink-mute:#736E80;--rule:#DEDAE6;--rule-soft:#EAE7F0;--accent:oklch(0.55 0.15 295);--accent-bg:#F0EBFC}

  *,*::before,*::after{box-sizing:border-box}
  body{margin:0;background:var(--paper);color:var(--ink);font-family:var(--sans);
    font-size:16.5px;line-height:1.65;-webkit-font-smoothing:antialiased}

  /* ── barra superior: identidade + busca ─────────────────────────── */
  .topo{border-bottom:1px solid var(--rule);background:var(--surface)}
  .topo-in{max-width:1160px;margin:0 auto;padding:14px 32px;display:flex;
    align-items:center;gap:24px;flex-wrap:wrap}
  .marca{font-family:var(--mono);font-size:11px;letter-spacing:.14em;text-transform:uppercase;
    color:var(--accent);text-decoration:none;white-space:nowrap}
  .busca{flex:1;min-width:220px;display:flex;gap:8px}
  .busca input{flex:1;font:inherit;font-size:14px;padding:7px 12px;color:var(--ink);
    background:var(--paper);border:1px solid var(--rule);border-radius:4px}
  .busca input::placeholder{color:var(--ink-mute)}
  .busca input:focus{outline:2px solid var(--accent);outline-offset:1px;border-color:transparent}
  .busca button{font:inherit;font-size:13px;padding:7px 16px;cursor:pointer;color:#fff;
    background:var(--accent);border:1px solid var(--accent);border-radius:4px}
  .topo a.volta{font-size:13px;color:var(--ink-soft);text-decoration:none;white-space:nowrap}
  .topo a.volta:hover{color:var(--accent)}

  .wrap{max-width:1160px;margin:0 auto;padding:0 32px 120px}
  .col{max-width:72ch}

  /* ── trilho de sumário ───────────────────────────────────────────
     Os itens vêm DERIVADOS dos títulos (DocumentacaoController::comSumario) —
     não há lista escrita à mão em lugar nenhum pra ficar desatualizada.
     Estreito: só acompanha a leitura; quem lê continua lendo a coluna. */
  .com-trilho{display:block}
  .trilho{font-size:13px;margin:36px 0 8px;max-width:72ch}
  .trilho-tit{font-family:var(--mono);font-size:10px;letter-spacing:.12em;text-transform:uppercase;
    color:var(--ink-mute);margin:0 0 8px}
  .trilho ol{list-style:none;margin:0;padding:0;display:block}
  .trilho a{display:flex;gap:9px;align-items:baseline;padding:3px 0 3px 11px;
    color:var(--ink-soft);text-decoration:none;border-left:2px solid var(--rule-soft);
    line-height:1.35;border-bottom:0}
  .trilho a:hover{color:var(--accent);border-left-color:var(--rule)}
  .trilho a[aria-current="true"]{color:var(--accent);border-left-color:var(--accent);font-weight:600}
  .trilho .grupo > a{font-family:var(--serif);font-size:14.5px;color:var(--ink);
    margin-top:15px;border-left-color:transparent;padding-left:0}
  .trilho .grupo:first-child > a{margin-top:0}
  .trilho .cod{font-family:var(--mono);font-size:10.5px;color:var(--ink-mute);flex:0 0 auto}
  .trilho a[aria-current="true"] .cod{color:var(--accent)}

  @media (min-width:1080px){
    .com-trilho{display:grid;grid-template-columns:minmax(0,72ch) 208px;
      gap:0 60px;align-items:start}
    .com-trilho > .col{grid-column:1;grid-row:1}
    .com-trilho > .trilho{grid-column:2;grid-row:1;position:sticky;top:22px;margin:6px 0 0;
      max-height:calc(100vh - 44px);overflow-y:auto;overscroll-behavior:contain}
    .trilho ol{columns:1}
  }
  @media (max-width:1079px){
    .trilho ol{columns:2;column-gap:26px}
    .trilho li{break-inside:avoid}
  }

  .stamp{font-family:var(--mono);font-size:11px;letter-spacing:.1em;text-transform:uppercase;
    color:var(--accent);margin:44px 0 16px}
  .colophon{display:flex;flex-wrap:wrap;gap:4px 22px;font-family:var(--mono);font-size:11.5px;
    color:var(--ink-mute);padding:14px 0 36px;border-bottom:1px solid var(--rule-soft);margin-bottom:36px}
  .colophon b{font-weight:600;color:var(--ink-soft)}

  /* ── tipografia do markdown renderizado ─────────────────────────── */
  .doc h1{font-family:var(--serif);font-weight:400;font-size:clamp(32px,5vw,48px);
    line-height:1.07;letter-spacing:-.015em;margin:0 0 20px;text-wrap:balance}
  .doc h2{font-family:var(--serif);font-weight:400;font-size:29px;line-height:1.2;
    margin:48px 0 10px;padding-top:20px;border-top:1px solid var(--rule-soft);
    letter-spacing:-.01em;text-wrap:balance;scroll-margin-top:20px}
  .doc h3{font-size:15px;font-weight:650;margin:28px 0 8px;scroll-margin-top:20px}
  .doc h4{font-size:13px;font-weight:650;color:var(--ink-soft);margin:22px 0 6px}
  .doc p{margin:0 0 16px}
  .doc strong{font-weight:640}
  .doc a{color:var(--accent);text-decoration:none;
    border-bottom:1px solid color-mix(in srgb,var(--accent) 32%,transparent)}
  .doc a:hover{border-bottom-color:var(--accent)}
  .doc a:focus-visible,a:focus-visible{outline:2px solid var(--accent);outline-offset:3px;border-radius:2px}
  .doc code{font-family:var(--mono);font-size:.855em;background:var(--surface);padding:.1em .36em;border-radius:3px}
  .doc pre{font-family:var(--mono);font-size:12.5px;line-height:1.6;background:var(--surface);
    color:var(--ink-soft);padding:18px 20px;margin:6px 0 26px;overflow-x:auto;border-left:2px solid var(--rule)}
  .doc pre code{background:none;padding:0;font-size:inherit}
  .doc ul,.doc ol{margin:0 0 16px;padding-left:22px;display:flex;flex-direction:column;gap:8px}
  .doc blockquote{border-left:2px solid var(--accent);background:var(--accent-bg);
    padding:14px 20px;margin:6px 0 26px;font-size:15px}
  .doc blockquote p:last-child{margin-bottom:0}
  .doc hr{border:0;border-top:1px solid var(--rule);margin:40px 0}
  .tabela-scroll{overflow-x:auto;margin:6px 0 26px}
  .doc table{border-collapse:collapse;width:100%;font-size:14.5px}
  .doc th,.doc td{text-align:left;padding:10px 16px 10px 0;border-bottom:1px solid var(--rule-soft);vertical-align:top}
  .doc th{font-family:var(--mono);font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;
    color:var(--ink-mute);font-weight:500;border-bottom:1px solid var(--rule);white-space:nowrap}
  .doc td:last-child,.doc th:last-child{padding-right:0}

  /* ── resultados de busca ────────────────────────────────────────── */
  .achado{padding:18px 0;border-bottom:1px solid var(--rule-soft)}
  .achado a.tit{font-family:var(--serif);font-size:20px;color:var(--ink);text-decoration:none;
    display:inline-block;margin-bottom:4px}
  .achado a.tit:hover{color:var(--accent)}
  .achado .meta{font-family:var(--mono);font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;
    color:var(--ink-mute);margin-bottom:6px;display:flex;gap:14px;flex-wrap:wrap}
  .achado .tag{color:var(--accent)}
  .achado p{margin:0;font-size:14.5px;color:var(--ink-soft)}
  .vazio{padding:40px 0;color:var(--ink-soft)}

  footer{margin-top:60px;padding-top:22px;border-top:1px solid var(--ink);
    font-size:13.5px;color:var(--ink-soft);max-width:72ch}

  @media (max-width:900px){ .wrap,.topo-in{padding-left:20px;padding-right:20px} }
  @media (prefers-reduced-motion: reduce){*{scroll-behavior:auto !important}}
  html{scroll-behavior:smooth}
</style>
@stack('estilo')
</head>
<body>

<div class="topo">
  <div class="topo-in">
    <a class="marca" href="{{ route('documentacao') }}">oimpresso · documentação</a>
    <form class="busca" method="GET" action="{{ route('documentacao.buscar') }}" role="search">
      <input type="search" name="q" value="{{ $termo ?? '' }}"
             placeholder="Buscar em decisões, referências, specs e runbooks…"
             aria-label="Buscar na documentação">
      <button type="submit">Buscar</button>
    </form>
    <a class="volta" href="/">← voltar ao sistema</a>
  </div>
</div>

<div class="wrap">
  @yield('conteudo')
</div>

{{-- Mermaid servido do NOSSO domínio (public/js), nunca de CDN: a página não carrega
     nenhum recurso externo, e essa é uma qualidade que não se derruba por conveniência.
     O bundle expõe o global via `globalThis["mermaid"]` na última linha — por isso
     <script src> simples basta, sem type="module". --}}
<script src="/js/mermaid.min.js"></script>
<script>
  (function () {
    if (typeof mermaid === 'undefined') return;   // lib ausente: diagrama fica como código, página não quebra

    var escuro = document.documentElement.dataset.theme === 'dark'
      || (!document.documentElement.dataset.theme
          && window.matchMedia('(prefers-color-scheme: dark)').matches);

    mermaid.initialize({
      startOnLoad: false,
      theme: escuro ? 'dark' : 'neutral',
      securityLevel: 'strict',                    // sem HTML arbitrário vindo do markdown
      fontFamily: 'Segoe UI, -apple-system, sans-serif',
      flowchart: { htmlLabels: true, curve: 'basis' },
    });

    // O markdown vira <pre><code class="language-mermaid">; o mermaid espera <pre class="mermaid">.
    // textContent (não innerHTML) porque o conteúdo vem HTML-escaped do conversor.
    var blocos = document.querySelectorAll('pre > code.language-mermaid');
    if (!blocos.length) return;

    blocos.forEach(function (code) {
      var pre = code.parentElement;
      var alvo = document.createElement('pre');
      alvo.className = 'mermaid';
      alvo.textContent = code.textContent;
      pre.parentNode.replaceChild(alvo, pre);
    });

    mermaid.run({ querySelector: 'pre.mermaid' }).catch(function () {
      // diagrama inválido não derruba a página — fica o texto do próprio diagrama
    });
  })();
</script>

@stack('script')
</body>
</html>
