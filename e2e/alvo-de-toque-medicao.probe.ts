/**
 * COMO RODAR (nao roda sozinho, de proposito):
 *
 *   npx playwright test --testMatch="*.probe.ts" e2e/alvo-de-toque-medicao.probe.ts
 *
 * O playwright.config.ts casa apenas *.spec.ts — a extensao .probe.ts mantem este arquivo
 * FORA da suite que a lane e2e-gate roda em todo PR. E um instrumento de medicao sob demanda,
 * NAO um gate: nao deve virar um sem decisao [W] e sem falso-positivo medido no corpus real.
 *
 * Medicao de referencia: run 33940260653 (dispatch de e2e-gate na branch
 * claude/medicao-alvo-de-toque-1280), 2026-09-05.
 */
import { test } from '@playwright/test';
import { readFileSync } from 'node:fs';

/**
 * MEDIÇÃO (report-only) — tamanho de alvo de toque no ERP denso a 1280.
 *
 * NÃO É GATE e não deve virar um sem decisão [W]: zero assert de limiar, nunca falha
 * por tamanho. Existe para dar NÚMERO à pergunta aberta "24×24 mínimo ou exceção
 * declarada?", listada como decisão [W] no export do Repair e no do CRM.
 *
 * Como mede, e por que assim:
 *   - getBoundingClientRect() no DOM RENDERIZADO, nunca classe/CSS (§5 2026-07-16:
 *     medir a propriedade errada e chamar de verificado).
 *   - visibilidade por computed style (display/visibility/opacity/pointer-events).
 *   - DUAS leituras com intervalo; instabilidade é REPORTADA, não arredondada
 *     (§5 2026-08-24: número que ainda sobe não é medida, é retrato de meio-caminho).
 *   - exceção de ESPAÇAMENTO da própria 2.5.8: círculo de diâmetro 24 centrado no alvo
 *     subdimensionado não pode intersectar outro alvo nem o círculo de outro
 *     subdimensionado. Sem ela o número bruto superestima a violação em ordem de grandeza.
 *   - CONTROLE POSITIVO embutido (§5 2026-08-01): se o canário não discriminar,
 *     nenhum número abaixo dele vale nada.
 *
 * Denominador DERIVADO do manifesto que o visual-regression já consome
 * (tests/Browser/visreg-screens.json) — não é amostra escolhida a dedo.
 */

type Tela = { screen: string; route: string; anchor: string };

const TELAS: Tela[] = JSON.parse(readFileSync('tests/Browser/visreg-screens.json', 'utf8'));

const SONDA = `(() => {
  const SEL = ['a[href]','button','input','select','textarea','summary','[role=button]','[role=link]','[role=tab]','[role=checkbox]','[role=radio]','[role=switch]','[role=menuitem]','[role=menuitemcheckbox]','[role=menuitemradio]','[role=option]','[role=combobox]','[role=slider]','[role=spinbutton]','[tabindex]:not([tabindex="-1"])'].join(',');
  const limpa = s => String(s || '').replace(/\\s+/g, ' ').trim().slice(0, 40);
  const vis = el => { const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden' || cs.visibility === 'collapse') return false;
    if (parseFloat(cs.opacity) === 0 || cs.pointerEvents === 'none') return false;
    if (el.hasAttribute('hidden') || el.closest('[aria-hidden="true"]')) return false;
    const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
  const A = [];
  document.querySelectorAll(SEL).forEach(el => {
    if (el.disabled) return;
    if (el.tagName === 'INPUT' && el.type === 'hidden') return;
    if (!vis(el)) return;
    const r = el.getBoundingClientRect(), cs = getComputedStyle(el), pai = el.parentElement;
    const irmaoTexto = !!pai && [...pai.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 1);
    A.push({ x: r.x, y: r.y, w: +r.width.toFixed(2), h: +r.height.toFixed(2),
      min: +Math.min(r.width, r.height).toFixed(2),
      tag: el.tagName, cls: (typeof el.className === 'string' ? el.className : '').split(' ')[0] || '',
      nome: limpa(el.getAttribute('aria-label') || el.innerText || el.value || el.title || ''),
      inline: cs.display.startsWith('inline') && irmaoTexto });
  });
  const sub = A.filter(a => a.w < 24 || a.h < 24);
  const dR = (cx, cy, r) => Math.hypot(Math.max(r.x - cx, 0, cx - (r.x + r.w)), Math.max(r.y - cy, 0, cy - (r.y + r.h)));
  sub.forEach(u => { const ux = u.x + u.w / 2, uy = u.y + u.h / 2; let ch = false;
    for (const o of A) { if (o === u) continue;
      if (o.w < 24 || o.h < 24) { if (Math.hypot(ux - (o.x + o.w / 2), uy - (o.y + o.h / 2)) < 24) { ch = true; break; } }
      else if (dR(ux, uy, o) < 12) { ch = true; break; } }
    u.passa = !ch; });
  const falha = sub.filter(a => !a.passa);
  const g = {}; falha.forEach(x => { const k = x.tag + '.' + (x.cls || '(sem)') + ' ' + x.w.toFixed(0) + 'x' + x.h.toFixed(0); g[k] = (g[k] || 0) + 1; });
  const b = n => A.filter(a => a.min < n).length;
  return { medidos: A.length, botoes: A.filter(a => a.tag === 'BUTTON').length,
    abaixo24: b(24), abaixo32: b(32), abaixo44: b(44),
    subdim: sub.length, passamEspaco: sub.filter(a => a.passa).length, falham: falha.length,
    subInline: sub.filter(a => a.inline).length,
    grupos: Object.entries(g).sort((p, q) => q[1] - p[1]).slice(0, 8).map(([k, n]) => n + 'x ' + k),
    vw: innerWidth + 'x' + innerHeight };
})()`;

