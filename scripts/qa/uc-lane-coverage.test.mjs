#!/usr/bin/env node
// Self-test uc-lane-coverage — bite-test com CONTROLE NEGATIVO.
// Roda: node scripts/qa/uc-lane-coverage.test.mjs
//
// ENCOLHEU em 2026-08-23, e o encolhimento é o ponto. A versão anterior testava um parser
// de workflow PRÓPRIO (fronteira de job, matriz, `.list`, `find`, quarentena) que duplicava
// `scripts/governance/test-lane-coverage.mjs`. Aquela derivação foi deletada e os testes
// dela foram junto — testar a cópia é como a cópia sobrevive.
//
// O que sobra é o que este script de fato decide:
//   (1) ler a coluna `Teste` da tabela de Rastreabilidade de um `casos.md`;
//   (2) classificar em TRÊS estados — `na-lane` · `quarentena` · `órfão`;
//   (3) o CLI de fora: exit codes, baseline no-new-lie, `--baseline` ausente = 2.
//
// A asserção mais importante é a negativa da (2): **quarentena declarada NÃO vira órfão**.
// Misturar as duas apaga a diferença entre "alguém decidiu" e "ninguém sabe" — que é a
// única coisa que torna o número acionável.

import { mkdtempSync, mkdirSync, rmSync, writeFileSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync, spawnSync } from 'node:child_process';
import { citacoesEm } from './uc-lane-coverage.mjs';

const AQUI = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(AQUI, 'uc-lane-coverage.mjs');
let fails = 0;
const check = (n, c, extra = '') => {
  console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : `  → ${extra}`}`);
  if (!c) fails++;
};

// ═════════════════════════════════════════════════════════════════════════════════════
// (A) LEITURA DA TABELA DE RASTREABILIDADE — o único parser que é DESTE script
// ═════════════════════════════════════════════════════════════════════════════════════
{
  const md = [
    '## UC-AB-01 · caso',
    '',
    '| UC | Caso | Prio | Âncora | Teste | Status |',
    '|----|------|------|--------|-------|--------|',
    '| UC-AB-01 | faz algo | must | `CU-1` | `MeuTest` | ✅ |',
    '| UC-AB-02 | outro | should | `CU-2` | — | ⬜ |',
  ].join('\n');
  const c = citacoesEm(md);
  check('tabela: 2 linhas de UC lidas', c.length === 2, JSON.stringify(c.map((x) => x.uc)));
  check('tabela: nome do teste extraído sem crase', c[0].nomes[0] === 'MeuTest', JSON.stringify(c[0]));
  check('tabela: status lido da penúltima coluna', c[0].status === '✅', c[0].status);
  check('tabela: linha sem teste não inventa nome', c[1].nomes.length === 0, JSON.stringify(c[1].nomes));
  check('tabela: separadora |---| ignorada', !c.some((x) => /^-+$/.test(x.status)));
  check('tabela: prosa fora de tabela não vira citação',
    citacoesEm('## UC-AB-01 · caso\n\nO `MeuTest` cobre isso.\n').length === 0);
}

// ═════════════════════════════════════════════════════════════════════════════════════
// (B) CLI DE FORA — repo git hermético em temp.
//     `coletarAlvos()`/`emQuarentena()` do dono resolvem contra `process.cwd()`, e o spawn
//     abaixo usa `cwd: raiz` — por isso o fixture é hermético de verdade, sem tocar o corpus
//     vivo (que mudaria o veredito conforme o dia).
// ═════════════════════════════════════════════════════════════════════════════════════
const raiz = mkdtempSync(join(tmpdir(), 'uc-lane-'));
const put = (rel, body) => {
  const a = join(raiz, rel);
  mkdirSync(dirname(a), { recursive: true });
  writeFileSync(a, body);
};
const git = (...a) => execFileSync('git', a, { cwd: raiz, stdio: 'pipe' });
const rodar = (...args) => spawnSync(process.execPath, [SCRIPT, '--root', raiz, ...args], { cwd: raiz, encoding: 'utf8' });
const resumoDe = (r) => { try { return JSON.parse(r.stdout).resumo; } catch { return null; } };

const tabela = (uc, teste) => [
  `## ${uc} · caso`, '',
  '| UC | Caso | Prio | Âncora | Teste | Status |',
  '|----|------|------|--------|-------|--------|',
  `| ${uc} | algo | must | \`CU-1\` | \`${teste}\` | 🧪 |`, '',
].join('\n');

