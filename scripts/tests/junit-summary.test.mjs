#!/usr/bin/env node
// Teste do tripwire + marcador de run invalido (FV-F1 + FV-F4 · US-GOV-045).
//
// ANCORA EXTERNA (nao derivado do codigo — proibicoes §5 anti-tautologico):
//   SPEC memory/requisitos/Governance/SPEC.md US-GOV-045 DoD D.2 — "run com junit
//   ausente/0 bytes/incoerente grava marcador EXPLICITO {invalid:true, reason} no
//   summary.json (quando --out) e mantem exit code do tripwire FV-F1 (1=artefato,
//   2=incoerente)". Incidente-origem: 20260629-020001 (morte silenciosa exit 2
//   mid-suite, junit 0 bytes — run sumia sem rastro legivel por maquina).
//
// Roda o script REAL como subprocess (comportamento, nao presenca).
//
// @covers-us US-GOV-045
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, readFileSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const SCRIPT = join(dirname(fileURLToPath(import.meta.url)), 'junit-summary.mjs');
const root = join(tmpdir(), `junit-summary-test-${process.pid}`);
mkdirSync(root, { recursive: true });

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

// executa e devolve {code, out} sem lancar
function run(xml, out) {
  try {
    execFileSync(process.execPath, [SCRIPT, xml, '--out', out], { stdio: 'pipe' });
    return { code: 0 };
  } catch (e) {
    return { code: e.status };
  }
}
const marker = (p) => JSON.parse(readFileSync(p, 'utf8'));

try {
  // D.2a — XML 0 bytes (o padrao dos runs mortos 20260629/20260702): exit 1 + marcador
  const xml0 = join(root, 'junit-0b.xml'); writeFileSync(xml0, '');
  const out0 = join(root, 'summary-0b.json');
  const r0 = run(xml0, out0);
  ok(r0.code === 1, `XML 0 bytes → exit 1 (tripwire FV-F1 preservado) — got ${r0.code}`);
  ok(existsSync(out0) && marker(out0).invalid === true, 'XML 0 bytes → summary.json com invalid:true (marcador explicito)');
  ok(marker(out0).reason === 'xml_0_bytes', `reason=xml_0_bytes — got ${marker(out0).reason}`);
  ok(!marker(out0).coherent && !marker(out0).n_testcases, 'marcador NUNCA tem coherent/n_testcases (nenhum leitor legado confunde com run valido)');

  // D.2b — XML ausente: exit 1 + marcador xml_ausente
  const outA = join(root, 'summary-ausente.json');
  const rA = run(join(root, 'nao-existe.xml'), outA);
  ok(rA.code === 1 && marker(outA).reason === 'xml_ausente', 'XML ausente → exit 1 + reason=xml_ausente');

  // D.2c — XML incoerente (declarados != contados): exit 2 + marcador SOBRESCREVE o summary
  const xmlI = join(root, 'junit-incoerente.xml');
  writeFileSync(xmlI, '<testsuites><testsuite name="s" tests="5" file="T.php"><testcase name="a" file="T.php" time="0.1"/></testsuite></testsuites>');
  const outI = join(root, 'summary-incoerente.json');
  const rI = run(xmlI, outI);
  ok(rI.code === 2, `XML incoerente → exit 2 — got ${rI.code}`);
  ok(marker(outI).invalid === true && marker(outI).reason === 'coleta_incoerente', 'XML incoerente → marcador sobrescreve (invalid:true, reason=coleta_incoerente)');

  // Controle — XML valido: exit 0, summary normal SEM invalid
  const xmlV = join(root, 'junit-valido.xml');
  writeFileSync(xmlV, '<testsuites><testsuite name="s" tests="2" file="T.php"><testcase name="a" file="T.php" time="0.1"/><testcase name="b" file="T.php" time="0.2"><failure>x</failure></testcase></testsuite></testsuites>');
  const outV = join(root, 'summary-valido.json');
  const rV = run(xmlV, outV);
  const sv = marker(outV);
  ok(rV.code === 0, `XML valido → exit 0 — got ${rV.code}`);
  ok(sv.invalid === undefined && sv.coherent === true && sv.n_testcases === 2, 'XML valido → summary coerente sem campo invalid (contrato de sempre intacto)');
  ok(sv.totals.passed === 1 && sv.totals.failed === 1, 'XML valido → totals contados certos');
} finally { rmSync(root, { recursive: true, force: true }); }

console.log(fails === 0 ? '\n  junit-summary (FV-F1 + FV-F4/US-GOV-045): OK\n' : `\n  junit-summary: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
