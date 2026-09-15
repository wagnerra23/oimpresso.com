#!/usr/bin/env node
// @ts-check
// Bite-test do whats-active-troca-de-alvo. Duas camadas de propósito:
//   (A) núcleo puro (alvoDe / deveAvisar) — determinístico, sem I/O;
//   (B) o CLI de FORA, por subprocesso com stdin + transcript sintéticos — porque assert
//       em helper exportado prova a função, não o pipeline (§5 2026-07-30).
// Cada MORDE tem controle NEGATIVO ao lado: aviso que não pode ser silenciado é ruído, e
// aviso que nunca cala é pior.
import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { alvoDe, deveAvisar, alvosDoTranscript } from './whats-active-troca-de-alvo.mjs';

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };
const HOOK = fileURLToPath(new URL('./whats-active-troca-de-alvo.mjs', import.meta.url));

// ── A: núcleo puro ────────────────────────────────────────────────────────────
ok(alvoDe('Modules/Fiscal/Http/Controllers/X.php') === 'Modules/Fiscal', 'alvo: Modules/<X>');
ok(alvoDe('resources/js/Pages/Sells/Create.tsx') === 'Pages/Sells', 'alvo: Pages/<X>');
ok(alvoDe('memory/requisitos/Jana/BRIEFING.md') === 'requisitos/Jana', 'alvo: requisitos/<X>');
ok(alvoDe('scripts/governance/sdd-scorecard.mjs') === 'scripts/governance', 'alvo: scripts/<sub>');
ok(alvoDe('.github/workflows/casos-gate.yml') === 'workflows', 'alvo: workflows');
ok(alvoDe('README.md') === null, 'path sem alvo classificável → null (controle negativo)');
// Windows manda barra invertida — o incidente que motivou o hook rodou em worktree Windows.
// O path e MONTADO com fromCharCode(92): escrever a barra literal aqui a faz colapsar no
// transporte da escrita (LC-26): o escape de carriage return viraria um CR LITERAL no
// arquivo, e CR e terminador de linha em JS — o comentario acabaria ali e o resto da
// linha viraria codigo. Foi exatamente o que aconteceu na 1a versao deste teste.
// versao deste teste — e foi este assert que pegou.
const BSL = String.fromCharCode(92);
ok(alvoDe(['D:', 'repo', 'Modules', 'Jana', 'X.php'].join(BSL)) === 'Modules/Jana',
  'alvo: normaliza separador do Windows');

ok(deveAvisar('scripts/governance', new Set(['Modules/Jana'])) === true,
  'MORDE: alvo novo com sessão já em outro alvo');
ok(deveAvisar('Modules/Jana', new Set(['Modules/Jana'])) === false,
  'mesmo alvo → não avisa (controle negativo: não vira ruído por arquivo)');
ok(deveAvisar('Modules/Jana', new Set()) === false,
  'PRIMEIRO alvo da sessão → não avisa (o session_start já cobre — controle negativo)');
ok(deveAvisar(null, new Set(['x'])) === false, 'alvo nulo → não avisa (controle negativo)');

ok(alvosDoTranscript('{"file_path":"Modules/Jana/a.php"}\n{"file_path":"scripts/qa/b.mjs"}').size === 2,
  'alvosDoTranscript extrai os 2 alvos distintos');

// ── B: o CLI de fora ─────────────────────────────────────────────────────────
const dir = mkdtempSync(join(tmpdir(), 'wa-alvo-'));
try {
  const tp = join(dir, 't.jsonl');
  const rodar = (payload) => spawnSync(process.execPath, [HOOK], {
    input: JSON.stringify(payload), encoding: 'utf8',
  });

  writeFileSync(tp, '{"tool_name":"Edit","tool_input":{"file_path":"Modules/Jana/X.php"}}\n');
  let r = rodar({ tool_name: 'Edit', tool_input: { file_path: 'scripts/governance/y.mjs' }, transcript_path: tp });
  ok(r.status === 0, 'CLI: advisory — exit 0 mesmo avisando (não bloqueia)');
  ok(/ALVO NOVO nesta sessão: scripts\/governance/.test(r.stderr || ''),
    'CLI MORDE: troca de alvo real (Modules/Jana -> scripts/governance) avisa');
  ok(/whats-active/.test(r.stderr || '') && /--remotes/.test(r.stderr || ''),
    'CLI: a mensagem dá o caminho COM MCP e o fallback SEM MCP (lição do mcp-first-warning)');

  r = rodar({ tool_name: 'Edit', tool_input: { file_path: 'Modules/Jana/Z.php' }, transcript_path: tp });
  ok((r.stderr || '').trim() === '', 'CLI CN: mesmo alvo do transcript → silencioso');

  writeFileSync(tp, '{"tool_name":"Read","tool_input":{}}\n');
  r = rodar({ tool_name: 'Edit', tool_input: { file_path: 'Modules/Jana/X.php' }, transcript_path: tp });
  ok((r.stderr || '').trim() === '', 'CLI CN: primeiro alvo da sessão → silencioso');

  r = rodar({ tool_name: 'Read', tool_input: { file_path: 'scripts/qa/x.mjs' }, transcript_path: tp });
  ok((r.stderr || '').trim() === '', 'CLI CN: tool que não escreve → silencioso');

  r = rodar({ tool_name: 'Edit', tool_input: { file_path: 'CHANGELOG.md' }, transcript_path: tp });
  ok((r.stderr || '').trim() === '', 'CLI CN: path sem alvo classificável → silencioso');

  r = rodar({ tool_name: 'Edit', tool_input: { file_path: 'Modules/X/a.php' }, transcript_path: join(dir, 'nao-existe.jsonl') });
  ok(r.status === 0 && (r.stderr || '').trim() === '',
    'CLI CN: transcript ilegível → fail-open silencioso (nunca inventa troca de alvo)');
} finally { rmSync(dir, { recursive: true, force: true }); }

console.log(fails === 0 ? '\n  whats-active-troca-de-alvo: OK\n' : `\n  whats-active-troca-de-alvo: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
