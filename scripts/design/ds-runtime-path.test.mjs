#!/usr/bin/env node
// @ts-check
/**
 * Teste do dono `ds-runtime-path.mjs` — onde `_ds/**` pousa no cache de preview.
 *
 * Os asserts moravam em `cowork-mirror-freshness.test.mjs` até 2026-10-23 e mudaram junto com a
 * função (ADR 0438, onda 2c-3). O conteúdo é o mesmo: as duas formas de bind (Wagner com slug,
 * Felipe sem slug), a ordem declarada no caso ambíguo e os controles negativos.
 *
 *   node scripts/design/ds-runtime-path.test.mjs
 */
import { dsRuntimeRelPath } from './ds-runtime-path.mjs';

let fails = 0;
const check = (nome, cond, detalhe = '') => {
  console.log(`[${cond ? 'OK' : 'FAIL'}] ${nome}${cond ? '' : `  → ${detalhe}`}`);
  if (!cond) fails++;
};
const lanca = (path) => { try { dsRuntimeRelPath(path); return ''; } catch (e) { return String(e.message); } };

check('remove o slug _ds e mantém o path relativo consumido pelo preview',
  dsRuntimeRelPath('_ds/office-impresso-019dd0/assets/fonts/x.woff2') === 'assets/fonts/x.woff2');
// PR-A9 — as DUAS formas de bind. Wagner carimba o slug; Felipe não (`Felipe/venda-v3/index.html`).
check('A9: bind SEM slug resolve (a forma do Felipe, que antes lançava)',
  dsRuntimeRelPath('_ds/colors_and_type.css') === 'colors_and_type.css'
  && dsRuntimeRelPath('_ds/_ds_bundle.js') === '_ds_bundle.js');
check('A9: styles.css entra na whitelist (com e sem slug)',
  dsRuntimeRelPath('_ds/styles.css') === 'styles.css'
  && dsRuntimeRelPath('_ds/slug-x/styles.css') === 'styles.css');
// A ordem das tentativas é CONTRATO, senão é acaso de implementação.
check('A9: com-slug só vence quando o resultado bate a whitelist',
  dsRuntimeRelPath('_ds/assets/fonts/a.woff2') === 'assets/fonts/a.woff2');
check('A9: no caso AMBÍGUO (ambos batem) com-slug vence, por regra declarada',
  dsRuntimeRelPath('_ds/assets/colors_and_type.css') === 'colors_and_type.css');

// CONTROLES NEGATIVOS: a whitelist não virou "aceita tudo".
const foraDaWhitelist = lanca('_ds/qualquer.txt');
check('CONTROLE A9: path sem slug FORA da whitelist segue lançando', /use --ds/.test(foraDaWhitelist), foraDaWhitelist);
const template = lanca('templates/pt-05-dashboard/Pt05Dashboard.dc.html');
check('BITE: template não contamina o cache de runtime', /use --ds/.test(template), template);
const traversal = lanca('_ds/ds-teste/assets/../../fora.txt');
check('BITE: path traversal é recusado', /caminho inseguro/.test(traversal), traversal);

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ ds-runtime-path: duas formas de bind + ordem declarada + controles negativos');
process.exit(fails ? 1 : 0);
