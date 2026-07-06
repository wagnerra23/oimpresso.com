#!/usr/bin/env node
// Self-test prototipo-readiness — prova as regras vs o CONTRATO (Wagner 2026-07-06 +
// ADR 0264 G-2: aplicar sem se preocupar = contrato executável trava o comportamento).
// Roda: node scripts/qa/prototipo-readiness.test.mjs
import { relatedPrototype, temPrototipoReal, contaUCs, classifica } from './prototipo-readiness.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  → ' + extra}`); if (!c) fails++; };

// 1. relatedPrototype lê o campo do frontmatter.
check('lê related_prototype com caminho', relatedPrototype('---\nrelated_prototype: prototipo-ui/cowork/x.jsx\n---') === 'prototipo-ui/cowork/x.jsx');
check('sem campo → null', relatedPrototype('---\ncharter: X\n---') === null);

// 2. temPrototipoReal distingue alvo de aplicação de "n/a".
check('caminho .jsx → real', temPrototipoReal('prototipo-ui/cowork/compras-page.jsx') === true);
check('.html handoff → real', temPrototipoReal('design-handoff "Prova Viva.html" (Cowork chat46)') === true);
check('n/a explícito → NÃO real', temPrototipoReal('n/a (sem protótipo Cowork — nasceu no DS)') === false);
check('MIS-ANCHOR → NÃO real', temPrototipoReal('removido related_prototype: oficina.jsx — MIS-ANCHOR') === false);
check('null → NÃO real', temPrototipoReal(null) === false);

// 3. contaUCs conta só headings ## UC-.
check('2 UCs contados', contaUCs('## UC-01 x\nprosa\n## UC-02 y\n## Backlog z') === 2);
check('0 UC (só backlog) → 0', contaUCs('## Backlog de casos\n- item') === 0);

// 4. classifica — o coração do contrato "aplicar sem se preocupar".
//    PRONTA exige trio completo + scorecard (o casos+UC é o que trava o comportamento).
check('trio completo + scorecard → PRONTA', classifica({ prototipoReal: true, temTsx: true, temCasosComUC: true, temScorecard: true }) === 'pronta');
check('sem casos+UC → 1-CICLO (contrato não trava ainda)', classifica({ prototipoReal: true, temTsx: true, temCasosComUC: false, temScorecard: true }) === '1-ciclo');
check('sem scorecard → 1-CICLO (nota honesta falta)', classifica({ prototipoReal: true, temTsx: true, temCasosComUC: true, temScorecard: false }) === '1-ciclo');
check('sem protótipo real → SEM-ANCORA (não é alvo)', classifica({ prototipoReal: false, temTsx: true, temCasosComUC: true, temScorecard: true }) === 'sem-ancora');
// Counterfactual central: casos.md SEM UC não compra "pronta" (presença ≠ contrato — L-24).
check('casos.md presente mas 0 UC → NÃO pronta', classifica({ prototipoReal: true, temTsx: true, temCasosComUC: false, temScorecard: true }) !== 'pronta');

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato da prontidão preservado');
process.exit(fails ? 1 : 0);
