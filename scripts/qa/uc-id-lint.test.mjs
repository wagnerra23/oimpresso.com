#!/usr/bin/env node
// Self-test uc-id-lint — bite-test com CONTROLE NEGATIVO (Lei C: catraca que nunca
// reprova é decorativa; sem um caso que a faz morder, o verde não vale nada).
// Roda: node scripts/qa/uc-id-lint.test.mjs
//
// DUAS CAMADAS, de propósito (§5 2026-07-30 — assert sobre helper exportado NÃO prova
// contrato de pipeline; §5 2026-08-14 — selftest que exercita a CÓPIA fica verde enquanto
// o caminho de produção regride):
//   (A) unitária  — invalidosEm/diagnostica, rápido e legível;
//   (B) CLI de fora — spawna o script de verdade num fixture temp e confere EXIT CODE,
//       que é o que o CI lê. É a camada que pega "o helper acerta mas o main não morde".

import { mkdtempSync, mkdirSync, rmSync, writeFileSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync, spawnSync } from 'node:child_process';
import { invalidosEm, diagnostica, tokenDe, blocosUcEm } from './uc-id-lint.mjs';

const AQUI = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(AQUI, 'uc-id-lint.mjs');

let fails = 0;
const check = (n, c, extra = '') => {
  console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : `  → ${extra}`}`);
  if (!c) fails++;
};

// ═════════════════════════════════════════════════════════════════════════════════════
// (A) UNITÁRIA — controle NEGATIVO primeiro. Cada caso aqui é um erro REAL do corpus,
//     não um exemplo inventado: os ids saíram da medição de 2026-08-23.
// ═════════════════════════════════════════════════════════════════════════════════════

// A.1 · MORDE — prefixo longo demais. É o erro literal que custou uma rodada (UC-PTPAINEL-01,
//       8 caracteres) e é a família de ESPSHOW/IMPSHOW/INTSHOW/PROGDOC (7), 17 blocos vivos.
{
  const r = invalidosEm('f.casos.md', '## UC-PTPAINEL-01 · algo\n\nStatus: ✅\n');
  check('MORDE: prefixo longo demais (UC-PTPAINEL-01)', r.length === 1, `veio ${r.length}`);
  // 8, não 7: P-T-P-A-I-N-E-L. A 1a versão desta linha dizia 7 e o script me corrigiu —
  // fica registrado porque é o comportamento que se quer de um bite-test (a máquina conta,
  // o autor não estima).
  check('  … e diz o limite na razão', /8 caracteres \(máx 6\)/.test(r[0]?.razao || ''), r[0]?.razao);
  check('  … e sugere o corte', r[0]?.sugestao === 'UC-PTPAIN-01', r[0]?.sugestao);
}

// A.2 · MORDE — prefixo em DOIS segmentos (Jana/Chat, 11 blocos; Jana/Index, 14).
{
  const r = invalidosEm('f.casos.md', '## UC-COPI-CHAT-01 — algo\n');
  check('MORDE: prefixo em 2 segmentos (UC-COPI-CHAT-01)', r.length === 1, `veio ${r.length}`);
  check('  … e nomeia os 2 segmentos', /2 segmentos/.test(r[0]?.razao || ''), r[0]?.razao);
}

// A.3 · MORDE — os casos vivos do Ponto, um a um (são os que o comentário da lane
//       ponto-pest.yml diz estar "denunciando regressão medida" — e que a máquina não vê).
for (const [id, sug] of [['UC-ESPSHOW-01', 'UC-ESPSHO-01'], ['UC-IMPSHOW-04', 'UC-IMPSHO-04'], ['UC-INTSHOW-02', 'UC-INTSHO-02']]) {
  const r = invalidosEm('f.casos.md', `## ${id} · algo · \`must\`\n`);
  check(`MORDE: ${id} (caso vivo do Ponto)`, r.length === 1 && r[0].sugestao === sug, `${r.length} / ${r[0]?.sugestao}`);
}

// A.4 · MORDE — prefixo começando por dígito (ambíguo com o número do UC).
{
  const r = invalidosEm('f.casos.md', '## UC-2FA-01 · algo\n');
  check('MORDE: prefixo começa por dígito (UC-2FA-01)', r.length === 1, `veio ${r.length}`);
  check('  … e recusa sugerir corte (corte não resolve)', r[0]?.sugestao === null, String(r[0]?.sugestao));
}

// A.5 · MORDE — cauda com 4 dígitos.
check('MORDE: cauda com 4 dígitos (UC-PROD-0001)', invalidosEm('f.casos.md', '## UC-PROD-0001 · algo\n').length === 1);

// A.6 · LIBERA — todo formato que o canônico aceita. Se algum destes morder, o lint virou
//       ruído e reprovaria trabalho legítimo (a doença das 4 lápides de guard sintático).
for (const id of ['UC-01', 'UC-CEDI-01', 'UC-KBV2-01', 'UC-10b', 'UC-FORJA-01', 'UC-DSR-08b', 'UC-F02']) {
  check(`libera: ${id}`, invalidosEm('f.casos.md', `## ${id} · algo\n`).length === 0);
}

// A.7 · NÃO-ALVO — bloco que não se apresenta como UC nunca entra no relatório. Sem isto o
//       lint acusaria prosa, que é como um guard sintático vira 100% falso-positivo.
check('ignora: heading comum com id inválido citado em prosa',
  invalidosEm('f.casos.md', '## Contexto\n\nUC-PTPAINEL-01 citado no texto\n').length === 0);
check('ignora: heading "UCs declarados" (prosa que começa com UC)',
  invalidosEm('f.casos.md', '## UCs declarados neste arquivo\n- a\n').length === 0);
