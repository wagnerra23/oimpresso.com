#!/usr/bin/env node
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { attachSmartAnchorCheck, buildFlowReport, extractUsAnchor, smartAnchorDocs } from './sdd-flow.mjs';

let fails = 0;
const check = (name, condition, extra = '') => {
  console.log(`${condition ? '[OK]' : '[FAIL]'} ${name}${condition ? '' : ` -> ${extra}`}`);
  if (!condition) fails++;
};

const root = mkdtempSync(join(tmpdir(), 'sdd-flow-'));
const feature = join(root, 'memory', 'requisitos', 'Mod', 'features', 'fluxo-integrado');
const page = join(root, 'resources', 'js', 'Pages', 'Mod', 'Index');
mkdirSync(feature, { recursive: true });
mkdirSync(join(root, 'resources', 'js', 'Pages', 'Mod'), { recursive: true });

const spec = (anchor) => `# SPEC\n\n### US-MOD-001 · Fluxo integrado\n\n**Implementado em:** ${anchor}\n`;
writeFileSync(join(root, 'memory', 'requisitos', 'Mod', 'SPEC.md'), spec('_pendente_'));
writeFileSync(join(root, 'memory', 'requisitos', 'Mod', 'SDD-dominio-v1.md'), `---\ntype: sdd\nmodule: Mod\n---\n# SDD\n\n#### CU-MOD-01 — Fluxo seguro\n`);
writeFileSync(`${page}.tsx`, 'export default function Index() { return null; }\n');
writeFileSync(`${page}.charter.md`, '---\npage: /mod\ncomponent: resources/js/Pages/Mod/Index.tsx\nstatus: live\n---\n# Charter\n');
writeFileSync(`${page}.casos.md`, '---\nowner: wagner\nsdd: memory/requisitos/Mod/SDD-dominio-v1.md\n---\n# Casos\n');
writeFileSync(join(feature, 'requirements.md'), `---\nfeature: fluxo-integrado\nmodule: Mod\nus: ["US-MOD-001"]\nsdd: ["memory/requisitos/Mod/SDD-dominio-v1.md"]\nrelated_cus: ["CU-MOD-01"]\nscreens: ["resources/js/Pages/Mod/Index.tsx"]\ncreated: "2026-08-03"\n---\n# Requirements\n- **AC-1** — O SISTEMA DEVE integrar.\n`);
writeFileSync(join(feature, 'plan.md'), '# Plan\n');
writeFileSync(join(feature, 'tasks.md'), `# Tasks\n\n### T-01 · Implementar\n> blocked_by: — · covers: AC-1 · us: US-MOD-001\n\n**DoD:** teste.\n\n### T-02 · Fechar o loop — âncora da US + smoke real\n> blocked_by: T-01 · covers: AC-1 · us: US-MOD-001\n\nAtualizar **Implementado em:** e rodar anchor-lint.mjs após smoke real.\n\n**DoD:** recibo.\n`);

const planned = buildFlowReport(root, 'Mod/fluxo-integrado');
check('cadeia integra SPEC, feature, SDD/CU e tela', planned.feature.errors.length === 0 && planned.sdds.length === 1 && planned.screens[0]?.sdd_coerente, JSON.stringify(planned));
check('ancora pendente mantem etapa planejada', planned.stage === 'planejado' && planned.receipt_blockers.some((x) => x.includes('ancora-US-MOD-001-pendente')));
check('task final fecha loop estrutural', planned.feature.close_loop.ok);
const hashDocs = smartAnchorDocs(planned);
check('hash fica nos docs exclusivos da feature/tela',
  hashDocs.includes('memory/requisitos/Mod/features/fluxo-integrado/tasks.md')
    && hashDocs.includes('resources/js/Pages/Mod/Index.casos.md')
    && !hashDocs.includes('memory/requisitos/Mod/SPEC.md')
    && !hashDocs.includes('memory/requisitos/Mod/SDD-dominio-v1.md'),
  JSON.stringify(hashDocs));

writeFileSync(join(root, 'memory', 'requisitos', 'Mod', 'SPEC.md'), spec('`Modules/Mod/X.php` · verificado@abcdef1 (2026-08-05)'));
const delivered = buildFlowReport(root, 'Mod/fluxo-integrado');
check('ancora verificada posterior fecha recibo estrutural', delivered.stage === 'rastreabilidade-estrutural-fechada' && delivered.receipt_blockers.length === 0, JSON.stringify(delivered.receipt_blockers));

