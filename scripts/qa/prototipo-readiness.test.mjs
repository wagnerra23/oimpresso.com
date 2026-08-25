#!/usr/bin/env node
// Self-test prototipo-readiness — prova as regras vs o CONTRATO (Wagner 2026-07-06 +
// ADR 0264 G-2: aplicar sem se preocupar = contrato executável trava o comportamento).
// Roda: node scripts/qa/prototipo-readiness.test.mjs
import { mkdtempSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { relatedPrototype, temPrototipoReal, contaUCs, classifica, coleta } from './prototipo-readiness.mjs';

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

// 3. contaUCs conta só headings ## UC- — delegando pra fonte única scripts/lib/uc-regex.mjs.
check('2 UCs contados', contaUCs('## UC-01 x\nprosa\n## UC-02 y\n## Backlog z') === 2);
check('0 UC (só backlog) → 0', contaUCs('## Backlog de casos\n- item') === 0);
// CONTROLE-NEGATIVO da delegação (2026-07-27): `## UC-` malformado (sem número) NÃO é UC.
// É o que MORDE se alguém reintroduzir o `/^UC-/i` permissivo que estava aqui — ele contava
// qualquer heading começando em "UC-". Delta no corpus real era 0 (batia por ACASO); esta
// asserção troca o acaso por contrato.
check('heading `## UC-` solto (malformado) → 0', contaUCs('## UC- rascunho sem número\n- item') === 0);
// Sufixo de letra (UC-DSR-08b) conta — o caso que o irmão screen-coverage-map PERDIA.
check('UC com sufixo de letra conta', contaUCs('## UC-DSR-08b acesso\n## UC-DSR-09 outro') === 2);

// 4. classifica — o coração do contrato "aplicar sem se preocupar".
//    PRONTA exige trio completo + scorecard (o casos+UC é o que trava o comportamento).
check('trio completo + scorecard → PRONTA', classifica({ prototipoReal: true, temTsx: true, temCasosComUC: true, temScorecard: true }) === 'pronta');
check('sem casos+UC → 1-CICLO (contrato não trava ainda)', classifica({ prototipoReal: true, temTsx: true, temCasosComUC: false, temScorecard: true }) === '1-ciclo');
check('sem scorecard → 1-CICLO (nota honesta falta)', classifica({ prototipoReal: true, temTsx: true, temCasosComUC: true, temScorecard: false }) === '1-ciclo');
check('sem protótipo real → SEM-ANCORA (não é alvo)', classifica({ prototipoReal: false, temTsx: true, temCasosComUC: true, temScorecard: true }) === 'sem-ancora');
// Counterfactual central: casos.md SEM UC não compra "pronta" (presença ≠ contrato — L-24).
check('casos.md presente mas 0 UC → NÃO pronta', classifica({ prototipoReal: true, temTsx: true, temCasosComUC: false, temScorecard: true }) !== 'pronta');

// 5. Integração das DUAS raízes de Pages. Este é o caso que falhava no corpus real:
// Superadmin e Officeimpresso vivem em Modules/<X>/Resources/js/Pages e sumiam da fila.
const fixtureRoot = mkdtempSync(join(tmpdir(), 'proto-readiness-'));
const put = (rel, body = '') => {
  const abs = join(fixtureRoot, rel);
  mkdirSync(join(abs, '..'), { recursive: true });
  writeFileSync(abs, body);
};
try {
  const charter = (component, prototype) => `---\ncomponent: ${component}\nrelated_prototype: ${prototype}\n---\n`;
  const casos = '## UC-REG-01 preserva comportamento\n';

  put('resources/js/Pages/Core/Index.tsx', 'export default function Index() {}\n');
  put('resources/js/Pages/Core/Index.charter.md', charter('resources/js/Pages/Core/Index.tsx', 'prototipo-ui/cowork/core-page.jsx'));
  put('resources/js/Pages/Core/Index.casos.md', casos);
  put('Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx', 'export default function Index() {}\n');
  put('Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.charter.md', charter('Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx', 'prototipo-ui/cowork/superadmin-page.jsx'));
  put('Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.casos.md', casos);
  put('Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx', 'export default function Index() {}\n');
  put('Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.charter.md', charter('Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx', 'prototipo-ui/cowork/officeimpresso-page.jsx'));
  put('Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.casos.md', casos);

  put('memory/governance/scorecards/screens/core-index.yaml', 'score: 90\n');
  put('memory/governance/scorecards/screens/superadmin-dashboard-index.yaml', 'score: 90\n');
  // Officeimpresso fica deliberadamente sem scorecard para provar que aparece como 1-ciclo.

  const rows = coleta(fixtureRoot);
  const byTela = new Map(rows.map((row) => [row.tela, row]));
  check('raiz core continua descoberta', byTela.get('Core/Index')?.modulo === 'core');
  check('raiz modular Superadmin é descoberta e fica pronta', byTela.get('superadmin/Dashboard/Index')?.modulo === 'Superadmin' && byTela.get('superadmin/Dashboard/Index')?.status === 'pronta');
  check('raiz modular Officeimpresso não some e acusa scorecard faltante', byTela.get('Officeimpresso/Logs/Index')?.modulo === 'Officeimpresso' && byTela.get('Officeimpresso/Logs/Index')?.status === '1-ciclo' && byTela.get('Officeimpresso/Logs/Index')?.falta.includes('scorecard'));
  check('arquivo físico modular é preservado na fila', byTela.get('superadmin/Dashboard/Index')?.arquivo.startsWith('Modules/Superadmin/'));
  check('fixture contém exatamente core + 2 módulos', rows.length === 3, `obtido=${rows.length}`);
} finally {
  rmSync(fixtureRoot, { recursive: true, force: true });
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato da prontidão preservado');
process.exit(fails ? 1 : 0);