check('ignora: frontmatter e H1 (só blocos ## contam)',
  invalidosEm('f.casos.md', '---\ncharter: x\n---\n\n# UC-PTPAINEL-01\n').length === 0);

// A.8 · denominador honesto — blocosUcEm conta quem SE APRESENTA como UC (válido ou não).
check('blocosUcEm conta válidos + inválidos',
  blocosUcEm('## UC-01 a\n## UC-PTPAINEL-02 b\n## Contexto c\n') === 2);

// A.9 · tokenDe para o id no primeiro separador de prosa, não engole a descrição.
check('tokenDe corta no separador ·', tokenDe('UC-CEDI-01 · descrição longa') === 'UC-CEDI-01');
check('tokenDe corta no travessão —', tokenDe('UC-COPI-CHAT-01 — descrição') === 'UC-COPI-CHAT-01');

// A.10 · diagnostica sempre devolve razão — nunca string vazia (mensagem muda é gate mudo).
check('diagnostica nunca devolve razão vazia', String(diagnostica('UC-ZZZZZZZZ-01').razao).length > 0);

// ═════════════════════════════════════════════════════════════════════════════════════
// (B) CLI DE FORA — o que o CI de fato executa. Fixture é um repo git PRÓPRIO em temp,
//     porque `arquivosDoRepo()` chama `git ls-files`: rodar contra o repo real mediria
//     o corpus vivo (não-hermético) e o teste passaria a depender do estado do dia.
// ═════════════════════════════════════════════════════════════════════════════════════
const raiz = mkdtempSync(join(tmpdir(), 'uc-id-lint-'));
const put = (rel, body) => {
  const abs = join(raiz, rel);
  mkdirSync(dirname(abs), { recursive: true });
  writeFileSync(abs, body);
};
const git = (...a) => execFileSync('git', a, { cwd: raiz, stdio: 'pipe' });
const rodar = (...args) => spawnSync(process.execPath, [SCRIPT, ...args], { cwd: raiz, encoding: 'utf8' });

try {
  git('init', '-q');
  git('config', 'user.email', 'selftest@local');
  git('config', 'user.name', 'selftest');

  put('resources/js/Pages/Bom/Tela.casos.md', '## UC-BOM-01 · ok\n\n## UC-BOM-02 · ok\n');
  put('resources/js/Pages/Ruim/Tela.casos.md', '## UC-PTPAINEL-01 · ruim\n');
  git('add', '-A');
  git('commit', '-qm', 'fixture');

  // B.1 · report puro NUNCA falha (é relato, não gate) — mesmo com violação presente.
  const rep = rodar();
  check('CLI: report sem --check sai 0 mesmo com violação', rep.status === 0, `status=${rep.status}`);
  check('CLI: report nomeia o arquivo ruim', /Ruim\/Tela\.casos\.md/.test(rep.stdout), rep.stdout.slice(0, 200));

  // B.2 · CONTROLE NEGATIVO DO PIPELINE — --check tem que sair != 0. É a asserção que
  //       impede o instrumento de virar carimbo; se ela cair, tudo o mais é decorativo.
  const chk = rodar('--check');
  check('CLI: --check MORDE (exit != 0) com id inválido', chk.status === 1, `status=${chk.status}`);

  // B.3 · controle POSITIVO do pipeline — sem o arquivo ruim, --check libera.
  rmSync(join(raiz, 'resources/js/Pages/Ruim/Tela.casos.md'));
  git('add', '-A');
  git('commit', '-qm', 'remove ruim');
  const limpo = rodar('--check');
  check('CLI: --check LIBERA (exit 0) quando tudo casa', limpo.status === 0, `status=${limpo.status}\n${limpo.stdout}`);

  // B.4 · baseline grandfathera o legado e o --check para de morder (no-new-lie).
  put('resources/js/Pages/Ruim/Tela.casos.md', '## UC-PTPAINEL-01 · ruim\n');
  git('add', '-A');
  git('commit', '-qm', 'volta ruim');
  const w = rodar('--write-baseline', 'base.json');
  check('CLI: --write-baseline sai 0', w.status === 0, `status=${w.status}`);
  const base = JSON.parse(readFileSync(join(raiz, 'base.json'), 'utf8'));
  check('CLI: baseline contém a chave arquivo::id',
    base.grandfathered.includes('resources/js/Pages/Ruim/Tela.casos.md::UC-PTPAINEL-01'),
    JSON.stringify(base.grandfathered));
  const comBase = rodar('--check', '--baseline', 'base.json');
  check('CLI: --check --baseline LIBERA o legado grandfatherado', comBase.status === 0, `status=${comBase.status}`);

  // B.5 · … e continua mordendo id NOVO, que é o ponto inteiro do baseline.
  put('resources/js/Pages/Novo/Tela.casos.md', '## UC-OUTROERRADO-01 · novo\n');
  git('add', '-A');
  git('commit', '-qm', 'id novo errado');
  const novo = rodar('--check', '--baseline', 'base.json');
  check('CLI: --check --baseline MORDE id NOVO fora do baseline', novo.status === 1, `status=${novo.status}`);

  // B.6 · baseline inexistente é ERRO DE EXECUÇÃO (exit 2), nunca "vazio" silencioso —
  //       senão o gate cai em fail-open, que é a classe LC-13 (§5 2026-07-29).
  const semBase = rodar('--check', '--baseline', 'nao-existe.json');
  check('CLI: --baseline ausente sai 2 (não-medi != tudo-ok)', semBase.status === 2, `status=${semBase.status}`);
} finally {
  rmSync(raiz, { recursive: true, force: true });
}

console.log(`\n${fails === 0 ? 'SELFTEST OK' : 'SELFTEST FALHOU'} — ${fails} falha(s)`);
process.exit(fails === 0 ? 0 : 1);