writeFileSync(join(root, 'memory', 'requisitos', 'Mod', 'SPEC.md'), spec('`Modules/Mod/X.php` · verificado@abcdef1 (2026-08-02)'));
const staleAnchor = buildFlowReport(root, 'Mod/fluxo-integrado');
check('ancora anterior ao trio nao prova a feature nova', staleAnchor.receipt_blockers.some((x) => x.includes('anterior-a-feature')));
writeFileSync(join(root, 'memory', 'requisitos', 'Mod', 'SPEC.md'), spec('`Modules/Mod/X.php` · verificado@abcdef1 (2026-08-05)'));

writeFileSync(join(root, 'memory', 'requisitos', 'Mod', 'SDD-dominio-v1.md'), '---\ntype: sdd\nmodule: Mod\n---\n# SDD sem CU\n');
const broken = buildFlowReport(root, 'Mod/fluxo-integrado');
check('CU declarado que nao existe no SDD morde', broken.feature.errors.some((issue) => issue.code === 'cu-fora-do-sdd'));

check('extrator nao confunde US-MOD-001 com prefixo 0010', extractUsAnchor('### US-MOD-0010 · outra\n**Implementado em:** _pendente_\n', 'US-MOD-001').state === 'us-ausente');

// Integracao real dos dois mecanismos de hash existentes: anchor-lint --stale na US
// e ancora-codigo-sync na ref arquivo:linha. O contrafactual altera o codigo e ambos mordem.
mkdirSync(join(root, 'Modules', 'Mod'), { recursive: true });
const source = join(root, 'Modules', 'Mod', 'X.php');
writeFileSync(source, '<?php final class X {}\n');
writeFileSync(join(root, 'memory', 'requisitos', 'Mod', 'SDD-dominio-v1.md'), `---\ntype: sdd\nmodule: Mod\n---\n# SDD\n\n#### CU-MOD-01 — Fluxo seguro\n`);
const G = (...args) => execFileSync('git', ['-C', root, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
G('init', '-q');
G('config', 'user.email', 'sdd-flow@test');
G('config', 'user.name', 'sdd-flow-test');
G('add', '-A');
G('commit', '-qm', 'base');
const sha = G('rev-parse', '--short=7', 'HEAD').trim();
writeFileSync(join(root, 'memory', 'requisitos', 'Mod', 'SPEC.md'), spec(`\`Modules/Mod/X.php\` · verificado@${sha} (2026-08-05)`));
writeFileSync(join(feature, 'plan.md'), `# Plan\n\nPlug-point: Modules/Mod/X.php:1 (verificado@${sha})\n`);
G('add', '-A');
G('commit', '-qm', 'liga hashes');

const hashesOk = attachSmartAnchorCheck(root, buildFlowReport(root, 'Mod/fluxo-integrado'));
check('composicao dos hashes libera cadeia comprovada',
  hashesOk.spec_hash.ok && hashesOk.smart_anchors.ok && hashesOk.receipt_blockers.length === 0,
  JSON.stringify({ spec: hashesOk.spec_hash, refs: hashesOk.smart_anchors, blockers: hashesOk.receipt_blockers }));

writeFileSync(source, '<?php final class X { public function mudou(): void {} }\n');
G('add', '-A');
G('commit', '-qm', 'drift de codigo');
const hashesDrift = attachSmartAnchorCheck(root, buildFlowReport(root, 'Mod/fluxo-integrado'));
check('drift de codigo morde US e ref por linha',
  hashesDrift.spec_hash.states.some((entry) => entry.state === 'stale')
    && !hashesDrift.smart_anchors.ok
    && hashesDrift.receipt_blockers.includes('smart-anchor-git-sha-invalida'),
  JSON.stringify({ spec: hashesDrift.spec_hash, refs: hashesDrift.smart_anchors, blockers: hashesDrift.receipt_blockers }));

rmSync(root, { recursive: true, force: true });
console.log(fails ? `\n${fails} FALHA(S)` : '\nSELFTEST OK — cadeia SDD integrada e falsificavel.');
process.exit(fails ? 1 : 0);
