<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Documentação do sistema — oimpresso</title>
<meta name="color-scheme" content="light dark">
<style>
  /* Acento = roxo canônico do projeto (ADR 0190). Neutros com viés violeta,
     escolhidos — cinza puro lê como não-considerado. */
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
  :root[data-theme="dark"] { --paper:#131118; --surface:#1C1926; --ink:#E9E6F0;
    --ink-soft:#B6B1C4; --ink-mute:#8B8598; --rule:#2E2A3A; --rule-soft:#241F2E;
    --accent:oklch(0.74 0.13 295); --accent-bg:#241C3D; }
  :root[data-theme="light"] { --paper:#FBFAFC; --surface:#F3F1F7; --ink:#17151E;
    --ink-soft:#4A4655; --ink-mute:#736E80; --rule:#DEDAE6; --rule-soft:#EAE7F0;
    --accent:oklch(0.55 0.15 295); --accent-bg:#F0EBFC; }

  *,*::before,*::after{box-sizing:border-box}
  body{margin:0;background:var(--paper);color:var(--ink);font-family:var(--sans);
    font-size:16.5px;line-height:1.65;-webkit-font-smoothing:antialiased}

  .shell{display:grid;grid-template-columns:236px minmax(0,1fr);gap:56px;
    max-width:1160px;margin:0 auto;padding:0 32px 120px}

  .rail-inner{position:sticky;top:0;padding:56px 0 40px;max-height:100vh;overflow-y:auto}
  .rail-label{font-family:var(--mono);font-size:10.5px;letter-spacing:.14em;
    text-transform:uppercase;color:var(--ink-mute);margin-bottom:14px}
  .rail-inner ol{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:1px}
  .rail-inner a{display:block;padding:4px 8px 4px 10px;font-size:13.5px;line-height:1.4;
    color:var(--ink-soft);text-decoration:none;border-left:2px solid transparent}
  .rail-inner a:hover{color:var(--ink);border-left-color:var(--rule)}
  .rail-inner a.on{color:var(--accent);border-left-color:var(--accent)}

  main{padding-top:56px;max-width:72ch}

  .stamp{font-family:var(--mono);font-size:11px;letter-spacing:.1em;text-transform:uppercase;
    color:var(--accent);margin-bottom:18px}
  .colophon{display:flex;flex-wrap:wrap;gap:4px 22px;font-family:var(--mono);font-size:11.5px;
    color:var(--ink-mute);padding:16px 0 40px;border-bottom:1px solid var(--rule-soft);margin-bottom:40px}
  .colophon b{font-weight:600;color:var(--ink-soft)}

  /* ── tipografia do markdown renderizado ─────────────────────────── */
  main h1{font-family:var(--serif);font-weight:400;font-size:clamp(34px,5.5vw,52px);
    line-height:1.06;letter-spacing:-.015em;margin:0 0 20px;text-wrap:balance}
  main h2{font-family:var(--serif);font-weight:400;font-size:30px;line-height:1.2;
    margin:52px 0 10px;padding-top:20px;border-top:1px solid var(--rule-soft);
    letter-spacing:-.01em;text-wrap:balance;scroll-margin-top:20px}
  main h3{font-size:15px;font-weight:650;margin:30px 0 8px}
  main h4{font-size:13px;font-weight:650;color:var(--ink-soft);margin:24px 0 6px}
  main p{margin:0 0 16px}
  main strong{font-weight:640}
  main a{color:var(--accent);text-decoration:none;
    border-bottom:1px solid color-mix(in srgb,var(--accent) 32%,transparent)}
  main a:hover{border-bottom-color:var(--accent)}
  main a:focus-visible,.rail-inner a:focus-visible{outline:2px solid var(--accent);
    outline-offset:3px;border-radius:2px}
  main code{font-family:var(--mono);font-size:.855em;background:var(--surface);
    padding:.1em .36em;border-radius:3px}
  main pre{font-family:var(--mono);font-size:12.5px;line-height:1.6;background:var(--surface);
    color:var(--ink-soft);padding:18px 20px;margin:6px 0 26px;overflow-x:auto;
    border-left:2px solid var(--rule)}
  main pre code{background:none;padding:0;font-size:inherit}
  main ul,main ol{margin:0 0 16px;padding-left:22px;display:flex;flex-direction:column;gap:8px}
  main li{padding-left:2px}
  main blockquote{border-left:2px solid var(--accent);background:var(--accent-bg);
    padding:14px 20px;margin:6px 0 26px;font-size:15px}
  main blockquote p:last-child{margin-bottom:0}
  main hr{border:0;border-top:1px solid var(--rule);margin:40px 0}
  .tabela-scroll{overflow-x:auto;margin:6px 0 26px}
  main table{border-collapse:collapse;width:100%;font-size:14.5px}
  main th,main td{text-align:left;padding:10px 16px 10px 0;
    border-bottom:1px solid var(--rule-soft);vertical-align:top}
  main th{font-family:var(--mono);font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;
    color:var(--ink-mute);font-weight:500;border-bottom:1px solid var(--rule);white-space:nowrap}
  main td:last-child,main th:last-child{padding-right:0}

  footer{margin-top:64px;padding-top:24px;border-top:1px solid var(--ink);
    font-size:13.5px;color:var(--ink-soft)}

  @media (max-width:900px){
    .shell{grid-template-columns:1fr;gap:0;padding:0 22px 80px}
    .rail-inner{position:static;max-height:none;padding:36px 0 0}
    .rail-inner ol{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}
    main{padding-top:30px}
  }
  @media (prefers-reduced-motion: reduce){*{scroll-behavior:auto !important}}
  html{scroll-behavior:smooth}
</style>
</head>
<body>
<div class="shell">
  <nav class="rail" aria-label="Sumário">
    <div class="rail-inner">
      <div class="rail-label">Sumário</div>
      <ol id="toc"></ol>
    </div>
  </nav>

  <main id="conteudo">
    <div class="stamp">Documentação do sistema</div>

    <div class="colophon">
      <span><b>Fonte</b> {{ $fonte }}</span>
      @if ($atualizadoEm)
        <span><b>Atualizado em</b> {{ $atualizadoEm }}</span>
      @endif
      <span><b>Renderizado</b> a cada acesso</span>
    </div>

    {!! $html !!}

    <footer>
      Esta página <strong>é</strong> o documento <code>{{ $fonte }}</code>, renderizado —
      não uma cópia dele. Alterou a fonte por PR? A página muda no próximo acesso.
      Não existe versão intermediária para ficar desatualizada.
    </footer>
  </main>
</div>

<script>
  // Sumário derivado dos <h2> do próprio conteúdo — nada mantido à mão.
  (function () {
    var main = document.getElementById('conteudo');
    var toc = document.getElementById('toc');
    var hs = main.querySelectorAll('h2');
    if (!hs.length) { toc.closest('.rail').style.display = 'none'; return; }

    var mapa = {};
    hs.forEach(function (h, i) {
      if (!h.id) { h.id = 'sec-' + i; }
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.href = '#' + h.id;
      a.textContent = h.textContent.replace(/^[^A-Za-zÀ-ÿ0-9]+/, '').trim();
      li.appendChild(a); toc.appendChild(li);
      mapa[h.id] = a;
    });

    // Tabela larga rola sozinha — o corpo da página nunca rola de lado.
    main.querySelectorAll('table').forEach(function (t) {
      if (t.parentElement.classList.contains('tabela-scroll')) return;
      var box = document.createElement('div');
      box.className = 'tabela-scroll';
      t.parentNode.insertBefore(box, t); box.appendChild(t);
    });

    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        Object.values(mapa).forEach(function (a) { a.classList.remove('on'); });
        if (mapa[e.target.id]) { mapa[e.target.id].classList.add('on'); }
      });
    }, { rootMargin: '0px 0px -72% 0px', threshold: 0 });
    hs.forEach(function (h) { obs.observe(h); });
  })();
</script>
</body>
</html>
