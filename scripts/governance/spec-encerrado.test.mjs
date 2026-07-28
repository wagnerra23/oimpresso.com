#!/usr/bin/env node
// Teste do filtro de SPEC encerrado — contrato morto sai do corpus dos gates.
//
// ÂNCORA EXTERNA (não derivada do código — proibicoes §5 anti-tautológico):
//   `memory/requisitos/PontoWr2/SPEC.md:10` declara, textual: "⚰️ HISTORICAL … As
//   `US-PONT-NNN` aqui NÃO SÃO CONTRATO VIVO". O frontmatter `status: historical|arquivado`
//   é enum já validado por schema (memory-health STATUS_OK). O contrato que este teste
//   defende: SPEC que o AUTOR declarou encerrado não conta como dívida em gate required;
//   e NADA MAIS sai — rascunho, ativo, sem-status e ilegível continuam dentro (fail-open).
//   Medição de origem: 8 SPECs / 56 US inflando anchor-lint + doneness-lint (2026-07-28).
//
// Roda o script REAL como subprocess contra uma árvore de fixture (comportamento, não
// presença). Cada caso tem par bite/release: sem controle-negativo, um filtro que excluísse
// TUDO passaria verde "por não medir nada" — que é como o §5 descreve o gate mudo.
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { specEncerrado, particionarSpecs } from './lib/spec-encerrado.mjs';

const AQUI = dirname(fileURLToPath(import.meta.url));
const DONENESS = join(AQUI, 'doneness-lint.mjs');
let fails = 0;
const ok = (nome, cond, extra = '') => {
  console.log(`  ${cond ? '[OK]' : '[FAIL]'} ${nome}${cond ? '' : ` — ${extra}`}`);
  if (!cond) fails++;
};

// ── 1. núcleo puro: o predicado só reage a declaração explícita ──────────────
const tmp = mkdtempSync(join(tmpdir(), 'spec-encerrado-'));
const escrever = (mod, conteudo) => {
  const d = join(tmp, 'memory', 'requisitos', mod);
  mkdirSync(d, { recursive: true });
  const p = join(d, 'SPEC.md');
  writeFileSync(p, conteudo, 'utf8');
  return p;
};
const fm = (status, us = 'US-DEMO-001') =>
  `---\nmodule: Demo\nversion: '1.0'\nlast_updated: '2026-07-28'\n`
  + (status === null ? '' : `status: ${status}\n`)
  + `---\n\n## US ativas\n\n### ${us} · Demo\n\n> status: done\n\n**Implementado em:** \`package.json\` · verificado@abc1234 (2026-07-28)\n`;

// BITE — os dois valores que encerram
ok('historical → encerrado', specEncerrado(escrever('MortoH', fm('historical'))) === 'historical');
ok('arquivado → encerrado', specEncerrado(escrever('MortoA', fm('arquivado'))) === 'arquivado');
ok('aspas + maiúscula → encerrado', specEncerrado(escrever('MortoQ', fm('"Historical"'))) === 'historical');

// CONTROLE NEGATIVO — o que NÃO pode sair do gate
ok('ativo → VIVO', specEncerrado(escrever('VivoA', fm('ativo'))) === null);
ok('rascunho → VIVO (rascunho não é morto)', specEncerrado(escrever('VivoR', fm('rascunho'))) === null);
ok('sem campo status → VIVO', specEncerrado(escrever('VivoS', fm(null))) === null);
ok('sem frontmatter → VIVO (fail-open)', specEncerrado(escrever('VivoF', '# Sem FM\n\n### US-X-001 · a\n')) === null);
ok('arquivo inexistente → VIVO (fail-open)', specEncerrado(join(tmp, 'nao', 'existe', 'SPEC.md')) === null);
ok('status só no CORPO não conta', specEncerrado(escrever('VivoB', '---\nmodule: D\n---\n\nstatus: historical\n')) === null);

// partição
{
  const todos = [escrever('P1', fm('historical')), escrever('P2', fm('ativo'))];
  const { vivos, encerrados } = particionarSpecs(todos);
  ok('particionarSpecs separa 1/1', vivos.length === 1 && encerrados.length === 1,
    `vivos=${vivos.length} encerrados=${encerrados.length}`);
}

// ── 2. comportamento do gate REAL (subprocess) ──────────────────────────────
// Árvore limpa: 1 SPEC vivo + 1 encerrado. O encerrado tem `status: done` + âncora
// morta (path inexistente) = conflito `done-sem-âncora` que APARECERIA se ele contasse.
const cenario = mkdtempSync(join(tmpdir(), 'spec-encerrado-cli-'));
const escreverEm = (raiz, mod, conteudo) => {
  const d = join(raiz, 'memory', 'requisitos', mod);
  mkdirSync(d, { recursive: true });
  writeFileSync(join(d, 'SPEC.md'), conteudo, 'utf8');
};
const comConflito = (status) =>
  `---\nmodule: X\nversion: '1.0'\nlast_updated: '2026-07-28'\n${status ? `status: ${status}\n` : ''}---\n\n`
  + `## US ativas\n\n### US-XX-001 · conflito plantado\n\n> status: done\n\n`
  + `**Implementado em:** \`caminho/que/nao/existe.php\` · verificado@abc1234 (2026-07-28)\n`;
escreverEm(cenario, 'Encerrado', comConflito('historical'));
escreverEm(cenario, 'Vivo', comConflito('ativo'));

// spawnSync (não execFileSync): precisamos do STDERR mesmo quando o processo sai 0 —
// o aviso de exclusão vai pra stderr de propósito (o stdout do --json alimenta o scorecard).
const r = spawnSync(process.execPath, [DONENESS], { cwd: cenario, encoding: 'utf8' });
const stdout = String(r.stdout || '');
const stderrSaida = String(r.stderr || '');
const saida = stdout + stderrSaida;
ok('gate NÃO conta o SPEC encerrado', /1 SPECs?/.test(saida) || / 1 SPECs/.test(saida), `saída: ${saida.split('\n').find((l) => /SPECs/.test(l)) || '?'}`);
ok('gate AINDA conta o SPEC vivo (controle negativo)', /US-XX-001/.test(saida) || /1 done-sem-âncora|1 = 1 done/.test(saida) || /CONFLITOS.*1/.test(saida),
  'o conflito plantado no SPEC vivo tem que aparecer — se sumir, o filtro está excluindo demais');

// ── 3. o aviso NÃO pode ser silencioso (regra "Sempre fazer" #5) ────────────
ok('exclusão é REPORTADA em STDERR, não silenciosa', /FORA do gate/.test(stderrSaida),
  `stderr não mencionou a exclusão — gate mudo é pior que gate ausente. stderr="${stderrSaida.trim().slice(0, 120)}"`);
ok('STDOUT fica limpo do aviso (não polui o --json do scorecard)', !/FORA do gate/.test(stdout));

rmSync(tmp, { recursive: true, force: true });
rmSync(cenario, { recursive: true, force: true });
console.log(fails === 0 ? '\n✅ spec-encerrado: todos os casos' : `\n❌ spec-encerrado: ${fails} falha(s)`);
process.exit(fails === 0 ? 0 : 1);
