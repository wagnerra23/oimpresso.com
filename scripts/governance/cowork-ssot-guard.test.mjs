#!/usr/bin/env node
// cowork-ssot-guard.test.mjs — bite-test do guard de topologia do protótipo.
//
// POR QUE EXISTE (2026-09-13): a R3 mudou de significado por decisão [W] — `.md` deixou de ser
// proibido em `cowork/<dono>/` e passou a ser aceito com a árvore do Cowork. Mudança de regra em
// gate required sem bite-test é promessa, e promessa não testada apodrece calada (§5 2026-07-30).
//
// POR QUE PELO CLI, E NÃO IMPORTANDO A FUNÇÃO: o guard EXECUTA no import (ele varre o cwd e chama
// `process.exit` no fim), então `import { violaR3 }` rodaria o guard inteiro e mataria o processo
// do teste. Assert sobre helper puro exportado não prova pipeline (§5 2026-07-30) — aqui o teste
// monta uma árvore de mentira num tmpdir e roda o guard COM AQUELE cwd, que é o caminho real.
//
// Uso: node scripts/governance/cowork-ssot-guard.test.mjs

import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

const GUARD = join(dirname(fileURLToPath(import.meta.url)), 'cowork-ssot-guard.mjs');
let falhas = 0;
function check(nome, cond, extra = '') {
  if (cond) { console.log(`  [OK]   ${nome}`); return; }
  falhas++; console.log(`  [FAIL] ${nome}${extra ? ' → ' + extra : ''}`);
}

/** monta um repo de mentira e roda o guard com aquele cwd. Devolve {code, errors[]}. */
function rodar(arquivos) {
  const raiz = mkdtempSync(join(tmpdir(), 'ssot-guard-'));
  for (const [rel, conteudo] of Object.entries(arquivos)) {
    const abs = join(raiz, rel);
    mkdirSync(dirname(abs), { recursive: true });
    writeFileSync(abs, conteudo);
  }
  let out = '';
  let code = 0;
  try {
    out = execFileSync(process.execPath, [GUARD, '--json'], { cwd: raiz, encoding: 'utf8' });
  } catch (e) { out = e.stdout || ''; code = e.status ?? 1; }
  let errors = [];
  try { errors = JSON.parse(out).errors || []; } catch { errors = ['<json ilegível>: ' + out.slice(0, 200)]; }
  return { code, errors };
}
const soR3 = (r) => r.errors.filter((e) => e.startsWith('R3'));

console.log('=== R3 · o .md do Cowork pousa com a árvore dele (decisão [W] 2026-09-13) ===');
{
  // O caso que motivou a mudança: a ordem de serviço do Cowork, aninhada, com irmão de build
  // na MESMA pasta. Sob a redação antiga (`handoffs/[^/]+\.md$`) isto era 2 violações.
  const r = rodar({
    'prototipo-ui/cowork/Wagner/cowork-inbox/ancora/playbook/00-INDICE.md': '# indice\n',
    'prototipo-ui/cowork/Wagner/cowork-inbox/ancora/playbook/01-thread.md': '# thread\n',
    'prototipo-ui/cowork/Wagner/cowork-inbox/ancora/ds-anchor-check.mjs': 'export const x=1\n',
    'prototipo-ui/design-system/tokens.css': ':root{--a:1}\n',
  });
  check('BITE: .md aninhado sob cowork/<dono>/ NÃO viola R3', soR3(r).length === 0, soR3(r).join(' | '));
  check('BITE: o guard sai 0 com a árvore do Cowork inteira', r.code === 0, r.errors.join(' | '));
}
{
  // CONTROLE NEGATIVO — sem ele, uma R3 que aceitasse tudo passaria no bite acima.
  // `.md` solto em `cowork/` não tem dono: continua proibido (é o que sobrou da regra).
  const r = rodar({
    'prototipo-ui/cowork/LEIAME.md': '# solto\n',
    'prototipo-ui/design-system/tokens.css': ':root{--a:1}\n',
  });
  check('CONTROLE: .md solto em cowork/ (sem dono) SEGUE violando R3', soR3(r).length === 1, r.errors.join(' | '));
  check('CONTROLE: e o guard reprova', r.code !== 0);
}
{
  // CONTROLE NEGATIVO 2 — dono inventado não vira canal de documentação por escrever .md nele.
  const r = rodar({
    'prototipo-ui/cowork/Terceiro/nota.md': '# nao\n',
    'prototipo-ui/design-system/tokens.css': ':root{--a:1}\n',
  });
  check('CONTROLE: dono não declarado é recusado (R2)', r.errors.some((e) => e.startsWith('R2')), r.errors.join(' | '));
  check('CONTROLE: e o .md dele também não passa na R3', soR3(r).length === 1, r.errors.join(' | '));
}

