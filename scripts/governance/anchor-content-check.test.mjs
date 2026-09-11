#!/usr/bin/env node
// Self-test anchor-content-check — prova a classificação vs o CONTRATO (âncora tem que
// apontar pro FONTE da tela, não pro shell/arquivo-fantasma). Origem: buraco pego por
// Wagner 2026-07-06 (2/9 âncoras podres, nenhum gate viu). Roda: node ...test.mjs
import { anchorFile, anchorRelPath, anchorFragment, fragmentResolves, stylesheetCount, classifyAnchor, SHELL_MIN_CSS } from './anchor-content-check.mjs';

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

// 6. anchorFragment — seção DECLARADA no parêntese colado ao path (os 4 charters que
//    compartilham financeiro-telas-extras.jsx). LÊ o que o charter escreveu, nunca deriva.
check('fragmento (TelaConciliacao) simples', anchorFragment('prototipo-ui/cowork/financeiro-telas-extras.jsx (TelaConciliacao) — tela viva evoluiu') === 'TelaConciliacao');
check('fragmento (TelaFluxo; prosa) — corta no ;', anchorFragment('prototipo-ui/cowork/financeiro-telas-extras.jsx (TelaFluxo; corrigido 2026-07-06 — antes apontava)') === 'TelaFluxo');
check('fragmento (TelaImpostos), prosa depois', anchorFragment('prototipo-ui/cowork/financeiro-telas-extras.jsx (TelaImpostos), aprovado [W] 2026-06-10') === 'TelaImpostos');
check('prosa capitalizada multi-palavra NÃO é fragmento', anchorFragment('prototipo-ui/cowork/financeiro-page.jsx (design real da Visão Unificada; corrigido)') === null);
check('parêntese só-prosa lowercase → null', anchorFragment('prototipo-ui/cowork/vendas-page.jsx (formalizado 2026-07-09 — o visual_source já)') === null);
check('sem parêntese → null', anchorFragment('prototipo-ui/cowork/compras-page.jsx') === null);
check('parêntese NÃO-colado ao path (· função … (linhas)) → null', anchorFragment('prototipo-ui/cowork/vendas-extras.jsx · função VendasCaixaPage (linhas 123-354)') === null);
check('n/a → null', anchorFragment('n/a (herda PT-01 Lista)') === null);

// 7. fragmentResolves — o export existe no corpo? (dead-anchor de fragmento se não)
const corpoExtras = 'const TelaFluxo = () => {};\nconst TelaConciliacao = () => {};\nwindow.TelaImpostos = TelaImpostos;';
check('resolve const TelaFluxo', fragmentResolves(corpoExtras, 'TelaFluxo') === true);
check('resolve window.TelaImpostos', fragmentResolves(corpoExtras, 'TelaImpostos') === true);
check('NÃO resolve TelaInexistente (dead-anchor)', fragmentResolves(corpoExtras, 'TelaInexistente') === false);
check('sem fragmento (null) → resolve trivialmente', fragmentResolves(corpoExtras, null) === true);
check('menção solta NÃO conta como export (só num comentário)', fragmentResolves('// veja TelaX adiante', 'TelaX') === false);

// 8. classifyAnchor NO-SECTION — declara seção que não resolve = warn; resolve = OK; sem declarar = retrocompatível.
check('seção declarada + NÃO resolve → NO-SECTION', classifyAnchor({ exists: true, isHtml: false, stylesheetLinks: 0, moduleHits: 5, sectionDeclared: true, sectionResolves: false }) === 'NO-SECTION');
check('seção declarada + resolve → OK', classifyAnchor({ exists: true, isHtml: false, stylesheetLinks: 0, moduleHits: 5, sectionDeclared: true, sectionResolves: true }) === 'OK');
check('sem seção declarada → OK (retrocompatível, defaults)', classifyAnchor({ exists: true, isHtml: false, stylesheetLinks: 0, moduleHits: 5 }) === 'OK');
check('NO-MODULE tem prioridade sobre NO-SECTION', classifyAnchor({ exists: true, isHtml: false, stylesheetLinks: 0, moduleHits: 0, sectionDeclared: true, sectionResolves: false }) === 'NO-MODULE');
check('MISSING tem prioridade (arquivo sumiu, nem chega na seção)', classifyAnchor({ exists: false, isHtml: false, stylesheetLinks: 0, moduleHits: 0, sectionDeclared: true, sectionResolves: false }) === 'MISSING');

// 5. Fronteira do limiar de shell.
check('SHELL_MIN_CSS = 10', SHELL_MIN_CSS === 10);
check('9 css → não é shell (OK se tem módulo)', classifyAnchor({ exists: true, isHtml: true, stylesheetLinks: 9, moduleHits: 3 }) === 'OK');
check('10 css → é shell', classifyAnchor({ exists: true, isHtml: true, stylesheetLinks: 10, moduleHits: 3 }) === 'SHELL');

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato do sentinela de âncora preservado');
process.exit(fails ? 1 : 0);
