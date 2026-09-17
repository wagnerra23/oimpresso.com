#!/usr/bin/env node
// a11y-sondas.mjs — PR-A4: as sondas de a11y que o axe-core NAO faz, como codigo de PAGINA.
//
// ── POR QUE ESTAS 7, E NAO "rodar axe e pronto" (medido 2026-09-17 contra axe-core 4.12) ───
// O axe e REGRA sobre o DOM parado. Ele nao sabe (a) que um DIV recebe clique — React delega o
// handler no root, entao nao ha onclick no elemento pra ele ver; (b) que uma aba nunca troca de
// estado; (c) que a tela nao tem canal de anuncio; (d) alvo de toque (`target-size`, WCAG 2.2,
// fora do conjunto default). Estas sondas cobrem esse vao — o axe roda AO LADO, nao no lugar.
//
// ── DIVISAO DE TRABALHO (o calculo de cor NAO mora aqui) ───────────────────────────────────
// A sonda COLHE no browser (cor, fundo efetivo, tamanho, retangulo) e o Node CALCULA via
// `a11y-contraste.mjs`, que tem a sanidade abortiva do PR-A2. Fazer a conta dentro da pagina
// deixaria o numero fora do alcance do caso de sanidade — que e exatamente por onde o 2,62 de
// 2026-09-03 entrou.
//
// Sem regex escapada de proposito: par de barra invertida colapsa no transporte da escrita e
// grava byte de controle no arquivo (LC-26, emenda 2026-09-09).

