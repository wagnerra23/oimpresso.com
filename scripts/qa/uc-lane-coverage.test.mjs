#!/usr/bin/env node
// Self-test uc-lane-coverage — bite-test com CONTROLE NEGATIVO.
// Roda: node scripts/qa/uc-lane-coverage.test.mjs
//
// A asserção mais importante deste arquivo NÃO é "acha órfão". É a inversa:
// **lane indeterminada NÃO vira órfão**. Um parser que não entende a forma de seleção de
// uma lane e conclui "então ela não cobre nada" FABRICA achado — e achado fabricado em
// massa é como um instrumento perde a confiança de quem lê. É o §5 2026-07-29 pelo avesso
// (lá o instrumento afirmava saúde sem medir; aqui afirmaria doença sem medir).
//
// Duas camadas, mesma razão do irmão uc-id-lint: helper puro é legível, mas só o CLI de
// fora prova o que o CI executa (§5 2026-07-30 · 2026-08-14).

import { mkdtempSync, mkdirSync, rmSync, writeFileSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync, spawnSync } from 'node:child_process';
import { alvosDoRun, quarentenaDoRun, cobertoPor, citacoesEm, derivaCobertura } from './uc-lane-coverage.mjs';

const AQUI = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(AQUI, 'uc-lane-coverage.mjs');
let fails = 0;
const check = (n, c, extra = '') => {
  console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : `  → ${extra}`}`);
  if (!c) fails++;
};

// ═════════════════════════════════════════════════════════════════════════════════════
// (A) DERIVAÇÃO DO RUN-SET — as 4 formas medidas no repo, uma a uma
// ═════════════════════════════════════════════════════════════════════════════════════

// A.1 · forma (a): alvos literais com continuação de linha `\`
{
  const r = alvosDoRun('vendor/bin/pest --no-coverage \\\n  Modules/Ponto/Tests/Feature/A.php \\\n  Modules/Ponto/Tests/Feature/B.php\n');
  check('(a) alvos literais atravessam a continuação `\\`', r.alvos.length === 2, JSON.stringify(r.alvos));
  check('(a) flags não viram alvo', !r.alvos.includes('--no-coverage'), JSON.stringify(r.alvos));
  check('(a) sem indeterminado', r.indeterminado === null, String(r.indeterminado));
}

// A.2 · forma (b): `${{ matrix.module }}` tem ESPAÇOS dentro. Este é o caso que derrubou a
//       1a versão (modules-pest derivava 1 alvo em vez de 6) — o split por espaço quebrava
//       o token em três. Se esta asserção cair, a matriz voltou a ser invisível.
{
  const r = alvosDoRun('vendor/bin/pest Modules/${{ matrix.module }}/Tests --no-coverage\n');
  check('(b) `${{ matrix.x }}` com espaços continua UM alvo', r.alvos.length === 1, JSON.stringify(r.alvos));
  check('(b) … e preserva o placeholder pra expansão', /matrix\.module/.test(r.alvos[0] || ''), r.alvos[0]);
}

// A.3 · forma (c): array de shell alimentado por uma `.list`
{
  const r = alvosDoRun('mapfile -t PEST_TARGETS < <(grep -v "^#" .github/ci-sqlite-pest.list)\nvendor/bin/pest "${PEST_TARGETS[@]}" --no-coverage\n');
  check('(c) array + .list é reconhecido como lista', r.listas.includes('.github/ci-sqlite-pest.list'), JSON.stringify(r));
  check('(c) … e NÃO vira indeterminado', r.indeterminado === null, String(r.indeterminado));
}

// A.4 · forma (d): `find` com N diretórios. O estoque-pest passa DOIS; a 1a versão só
//       aceitava um e mandava a lane inteira pra `indeterminado`.
{
  const r = alvosDoRun("find tests/Feature/Estoque tests/Feature/Produto -name '*Test.php' | sort > /tmp/all.txt\nmapfile -t TARGETS < /tmp/run.txt\nvendor/bin/pest \"${TARGETS[@]}\"\n");
  check('(d) find com DOIS diretórios captura os dois', r.finds.length === 2, JSON.stringify(r.finds));
  check('(d) … e a lane não fica indeterminada', r.indeterminado === null, String(r.indeterminado));
}

// A.5 · o lado SUBTRATIVO (§5 2026-08-12): quarentena remove do run-set.
{
  const fora = quarentenaDoRun('QUAR_FILE=.github/estoque-pest-quarantine.list\n', (p) => (
    p === '.github/estoque-pest-quarantine.list' ? 'tests/Feature/X.php  # instável\n# comentário\n\ntests/Feature/Y.php\n' : null
  ));
  check('quarentena lida, comentário inline e linha-comentário fora', fora.length === 2, JSON.stringify(fora));
}

// A.6 · `--mutate` é IGNORADO de propósito, nunca indeterminado (não poluir o alarme).
{
  const r = alvosDoRun('vendor/bin/pest --mutate --covered-only\n');
  check('mutation-gate sai como ignorada, não indeterminada', r.ignorada !== null && r.indeterminado === null, JSON.stringify(r));
}

// A.7 · CONTROLE NEGATIVO DA DERIVAÇÃO — forma desconhecida TEM que virar indeterminado.
//       Sem esta, o parser poderia silenciosamente devolver "cobre nada" e fabricar órfãos.
{
  const r = alvosDoRun('vendor/bin/pest "${MISTERIO[@]}"\n');
  check('forma desconhecida vira INDETERMINADO (não "cobre nada")', r.indeterminado !== null, JSON.stringify(r));
}

// A.8 · cobertura por pasta ancestral (uma lane que roda `Modules/X/Tests` cobre os filhos).
{
  const cob = new Set(['Modules/Ponto/Tests', 'tests/Feature/A.php']);
  check('pasta ancestral cobre o arquivo', cobertoPor('Modules/Ponto/Tests/Feature/Z.php', cob));
  check('arquivo exato cobre', cobertoPor('tests/Feature/A.php', cob));
  check('irmão não coberto continua descoberto', !cobertoPor('Modules/Sells/Tests/Feature/Z.php', cob));
}

// A.9 · leitura da tabela de Rastreabilidade.
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
}

// A.10 · INDETERMINADO NÃO VIRA ÓRFÃO — a asserção-chave deste arquivo.
{
  const wf = 'name: x\njobs:\n  j:\n    steps:\n      - run: vendor/bin/pest "${MISTERIO[@]}"\n';
  const { cobertos, indeterminadas } = derivaCobertura(
    (p) => (p === '.github/workflows/x.yml' ? wf : null),
    () => ['.github/workflows/x.yml'],
  );
  check('lane indeterminada é CONTADA', indeterminadas.length === 1, JSON.stringify(indeterminadas));
  check('lane indeterminada NÃO adiciona cobertura falsa', cobertos.size === 0, JSON.stringify([...cobertos]));
}

// ═════════════════════════════════════════════════════════════════════════════════════
// (B) CLI DE FORA — repo git hermético em temp
// ═════════════════════════════════════════════════════════════════════════════════════
const raiz = mkdtempSync(join(tmpdir(), 'uc-lane-'));
const put = (rel, body) => {
  const a = join(raiz, rel);
  mkdirSync(dirname(a), { recursive: true });
  writeFileSync(a, body);
};
const git = (...a) => execFileSync('git', a, { cwd: raiz, stdio: 'pipe' });
const rodar = (...args) => spawnSync(process.execPath, [SCRIPT, '--root', raiz, ...args], { cwd: raiz, encoding: 'utf8' });

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

  put('.github/workflows/lane.yml', 'name: lane\njobs:\n  pest:\n    steps:\n      - run: |\n          vendor/bin/pest Modules/M/Tests/Feature/NaLaneTest.php --no-coverage\n');
  put('Modules/M/Tests/Feature/NaLaneTest.php', '<?php\n');
  put('Modules/M/Tests/Feature/OrfaoTest.php', '<?php\n');
  put('resources/js/Pages/M/Coberto.casos.md', tabela('UC-M-01', 'NaLaneTest'));
  put('resources/js/Pages/M/Orfao.casos.md', tabela('UC-M-02', 'OrfaoTest'));
  git('add', '-A');
  git('commit', '-qm', 'fixture');

  const rep = rodar();
  check('CLI: report sai 0 (relato, não gate)', rep.status === 0, `status=${rep.status}`);
  check('CLI: acha o órfão', /UC-M-02/.test(rep.stdout), rep.stdout.slice(-400));
  check('CLI: NÃO acusa o que está na lane', !/UC-M-01\s+⛔/.test(rep.stdout), rep.stdout.slice(-400));

  // B.2 · CONTROLE NEGATIVO DO PIPELINE
  check('CLI: --check MORDE com UC órfão', rodar('--check').status === 1);

  // B.3 · CONTROLE POSITIVO — pondo o órfão na lane, o --check libera.
  put('.github/workflows/lane.yml', 'name: lane\njobs:\n  pest:\n    steps:\n      - run: |\n          vendor/bin/pest Modules/M/Tests/Feature/NaLaneTest.php Modules/M/Tests/Feature/OrfaoTest.php\n');
  git('add', '-A');
  git('commit', '-qm', 'entra na lane');
  const ok = rodar('--check');
  check('CLI: --check LIBERA quando o teste entra na lane', ok.status === 0, `status=${ok.status}\n${ok.stdout}`);

  // B.4 · QUARENTENA — o teste está no run-set do `find` mas a quarentena o remove.
  //       É o gêmeo subtrativo: registro e trigger certos, arquivo fora mesmo assim.
  put('.github/q.list', 'Modules/M/Tests/Feature/OrfaoTest.php  # instável\n');
  put('.github/workflows/lane.yml', [
    'name: lane', 'jobs:', '  pest:', '    steps:', '      - run: |',
    '          QUAR_FILE=.github/q.list',
    "          find Modules/M/Tests -name '*Test.php' > /tmp/all.txt",
    '          mapfile -t TARGETS < /tmp/run.txt',
    '          vendor/bin/pest "${TARGETS[@]}"', '',
  ].join('\n'));
  git('add', '-A');
  git('commit', '-qm', 'quarentena');
  const quar = rodar('--check');
  check('CLI: quarentena TIRA o teste do run-set (--check volta a morder)', quar.status === 1, `status=${quar.status}\n${quar.stdout.slice(-500)}`);

  // B.5 · baseline: grandfathera o legado, segue mordendo o novo.
  check('CLI: --write-baseline sai 0', rodar('--write-baseline', 'b.json').status === 0);
  const b = JSON.parse(readFileSync(join(raiz, 'b.json'), 'utf8'));
  check('CLI: baseline tem a chave casos.md::UC', b.grandfathered.some((k) => /UC-M-02$/.test(k)), JSON.stringify(b.grandfathered));
  check('CLI: --check --baseline LIBERA o legado', rodar('--check', '--baseline', 'b.json').status === 0);

  put('resources/js/Pages/M/Novo.casos.md', tabela('UC-M-03', 'OrfaoTest'));
  git('add', '-A');
  git('commit', '-qm', 'uc novo citando teste em quarentena');
  check('CLI: --check --baseline MORDE citação NOVA', rodar('--check', '--baseline', 'b.json').status === 1);

  // B.6 · não-medi != tudo-ok.
  check('CLI: --baseline ausente sai 2', rodar('--check', '--baseline', 'nao-existe.json').status === 2);
} finally {
  rmSync(raiz, { recursive: true, force: true });
}

console.log(`\n${fails === 0 ? 'SELFTEST OK' : 'SELFTEST FALHOU'} — ${fails} falha(s)`);
process.exit(fails === 0 ? 0 : 1);
