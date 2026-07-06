#!/usr/bin/env node
// Self-test anchor-content-check — prova a classificação vs o CONTRATO (âncora tem que
// apontar pro FONTE da tela, não pro shell/arquivo-fantasma). Origem: buraco pego por
// Wagner 2026-07-06 (2/9 âncoras podres, nenhum gate viu). Roda: node ...test.mjs
import { anchorFile, anchorRelPath, stylesheetCount, classifyAnchor, SHELL_MIN_CSS } from './anchor-content-check.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  → ' + extra}`); if (!c) fails++; };

// 1. anchorFile extrai caminho de arquivo; prosa/n-a → null.
check('extrai .jsx', anchorFile('prototipo-ui/cowork/financeiro-page.jsx') === 'financeiro-page.jsx');
check('extrai .html com sufixo prosa', anchorFile('oimpresso.com.html (canon REAL — lugar fixo)') === 'oimpresso.com.html');
check('n/a → null', anchorFile('n/a (sem protótipo Cowork)') === null);
check('MIS-ANCHOR → null', anchorFile('removido related_prototype: x.jsx — MIS-ANCHOR') === null);
check('prosa sem arquivo → null', anchorFile('prototipo Cowork "payment-gateway-ui" F1+F1.5') === null);

// 1b. anchorRelPath — identidade por PATH COMPLETO (adversário 2026-07-06: basename colide;
//     arte 2026-07-06: hash normalizado keyed por path completo, nunca basename).
check('relpath raiz', anchorRelPath('prototipo-ui/cowork/financeiro-page.jsx') === 'financeiro-page.jsx');
check('relpath SUBDIR preservado (não colapsa pro basename)', anchorRelPath('prototipo-ui/cowork/prototipos/payment-gateway-ui/index.html') === 'prototipos/payment-gateway-ui/index.html');
check('relpath nome solto (sem dir) → como veio', anchorRelPath('financeiro-telas-extras.jsx (TelaFluxo)') === 'financeiro-telas-extras.jsx');
check('relpath dois homônimos ≠ mesma identidade', anchorRelPath('prototipo-ui/cowork/a/x.jsx') !== anchorRelPath('prototipo-ui/cowork/b/x.jsx'));
check('relpath n/a → null', anchorRelPath('n/a (sem protótipo Cowork)') === null);
check('relpath prosa → null', anchorRelPath('prototipo Cowork "payment-gateway-ui" F1+F1.5') === null);

// 2. stylesheetCount detecta shell.
check('conta <link stylesheet>', stylesheetCount('<link rel="stylesheet" href="a.css"/><link rel=\'stylesheet\' href="b.css">') === 2);
check('jsx sem stylesheet → 0', stylesheetCount('export function Tela(){return <div/>}') === 0);

// 3. classifyAnchor — o coração.
check('arquivo sumiu → MISSING', classifyAnchor({ exists: false, isHtml: true, stylesheetLinks: 0, moduleHits: 0 }) === 'MISSING');
check('html com 27 css → SHELL (o caso Unificado→oimpresso.com.html)', classifyAnchor({ exists: true, isHtml: true, stylesheetLinks: 27, moduleHits: 10 }) === 'SHELL');
check('html com poucos css + módulo → OK', classifyAnchor({ exists: true, isHtml: true, stylesheetLinks: 2, moduleHits: 5 }) === 'OK');
check('jsx (não html) com módulo → OK (o caso financeiro-page.jsx)', classifyAnchor({ exists: true, isHtml: false, stylesheetLinks: 0, moduleHits: 59 }) === 'OK');
check('existe mas 0 menção do módulo → NO-MODULE', classifyAnchor({ exists: true, isHtml: false, stylesheetLinks: 0, moduleHits: 0 }) === 'NO-MODULE');

// 4. Counterfactual: o shell NÃO escapa por ter menção do módulo (linka financeiro.css → "financeiro" aparece).
//    A regra do shell (≥10 css) tem prioridade sobre moduleHits — senão o oimpresso.com.html passaria.
check('shell com módulo presente AINDA é SHELL (não passa)', classifyAnchor({ exists: true, isHtml: true, stylesheetLinks: 27, moduleHits: 10 }) !== 'OK');

// 5. Fronteira do limiar de shell.
check('SHELL_MIN_CSS = 10', SHELL_MIN_CSS === 10);
check('9 css → não é shell (OK se tem módulo)', classifyAnchor({ exists: true, isHtml: true, stylesheetLinks: 9, moduleHits: 3 }) === 'OK');
check('10 css → é shell', classifyAnchor({ exists: true, isHtml: true, stylesheetLinks: 10, moduleHits: 3 }) === 'SHELL');

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato do sentinela de âncora preservado');
process.exit(fails ? 1 : 0);