export const SONDAS_SOURCE = `(() => {
  const TAG_INTERATIVA = ['BUTTON','A','INPUT','SELECT','TEXTAREA','SUMMARY','DETAILS','LABEL','OPTION'];
  const ROLE_INTERATIVO = ['button','link','tab','checkbox','radio','menuitem','menuitemcheckbox',
    'menuitemradio','option','switch','slider','spinbutton','textbox','combobox','searchbox','treeitem'];
  const vis = (el) => {
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden' || cs.opacity === '0') return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  };
  const roleDe = (el) => (el.getAttribute('role') || '').trim().toLowerCase();
  const ehInterativo = (el) => TAG_INTERATIVA.includes(el.tagName)
    || ROLE_INTERATIVO.includes(roleDe(el))
    || el.hasAttribute('tabindex');
  // O clique tem DONO? Se um ancestral ja e interativo, o DIV interno e decoracao DENTRO de um
  // controle correto — acusa-lo seria o falso-positivo classico (o filho do <button>).
  const donoInterativo = (el) => {
    for (let p = el.parentElement; p; p = p.parentElement) if (ehInterativo(p)) return p;
    return null;
  };
  // A propriedade cursor e HERDADA: num <li> clicavel, TODOS os descendentes computam cursor:pointer. Sem
  // isto, um unico defeito vira ~9 achados (medido na Forja em 2026-09-17: 63 achados eram 8
  // defeitos, 87% de ruido). O clicavel e o TOPO da cadeia — quem tem pai tambem pointer nao e.
  const ehTopoDoPointer = (el) => {
    const pai = el.parentElement;
    return !pai || getComputedStyle(pai).cursor !== 'pointer';
  };
  const nomeAcessivel = (el) => {
    const t = (el.getAttribute('aria-label') || '').trim();
    if (t) return t;
    if (el.getAttribute('aria-labelledby')) return el.getAttribute('aria-labelledby');
    const titulo = el.querySelector(':scope > title');
    return titulo && titulo.textContent.trim() ? titulo.textContent.trim() : '';
  };
  const seletor = (el) => {
    const c = [...el.classList].slice(0, 2).join('.');
    return el.tagName.toLowerCase() + (c ? '.' + c : '') + (el.id ? '#' + el.id : '');
  };
  const corte = (el) => (el.textContent || '').trim().slice(0, 40);
  const achados = [];
  const push = (sonda, el, motivo, extra) => achados.push(Object.assign(
    { sonda, motivo, seletor: seletor(el), texto: corte(el) }, extra || {}));

  const todos = [...document.querySelectorAll('*')].filter(vis);

  // ── S1 · elemento nao-interativo com cursor:pointer, sem role e sem tabindex ─────────────
  for (const el of todos) {
    if (ehInterativo(el)) continue;
    if (getComputedStyle(el).cursor !== 'pointer') continue;
    if (donoInterativo(el)) continue;        // o clique ja tem dono acessivel
    if (!ehTopoDoPointer(el)) continue;      // herdou o pointer do pai: o defeito e do pai, nao dele
    if (el.closest('svg')) continue;         // interno de icone: o dono e o svg/botao
    push('S1-clicavel-sem-papel', el, 'cursor:pointer sem role nem tabindex e sem ancestral interativo');
  }

  // ── S2 · svg dentro de clicavel, sem aria-hidden e sem nome ──────────────────────────────
  for (const svg of document.querySelectorAll('svg')) {
    if (!vis(svg)) continue;
    const dono = ehInterativo(svg) ? svg : donoInterativo(svg);
    if (!dono) continue;
    if (svg.getAttribute('aria-hidden') === 'true') continue;
    if (nomeAcessivel(svg)) continue;
    // O dono tem nome proprio? Sem ele, o svg anonimo deixa o CONTROLE anonimo (grave).
    const nomeDono = corte(dono) || nomeAcessivel(dono) || (dono.getAttribute('title') || '').trim();
    push('S2-svg-anonimo', svg,
      nomeDono ? 'svg sem aria-hidden dentro de controle que JA tem nome (ruido para leitor de tela)'
               : 'svg sem nome dentro de controle SEM texto (o controle fica anonimo)',
      { grave: !nomeDono });
  }

  // ── S3 · nenhum canal de anuncio na pagina (por PAGINA, nao por elemento) ────────────────
  if (document.querySelectorAll('[aria-live], [role=status], [role=alert], [role=log]').length === 0) {
    achados.push({ sonda: 'S3-sem-aria-live', seletor: 'document', texto: '',
      motivo: 'a tela nao tem nenhuma regiao de anuncio (aria-live/status/alert/log)' });
  }

  // ── S4 · overlay grande e fixo sem semantica de dialogo ──────────────────────────────────
  const areaVp = innerWidth * innerHeight;
  for (const el of todos) {
    if (getComputedStyle(el).position !== 'fixed') continue;
    const r = el.getBoundingClientRect();
    const pct = (r.width * r.height) / areaVp;
    if (pct < 0.15) continue;                                   // barra/toolbar fixa nao e overlay
    if (el.closest('header, nav, [role=banner], [role=navigation]')) continue;
    const role = roleDe(el);
    const ehDialogo = role === 'dialog' || role === 'alertdialog';
    if (ehDialogo && el.getAttribute('aria-modal') === 'true') continue;
    push('S4-overlay-sem-dialogo', el,
      ehDialogo ? 'role de dialogo sem aria-modal=true' : 'overlay fixo grande sem role=dialog',
      { area_pct: Math.round(pct * 100) });
  }

  // ── S5 · ARIA de estado ausente ou congelado ─────────────────────────────────────────────
  for (const tab of document.querySelectorAll('[role=tab]')) {
    if (vis(tab) && !tab.hasAttribute('aria-selected')) push('S5-estado-aria', tab, 'role=tab sem aria-selected');
  }
  for (const lista of document.querySelectorAll('[role=tablist]')) {
    if (!vis(lista)) continue;
    const filhos = [...lista.children].filter(vis);
    if (filhos.length && !filhos.some((f) => roleDe(f) === 'tab')) {
      push('S5-estado-aria', lista, 'role=tablist cujos filhos nao declaram role=tab');
    }
  }
  // Grupo de irmaos que DECLARA estado com o MESMO valor em todos = estado decorativo.
  for (const attr of ['aria-selected', 'aria-pressed']) {
    const porPai = new Map();
    for (const el of document.querySelectorAll('[' + attr + ']')) {
      if (!vis(el) || !el.parentElement) continue;
      if (!porPai.has(el.parentElement)) porPai.set(el.parentElement, []);
      porPai.get(el.parentElement).push(el);
    }
    for (const [pai, irmaos] of porPai) {
      if (irmaos.length < 2) continue;
      if (new Set(irmaos.map((e) => e.getAttribute(attr))).size === 1) {
        push('S5-estado-aria', pai, attr + ' com o MESMO valor nos ' + irmaos.length + ' irmaos (estado congelado)');
      }
    }
  }

  // ── S6 · COLHE o par de cores (o calculo e no Node, sob a sanidade do A2) ────────────────
  const fundoEfetivo = (el) => {
    for (let p = el; p; p = p.parentElement) {
      const bg = getComputedStyle(p).backgroundColor;
      if (bg && bg !== 'transparent' && bg.indexOf('rgba(0, 0, 0, 0)') !== 0) return bg;
    }
    return getComputedStyle(document.body).backgroundColor || 'rgb(255, 255, 255)';
  };
  const pares = [];
  for (const el of todos) {
    const temTextoProprio = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim().length > 1);
    if (!temTextoProprio) continue;
    const cs = getComputedStyle(el);
    pares.push({
      seletor: seletor(el), texto: corte(el), cor: cs.color, fundo: fundoEfetivo(el),
      px: parseFloat(cs.fontSize) || 16, peso: parseInt(cs.fontWeight, 10) || 400,
    });
  }

  // ── S7 · alvo de toque menor que 24x24 (WCAG 2.2 AA) ────────────────────────────────────
  const alvos = [];
  for (const el of todos) {
    if (!ehInterativo(el) && getComputedStyle(el).cursor !== 'pointer') continue;
    if (donoInterativo(el)) continue;
    if (!ehInterativo(el) && !ehTopoDoPointer(el)) continue;   // mesma heranca de cursor do S1
    const r = el.getBoundingClientRect();
    if (r.width >= 24 && r.height >= 24) continue;
    alvos.push({ sonda: 'S7-alvo-pequeno', seletor: seletor(el), texto: corte(el),
      motivo: 'alvo de toque ' + Math.round(r.width) + 'x' + Math.round(r.height) + ' (menor que 24x24)' });
  }

  return { achados, pares, alvos, nos: document.querySelectorAll('*').length };
})()`;
