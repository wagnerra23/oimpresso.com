// Camada META — teste FÍSICO (caixa-preta) do coletor casos:results (Salto #2 / G-7).
//
// ⚠️ CONVENÇÃO (US-GOV-031) — NUNCA use um UC-id REAL (declarado em qualquer *.casos.md) em
//    fixture OU comentário deste arquivo. Mesmo sendo meta-test do COLETOR (não do guard), o
//    conteúdo deste *.spec.ts entra no testCorpus do casos-coverage-guard.mjs; um UC-id real
//    aqui vira COBERTURA-FANTASMA no G-2 (conta como coberto sem teste de verdade). Use ids
//    fictícios UC-ZZx — prefixo "ZZ"+1 letra (≤3 letras p/ casar UC_RE `UC-[A-Z]{0,3}\d`) e
//    SEM hífen interno. Ex.: UC-ZZA01, UC-ZZF01.
//
// Roda o .mjs real contra fixtures JUnit num dir temporário e exige a agregação por-UC:
// pass/fail/skip, pior-caso vence (fail domina; pass vence skip), merge de múltiplos
// relatórios, ran_at do timestamp, e a segurança "rodada vazia não apaga manifesto".
//
// Cobre: scripts/casos-results-collect.mjs
// Refs: ADR 0264 (G-7) · padrão casosGuard.spec.ts / dominioGuard.spec.ts.

import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, rmSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';

const REPO = process.cwd();
const COLLECT = resolve(REPO, 'scripts/casos-results-collect.mjs');
const MANIFEST = 'scripts/casos-test-results.json';

let tmp: string;

const write = (rel: string, content: string) => {
  const full = join(tmp, rel);
  mkdirSync(dirname(full), { recursive: true });
  writeFileSync(full, content);
};
const junit = (file: string, body: string) =>
  write(`test-results/${file}`, `<?xml version="1.0"?>\n<testsuites>\n${body}\n</testsuites>`);
const suite = (cases: string, timestamp = '2026-06-09T10:00:00') =>
  `<testsuite name="s" timestamp="${timestamp}">${cases}</testsuite>`;
const tcPass = (name: string) => `<testcase name="${name}"/>`;
const tcFail = (name: string) => `<testcase name="${name}"><failure>x</failure></testcase>`;
const tcSkip = (name: string) => `<testcase name="${name}"><skipped/></testcase>`;

const run = (args = '') =>
  execSync(`node "${COLLECT}" ${args} 2>&1`, { cwd: tmp, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
const readManifest = () => JSON.parse(readFileSync(join(tmp, MANIFEST), 'utf8'));

beforeEach(() => {
  tmp = mkdtempSync(join(tmpdir(), 'casos-results-'));
  mkdirSync(join(tmp, 'scripts'), { recursive: true });
});
afterEach(() => rmSync(tmp, { recursive: true, force: true }));

describe('casos:results — coletor de test-results → manifesto por-UC (físico)', () => {
  it('AGREGA pass/fail/skip do nome do <testcase>', () => {
    junit('a.xml', suite(tcPass('UC-ZZA01 · ok') + tcFail('UC-ZZB02 · ruim') + tcSkip('UC-ZZD06 · pulado')));
    run();
    const m = readManifest();
    expect(m.ucs['UC-ZZA01'].verdict).toBe('pass');
    expect(m.ucs['UC-ZZB02'].verdict).toBe('fail');
    expect(m.ucs['UC-ZZD06'].verdict).toBe('skip');
    expect(m.ucs['UC-ZZA01'].ran_at).toBe('2026-06-09');
  });

  it('PIOR-CASO: UC com pass E fail (testcases distintos) → fail domina', () => {
    junit('a.xml', suite(tcPass('UC-ZZA01 cenário A') + tcFail('UC-ZZA01 cenário B')));
    run();
    expect(readManifest().ucs['UC-ZZA01'].verdict).toBe('fail');
  });

  it('PASS vence SKIP: ≥1 pass + skip (sem fail) → pass', () => {
    junit('a.xml', suite(tcPass('UC-ZZC03 roda') + tcSkip('UC-ZZC03 condicional')));
    run();
    expect(readManifest().ucs['UC-ZZC03'].verdict).toBe('pass');
  });

  it('MERGE de múltiplos relatórios (Pest + Playwright)', () => {
    junit('pest-junit.xml', suite(tcPass('UC-ZZG02 saldo')));
    junit('playwright-junit.xml', suite(tcPass('UC-ZZD06 gate')));
    run();
    const m = readManifest();
    expect(Object.keys(m.ucs).sort()).toEqual(['UC-ZZD06', 'UC-ZZG02']);
    expect(m._meta.sources.length).toBe(2);
  });

  it('SEGURANÇA: nenhum test-results → NÃO sobrescreve manifesto existente', () => {
    write(MANIFEST, JSON.stringify({ ucs: { 'UC-ZZA01': { verdict: 'pass', ran_at: '2026-06-01' } } }));
    const out = run(); // test-results/ ausente
    expect(out).toMatch(/Nenhum test-results/);
    expect(readManifest().ucs['UC-ZZA01'].verdict).toBe('pass'); // preservado
  });

  it('--seed-empty cria manifesto vazio (bootstrap F1)', () => {
    run('--seed-empty');
    const m = readManifest();
    expect(m.ucs).toEqual({});
    expect(m._meta.stats.ucs).toBe(0);
  });

  it('ESPECIFICIDADE: testcase sem UC-id no nome é ignorado', () => {
    junit('a.xml', suite(tcPass('teste qualquer sem id') + tcPass('UC-ZZA01 com id')));
    run();
    const m = readManifest();
    expect(Object.keys(m.ucs)).toEqual(['UC-ZZA01']);
  });

  // ── Onda Q2 — MERGE per-UC entre RODADAS (runners em workflows separados) ──────────
  it('MERGE entre rodadas: UC ausente do XML atual PRESERVA veredito anterior', () => {
    write(MANIFEST, JSON.stringify({ ucs: { 'UC-ZZA01': { verdict: 'pass', ran_at: '2026-06-01', tests: 1 } } }));
    junit('pest.xml', suite(tcPass('UC-ZZF01 título gerado')));
    const out = run();
    const m = readManifest();
    expect(m.ucs['UC-ZZF01'].verdict).toBe('pass'); // rodada atual entra
    expect(m.ucs['UC-ZZA01'].verdict).toBe('pass'); // anterior preservado (runner parcial não apaga prova alheia)
    expect(m.ucs['UC-ZZA01'].ran_at).toBe('2026-06-01');
    expect(out).toMatch(/1 preservado/);
  });

  it('MERGE entre rodadas: UC presente no XML atual SOBRESCREVE o veredito antigo (fail novo vence pass velho)', () => {
    write(MANIFEST, JSON.stringify({ ucs: { 'UC-ZZA01': { verdict: 'pass', ran_at: '2026-06-01', tests: 1 } } }));
    junit('e2e.xml', suite(tcFail('UC-ZZA01 regrediu')));
    run();
    expect(readManifest().ucs['UC-ZZA01'].verdict).toBe('fail');
  });

  it('--no-merge: reset consciente sobrescreve o manifesto inteiro', () => {
    write(MANIFEST, JSON.stringify({ ucs: { 'UC-ZZA01': { verdict: 'pass', ran_at: '2026-06-01', tests: 1 } } }));
    junit('pest.xml', suite(tcPass('UC-ZZF01 título gerado')));
    run('--no-merge');
    expect(Object.keys(readManifest().ucs)).toEqual(['UC-ZZF01']); // UC-ZZA01 saiu
  });
});
