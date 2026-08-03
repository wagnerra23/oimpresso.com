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
  .doc h3{font-size:15px;font-weight:650;margin:28px 0 8px}
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

@stack('script')
</body>
</html>
