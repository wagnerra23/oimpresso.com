#!/usr/bin/env node
// Teste de regressao do deadlink-gate (integridade referencial doc↔doc, ADR 0256).
// Prova que o gate MORDE (link morto novo reprova) e que os controles-negativos
// passam (link valido, historia append-only, divida grandfathered pelo baseline).
// O RED original foi a medicao 2026-07-23: ~7,5% de links internos mortos no corpus
// vivo de memory/ sem NENHUM checker no CI.
//
// Hermetico: monta um repo temporario (memory/ + CLAUDE.md) e roda o gate como
// subprocesso com --root. Nao toca o corpus real.
//
// Rodar: node scripts/governance/deadlink-gate.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'deadlink-gate.mjs');

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK] ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

function run(root, ...args) {
  return spawnSync(process.execPath, [SCRIPT, '--root', root, ...args], { encoding: 'utf8' });
}

function makeRepo() {
  const root = mkdtempSync(join(tmpdir(), 'deadlink-'));
  mkdirSync(join(root, 'memory', 'decisions'), { recursive: true });
  mkdirSync(join(root, 'memory', 'handoffs'), { recursive: true });
  mkdirSync(join(root, 'governance'), { recursive: true });
  writeFileSync(join(root, 'CLAUDE.md'), '# primer\n');
  writeFileSync(join(root, 'memory', 'alvo-real.md'), '# alvo\n');
  return root;
}

// ── 1. MORDE: link morto em doc vivo, sem baseline → exit 1 ──────────────────
{
  const root = makeRepo();
  writeFileSync(join(root, 'memory', 'decisions', '0001-x.md'),
    'ver [morto](../nao-existe.md) e [vivo](../alvo-real.md)\n');
  const r = run(root, '--check');
  check('MORDE: link morto vivo sem baseline reprova (exit 1)', r.status === 1);
  check('MORDE: output nomeia o alvo morto', /nao-existe\.md/.test(r.stderr + r.stdout));
  rmSync(root, { recursive: true, force: true });
}

// ── 2. Controle-negativo: só links validos → exit 0 ──────────────────────────
{
  const root = makeRepo();
  writeFileSync(join(root, 'memory', 'decisions', '0001-x.md'),
    'ver [vivo](../alvo-real.md) e [externo](https://example.com) e [ancora](#secao) e [template](<Modulo>/SPEC.md)\n');
  const r = run(root, '--check');
  check('controle-negativo: corpus limpo passa (exit 0)', r.status === 0);
  rmSync(root, { recursive: true, force: true });
}

// ── 3. Historia append-only NUNCA enforça ────────────────────────────────────
{
  const root = makeRepo();
  writeFileSync(join(root, 'memory', 'handoffs', '2026-01-01-x.md'),
    'fossil aponta [morto](../purgado.md)\n');
  const r = run(root, '--check');
  check('historia: link morto em handoff NAO reprova (exit 0)', r.status === 0);
  const s = run(root, '--scan');
  check('historia: --scan ainda REPORTA o morto de historia', /HISTORIA: 1 /.test(s.stdout));
  rmSync(root, { recursive: true, force: true });
}

// ── 4. Ratchet: baseline grandfathers a divida; PIORAR reprova ───────────────
{
  const root = makeRepo();
  const adr = join(root, 'memory', 'decisions', '0002-y.md');
  writeFileSync(adr, 'divida antiga: [m1](../m1.md)\n');
  const w = run(root, '--write-baseline');
  check('baseline: --write-baseline grava (exit 0)', w.status === 0);
  const b = JSON.parse(readFileSync(join(root, 'governance', 'deadlink-baseline.json'), 'utf8'));
  check('baseline: registra 1 morto grandfathered', b.total_vivo === 1 && b.files['memory/decisions/0002-y.md'] === 1);

  const r1 = run(root, '--check');
  check('ratchet: divida grandfathered passa (exit 0)', r1.status === 0);

  writeFileSync(adr, 'divida antiga: [m1](../m1.md) e NOVA: [m2](../m2.md)\n');
  const r2 = run(root, '--check');
  check('ratchet MORDE: mesmo arquivo piorando (1→2) reprova', r2.status === 1);

  writeFileSync(join(root, 'memory', 'decisions', '0003-novo.md'), 'novo doc com [morto](../nada.md)\n');
  writeFileSync(adr, 'divida antiga: [m1](../m1.md)\n');
  const r3 = run(root, '--check');
  check('ratchet MORDE: arquivo NOVO com link morto reprova', r3.status === 1);
  rmSync(root, { recursive: true, force: true });
}