try {
  git('init', '-q');
  git('config', 'user.email', 'selftest@local');
  git('config', 'user.name', 'selftest');

  put('.github/workflows/lane.yml', [
    'jobs:', '  pest:', '    steps:', '      - run: |',
    '          vendor/bin/pest Modules/M/Tests/Feature/NaLaneTest.php --no-coverage', '',
  ].join('\n'));
  put('Modules/M/Tests/Feature/NaLaneTest.php', '<?php\n');
  put('Modules/M/Tests/Feature/OrfaoTest.php', '<?php\n');
  put('resources/js/Pages/M/Coberto.casos.md', tabela('UC-M-01', 'NaLaneTest'));
  put('resources/js/Pages/M/Orfao.casos.md', tabela('UC-M-02', 'OrfaoTest'));
  git('add', '-A');
  git('commit', '-qm', 'fixture');

  const rep = rodar();
  check('CLI: report sai 0 (relato, não gate)', rep.status === 0, `status=${rep.status}`);
  check('CLI: acha o órfão', /UC-M-02/.test(rep.stdout), rep.stdout.slice(-300));
  check('CLI: NÃO acusa o que está na lane', !/UC-M-01\s+⛔/.test(rep.stdout), rep.stdout.slice(-300));

  // B.2 · CONTROLE NEGATIVO DO PIPELINE — sem isto, tudo o mais é decorativo.
  check('CLI: --check MORDE com UC órfão', rodar('--check').status === 1);

  // B.3 · controle POSITIVO — o teste entra na lane e o --check libera.
  put('.github/workflows/lane.yml', [
    'jobs:', '  pest:', '    steps:', '      - run: |',
    '          vendor/bin/pest Modules/M/Tests/Feature/NaLaneTest.php Modules/M/Tests/Feature/OrfaoTest.php', '',
  ].join('\n'));
  git('add', '-A');
  git('commit', '-qm', 'entra na lane');
  const ok = rodar('--check');
  check('CLI: --check LIBERA quando o teste entra na lane', ok.status === 0, `status=${ok.status}\n${ok.stdout}`);

  // ═══════════════════════════════════════════════════════════════════════════════════
  // B.4 · A ASSERÇÃO-CHAVE: quarentena declarada NÃO é órfã.
  //   A lane cobre o DIRETÓRIO inteiro via `find`, e o arquivo está na quarentena. Se a
  //   ordem dos estados estiver errada (cobertura antes de quarentena), ele sai `na-lane`
  //   — carimbo de "roda" num teste parado. Se a quarentena for SUBTRAÍDA do run-set (o
  //   erro da 1ª versão), ele sai `órfão` — acusa quem decidiu conscientemente.
  //   Há um único veredito certo, e é o terceiro.
  // ═══════════════════════════════════════════════════════════════════════════════════
  put('.github/m-quarantine.list', 'Modules/M/Tests/Feature/OrfaoTest.php  # instável\n');
  put('.github/workflows/lane.yml', [
    'jobs:', '  pest:', '    steps:', '      - run: |',
    '          QUAR_FILE=.github/m-quarantine.list',
    "          find Modules/M/Tests -name '*Test.php' > /tmp/all.txt",
    '          mapfile -t TARGETS < /tmp/run.txt',
    '          vendor/bin/pest "${TARGETS[@]}"', '',
  ].join('\n'));
  git('add', '-A');
  git('commit', '-qm', 'quarentena');

  const q = resumoDe(rodar('--json'));
  check('CLI: quarentena é contada como QUARENTENA', q && q.quarentena === 1, JSON.stringify(q));
  check('CLI: … e NÃO como órfã', q && q.orfao === 0, JSON.stringify(q));
  check('CLI: … e NÃO como na-lane (o teste não roda)', q && q.na_lane === 1, JSON.stringify(q));
  check('CLI: --check NÃO morde por quarentena', rodar('--check').status === 0);
  check('CLI: soma dos estados == citações (nenhuma linha some na classificação)',
    q && (q.na_lane + q.orfao + q.quarentena + q.arquivo_ausente + q.sem_teste) === q.citacoes, JSON.stringify(q));

  // B.5 · teste citado que não existe no disco é `arquivo-ausente`, não órfão.
  put('resources/js/Pages/M/Fantasma.casos.md', tabela('UC-M-04', 'NaoExisteTest'));
  git('add', '-A');
  git('commit', '-qm', 'teste fantasma');
  const f = resumoDe(rodar('--json'));
  check('CLI: teste inexistente vira arquivo-ausente', f && f.arquivo_ausente === 1, JSON.stringify(f));

  // B.6 · baseline no-new-lie.
  check('CLI: --write-baseline sai 0', rodar('--write-baseline', 'b.json').status === 0);
  const b = JSON.parse(readFileSync(join(raiz, 'b.json'), 'utf8'));
  check('CLI: baseline tem chave casos.md::UC', b.grandfathered.length > 0, JSON.stringify(b.grandfathered));
  check('CLI: --check --baseline LIBERA o legado', rodar('--check', '--baseline', 'b.json').status === 0);

  // FORA da árvore que o `find Modules/M/Tests` cobre — senão o "órfão novo" nasce na lane
  // e a asserção passaria a medir a fixture, não o baseline. (A 1ª versão punha em
  // `Modules/M/Tests/Feature/` e o próprio teste denunciou.)
  put('tests/Feature/Solto/NovoOrfaoTest.php', '<?php\n');
  put('resources/js/Pages/M/Novo.casos.md', tabela('UC-M-03', 'NovoOrfaoTest'));
  git('add', '-A');
  git('commit', '-qm', 'orfao novo');
  check('CLI: --check --baseline MORDE órfão NOVO', rodar('--check', '--baseline', 'b.json').status === 1);

  // B.7 · não-medi != tudo-ok (fail-open é a classe LC-13).
  check('CLI: --baseline ausente sai 2', rodar('--check', '--baseline', 'nao-existe.json').status === 2);
} finally {
  rmSync(raiz, { recursive: true, force: true });
}

console.log(`\n${fails === 0 ? 'SELFTEST OK' : 'SELFTEST FALHOU'} — ${fails} falha(s)`);
process.exit(fails === 0 ? 0 : 1);