console.log('\n=== R4 · unicidade por dono, contas independentes e DS canônico ===');
{
  const r = rodar({
    'prototipo-ui/cowork/Wagner/page.jsx': 'mesma fonte\n',
    'prototipo-ui/cowork/Felipe/page.jsx': 'mesma fonte\n',
  });
  check('RELEASE: bytes iguais em contas diferentes são permitidos', r.code === 0, r.errors.join(' | '));
  const ds = rodar({
    'prototipo-ui/cowork/Wagner/tokens.css': 'mesmo DS\n',
    'prototipo-ui/design-system/tokens.css': 'mesmo DS\n',
  });
  check('BITE: namespace de conta não permite duplicar DS canônico', ds.errors.some((e) => e.startsWith('R4')));
  const internal = rodar({
    'prototipo-ui/cowork/Wagner/a.md': 'mesmo\n',
    'prototipo-ui/cowork/Wagner/b.md': 'mesmo\n',
    'prototipo-ui/cowork/Felipe/a.md': 'mesmo\n',
  });
  check('BITE: outra conta não encobre duplicata interna', internal.errors.some((e) => e.startsWith('R4')));
}
{
  // Medido em 2026-09-13 no pacote real: `cowork-inbox/sidebar/playbook/` e
  // `entrega-sidebar-code/playbook/` trazem 10 pares byte-idênticos. Duplicata na FONTE não
  // pode virar duplicata no espelho — senão a âncora fica ambígua e o import "move" arquivos.
  const mesmo = '# mesma coisa\n';
  const r = rodar({
    'prototipo-ui/cowork/Wagner/cowork-inbox/sidebar/playbook/01.md': mesmo,
    'prototipo-ui/cowork/Wagner/entrega-sidebar-code/playbook/01.md': mesmo,
    'prototipo-ui/design-system/tokens.css': ':root{--a:1}\n',
  });
  check('BITE: R4 morde o par byte-idêntico que o próprio pacote traz',
    r.errors.some((e) => e.startsWith('R4')), r.errors.join(' | '));
  check('BITE: e a R3 não reclama deles (o defeito é duplicata, não localização)',
    soR3(r).length === 0, soR3(r).join(' | '));
}

console.log('\n=== R1 · a raiz do protótipo só aceita as duas áreas (e decide pelo DISCO) ===');
{
  // POR QUE ESTE CASO NASCEU EM 2026-09-14: a R1 não tinha bite-test NENHUM, e por isso um
  // conflito real viveu invisível. `design:ingest-zip` criava `prototipo-ui/_incoming/<tela>/`;
  // como a R1 decide por `readdirSync` (o disco, não o índice do git), o guard passava a
  // reprovar na máquina de quem rodava o comando — inclusive pelo painel do protocolo, que o
  // roda localmente. O CI nunca via: a pasta era gitignored e o checkout não a traz. O staging
  // foi para `storage/app/design-incoming/`; a R1 continua dura, e agora com quem a prove.
  const r = rodar({
    'prototipo-ui/_incoming/vendas/handoff.jsx': 'export const x=1\n',
    'prototipo-ui/cowork/Wagner/venda-page.jsx': 'export const y=1\n',
    'prototipo-ui/design-system/tokens.css': ':root{--a:1}\n',
  });
  check('BITE: pasta de trabalho na raiz de prototipo-ui/ viola R1',
    r.errors.some((e) => e.startsWith('R1') && e.includes('_incoming')), r.errors.join(' | '));
  check('BITE: e o guard reprova', r.code !== 0);
}
{
  // CONTROLE NEGATIVO — sem ele, uma R1 que aceitasse qualquer coisa passaria no bite acima.
  const r = rodar({
    'prototipo-ui/cowork/Wagner/venda-page.jsx': 'export const y=1\n',
    'prototipo-ui/design-system/tokens.css': ':root{--a:1}\n',
  });
  check('CONTROLE: raiz só com cowork/ + design-system/ não gera R1',
    r.errors.filter((e) => e.startsWith('R1')).length === 0, r.errors.join(' | '));
  check('CONTROLE: e o guard sai 0', r.code === 0, r.errors.join(' | '));
}

console.log(falhas ? `\n✗ ${falhas} falha(s)` : '\n✓ OK');
process.exit(falhas ? 1 : 0);