const CANARIO = `(() => {
  const box = document.createElement('div'); box.id = '__canario';
  box.innerHTML = '<div style="position:fixed;top:300px;left:1150px;z-index:99999">'
    + '<button style="width:20px;height:20px" aria-label="cn-isolado"></button></div>'
    + '<div style="position:fixed;top:500px;left:1150px;z-index:99999">'
    + '<button style="width:16px;height:16px" aria-label="cn-par-A"></button>'
    + '<button style="width:16px;height:16px" aria-label="cn-par-B"></button></div>';
  document.body.appendChild(box); return true;
})()`;

test('MEDICAO alvo de toque 1280 - distribuicao por tela (report-only, nao falha)', async ({ page }) => {
  test.setTimeout(20 * 60 * 1000);
  await page.setViewportSize({ width: 1280, height: 900 });

  // ── canário: prova que a sonda discrimina ANTES de citar qualquer número dela ──
  await page.goto('/home', { waitUntil: 'domcontentloaded' }).catch(() => {});
  const semCanario: any = await page.evaluate(SONDA);
  await page.evaluate(CANARIO);
  const comCanario: any = await page.evaluate(SONDA);
  await page.evaluate("(() => { const e = document.getElementById('__canario'); if (e) e.remove(); })()");
  console.log('=== CANARIO (controle positivo da sonda) ===');
  console.log('  sem canario: alvos=' + semCanario.medidos + ' subdim=' + semCanario.subdim + ' falham=' + semCanario.falham);
  console.log('  com canario: alvos=' + comCanario.medidos + ' subdim=' + comCanario.subdim + ' falham=' + comCanario.falham);
  console.log('  ESPERADO: +3 alvos, +3 subdim, +2 falham (o 20x20 isolado passa por espacamento; o par 16x16 colado reprova)');

  const linhas: string[] = [];
  const cru: any[] = [];

  for (const t of TELAS) {
    let r: any = null, montou = true, obs = '';
    try {
      await page.goto(t.route, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
      const url = page.url();
      const corpo = (await page.evaluate("document.body.innerText.slice(0,6000)")) as string;
      if (url.includes('/login')) { montou = false; obs = 'caiu no login'; }
      else if (!corpo.includes(t.anchor)) { montou = false; obs = 'ancora ausente: ' + t.anchor; }
      if (montou) {
        const a: any = await page.evaluate(SONDA);
        await page.waitForTimeout(1500);
        r = await page.evaluate(SONDA);
        if (a.medidos !== r.medidos || a.falham !== r.falham) {
          obs = 'INSTAVEL (' + a.medidos + '/' + a.falham + ' -> ' + r.medidos + '/' + r.falham + ')';
        }
      }
    } catch (e: any) {
      montou = false; obs = 'erro: ' + String(e && e.message).slice(0, 70);
    }
    if (!montou || !r) {
      linhas.push(t.screen.padEnd(30) + ' | NAO MEDIDA - ' + obs);
      cru.push({ screen: t.screen, route: t.route, medida: false, obs });
      continue;
    }
    linhas.push(
      t.screen.padEnd(30) + ' | ' + String(r.medidos).padStart(4) + ' alvos' +
      ' | <24: ' + String(r.abaixo24).padStart(3) +
      ' | <32: ' + String(r.abaixo32).padStart(3) +
      ' | <44: ' + String(r.abaixo44).padStart(3) +
      ' | passam p/espaco: ' + String(r.passamEspaco).padStart(3) +
      ' | FALHAM 2.5.8: ' + String(r.falham).padStart(3) + (obs ? ' | ' + obs : ''));
    cru.push(Object.assign({ screen: t.screen, route: t.route, medida: true, obs }, r));
  }

  console.log('================ MEDICAO ALVO DE TOQUE - viewport 1280x900 ================');
  console.log(linhas.join('\n'));

  const medidas = cru.filter(c => c.medida);
  const soma = (k: string) => medidas.reduce((s, c) => s + (c[k] || 0), 0);
  console.log('--- TOTAIS (' + medidas.length + ' de ' + TELAS.length + ' telas do manifesto realmente medidas) ---');
  console.log('  alvos medidos: ' + soma('medidos') +
    ' | abaixo de 24: ' + soma('abaixo24') +
    ' | abaixo de 32: ' + soma('abaixo32') +
    ' | abaixo de 44: ' + soma('abaixo44'));
  console.log('  subdimensionados que PASSAM pela excecao de espacamento: ' + soma('passamEspaco'));
  console.log('  REPROVAM a 2.5.8 de fato: ' + soma('falham'));
  console.log('--- GRUPOS QUE REPROVAM (por tela) ---');
  for (const c of medidas) { if (c.falham > 0) console.log('  ' + c.screen + ': ' + c.grupos.join(' | ')); }
  console.log('<<<JSON>>>' + JSON.stringify(cru) + '<<<FIM>>>');
});
