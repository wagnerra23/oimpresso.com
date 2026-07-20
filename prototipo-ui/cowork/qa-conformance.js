/* qa-conformance.js — v2 GENÉRICO · Probe de CONFORMÂNCIA DS (camada 2 · METODO_TELA_ANTI-REGRESSAO §Matriz)
 *
 * v1 (2026-06-03) cobria 4 UCs da Vendas (roxo×verde, cor crua, drawer 480, dark) hardcoded .vendas-aplus.
 * v2 (2026-06-10 · [W] aprovou P1/P2 da "Auditoria - Vazamento de Conhecimento") generaliza:
 *   roda na TELA ATIVA (qualquer rota) com CLASSES de asserção — não instâncias fossilizadas:
 *   G1 accent canon      — primários computam roxo 250–330 (era UC-V09)
 *   G2 controle nativo   — checkbox/radio visível sem accent-color tokenizado = 🔴 (erro 06-10a)
 *   G3 papel de token    — var(--*-fg) em background/fill, var(--*-bg) em color = 🔴 (erro 06-10a)
 *   G4 overflow-x        — drawer/dialog com scrollWidth > clientWidth = 🔴 (erro 06-10b, add-row)
 *   G5 cor crua aplicada — hex/oklch/white/black cru em regra que CASA com o DOM atual (era UC-V10,
 *                          agora DOM-matched = só o que está NO AR; ratchet por baseline only-down)
 *   G6 dark legível      — superfície grande quase-branca no tema dark = 🔴 (era UC-V12)
 *   G7 escopo por módulo — regra CSS aplicando prefixo dominante de OUTRO módulo = 🔴 ([W] 06-10)
 *   G8 type ramp         — font-size computado fora do ramp --fs-1..9 = 🔴 ([W] "vai" 06-10, ratchet)
 *   G9 tempero           — body sem atmosfera --atmo / flutuante sem sombra = 🔴 ([W] norte visual 06-10)
 *   G10 vida (chip fosco) — chip/pill/badge com texto semântico sobre fundo acromático = 🔴 ([W] 06-11 "cor fosca é sistêmico")
 *   G11 select fantasma  — select custom dimensionado pela LISTA, chevron longe do valor = 🔴 ([W] 06-11, drawer)
 *   G12 grid órfão       — linha com 1 célula em grid de 2 colunas = 🔴 ([W] 06-11 "Detalhes mal formatado")
 *   G13 texto cortado    — nowrap estourando sem ellipsis = 🔴 ([W] 06-11, footer do drawer)
 *   G14 contraste AA     — razão de contraste texto/fundo COMPUTADA < WCAG AA (4.5 / 3 grande) = 🔴 (a11y, 2026-07-20 · ratchet, ⬜ sem baseline)
 *   G15 foco visível     — regra :focus/:focus-visible remove outline SEM repor indicador = 🔴 (a11y, 2026-07-20 · rule-scan determinístico)
 *
 * LOOP ERRO→ASSERÇÃO (P4): todo erro novo de craft DEVE virar um G# aqui (ou declarar não-mecanizável
 * na sessão). Este header lista a origem de cada G — é o índice do loop.
 *
 * Gated (invisível pro cliente): ?qa=1 · localStorage.oimpresso.qa=1 · Ctrl/Cmd+Shift+Q.
 * API sempre exposta (ritual pré-done do [CC] + verificador): window.QAConformance.run() / .negative()
 * Controle-negativo (L-31/Regra 5): injeta bug por classe e prova que o check fica vermelho.
 * Read-only no app (só lê; injeções do negative são revertidas no mesmo tick).
 */