// ── 5. Case-sensitive (paridade CI Linux, mesmo no Windows) ──────────────────
{
  const root = makeRepo();
  // alvo real: memory/alvo-real.md — link com case errado deve ser MORTO
  writeFileSync(join(root, 'memory', 'decisions', '0004-case.md'),
    'case errado: [x](../Alvo-Real.md)\n');
  const r = run(root, '--check');
  check('case: link com case divergente do FS conta como morto (paridade Linux)', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}

// ── 6. Raiz do repo entra no corpus vivo ─────────────────────────────────────
{
  const root = makeRepo();
  writeFileSync(join(root, 'README.md'), 'porta global aponta [morto](memory/sumiu.md)\n');
  const r = run(root, '--check');
  check('raiz: README.md com link morto reprova (entrypoints cobertos)', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}

// ── 7. Tombstone-aware (ADR 0316 × 0347) ─────────────────────────────────────
// O par que destrava o esquecimento real: referência a ADR TOMBADA resolve pelo
// ledger e NÃO conta como morta; ADR que sumiu SEM lápide continua mordendo.
function writeTombstones(root, slugs) {
  writeFileSync(join(root, 'governance', 'adr-tombstones.json'),
    JSON.stringify({ tombstones: slugs.map((s, i) => ({ number: 900 + i, slug: s, died_reason: 'teste', superseded_by: '0999-sucessora', last_sha: 'deadbeef', forgotten_in_pr: 0 })) }, null, 2));
}
{
  // BOA: o alvo não existe no disco, mas está no ledger → passa
  const root = makeRepo();
  writeTombstones(root, ['0101-tests-business-id-1-nunca-cliente']);
  writeFileSync(join(root, 'memory', 'decisions', '0005-cita-tombada.md'),
    'ver [tombada](0101-tests-business-id-1-nunca-cliente.md)\n');
  const r = run(root, '--check');
  check('tombstone BOA: link pra ADR tombada NAO conta como morto (exit 0)', r.status === 0);
  check('tombstone BOA: a absorcao aparece no output (nao e silenciosa)', /ADR TOMBADA resolvidas pelo ledger/.test(r.stdout));
  rmSync(root, { recursive: true, force: true });
}
{
  // RUIM (controle-negativo decisivo): ADR ausente e NAO tombada → segue mordendo
  const root = makeRepo();
  writeTombstones(root, ['0101-tests-business-id-1-nunca-cliente']);
  writeFileSync(join(root, 'memory', 'decisions', '0006-cita-sumida.md'),
    'ver [sumiu sem lapide](0777-nunca-tombada.md)\n');
  const r = run(root, '--check');
  check('tombstone RUIM: ADR ausente SEM lapide segue reprovando (exit 1)', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}
{
  // Escopo: o ledger so vale dentro de memory/decisions/ — nao vira coringa global
  const root = makeRepo();
  writeTombstones(root, ['0101-tests-business-id-1-nunca-cliente']);
  writeFileSync(join(root, 'memory', 'decisions', '0007-fora-de-escopo.md'),
    'mesmo slug, outra pasta: [x](../0101-tests-business-id-1-nunca-cliente.md)\n');
  const r = run(root, '--check');
  check('tombstone ESCOPO: slug tombado fora de memory/decisions/ NAO resolve (exit 1)', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}
{
  // Sem ledger no repo: comportamento identico ao de antes (zero efeito colateral)
  const root = makeRepo();
  writeFileSync(join(root, 'memory', 'decisions', '0008-sem-ledger.md'),
    'ver [tombada](0101-tests-business-id-1-nunca-cliente.md)\n');
  const r = run(root, '--check');
  check('tombstone AUSENTE: sem ledger, link pra ADR inexistente segue morto (exit 1)', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}

// ── 8. Rename-aware (par do tombstone, 2026-07-30) ───────────────────────────
// Rename de pasta fabrica link morto em ADR aceita, que o append-only PROÍBE editar.
// Referência a path RENOMEADO resolve pelo rename-map classe A; rename que aponta pra
// lugar nenhum continua mordendo (é o caso que o gate tem que pegar).
function writeRenameMap(root, pairs) {
  const renames = {};
  for (const [from, to, cls] of pairs) renames[from] = { to, class: cls || 'A', evidence: ['teste'] };
  writeFileSync(join(root, 'governance', 'ghost-rename-map.json'), JSON.stringify({ renames }, null, 2));
}
{
  // RELEASE: alvo antigo não existe, mas o destino renomeado existe → resolve
  const root = makeRepo();
  writeRenameMap(root, [['ProjectMgmt', 'Forja']]);
  mkdirSync(join(root, 'memory', 'requisitos', 'Forja'), { recursive: true });
  writeFileSync(join(root, 'memory', 'requisitos', 'Forja', 'SPEC.md'), '# spec\n');
  writeFileSync(join(root, 'memory', 'decisions', '0010-cita-renomeado.md'),
    'ver [antigo](../requisitos/ProjectMgmt/SPEC.md)\n');
  const r = run(root, '--check');
  check('rename RELEASE: link pra path renomeado NAO conta como morto (exit 0)', r.status === 0);
  check('rename RELEASE: a absorcao aparece no output (nao e silenciosa)', /PATH RENOMEADO resolvidas pelo rename-map/.test(r.stdout));
  rmSync(root, { recursive: true, force: true });
}
{
  // BITE: rename mapeado, mas o destino NÃO existe → segue morto
  const root = makeRepo();
  writeRenameMap(root, [['ProjectMgmt', 'Forja']]);
  writeFileSync(join(root, 'memory', 'decisions', '0011-destino-inexistente.md'),
    'ver [antigo](../requisitos/ProjectMgmt/SPEC.md)\n');
  const r = run(root, '--check');
  check('rename BITE: destino renomeado inexistente segue morto (exit 1)', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}
{
  // BITE: classe != A (curadoria humana pendente) NAO resolve, mesmo com destino vivo
  const root = makeRepo();
  writeRenameMap(root, [['Project', 'Forja', 'AMBIGUO']]);
  mkdirSync(join(root, 'memory', 'requisitos', 'Forja'), { recursive: true });
  writeFileSync(join(root, 'memory', 'requisitos', 'Forja', 'SPEC.md'), '# spec\n');
  writeFileSync(join(root, 'memory', 'decisions', '0012-classe-ambigua.md'),
    'ver [antigo](../requisitos/Project/SPEC.md)\n');
  const r = run(root, '--check');
  check('rename BITE: classe AMBIGUO nao resolve (so classe A curada)', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}
{
  // BITE: match e por SEGMENTO de path, nao substring (ProjectMgmtLegado != ProjectMgmt)
  const root = makeRepo();
  writeRenameMap(root, [['ProjectMgmt', 'Forja']]);
  mkdirSync(join(root, 'memory', 'requisitos', 'Forja'), { recursive: true });
  writeFileSync(join(root, 'memory', 'requisitos', 'Forja', 'SPEC.md'), '# spec\n');
  writeFileSync(join(root, 'memory', 'decisions', '0013-substring.md'),
    'ver [antigo](../requisitos/ProjectMgmtLegado/SPEC.md)\n');
  const r = run(root, '--check');
  check('rename BITE: substring (ProjectMgmtLegado) nao resolve — so segmento inteiro', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}
{
  // Sem rename-map no repo: comportamento identico ao de antes (zero efeito colateral)
  const root = makeRepo();
  writeFileSync(join(root, 'memory', 'decisions', '0014-sem-map.md'),
    'ver [antigo](../requisitos/ProjectMgmt/SPEC.md)\n');
  const r = run(root, '--check');
  check('rename AUSENTE: sem rename-map, link segue morto (exit 1)', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}

// ── 9. Charters de tela no corpus vivo (2026-08-10) ──────────────────────────
// Charter é ARTEFATO DE CONTRATO e estava FORA do corpus (walkMd só via memory/ +
// raiz). FP medido antes de ligar: 209 charters, 501 links internos, 2 mortos — e os
// 2 são dívida real (path relativo mal formado; irmão declarado "parkeado"), zero
// falso-positivo. Os 2 entraram no baseline como grandfathered (forward-only).
function makeCharter(root, relDir, name, body) {
  const dir = join(root, 'resources', 'js', 'Pages', relDir);
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, name), body);
}
{
  // BITE: charter com link morto no corpo reprova
  const root = makeRepo();
  makeCharter(root, 'Jana', 'Index.charter.md', 'ver [morto](./Sumiu.charter.md)\n');
  const r = run(root, '--check');
  check('charter BITE: link morto em charter reprova (exit 1)', r.status === 1);
  check('charter BITE: output nomeia o charter culpado', /Jana\/Index\.charter\.md/.test(r.stderr + r.stdout));
  rmSync(root, { recursive: true, force: true });
}
{
  // Controle-negativo: charter com link VIVO nao acusa
  const root = makeRepo();
  makeCharter(root, 'Jana', 'Chat.charter.md', '# irmao vivo\n');
  makeCharter(root, 'Jana', 'Index.charter.md', 'ver [vivo](./Chat.charter.md)\n');
  const r = run(root, '--check');
  check('charter controle-negativo: link vivo entre charters passa (exit 0)', r.status === 0);
  rmSync(root, { recursive: true, force: true });
}
{
  // Controle-negativo de ESCOPO: .md sob Pages/ que NAO e .charter.md fica fora do
  // corpus. Impede que o walk vire coringa sobre todo markdown de resources/js/.
  const root = makeRepo();
  makeCharter(root, 'Jana', 'NOTAS.md', 'rascunho com [morto](./nada.md)\n');
  const r = run(root, '--check');
  check('charter ESCOPO: .md sob Pages/ que nao e .charter.md NAO entra (exit 0)', r.status === 0);
  rmSync(root, { recursive: true, force: true });
}
{
  // LIMITE MEDIDO (pina o escopo — nao e aspiracao): o gate casa markdown link
  // `[texto](alvo)`. Lista YAML de frontmatter (`related_charters:`) NAO e lida, logo
  // charter apontando pra irmao apagado por ali segue INVISIVEL a este gate. Provado
  // com controle positivo em 2026-08-10. Este check existe pra que ninguem leia
  // "charters no corpus" como "related_charters validado" — nao esta.
  const root = makeRepo();
  makeCharter(root, 'Jana', 'Index.charter.md',
    '---\nrelated_charters:\n  - resources/js/Pages/Jana/Apagado.charter.md\n---\n\n# corpo sem link markdown\n');
  const r = run(root, '--check');
  check('charter LIMITE: frontmatter related_charters NAO e validado por este gate (exit 0)', r.status === 0);
  rmSync(root, { recursive: true, force: true });
}

// ── MIGRATION que trocou de modulo dono (2026-08-13, ADR 0366 §D-C item 4) ───
// A migration mudou de casa preservando o NOME (a tabela `migrations` do Laravel casa por
// nome, e e por isso que ele serve de chave). ADR que a cita e append-only: sem isto,
// executar uma decisao de fronteira quebraria o gate por divida que a proibicoes.md manda
// NAO pagar editando ADR aceita. Os dois lados sao testados — absorver o que MUDOU DE CASA
// sem absorver o que SUMIU e o unico jeito do mecanismo nao virar cobertura falsa.
{
  const root = makeRepo();
  mkdirSync(join(root, 'Modules', 'Forja', 'Database', 'Migrations'), { recursive: true });
  writeFileSync(join(root, 'Modules', 'Forja', 'Database', 'Migrations', '2026_01_01_000001_create_mcp_x_table.php'), '<?php\n');
  writeFileSync(join(root, 'memory', 'decisions', '0002-migration.md'),
    'cita o path ANTIGO: [m](../../Modules/Jana/Database/Migrations/2026_01_01_000001_create_mcp_x_table.php)\n');
  const r = run(root, '--check');
  check('CN: migration que trocou de modulo (mesmo nome) RESOLVE — exit 0', r.status === 0);
  check('CN: e o relatorio DIZ quantas absorveu (mecanismo mudo = cobertura falsa)',
    /1 referência\(s\) a MIGRATION que trocou de módulo dono/.test(r.stdout));
  rmSync(root, { recursive: true, force: true });
}
{
  const root = makeRepo();
  mkdirSync(join(root, 'Modules', 'Forja', 'Database', 'Migrations'), { recursive: true });
  writeFileSync(join(root, 'memory', 'decisions', '0003-deletada.md'),
    'migration DELETADA: [m](../../Modules/Jana/Database/Migrations/2026_01_01_000009_nunca_existiu.php)\n');
  const r = run(root, '--check');
  check('MORDE: migration que NAO existe sob modulo nenhum continua MORTA — exit 1', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}
{
  const root = makeRepo();
  mkdirSync(join(root, 'Modules', 'Forja', 'Database', 'Seeders'), { recursive: true });
  writeFileSync(join(root, 'Modules', 'Forja', 'Database', 'Seeders', 'XSeeder.php'), '<?php\n');
  writeFileSync(join(root, 'memory', 'decisions', '0004-seeder.md'),
    'NAO e migration: [s](../../Modules/Jana/Database/Seeders/XSeeder.php)\n');
  const r = run(root, '--check');
  check('MORDE: o padrao e EXATO — Database/Seeders/ nao entra pela porta das migrations', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}

console.log('');
if (fails > 0) { console.error(`${fails} check(s) falharam`); process.exit(1); }
console.log('deadlink-gate.test: todos os checks passaram');
