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
function run(xml, out, extra = []) {
  try {
    execFileSync(process.execPath, [SCRIPT, xml, '--out', out, ...extra], { stdio: 'pipe' });
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

  // ── assertions: o único campo que prova EXECUÇÃO (2026-07-29 · LC-13) ──────────
  // `0 failed` é compatível com "nada rodou": suíte 100% skipped sai exit 0 e parece
  // verde. O recibo real está em proibicoes §Ambiente — `4 skipped, 0 assertions,
  // EXIT_CODE=0` no CT 100 sem schema. O summary tinha o dado no XML e o descartava.
  const xmlP = join(root, 'junit-provou.xml');
  writeFileSync(xmlP, '<testsuites><testsuite name="s" tests="2" file="T.php"><testcase name="a" file="T.php" assertions="5" time="0.1"/><testcase name="b" file="T.php" assertions="2" time="0.2"/></testsuite></testsuites>');
  const outP = join(root, 'summary-provou.json');
  const rP = run(xmlP, outP);
  const sp = marker(outP);
  ok(rP.code === 0 && sp.totals.assertions === 7, `assertions somadas do XML — got ${sp.totals.assertions}`);
  ok(sp.provou_algo === true, 'rodou e provou → provou_algo:true');
  ok(sp.files[0].assertions === 7, 'assertions também agregadas POR ARQUIVO');

  // A fixture que define a classe: 0 failed, tudo pulado, nenhuma asserção.
  const xmlS = join(root, 'junit-tudo-pulado.xml');
  writeFileSync(xmlS, '<testsuites><testsuite name="s" tests="2" file="T.php"><testcase name="a" file="T.php" time="0"><skipped/></testcase><testcase name="b" file="T.php" time="0"><skipped/></testcase></testsuite></testsuites>');
  const outS = join(root, 'summary-pulado.json');
  const rS = run(xmlS, outS);
  const ss = marker(outS);
  ok(ss.totals.failed === 0 && ss.totals.skipped === 2 && ss.totals.assertions === 0,
    'suíte 100% pulada → 0 failed E 0 assertions (é a assinatura da classe)');
  ok(ss.provou_algo === false, 'BITE: rodou e NÃO provou → provou_algo:false');
  // Report-only por padrão: o default NÃO pode derrubar antes do FP medido.
  ok(rS.code === 0, `default segue report-only (exit 0) mesmo com 0 assertions — got ${rS.code}`);
  // E o opt-in morde. Sem este par, o campo seria decorativo.
  const rSc = run(xmlS, join(root, 's2.json'), ['--check-assertions']);
  ok(rSc.code === 1, `BITE: --check-assertions com 0 assertions → exit 1 — got ${rSc.code}`);
  const rPc = run(xmlP, join(root, 'p2.json'), ['--check-assertions']);
  ok(rPc.code === 0, `LIBERA: --check-assertions com assertions>0 → exit 0 — got ${rPc.code}`);
  // Controle negativo: JUnit de runner que não emite `assertions` não pode ser acusado
  // de "não provou" — ausência do atributo ≠ prova de que nada rodou. Aqui o default
  // segue 0, e é por isso que a reprovação é OPT-IN e não entra em lane nenhuma ainda.
  ok(sv.totals.assertions === 0 && sv.provou_algo === false,
    'XML sem atributo assertions → 0 e provou_algo:false (motivo de o --check ficar desarmado)');
} finally { rmSync(root, { recursive: true, force: true }); }

console.log(fails === 0 ? '\n  junit-summary (FV-F1 + FV-F4/US-GOV-045): OK\n' : `\n  junit-summary: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
