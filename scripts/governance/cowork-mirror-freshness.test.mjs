#!/usr/bin/env node
// Self-test cowork-mirror-freshness v2 — prova a classificação vs o CONTRATO, não vs a
// implementação. Contrato ancorado em: INDEX-DESIGN-MEMORIAS §0.2 (identidade sob normalização
// canônica; "diffar antes de concluir") · ADR 0315 (DesignSync: leitura livre, escrita gateada) ·
// session 2026-07-06-ancora-podre (adversário: basename colide · CRLF dá STALE falso) ·
// arte 2026-07-06-arte-design-code-sync-frescor (hash(normalizado) por PATH COMPLETO).
// Os asserts de EOL/BOM e colisão-por-path existem porque a v1 NÃO os tinha e morreu por isso.
// Roda: node scripts/governance/cowork-mirror-freshness.test.mjs
import { readFileSync, mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  normalize,
  contentHash,
  classifyMirror,
  verdictFor,
  shouldFail,
  buildManifest,
  ledgerEntry,
  slaVerdict,
  SLA_DAYS,
} from './cowork-mirror-freshness.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  → ' + extra}`); if (!c) fails++; };

// 1. NORMALIZAÇÃO — o furo #2 do adversário (CRLF dava STALE falso; agora é contrato).
check('CRLF == LF (o falso-STALE da v1 morreu)', contentHash('a\r\nb\r\n') === contentHash('a\nb\n'));
check('CR solto == LF', contentHash('a\rb\r') === contentHash('a\nb\n'));
check('BOM é ignorado na identidade', contentHash('﻿abc\n') === contentHash('abc\n'));
check('trailing newlines colapsam pra 1', contentHash('abc\n\n\n') === contentHash('abc\n'));
check('sem trailing newline == com 1 (canônico)', contentHash('abc') === contentHash('abc\n'));
check('conteúdo REALMENTE diferente → hash difere', contentHash('abc\n') !== contentHash('abd\n'));
check('normalize é idempotente', normalize(normalize('﻿a\r\nb\n\n')) === normalize('﻿a\r\nb\n\n'));
check('string vazia permanece vazia', normalize('') === '');
check('contentHash(Buffer) === contentHash(string) pro mesmo utf8', contentHash(Buffer.from('áé\r\n')) === contentHash('áé\r\n'));
check('hash é sha256 (64 hex)', /^[0-9a-f]{64}$/.test(contentHash('x')));

// 2. classifyMirror — o coração (3 vias).
const H1 = contentHash('design v1'), H2 = contentHash('design v2');
check('hash iguais → SYNC (espelho acompanha o vivo · §0.2)', classifyMirror({ repoHash: H1, liveHash: H1 }) === 'SYNC');
check('hash diferem → STALE (o vivo avançou, espelho ficou)', classifyMirror({ repoHash: H1, liveHash: H2 }) === 'STALE');
check('vivo null → LIVE-ABSENT, NÃO stale (rename/delete ≠ divergência)', classifyMirror({ repoHash: H1, liveHash: null }) === 'LIVE-ABSENT');
check('vivo "" → LIVE-ABSENT (ausência explícita)', classifyMirror({ repoHash: H1, liveHash: '' }) === 'LIVE-ABSENT');

// 3. verdictFor — "não buscado" ≠ "buscado e ausente"; e sem fantasma de prototype (furo #6).
check('chave fora do snapshot → UNCHECKED (nunca SYNC no silêncio)', verdictFor('x.jsx', H1, {}) === 'UNCHECKED');
check('presente e igual → SYNC', verdictFor('x.jsx', H1, { 'x.jsx': H1 }) === 'SYNC');
check('presente e diferente → STALE', verdictFor('x.jsx', H1, { 'x.jsx': H2 }) === 'STALE');
check('presente e null → LIVE-ABSENT', verdictFor('x.jsx', H1, { 'x.jsx': null }) === 'LIVE-ABSENT');
check('path homônimo de membro do prototype (toString) → UNCHECKED, não lixo', verdictFor('toString', H1, {}) === 'UNCHECKED');
check('constructor idem', verdictFor('constructor', H1, {}) === 'UNCHECKED');

// 4. shouldFail (--check) — SÓ STALE morde.
check('STALE presente → morde', shouldFail(['SYNC', 'STALE', 'UNCHECKED']) === true);
check('só SYNC → libera', shouldFail(['SYNC', 'SYNC']) === false);
check('UNCHECKED/LIVE-ABSENT sozinhos → NÃO morde (warn, não podre)', shouldFail(['UNCHECKED', 'LIVE-ABSENT', 'SYNC']) === false);

// 5. READ-ONLY por contrato (ADR 0315 Eixo B): o fonte não pode invocar método de ESCRITA do
//    DesignSync. Denylist = métodos de escrita reais do schema da tool (validados na sessão
//    2026-07-06: register_assets/unregister_assets EXISTEM no schema — o adversário errou aí).
{
  const HERE = dirname(fileURLToPath(import.meta.url));
  const src = readFileSync(join(HERE, 'cowork-mirror-freshness.mjs'), 'utf8');
  const writeMethods = ['finalize_plan', 'write_files', 'delete_files', 'create_project', 'register_assets', 'unregister_assets'];
  const embedded = writeMethods.filter((w) => src.includes(`${w}(`) || src.includes(`method: '${w}'`) || src.includes(`"${w}"`));
  check('fonte não invoca método de ESCRITA do DesignSync (só leitura — 0315)', embedded.length === 0, `achou: ${embedded.join(', ')}`);
}

// 6. buildManifest — IDENTIDADE POR PATH COMPLETO (o furo #1 do adversário, agora contrato).
{
  const dir = mkdtempSync(join(tmpdir(), 'mirror-fresh-'));
  try {
    const pages = join(dir, 'resources', 'js', 'Pages');
    const cowork = join(dir, 'prototipo-ui', 'cowork');
    mkdirSync(join(cowork, 'a'), { recursive: true });
    mkdirSync(join(cowork, 'b'), { recursive: true });
    // COLISÃO: mesmo basename, subdirs diferentes, CONTEÚDO diferente — v1 colapsava, v2 não pode.
    writeFileSync(join(cowork, 'a', 'x-page.jsx'), 'export const A = 1;\n');
    writeFileSync(join(cowork, 'b', 'x-page.jsx'), 'export const B = 2;\n');
    writeFileSync(join(cowork, 'raiz-page.jsx'), 'export const R = 0;\n');
    const mk = (mod, anchor) => {
      mkdirSync(join(pages, mod), { recursive: true });
      writeFileSync(join(pages, mod, 'Tela.charter.md'), `related_prototype: ${anchor}\n`);
    };
    mk('ModA', 'prototipo-ui/cowork/a/x-page.jsx');
    mk('ModB', 'prototipo-ui/cowork/b/x-page.jsx');
    mk('ModR', 'raiz-page.jsx'); // nome solto resolve na raiz (compat v1)
    mk('ModProsa', 'prototipo Cowork "payment-gateway-ui" F1+F1.5'); // prosa → pulada
    mk('ModMiss', 'sumiu.jsx'); // MISSING → fora do manifesto (território do anchor-content)

    const man = buildManifest(dir);
    check('homônimos em subdirs = 2 ENTRADAS separadas (não colapsa)', man.filter((m) => m.cowork.endsWith('x-page.jsx')).length === 2,
      `tem: ${man.map((m) => m.cowork).join(',')}`);
    const a = man.find((m) => m.cowork === 'a/x-page.jsx');
    const b = man.find((m) => m.cowork === 'b/x-page.jsx');
    check('chave é o PATH RELATIVO completo', !!a && !!b);
    check('homônimos têm hash DIFERENTE (conteúdo difere)', a && b && a.repoHash !== b.repoHash);
    check('cada tela ancora no path certo', a?.telas.includes('ModA/Tela') && b?.telas.includes('ModB/Tela'));
    check('nome solto resolve na raiz', man.some((m) => m.cowork === 'raiz-page.jsx' && m.telas.includes('ModR/Tela')));
    check('prosa e MISSING ficam fora (escopo == anchor-content)', !man.some((m) => /payment|sumiu/.test(m.cowork)));
    check('repoHash = contentHash(bytes do arquivo)', a?.repoHash === contentHash(readFileSync(join(cowork, 'a', 'x-page.jsx'))));
    // CRLF no disco ≠ LF no vivo → MESMO hash (identidade normalizada de ponta a ponta)
    writeFileSync(join(cowork, 'a', 'x-page.jsx'), 'export const A = 1;\r\n');
    const man2 = buildManifest(dir);
    check('arquivo CRLF no repo == vivo LF → SYNC (não falso-STALE)',
      verdictFor('a/x-page.jsx', man2.find((m) => m.cowork === 'a/x-page.jsx').repoHash, { 'a/x-page.jsx': contentHash('export const A = 1;\n') }) === 'SYNC');
    // --all inclui subdirs por path relativo (v1 sumia com homônimos no --all)
    const all = buildManifest(dir, { all: true });
    check('--all enumera por path relativo (2 homônimos presentes)', all.filter((m) => m.cowork.endsWith('x-page.jsx')).length === 2);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

// 7. CLI: snapshot inexistente/malformado → exit 2 limpo (não stack não-capturada) (furo #5).
{
  const HERE = dirname(fileURLToPath(import.meta.url));
  const script = join(HERE, 'cowork-mirror-freshness.mjs');
  const run = (args) => { try { execFileSync(process.execPath, [script, ...args], { cwd: dirname(dirname(HERE)), stdio: 'pipe' }); return 0; } catch (e) { return e.status ?? -1; } };
  check('--compare sem snapshot → exit 2', run(['--compare', join(tmpdir(), 'nao-existe.json')]) === 2);
  const bad = join(tmpdir(), `mf-bad-${process.pid}.json`);
  writeFileSync(bad, '{ nao é json');
  check('--compare snapshot malformado → exit 2 (JSON.parse capturado)', run(['--compare', bad]) === 2);
  rmSync(bad, { force: true });
}

// 8. LEDGER + SLA — o CI headless mede CADÊNCIA da rotina, nunca frescor (honestidade do
//    split: dispatch logado mede frescor; --sla mede se o dispatch anda rodando).
{
  const rows = [
    { cowork: 'a.jsx', veredito: 'SYNC' },
    { cowork: 'b.jsx', veredito: 'STALE' },
    { cowork: 'c.jsx', veredito: 'UNCHECKED' },
  ];
  const e = ledgerEntry(rows, '2026-07-06T12:00:00.000Z');
  check('ledgerEntry conta por veredito', e.files === 3 && e.sync === 1 && e.stale === 1 && e.unchecked === 1 && e.liveAbsent === 0);
  check('ledgerEntry lista os STALE por path', e.staleList.length === 1 && e.staleList[0] === 'b.jsx');
  check('ledgerEntry carrega a data da rodada', e.date === '2026-07-06T12:00:00.000Z');

  const NOW = '2026-07-06T12:00:00.000Z';
  const clean = { date: '2026-07-01T12:00:00.000Z', stale: 0, sync: 3, unchecked: 0 };
  const dirty = { date: '2026-07-01T12:00:00.000Z', stale: 2, sync: 1, unchecked: 0, staleList: ['x.jsx', 'y.jsx'] };
  const old = { date: '2026-06-01T12:00:00.000Z', stale: 0, sync: 3, unchecked: 0 };
  check('ledger vazio → NEVER-RAN (rotina nunca rodou ≠ tudo bem)', slaVerdict([], NOW).veredito === 'NEVER-RAN');
  check('rodada recente e limpa → FRESH', slaVerdict([clean], NOW).veredito === 'FRESH');
  check('rodada além do SLA → OVERDUE', slaVerdict([old], NOW).veredito === 'OVERDUE');
  check('última rodada com STALE → LAST-STALE (resultado sujo não some no tempo)', slaVerdict([dirty], NOW).veredito === 'LAST-STALE');
  check('rodada nova limpa APÓS suja → FRESH (só a última conta)', slaVerdict([dirty, clean], NOW).veredito === 'FRESH');
  check('ageDays calculado', slaVerdict([clean], NOW).ageDays === 5);
  check('fronteira: exatamente SLA_DAYS não é OVERDUE', slaVerdict([{ ...clean, date: '2026-06-22T12:00:00.000Z' }], NOW, SLA_DAYS).veredito === 'FRESH');
  check(`SLA_DAYS = 14`, SLA_DAYS === 14);
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato v2 do comparador de frescor preservado (path completo + hash normalizado + ledger/SLA)');
process.exit(fails ? 1 : 0);