(function () {
  "use strict";

  // ── G5: baselines ratchet (only-down · espelho do conformance-gate.mjs do repo) ──
  // chave = classe do root da tela ativa; valor = cruas DOM-matched MEDIDAS (nunca chutadas).
  // REGRA: número só DESCE. Subiu = regressão = 🔴. Tela sem baseline = ⬜ "calibrar" (nunca verde falso).
  // oficina-root: medido 2026-06-10 · 17 = dívida do chrome (scrollbar-color + avatares sidebar), não da tela.
  // fin-root: medido 2026-06-10 (verificador) — recalibrada 10→8 após tokenizar .fin-num-pos/-neg
  //   (par light+dark hardcoded deletado; --pos/--neg do ds-v6 flipam sozinhos). Só-desce.
  var G5_BASELINE = { "oficina-root": 17, "fin-root": 8 };

  var EXC_SELECTOR = /\.vd-trans|\.vd-pres|\.fin-pres|backdrop|\.ofc-sheet|@|tweaks|__om-edit/i; // exceções provadas (papel A4 / scrim / palcos de apresentação fullscreen — escuros por design, não flipam com tema)

  function gated() {
    try {
      if (/[?&]qa=1\b/.test(location.search)) { localStorage.setItem("oimpresso.qa", "1"); }
      return localStorage.getItem("oimpresso.qa") === "1";
    } catch (e) { return /[?&]qa=1\b/.test(location.search); }
  }

  // ── helpers ──────────────────────────────────────────────────────
  function visible(el) {
    if (!el || !el.getClientRects().length) return false;
    var cs = getComputedStyle(el);
    return cs.visibility !== "hidden" && cs.display !== "none";
  }
  function hueVerdict(bg) {
    var n = (bg.match(/[\d.]+/g) || []).map(Number);
    if (/oklch/i.test(bg)) {
      var h = n[2];
      if (h >= 250 && h <= 330) return { ok: true, label: "roxo ✅", val: bg };
      return { ok: false, label: "hue " + h + " 🔴", val: bg };
    }
    var r = n[0], g = n[1], b = n[2];
    if (b > g && r > g) return { ok: true, label: "roxo ✅", val: bg };
    return { ok: false, label: "não-roxo 🔴", val: bg };
  }
  function lightnessOf(bg) {
    var n = (bg.match(/[\d.]+/g) || []).map(Number);
    if (/oklch/i.test(bg)) return n[0];
    return (0.2126 * n[0] + 0.7152 * n[1] + 0.0722 * n[2]) / 255;
  }
  function pathOf(el) {
    var p = [], e = el;
    while (e && e !== document.body && p.length < 3) {
      p.unshift(e.tagName.toLowerCase() + (e.className && typeof e.className === "string" ? "." + e.className.split(" ")[0] : ""));
      e = e.parentElement;
    }
    return p.join(">");
  }
  // root da tela ativa: o maior container "de página" visível (genérico, sem hardcode de módulo)
  function activeRoot() {
    var cands = [].slice.call(document.querySelectorAll('[class*="-root"], [class*="aplus"], [class*="cowork"], main, [data-screen-label]'))
      .filter(visible).filter(function (el) { return !el.closest("#qa-panel"); });
    cands.sort(function (a, b) { return (b.getBoundingClientRect().width * b.getBoundingClientRect().height) - (a.getBoundingClientRect().width * a.getBoundingClientRect().height); });
    return cands[0] || document.body;
  }
  function rootKey(root) {
    // v2.1: preferir a classe ESPECÍFICA do módulo (*-root) — "os-page fin-root" tem que
    // virar chave "fin-root", não "os-page" (bug 2026-06-10: 3 rotas fin reportavam escopo os-page).
    var cls = (typeof root.className === "string" ? root.className : "").split(/\s+/).filter(Boolean);
    var spec = cls.filter(function (c) { return /-root$/.test(c); })[0];
    return spec || cls[0] || root.tagName.toLowerCase();
  }
  function eachAppliedRule(declTest, cb) {
    // varre stylesheets locais; chama cb(rule) só se a DECLARAÇÃO casa o teste E o seletor casa o DOM atual.
    for (var i = 0; i < document.styleSheets.length; i++) {
      var rules; try { rules = document.styleSheets[i].cssRules; } catch (e) { continue; }
      if (!rules) continue;
      for (var j = 0; j < rules.length; j++) {
        var r = rules[j];
        if (!r.selectorText || !r.style) continue;
        var decl = r.style.cssText;
        if (!declTest.test(decl)) continue;
        if (EXC_SELECTOR.test(r.selectorText)) continue;
        var matched = false;
        try { matched = !!document.querySelector(r.selectorText); } catch (e) { matched = false; }
        if (matched) cb(r, decl);
      }
    }
  }

  // ── G1 · accent canon: primários computam roxo ───────────────────
  function g1() {
    var btns = [].slice.call(document.querySelectorAll(".os-btn.primary, button.primary")).filter(visible)
      .filter(function (b) { return !b.closest("#qa-panel"); });
    if (!btns.length) return { id: "G1", label: "Accent canon (primário == roxo)", status: "na", detail: "sem primário visível" };
    var bad = [];
    btns.forEach(function (b) {
      var v = hueVerdict(getComputedStyle(b).backgroundColor);
      if (!v.ok) bad.push(pathOf(b) + " → " + v.val);
    });
    return { id: "G1", label: "Accent canon (primário == roxo)", status: bad.length ? "fail" : "pass",
      detail: bad.length ? bad.slice(0, 3).join(" · ") : btns.length + " primário(s) roxo(s)" };
  }

  // ── G2 · controle nativo sem accent-color tokenizado ─────────────
  // v2.1: só conta input PINTADO PELO BROWSER. Proxy custom (input opacity:0/colapsado +
  // box CSS própria, ex. .fin-filter-cb) e appearance:none são padrão APROVADO — accent-color
  // é irrelevante neles (falso-positivo 2026-06-10 nas rotas fin). Cor crua na box custom é caso do G5.
  function nativePainted(el) {
    var cs = getComputedStyle(el);
    if (parseFloat(cs.opacity) === 0) return false;
    var r = el.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) return false;
    var ap = cs.appearance || cs.webkitAppearance || "";
    if (ap === "none") return false;
    return true;
  }
  function g2() {
    var inputs = [].slice.call(document.querySelectorAll('input[type="checkbox"], input[type="radio"]')).filter(visible)
      .filter(nativePainted)
      .filter(function (el) { return !el.closest("#qa-panel"); });
    if (!inputs.length) return { id: "G2", label: "Controle nativo com accent-color", status: "na", detail: "sem checkbox/radio nativo pintado (proxy custom = ok)" };
    var bad = [];
    inputs.forEach(function (el) {
      var ac = getComputedStyle(el).accentColor;
      if (!ac || ac === "auto") { bad.push(pathOf(el) + " → accent-color:auto (azul do browser)"); return; }
      var v = hueVerdict(ac);
      if (!v.ok) bad.push(pathOf(el) + " → " + ac);
    });
    return { id: "G2", label: "Controle nativo com accent-color", status: bad.length ? "fail" : "pass",
      detail: bad.length ? bad.length + " cru(s): " + bad.slice(0, 3).join(" · ") : inputs.length + " controle(s) tokenizado(s)" };
  }

  // ── G3 · papel de token invertido (-fg como superfície / -bg como texto) ──
  var G3_BG_PROP = /(?:^|;)\s*(?:background(?:-color)?|fill)\s*:[^;]*var\(--[a-z0-9-]*-fg[,)]/i;
  var G3_FG_PROP = /(?:^|;)\s*(?:color|stroke)\s*:[^;]*var\(--[a-z0-9-]*-bg[,)]/i;
  function g3() {
    var bad = [];
    eachAppliedRule(new RegExp(G3_BG_PROP.source + "|" + G3_FG_PROP.source, "i"), function (r) {
      bad.push(r.selectorText.slice(0, 44));
    });
    return { id: "G3", label: "Papel de token (-fg nunca é superfície)", status: bad.length ? "fail" : "pass",
      detail: bad.length ? bad.slice(0, 3).join(" · ") : "sem inversão aplicada no DOM" };
  }

  // ── G4 · overflow-x em drawer/dialog (estado estourando a largura) ──
  function g4() {
    var hosts = [].slice.call(document.querySelectorAll('.prod-drawer, .prod-drawer-body, [role="dialog"], aside[class*="drawer"]')).filter(visible);
    if (!hosts.length) return { id: "G4", label: "Drawer sem overflow-x", status: "na", detail: "sem drawer aberto (abra um detalhe p/ testar)" };
    var bad = [];
    hosts.forEach(function (el) {
      if (el.scrollWidth > el.clientWidth + 1) bad.push(pathOf(el) + " → " + el.scrollWidth + "px em " + el.clientWidth + "px");
    });
    return { id: "G4", label: "Drawer sem overflow-x", status: bad.length ? "fail" : "pass",
      detail: bad.length ? bad.join(" · ") : hosts.length + " container(s) contidos" };
  }

  // ── G5 · cor crua APLICADA (DOM-matched · ratchet only-down) ─────
  var G5_RAW = /#[0-9a-fA-F]{3,8}\b|oklch\([^)v]*\)|(?:^|[\s:;])(?:white|black)(?=[\s;}!]|$)/;
  function g5(root) {
    var count = 0, ex = [];
    eachAppliedRule(G5_RAW, function (r) {
      // conta só propriedade NORMAL — definição de token (--x: oklch) é legítima (fonte de valor)
      for (var k = 0; k < r.style.length; k++) {
        var prop = r.style[k];
        if (prop.indexOf("--") === 0) continue;
        var val = r.style.getPropertyValue(prop);
        if (val.indexOf("var(") >= 0) continue;
        var hits = (val.match(/#[0-9a-fA-F]{3,8}\b|oklch\([^)]*\)|(?:^|[\s(,])(white|black)(?=[\s),]|$)/g) || []);
        if (hits.length) { count += hits.length; if (ex.length < 3) ex.push(r.selectorText.slice(0, 36) + " → " + prop + ":" + hits[0].trim()); }
      }
    });
    var key = rootKey(root);
    if (!(key in G5_BASELINE)) {
      return { id: "G5", label: "Cor crua aplicada (sem baseline p/ " + key + ")", status: "na",
        detail: count + " cruas no ar — calibrar baseline nesta tela (medir, registrar no G5_BASELINE, only-down)" + (ex.length ? " · ex: " + ex.join(" · ") : "") };
    }
    var base = G5_BASELINE[key];
    var ok = count <= base;
    return { id: "G5", label: "Cor crua aplicada ≤ baseline (" + key + ": " + base + ")", status: ok ? "pass" : "fail",
      detail: count + " cruas no ar" + (ok ? "" : " — REGRESSÃO (+" + (count - base) + ")") + (ex.length ? " · ex: " + ex.join(" · ") : "") };
  }

  // ── G6 · superfície legível no tema dark ─────────────────────────
  function g6(root) {
    var theme = document.documentElement.getAttribute("data-theme") || "claro";
    if (theme !== "dark") return { id: "G6", label: "Dark legível", status: "na", detail: "tema atual: " + theme };
    var cards = [].slice.call(root.querySelectorAll('[class*="kpi"], [class*="card"], [class*="hero"]')).filter(visible).slice(0, 12);
    var bad = [];
    cards.forEach(function (c) {
      var bg = getComputedStyle(c).backgroundColor;
      if (/rgba?\([^)]*,\s*0\s*\)/.test(bg)) return;
      if (lightnessOf(bg) > 0.7) bad.push(pathOf(c));
    });
    return { id: "G6", label: "Dark legível (sem card quase-branco)", status: bad.length ? "fail" : "pass",
      detail: bad.length ? bad.slice(0, 3).join(" · ") : cards.length + " superfícies ok" };
  }

  // ── G7 · escopo por módulo (vazamento de CSS entre módulos) ────────
  // Modelo autorizado [W] 2026-06-10: camada COMUM (styles.css + ds-v6/*) é compartilhada e ÚNICA
  // (existe justamente pra não duplicar — nomes os-*/sb-*/tokens, nome novo só autorizado);
  // CSS de MÓDULO só contém seletores do prefixo do próprio módulo. Regra cujo prefixo é o
  // DOMINANTE de OUTRO arquivo de módulo, aplicando no DOM = vazamento (caso real consertado
  // hoje: bloco .fin-* morava em vendas.css — mudou de casa, não foi duplicado).
  var G7_COMMON_SHEETS = /(?:^|\/)(styles\.css|ds-v6\/)/;
  var G7_COMMON_PREFIX = { os: 1, sb: 1, qa: 1, mockup: 1 };
  function firstClassPrefix(sel) {
    var m = /\.([a-zA-Z][a-zA-Z0-9]*)/.exec(sel);
    return m ? m[1].toLowerCase() : null;
  }
  function g7Census() {
    var out = [];
    [].slice.call(document.styleSheets).forEach(function (s) {
      if (!s.href || G7_COMMON_SHEETS.test(s.href)) return;
      var rules; try { rules = s.cssRules; } catch (e) { return; }
      if (!rules) return;
      var tally = {}, sels = [];
      for (var j = 0; j < rules.length; j++) {
        var r = rules[j];
        if (!r.selectorText) continue;
        var p = firstClassPrefix(r.selectorText);
        if (!p || G7_COMMON_PREFIX[p]) continue;
        tally[p] = (tally[p] || 0) + 1;
        sels.push({ rule: r, p: p });
      }
      var dom = Object.keys(tally).sort(function (a, b) { return tally[b] - tally[a]; })[0];
      if (!dom) return;
      out.push({ sheet: s, fname: s.href.split("/").pop().split("?")[0], dom: dom, sels: sels });
    });
    return out;
  }
  function g7() {
    var census = g7Census();
    if (!census.length) return { id: "G7", label: "Escopo por módulo", status: "na", detail: "sem CSS de módulo legível" };
    var owner = {};
    census.forEach(function (c) { if (!(c.dom in owner)) owner[c.dom] = c.fname; });
    var count = 0, ex = [];
    census.forEach(function (c) {
      c.sels.forEach(function (it) {
        if (it.p === c.dom) return;
        var ownedBy = owner[it.p];
        if (!ownedBy || ownedBy === c.fname) return; // prefixo não pertence a outro módulo → não é vazamento entre módulos
        var matched = false;
        try { matched = !!document.querySelector(it.rule.selectorText); } catch (e) {}
        if (matched) { count++; if (ex.length < 3) ex.push(c.fname + " define " + it.rule.selectorText.slice(0, 38) + " (" + it.p + "-* é de " + ownedBy + ")"); }
      });
    });
    return { id: "G7", label: "Escopo por módulo (CSS no arquivo certo)", status: count ? "fail" : "pass",
      detail: count ? count + " vazamento(s) aplicado(s): " + ex.join(" · ") : census.length + " arquivos de módulo · prefixos no próprio arquivo" };
  }

  // ── G8 · type ramp (tipografia ancorada · [W] "vai" 2026-06-10) ────
  // Todo font-size COMPUTADO na tela ativa tem que estar no ramp --fs-1..9 (ds-v6).
  // Ratchet por tela: baseline = nº de TAMANHOS distintos fora do ramp (só-desce).
  var G8_RAMP = [10.5, 11.5, 12.5, 13.5, 15, 18, 22, 28, 38];
  var G8_BASELINE = { "fin-root": 0 }; // fin: 0 medido 2026-06-10 (pós-snap 304 decls + shell os-page-h) · só-desce
  function g8(root) {
    var off = {}, seen = 0;
    var els = root.querySelectorAll("*");
    for (var i = 0; i < els.length && seen < 6000; i++) {
      var el = els[i]; seen++;
      if (el.closest("#qa-panel") || el.tagName === "svg" || el.tagName === "path") continue;
      if (el.closest('[class*="__om"]')) continue; // overlays do editor ( __om-t etc.) — não são conteúdo do app
      var hasText = false;
      for (var c = 0; c < el.childNodes.length; c++) {
        var nd = el.childNodes[c];
        if (nd.nodeType === 3 && nd.textContent.trim()) { hasText = true; break; }
      }
      if (!hasText || !visible(el)) continue;
      var fs = parseFloat(getComputedStyle(el).fontSize);
      var ok = G8_RAMP.some(function (r) { return Math.abs(r - fs) < 0.15; });
      if (!ok) { var k = Math.round(fs * 10) / 10; if (!off[k]) off[k] = pathOf(el); }
    }
    var vals = Object.keys(off).sort(function (a, b) { return a - b; });
    var key = rootKey(root);
    var detail = vals.length + " tamanho(s) fora do ramp" + (vals.length ? ": " + vals.slice(0, 5).map(function (v) { return v + "px (" + off[v].split(">").pop() + ")"; }).join(" · ") : "");
    if (!(key in G8_BASELINE)) {
      return { id: "G8", label: "Type ramp (sem baseline p/ " + key + ")", status: "na", detail: detail + " — calibrar (medir, registrar, only-down)" };
    }
    var base = G8_BASELINE[key];
    return { id: "G8", label: "Type ramp ≤ baseline (" + key + ": " + base + ")", status: vals.length <= base ? "pass" : "fail",
      detail: detail + (vals.length > base ? " — REGRESSÃO (+" + (vals.length - base) + ")" : "") };
  }

  // ── G9 · tempero: atmosfera + elevação ([W] norte visual 2026-06-10) ────
  // (a) body tem a atmosfera (--atmo aplicado como background-image radial);
  // (b) superfície FLUTUANTE visível (drawer/modal/popover) tem box-shadow real (não none).
  function g9() {
    var bgi = getComputedStyle(document.body).backgroundImage || "";
    var atmo = bgi.indexOf("radial-gradient") >= 0;
    var floats = [].slice.call(document.querySelectorAll('.os-drawer, .prod-drawer, .os-modal, [role="dialog"], aside[class*="drawer"]')).filter(visible)
      .filter(function (el) { return !el.closest("#qa-panel"); })
      .filter(function (el) {
        // FLUTUANTE = fora do fluxo (fixed/absolute/sticky). Painel DOCADO (static, ex. Compras)
        // não deve sombra — G9 over-broad acusava 🔴 permanente (verifier 06-11).
        var pos = getComputedStyle(el).position;
        return pos === "fixed" || pos === "absolute" || pos === "sticky";
      });
    var bad = [];
    if (!atmo) bad.push("body sem atmosfera (--atmo)");
    floats.forEach(function (el) {
      if ((getComputedStyle(el).boxShadow || "none") === "none") bad.push(pathOf(el) + " → flutuante sem sombra");
    });
    // L-37: classe Tailwind arbitrária de sombra com var() NÃO computa no CDN — presença = bug silencioso
    var l37 = [].slice.call(document.querySelectorAll('[class*="shadow-[var"]')).filter(visible);
    l37.forEach(function (el) { bad.push(pathOf(el) + ' → shadow-[var…] não computa no CDN (L-37: usar regra CSS)'); });
    return { id: "G9", label: "Tempero (atmosfera + elevação)", status: bad.length ? "fail" : "pass",
      detail: bad.length ? bad.slice(0, 3).join(" · ") : "atmosfera ✓" + (floats.length ? " · " + floats.length + " flutuante(s) com sombra" : " · sem flutuante aberto") };
  }

  // ── G10 · VIDA: chip/pill/badge semântico FOSCO ([W] 06-11 "cor fosca é erro sistêmico") ──
  // chip pequeno com TEXTO cromático sobre FUNDO acromático = esqueceu de tintar (classe L-40).
  function satOf(color) {
    if (!color || color === "transparent") return null;
    if (/oklch/i.test(color)) { var n = color.match(/[\d.]+/g); return n && n.length > 1 ? parseFloat(n[1]) * 600 : null; }
    var m = color.match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?/);
    if (!m) return null;
    if (m[4] !== undefined && parseFloat(m[4]) === 0) return null;
    return Math.max(+m[1], +m[2], +m[3]) - Math.min(+m[1], +m[2], +m[3]);
  }
  function g10() {
    var sel = '[class*="chip"],[class*="pill"],[class*="badge"],[class*="frescor"],[class*="lens-ic"]';
    var els = [].slice.call(document.querySelectorAll(sel)).filter(visible)
      .filter(function (el) { return !el.closest("#qa-panel"); })
      .filter(function (el) {
        var r = el.getBoundingClientRect();
        return r.height >= 10 && r.height <= 34 && r.width <= 320;
      })
      .filter(function (el) { return !/muted|\bmut\b|neutral|ghost/i.test(String(el.className)); });
    if (!els.length) return { id: "G10", label: "Vida (chip fosco)", status: "na", detail: "sem chip/pill visível" };
    var bad = [];
    els.forEach(function (el) {
      var cs = getComputedStyle(el);
      var bgSat = satOf(cs.backgroundColor);
      var fgSat = satOf(cs.color);
      if (bgSat === null) return; /* transparente — cor vem de outro nível */
      // Outline chip é padrão LEGÍTIMO: bg neutro + borda E texto com chroma (ex. .vd-cob-chip-pending).
      var bw = parseFloat(cs.borderTopWidth) || 0;
      var borderSat = bw >= 1 && cs.borderTopStyle !== "none" ? satOf(cs.borderTopColor) : null;
      if (borderSat !== null && borderSat >= 14) return;
      if (fgSat !== null && fgSat > 40 && bgSat < 14) bad.push(pathOf(el) + " → bg fosco (" + cs.backgroundColor + ")");
    });
    return { id: "G10", label: "Vida (chip fosco)", status: bad.length ? "fail" : "pass",
      detail: bad.length ? bad.slice(0, 3).join(" · ") : els.length + " chip(s) com chroma" };
  }

  // ── G11 · select fantasma: custom select dimensionado pela LISTA, não pelo valor ([W] 06-11) ──
  function g11() {
    var sels = [].slice.call(document.querySelectorAll("select")).filter(visible)
      .filter(function (el) { return !el.closest("#qa-panel"); })
      .filter(function (el) { var cs = getComputedStyle(el); return (cs.appearance || cs.webkitAppearance) === "none"; });
    if (!sels.length) return { id: "G11", label: "Select fantasma (chevron longe do valor)", status: "na", detail: "sem select custom visível" };
    var canvas = g11._c || (g11._c = document.createElement("canvas"));
    var ctx = canvas.getContext("2d");
    var bad = [];
    sels.forEach(function (el) {
      var opt = el.options[el.selectedIndex];
      var txt = opt ? opt.text : "";
      var cs = getComputedStyle(el);
      ctx.font = cs.fontWeight + " " + cs.fontSize + " " + cs.fontFamily;
      var inner = el.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
      var folga = inner - ctx.measureText(txt).width;
      if (folga > 26) bad.push(pathOf(el) + " → folga " + Math.round(folga) + "px ('" + txt + "')");
    });
    return { id: "G11", label: "Select fantasma (chevron longe do valor)", status: bad.length ? "fail" : "pass",
      detail: bad.length ? bad.slice(0, 3).join(" · ") : sels.length + " select(s) justos" };
  }

  // ── G12 · grid órfão: linha com 1 célula estreita em grid de 2 colunas ([W] 06-11) ──
  function g12() {
    var hosts = [].slice.call(document.querySelectorAll('[class*="grid"],[class*="kv"]')).filter(visible)
      .filter(function (el) { return !el.closest("#qa-panel"); })
      .filter(function (el) {
        var cs = getComputedStyle(el);
        return cs.display === "grid" && cs.gridTemplateColumns.split(" ").length === 2;
      });
    if (!hosts.length) return { id: "G12", label: "Grid órfão (linha de 1 célula)", status: "na", detail: "sem grid 2-col visível" };
    var bad = [];
    hosts.forEach(function (host) {
      var kids = [].slice.call(host.children).filter(visible);
      if (kids.length < 3) return;
      var rows = {};
      kids.forEach(function (k) { var key = Math.round(k.offsetTop / 8); (rows[key] = rows[key] || []).push(k); });
      var arr = Object.keys(rows).map(function (k) { return rows[k]; });
      var temCheia = arr.some(function (r) { return r.length >= 2; });
      var orfã = arr.filter(function (r) {
        return r.length === 1 && r[0].getBoundingClientRect().width < host.clientWidth * 0.66;
      });
      if (temCheia && orfã.length) bad.push(pathOf(host) + " → " + orfã.length + " linha(s) órfã(s)");
    });
    return { id: "G12", label: "Grid órfão (linha de 1 célula)", status: bad.length ? "fail" : "pass",
      detail: bad.length ? bad.slice(0, 3).join(" · ") : hosts.length + " grid(s) sem órfã" };
  }

  // ── G13 · texto cortado: nowrap estourando sem ellipsis ([W] 06-11, footer) ──
  function g13() {
    var els = [].slice.call(document.querySelectorAll('button, a, [class*="btn"], [class*="lbl"], [class*="label"]')).filter(visible)
      .filter(function (el) { return !el.closest("#qa-panel"); });
    var bad = [];
    els.forEach(function (el) {
      var cs = getComputedStyle(el);
      if (cs.whiteSpace !== "nowrap") return;
      if (el.scrollWidth > el.clientWidth + 1 && cs.textOverflow !== "ellipsis")
        bad.push(pathOf(el) + " → cortado " + (el.scrollWidth - el.clientWidth) + "px");
    });
    return { id: "G13", label: "Texto cortado (nowrap sem ellipsis)", status: bad.length ? "fail" : "pass",
      detail: bad.length ? bad.slice(0, 3).join(" · ") : "nada cortado" };
  }

  // ── helpers de cor (WCAG · sRGB) para G14 ────────────────────────
  function _rgba(str) {
    if (!str) return null;
    if (str === "transparent") return { r: 0, g: 0, b: 0, a: 0 };
    var m = str.match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?/i);
    if (!m) return null;
    return { r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] };
  }
  function _lin(c) { c = c / 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); }
  function _lum(rgb) { return 0.2126 * _lin(rgb.r) + 0.7152 * _lin(rgb.g) + 0.0722 * _lin(rgb.b); }
  function _over(fg, bg) {
    var a = fg.a;
    return { r: fg.r * a + bg.r * (1 - a), g: fg.g * a + bg.g * (1 - a), b: fg.b * a + bg.b * (1 - a), a: 1 };
  }
  function _contrast(l1, l2) { var hi = Math.max(l1, l2), lo = Math.min(l1, l2); return (hi + 0.05) / (lo + 0.05); }
  // fundo efetivo RESOLVIDO: sobe ancestrais compondo camadas semi-transparentes até a 1ª opaca.
  // Retorna null (INDETERMINÁVEL, honesto) se topar com background-image/gradiente — não dá pra medir cor sólida.
  function effectiveBg(el) {
    var layers = [], e = el;
    while (e && e !== document.documentElement) {
      var cs = getComputedStyle(e);
      if (cs.backgroundImage && cs.backgroundImage !== "none") return null;
      var bg = _rgba(cs.backgroundColor);
      if (bg && bg.a > 0) { layers.push(bg); if (bg.a >= 0.999) break; }
      e = e.parentElement;
    }
    var base = (layers.length && layers[layers.length - 1].a >= 0.999) ? layers.pop() : { r: 255, g: 255, b: 255, a: 1 };
    for (var i = layers.length - 1; i >= 0; i--) base = _over(layers[i], base);
    return base;
  }

  // ── G14 · contraste AA (WCAG 2.1 · RESOLVIDO no DOM · ratchet only-down) [a11y · mecaniza a falha ALTA] ──
  // Mede a razão texto/fundo COMPUTADA (não a intenção). Limiar: texto grande (≥24px, ou ≥18.66px && weight≥700)
  // = 3.0; senão 4.5. ⬜ sem baseline (NUNCA verde falso — igual G5/G8); skip honesto onde o fundo é indeterminável.
  var G14_BASELINE = {}; // nenhuma tela calibrada — calibrar rodando o probe e MEDINDO (nunca chutar o número)
  function g14(root) {
    var count = 0, ex = [], measured = 0, skipped = 0;
    var els = root.querySelectorAll("*");
    for (var i = 0; i < els.length && i < 6000; i++) {
      var el = els[i];
      if (el.closest("#qa-panel") || el.tagName === "svg" || el.tagName === "path") continue;
      var hasText = false;
      for (var c = 0; c < el.childNodes.length; c++) {
        var nd = el.childNodes[c];
        if (nd.nodeType === 3 && nd.textContent.trim().length >= 2) { hasText = true; break; }
      }
      if (!hasText || !visible(el)) continue;
      var cs = getComputedStyle(el);
      var fg = _rgba(cs.color);
      if (!fg || fg.a === 0) continue;
      var bg = effectiveBg(el);
      if (!bg) { skipped++; continue; }
      var fgC = fg.a < 1 ? _over(fg, bg) : fg;
      var ratio = _contrast(_lum(fgC), _lum(bg));
      var fs = parseFloat(cs.fontSize), wt = parseInt(cs.fontWeight, 10) || 400;
      var thr = (fs >= 24 || (fs >= 18.66 && wt >= 700)) ? 3.0 : 4.5;
      measured++;
      if (ratio < thr - 0.05) {
        count++;
        if (ex.length < 3) ex.push(pathOf(el) + " → " + (Math.round(ratio * 100) / 100) + ":1 (<" + thr + ")");
      }
    }
    var key = rootKey(root);
    var suffix = " · " + measured + " medidos" + (skipped ? " · " + skipped + " skip (fundo indeterminável)" : "");
    if (!(key in G14_BASELINE)) {
      return { id: "G14", label: "Contraste AA (sem baseline p/ " + key + ")", status: "na", _count: count,
        detail: count + " abaixo de AA" + suffix + " — calibrar (medir, registrar no G14_BASELINE, only-down)" + (ex.length ? " · ex: " + ex.join(" · ") : "") };
    }
    var base = G14_BASELINE[key];
    return { id: "G14", label: "Contraste AA ≤ baseline (" + key + ": " + base + ")", status: count <= base ? "pass" : "fail", _count: count,
      detail: count + " abaixo de AA" + suffix + (count > base ? " — REGRESSÃO (+" + (count - base) + ")" : "") + (ex.length ? " · ex: " + ex.join(" · ") : "") };
  }

  // ── G15 · foco visível (WCAG 2.4.7 · varredura de regras determinística) [a11y] ──
  // Detecta o anti-padrão real: regra :focus/:focus-visible que REMOVE o indicador (outline:none/0)
  // aplicando a um controle interativo SEM regra que o REPONHA (outline visível / box-shadow).
  // Determinístico e consistente com G3/G7 (rule-scan) — NÃO usa foco programático, que não dispara
  // :focus-visible de forma confiável (comprovado no smoke 2026-07-20: até input dava indet → gate cego).
  // ⬜ sem baseline. Limite honesto: não resolve cascade/specificity nem prova o render; pega o outline-removido-sem-reposição.
  var G15_BASELINE = {};
  function _focusRules() {
    var out = [];
    for (var i = 0; i < document.styleSheets.length; i++) {
      var rules; try { rules = document.styleSheets[i].cssRules; } catch (e) { continue; }
      if (!rules) continue;
      for (var j = 0; j < rules.length; j++) {
        var r = rules[j];
        if (r.selectorText && r.style && /:focus(-visible)?/.test(r.selectorText)) out.push(r);
      }
    }
    return out;
  }
  function _classifyFocus(st) {
    var os = st.outlineStyle, ow = st.outlineWidth, o = st.outline || "", bs = st.boxShadow;
    var removes = /(^|\s)none(\s|$)/.test(o) || os === "none" || ow === "0px" || ow === "0";
    var adds = (!!bs && bs !== "none") ||
      (!!os && os !== "none" && ow !== "0px" && ow !== "0") ||
      (/\b(solid|dashed|dotted|double|groove|ridge|inset|outset|auto)\b/.test(o) && !/(^|\s)none(\s|$)/.test(o));
    return { removes: removes, adds: adds };
  }
  function g15(root) {
    var sel = 'a[href], button, input:not([type="hidden"]), select, textarea, [tabindex], [role="button"], [role="link"], [role="tab"], [role="menuitem"]';
    var els = [].slice.call(root.querySelectorAll(sel)).filter(visible)
      .filter(function (el) { return !el.closest("#qa-panel"); })
      .filter(function (el) { return !el.disabled && el.getAttribute("tabindex") !== "-1"; });
    if (!els.length) return { id: "G15", label: "Foco visível", status: "na", _count: 0, detail: "sem controle interativo visível" };
    var rules = _focusRules();
    var count = 0, ex = [];
    for (var i = 0; i < els.length && i < 400; i++) {
      var el = els[i], removes = false, adds = false;
      for (var j = 0; j < rules.length; j++) {
        var parts = rules[j].selectorText.split(","), applies = false;
        for (var p = 0; p < parts.length; p++) {
          if (!/:focus(-visible)?/.test(parts[p])) continue;
          var b = parts[p].replace(/:focus-visible/g, "").replace(/:focus/g, "").trim() || "*";
          try { if (el.matches(b)) { applies = true; break; } } catch (e) {}
        }
        if (!applies) continue;
        var eff = _classifyFocus(rules[j].style);
        if (eff.removes) removes = true;
        if (eff.adds) adds = true;
      }
      if (removes && !adds) { count++; if (ex.length < 3) ex.push(pathOf(el) + " → :focus remove outline sem repor indicador"); }
    }
    var key = rootKey(root);
    var suffix = " · " + els.length + " controle(s)";
    if (!(key in G15_BASELINE)) {
      return { id: "G15", label: "Foco visível (sem baseline p/ " + key + ")", status: "na", _count: count,
        detail: count + " sem indicador de foco" + suffix + " — calibrar (medir, registrar no G15_BASELINE, only-down)" + (ex.length ? " · ex: " + ex.join(" · ") : "") };
    }
    var base = G15_BASELINE[key];
    return { id: "G15", label: "Foco visível ≤ baseline (" + key + ": " + base + ")", status: count <= base ? "pass" : "fail", _count: count,
      detail: count + " sem indicador de foco" + suffix + (count > base ? " — REGRESSÃO (+" + (count - base) + ")" : "") + (ex.length ? " · ex: " + ex.join(" · ") : "") };
  }

  function run() {
    var root = activeRoot();
    var res = [g1(), g2(), g3(), g4(), g5(root), g6(root), g7(), g8(root), g9(), g10(), g11(), g12(), g13(), g14(root), g15(root)];
    res.unshift({ id: "tela", label: "escopo ativo", status: "info", detail: rootKey(root) + " · " + (location.hash || "/") });
    res.forEach(function (r) {
      var icon = r.status === "pass" ? "🟢" : r.status === "fail" ? "🔴" : r.status === "na" ? "⬜" : "·";
      console.log("[QA] " + icon + " " + r.id + " " + r.label + " — " + r.detail);
    });
    var fails = res.filter(function (r) { return r.status === "fail"; }).length;
    console.log("[QA] placar: " + res.filter(function (r) { return r.status === "pass"; }).length + " ✅ · " + fails + " 🔴" + (fails ? " — NÃO ENTREGAR (ritual §8)" : ""));
    return res;
  }

  // ── Controle-negativo por classe (testa o teste · Regra 5) ───────
  function negative() {
    var out = {};
    function probe(name, setup, teardown, checkFn) {
      var before = checkFn().status;
      if (before === "fail") { out[name] = { dirty: true, note: "pré-condição suja: violação REAL no ar — corrigir antes de testar o teste" }; return out[name]; }
      var undo = setup();
      var during = checkFn().status;
      (teardown || undo || function () {})();
      var after = checkFn().status;
      out[name] = { before: before, during: during, after: after,
        discrimina: before !== "fail" && during === "fail" && after !== "fail" };
      return out[name];
    }
    // N1 → G1: primário pintado de verde tem que ficar 🔴
    probe("G1", function () {
      var st = document.createElement("style"); st.id = "__qa_neg";
      st.textContent = ".os-btn.primary, button.primary { background: oklch(0.45 0.11 155) !important; }";
      document.head.appendChild(st);
      return function () { st.remove(); };
    }, null, g1);
    // N2 → G2: checkbox nativo VISÍVEL com accent default do browser tem que ficar 🔴
    // (v2.1: injeta um input real — em telas onde todo controle é proxy custom, o g2 fica "na"
    //  e o style-override antigo não exercitava nada → controle-negativo ficava cego)
    probe("G2", function () {
      var el = document.createElement("input"); el.type = "checkbox"; el.id = "__qa_neg2el";
      el.style.cssText = "position:fixed;bottom:8px;left:8px;width:14px;height:14px;accent-color:auto;";
      document.body.appendChild(el);
      return function () { el.remove(); };
    }, null, g2);
    // N3 → G3: regra com -fg em background + elemento que a casa
    probe("G3", function () {
      var st = document.createElement("style"); st.id = "__qa_neg3";
      st.textContent = ".__qa-neg-role { background: var(--origin-MFG-fg); }";
      document.head.appendChild(st);
      var el = document.createElement("i"); el.className = "__qa-neg-role"; document.body.appendChild(el);
      return function () { st.remove(); el.remove(); };
    }, null, g3);
    // N4 → G4: filho de 9999px num drawer aberto (skip se não houver)
    var host = document.querySelector(".prod-drawer-body");
    if (host) {
      probe("G4", function () {
        var el = document.createElement("div"); el.style.cssText = "min-width:9999px;height:1px;"; host.appendChild(el);
        return function () { el.remove(); };
      }, null, g4);
    } else { out.G4 = { skipped: "sem drawer aberto" }; }
    // N7 → G7: regra com prefixo de OUTRO módulo injetada num CSS de módulo tem que ficar 🔴
    (function () {
      var census = g7Census();
      var a = census[0], b = census.filter(function (c) { return c.dom !== (a && a.dom); })[0];
      if (!a || !b) { out.G7 = { skipped: "menos de 2 módulos com dominantes distintos" }; return; }
      probe("G7", function () {
        // seletor com 1ª classe do prefixo de B, mas que casa o DOM via body — injetado no arquivo de A
        var idx = a.sheet.cssRules.length;
        a.sheet.insertRule("." + b.dom + "-__qa-neg, body { --qa-neg: 1; }", idx);
        return function () { try { a.sheet.deleteRule(idx); } catch (e) {} };
      }, null, g7);
    })();
    // N8 → G8: elemento com font-size fora do ramp tem que subir o contador · baseline=atual
    (function () {
      var root = activeRoot(); var key = rootKey(root);
      var had = key in G8_BASELINE; var prev = G8_BASELINE[key];
      var cur = (function () { var r = g8(root); var m = /^(\d+)/.exec(r.detail); return m ? +m[1] : 0; })();
      G8_BASELINE[key] = cur;
      probe("G8", function () {
        var el = document.createElement("span"); el.id = "__qa_neg8"; el.textContent = "neg";
        el.style.cssText = "font-size:17.3px;position:fixed;bottom:8px;right:8px;";
        root.appendChild(el);
        return function () { el.remove(); };
      }, null, function () { return g8(root); });
      if (had) G8_BASELINE[key] = prev; else delete G8_BASELINE[key];
    })();
    // N9 → G9: matar a atmosfera do body tem que ficar 🔴
    probe("G9", function () {
      var st = document.createElement("style"); st.id = "__qa_neg9";
      st.textContent = "body{ background-image: none !important; }";
      document.head.appendChild(st);
      return function () { st.remove(); };
    }, null, g9);
    // N10 → G10: chip cinza com texto verde tem que ficar 🔴
    probe("G10", function () {
      var el = document.createElement("span");
      el.className = "qa-neg-chip-x chip";
      el.style.cssText = "position:fixed;left:8px;bottom:8px;z-index:99999;display:inline-flex;align-items:center;height:18px;padding:0 8px;border-radius:9px;background:#ececec;color:#1a7f4e;font-size:11px";
      el.textContent = "neg";
      document.body.appendChild(el);
      return function () { el.remove(); };
    }, null, g10);
    // N11 → G11: select custom largo com valor curto tem que ficar 🔴
    probe("G11", function () {
      var s = document.createElement("select");
      s.style.cssText = "position:fixed;left:8px;bottom:36px;z-index:99999;appearance:none;-webkit-appearance:none;width:240px;font-size:13px";
      s.innerHTML = "<option>PIX</option>";
      document.body.appendChild(s);
      return function () { s.remove(); };
    }, null, g11);
    // N12 → G12: grid 2-col com linha órfã tem que ficar 🔴
    probe("G12", function () {
      var d = document.createElement("div");
      d.className = "qa-neg-grid grid-cols-2";
      d.style.cssText = "position:fixed;left:8px;bottom:70px;z-index:99999;display:grid;grid-template-columns:1fr 1fr;gap:8px;width:300px;background:#fff";
      d.innerHTML = "<i>a</i><i>b</i><i>c</i>";
      document.body.appendChild(d);
      return function () { d.remove(); };
    }, null, g12);
    // N13 → G13: botão nowrap cortado sem ellipsis tem que ficar 🔴
    probe("G13", function () {
      var b = document.createElement("button");
      b.style.cssText = "position:fixed;left:8px;bottom:110px;z-index:99999;width:60px;white-space:nowrap;overflow:hidden;font-size:12px";
      b.textContent = "texto muito comprido cortado";
      document.body.appendChild(b);
      return function () { b.remove(); };
    }, null, g13);
    // N14 → G14: texto cinza-claro sobre branco (~1.9:1) tem que subir o contador · baseline=atual (padrão N8)
    (function () {
      var root = activeRoot(); var key = rootKey(root);
      var had = key in G14_BASELINE; var prev = G14_BASELINE[key];
      G14_BASELINE[key] = g14(root)._count;
      probe("G14", function () {
        var el = document.createElement("span"); el.id = "__qa_neg14"; el.textContent = "baixo contraste";
        el.style.cssText = "position:fixed;bottom:8px;right:8px;z-index:99999;background:#ffffff;color:#bfbfbf;font-size:13px;padding:2px";
        root.appendChild(el);
        return function () { el.remove(); };
      }, null, function () { return g14(root); });
      if (had) G14_BASELINE[key] = prev; else delete G14_BASELINE[key];
    })();
    // N15 → G15: input de texto que zera o outline no foco sem indicador tem que subir o contador
    (function () {
      var root = activeRoot(); var key = rootKey(root);
      var had = key in G15_BASELINE; var prev = G15_BASELINE[key];
      G15_BASELINE[key] = g15(root)._count;
      probe("G15", function () {
        var st = document.createElement("style"); st.id = "__qa_neg15s";
        st.textContent = "#__qa_neg15:focus, #__qa_neg15:focus-visible { outline: none !important; box-shadow: none !important; }";
        document.head.appendChild(st);
        var el = document.createElement("input"); el.id = "__qa_neg15"; el.type = "text";
        el.style.cssText = "position:fixed;bottom:8px;left:8px;z-index:99999;width:80px";
        root.appendChild(el);
        return function () { st.remove(); el.remove(); };
      }, null, function () { return g15(root); });
      if (had) G15_BASELINE[key] = prev; else delete G15_BASELINE[key];
    })();
    var all = Object.keys(out).every(function (k) { return out[k].discrimina || out[k].skipped; });
    console.log("[QA] controle-negativo: " + Object.keys(out).map(function (k) {
      return k + "=" + (out[k].skipped ? "skip" : out[k].dirty ? "SUJO ⚠" : out[k].discrimina ? "discrimina ✅" : "FALHOU 🔴");
    }).join(" · ") + (all ? " — gate visto falhar e passar ✓" : " — GATE CEGO ou pré-condição suja"));
    return out;
  }

  // ── UI (gated) ───────────────────────────────────────────────────
  function chip(status) {
    var m = { pass: ["✅", "var(--pos,#16794d)"], fail: ["🔴", "var(--neg,#c0362c)"], na: ["⬜", "var(--text-3,#888)"], info: ["·", "var(--text-3,#888)"] }[status] || ["·", "#888"];
    return '<span style="font-weight:700;color:' + m[1] + '">' + m[0] + "</span>";
  }
  function render(panel) {
    var rows = run();
    var fail = rows.filter(function (r) { return r.status === "fail"; }).length;
    var pass = rows.filter(function (r) { return r.status === "pass"; }).length;
    panel.querySelector("#qa-body").innerHTML =
      '<div style="font-size:11px;color:var(--text-3,#888);margin-bottom:8px">Conformância DS v2 · classes G1–G6 · tela ativa · ' + pass + " ✅ · " + fail + " 🔴</div>" +
      rows.map(function (r) {
        return '<div style="display:grid;grid-template-columns:20px 52px 1fr;gap:6px;padding:5px 0;border-top:1px solid var(--border-2,#eee);font-size:11.5px;align-items:baseline">' +
          chip(r.status) + '<b style="font-family:var(--mono,monospace);font-size:10.5px">' + r.id + "</b>" +
          "<span><b>" + r.label + "</b><br/><span style='color:var(--text-3,#888);font-size:10.5px'>" + r.detail + "</span></span></div>";
      }).join("");
  }
  function mount() {
    var launch = document.createElement("button");
    launch.id = "qa-launch";
    launch.textContent = "▶ Conformância DS";
    launch.style.cssText = "position:fixed;left:14px;bottom:14px;z-index:9000;height:32px;padding:0 13px;border-radius:8px;border:1px solid var(--border,#ccc);background:var(--surface,#fff);color:var(--text,#222);font:600 12px/1 var(--sans,system-ui);cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.08)";
    var panel = document.createElement("div");
    panel.id = "qa-panel";
    panel.style.cssText = "position:fixed;left:14px;bottom:56px;z-index:9001;width:380px;max-height:70vh;overflow:auto;border-radius:10px;border:1px solid var(--border,#ddd);background:var(--surface,#fff);color:var(--text,#222);box-shadow:0 10px 36px rgba(0,0,0,.16);display:none;font-family:var(--sans,system-ui)";
    panel.innerHTML =
      '<div style="display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid var(--border,#eee)">' +
        '<b style="font-size:13px;flex:1">Conformância DS — tela ativa</b>' +
        '<button id="qa-x" style="border:0;background:none;cursor:pointer;color:var(--text-3,#999);font-size:18px">✕</button>' +
      "</div>" +
      '<div id="qa-body" style="padding:10px 14px"></div>' +
      '<div style="display:flex;gap:8px;padding:12px 14px;border-top:1px solid var(--border,#eee)">' +
        '<button id="qa-run" style="flex:1;height:32px;border-radius:7px;border:1px solid var(--accent,#64c);background:var(--accent,#64c);color:#fff;font:600 12px/1 var(--sans,system-ui);cursor:pointer">Rodar G1–G6</button>' +
        '<button id="qa-neg" style="height:32px;padding:0 11px;border-radius:7px;border:1px solid var(--border,#ccc);background:var(--surface,#fff);color:var(--text,#222);font:600 12px/1 var(--sans,system-ui);cursor:pointer">Testar o teste</button>' +
      "</div>";
    document.body.appendChild(launch);
    document.body.appendChild(panel);
    launch.addEventListener("click", function () {
      panel.style.display = panel.style.display === "none" ? "block" : "none";
      if (panel.style.display === "block") render(panel);
    });
    panel.querySelector("#qa-x").addEventListener("click", function () { panel.style.display = "none"; });
    panel.querySelector("#qa-run").addEventListener("click", function () { render(panel); });
    panel.querySelector("#qa-neg").addEventListener("click", function () {
      var r = negative();
      var ok = Object.keys(r).every(function (k) { return r[k].discrimina || r[k].skipped; });
      panel.querySelector("#qa-body").innerHTML =
        '<div style="font-size:12px;padding:6px 0"><b>Controle-negativo (testa o teste):</b> ' + (ok ? "✅ todos os gates discriminam" : "🔴 gate cego") + "</div>" +
        Object.keys(r).map(function (k) {
          var v = r[k];
          return '<div style="font:11px/1.6 var(--mono,monospace)">' + k + ": " + (v.skipped ? "skip (" + v.skipped + ")" : v.dirty ? "⚠ " + v.note : v.before + " → bug:" + v.during + " → " + v.after + (v.discrimina ? " ✅" : " 🔴")) + "</div>";
        }).join("");
    });
  }

  // API sempre disponível (ritual pré-done [CC] + verificador); UI removida ([W] 2026-06-29 "não faz mais sentido").
  window.QAConformance = { run: run, negative: negative, version: 2.5 };
})();
