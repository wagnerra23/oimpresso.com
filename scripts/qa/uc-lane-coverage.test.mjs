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
  // A asserção anterior era `citacoesEm(prosa).length === 0` — um PROXY. Com o FORMATO 2
  // (cabeçalho) o UC declarado passa a aparecer como entrada `sem-teste`, então o proxy
  // virou falso enquanto a PROPRIEDADE que ele protegia segue de pé. Asserção agora é
  // direta, e é estritamente mais forte: prosa não produz NOME de teste.
  const prosa = citacoesEm('## UC-AB-01 · caso\n\nO `MeuTest` cobre isso.\n');
  check('prosa fora de campo dedicado não vira citação de teste',
    prosa.flatMap((x) => x.nomes).length === 0, JSON.stringify(prosa));
  check('… mas o UC declarado fica VISÍVEL como sem-teste (silêncio é indistinguível de OK)',
    prosa.length === 1 && prosa[0].uc === 'UC-AB-01', JSON.stringify(prosa));
}

// ═════════════════════════════════════════════════════════════════════════════════════
// (A2) FORMATO 2 — cabeçalho `## UC-XX` + campo dedicado (adicionado 2026-08-26)
//   Antes, um `casos.md` escrito só neste formato não contribuía UM par: o UC não virava
//   `órfão` nem `na-lane`, simplesmente não existia pra ferramenta. 57 dos 143 arquivos
//   varridos estavam nesse estado, com 386 cabeçalhos `## UC-` invisíveis.
// ═════════════════════════════════════════════════════════════════════════════════════
{
  // B.1 — citação na linha `Status:` (formato do `Jana/Pro.casos.md`, o caso que originou).
  const viaStatus = citacoesEm('## UC-PRO-01 — abre\nStatus: 🧪 (ProContractTest P1 — status)\n');
  check('bloco: nome vindo da linha Status:', viaStatus[0]?.nomes[0] === 'ProContractTest', JSON.stringify(viaStatus));

  // B.2 — citação no bullet `**Teste:**` com CAMINHO (formato do `Forja/Gantt.casos.md`).
  const viaBullet = citacoesEm('## UC-RGT-01 · caso\n- **Teste:** `Modules/F/Tests/Feature/FooControllerTest.php` — `UC-RGT-01`\n');
  check('bloco: nome vindo do bullet **Teste:** com caminho',
    viaBullet[0]?.nomes[0] === 'FooControllerTest', JSON.stringify(viaBullet));

  // B.3 — DEDUPE: o mesmo UC na tabela E no cabeçalho conta UMA vez. Sem isto, `citacoes`
  //       infla e a invariante "soma dos estados == citações" (B.4 do CLI) quebra.
  const doisFormatos = [
    '## UC-AB-01 · caso', '',
    '| UC | Caso | Prio | Âncora | Teste | Status |',
    '|----|------|------|--------|-------|--------|',
    '| UC-AB-01 | algo | must | `CU-1` | `MeuTest` | ✅ |',
  ].join('\n');
  check('bloco: UC já lido pela tabela NÃO é contado 2×',
    citacoesEm(doisFormatos).length === 1, JSON.stringify(citacoesEm(doisFormatos)));

  // B.4 — CONTROLE NEGATIVO do limite honesto: teste front-end não casa `\w*Test` (o
  //       run-set desta ferramenta é de lane PHP). Sai como citação SEM nome, nunca órfão.
  const tsx = citacoesEm('## UC-PRO-07 — voltar\nStatus: 🧪 (`tests/jana-pro-voltar.test.tsx` — 4 casos)\n');
  check('bloco: citação de teste .test.tsx não inventa nome PHP',
    tsx[0]?.nomes.length === 0, JSON.stringify(tsx));
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

  // ═══════════════════════════════════════════════════════════════════════════════════
  // B.8 · FORMATO 2 ATRAVESSANDO O CLI (2026-08-26) — as asserções (A2) provam o parser;
  //   estas provam o PIPELINE. Sem elas, o parser poderia estar certo e o `casos.md` de
  //   cabeçalho continuar não chegando na classificação — que é exatamente o defeito que
  //   este bloco fecha (o §5 2026-08-14 catalogou selftest verde exercitando a CÓPIA e
  //   não o chokepoint; aqui o chokepoint é o CLI, rodado de fora).
  //
  //   Fixture ruim → exit ≠ 0 · fixture boa → exit 0. Nesta altura a lane cobre
  //   `find Modules/M/Tests`, então `NaLaneTest` roda e um teste em `tests/Feature/Solto/`
  //   não roda — é o par mínimo que separa "morde" de "carimba".
  // ═══════════════════════════════════════════════════════════════════════════════════
  const cabecalho = (uc, corpo) => `## ${uc} — caso\n${corpo}\n`;

  // (a) FIXTURE BOA — cabeçalho citando teste que a lane executa: não pode ACRESCENTAR órfão.
  //     O predicado é DELTA, não absoluto: nesta altura o fixture já carrega dívida
  //     deliberada (UC-M-03 órfão de B.6, UC-M-04 ausente de B.5), então `--check` nu morde
  //     por herança e um assert absoluto mediria o vizinho, não esta fixture. (É a mesma
  //     armadilha do §5 2026-08-24 — guard com predicado absoluto travado por estado
  //     herdado; ela reapareceu dentro do próprio bite-test.)
  const antes = resumoDe(rodar('--json'));
  put('resources/js/Pages/M/BlocoOk.casos.md', cabecalho('UC-M-05', 'Status: 🧪 (NaLaneTest — cobre o caso)'));
  git('add', '-A');
  git('commit', '-qm', 'bloco na lane');
  const depois = resumoDe(rodar('--json'));
  check('bloco/CLI: cabeçalho citando teste EM LANE não acrescenta órfão',
    antes && depois && depois.orfao === antes.orfao, `${antes?.orfao} -> ${depois?.orfao}`);
  check('bloco/CLI: … e entra como na-lane (+1)',
    antes && depois && depois.na_lane === antes.na_lane + 1, `${antes?.na_lane} -> ${depois?.na_lane}`);
  check('bloco/CLI: … e o UC não é acusado no relatório', !/UC-M-05/.test(rodar().stdout));

  // (b) FIXTURE RUIM — cabeçalho citando teste que lane NENHUMA executa: TEM de morder.
  //     Isolado por baseline FRESCO tirado ANTES da fixture. Sem isso o assert passa por
  //     herança: a esta altura o fixture já tem UC-M-03 órfão e UC-M-04 ausente, então
  //     `--check` nu sai 1 mesmo com o parser CEGO — foi o que o controle negativo
  //     (rodar este arquivo contra o parser antigo) mostrou: 6 asserções caíam e esta NÃO.
  //     Assert que o estado herdado satisfaz sozinho não discrimina nada.
  check('bloco/CLI: baseline fresco LIBERA o estado herdado', rodar('--write-baseline', 'b2.json').status === 0);
  check('bloco/CLI: … confirmado (sem fixture nova, não morde)', rodar('--check', '--baseline', 'b2.json').status === 0);
  const antesRuim = resumoDe(rodar('--json'));

  put('tests/Feature/Solto/BlocoOrfaoTest.php', '<?php\n');
  put('resources/js/Pages/M/BlocoOrfao.casos.md', cabecalho('UC-M-06', '- **Teste:** `tests/Feature/Solto/BlocoOrfaoTest.php` — `UC-M-06`'));
  git('add', '-A');
  git('commit', '-qm', 'bloco orfao');
  check('bloco/CLI: cabeçalho citando teste ÓRFÃO morde o no-new-lie',
    rodar('--check', '--baseline', 'b2.json').status === 1);
  check('bloco/CLI: … e o relatório NOMEIA o UC (acionável por tela)', /UC-M-06/.test(rodar().stdout));
  const rr = resumoDe(rodar('--json'));
  check('bloco/CLI: … contado como órfão (+1), não como sem-teste',
    rr && antesRuim && rr.orfao === antesRuim.orfao + 1, `${antesRuim?.orfao} -> ${rr?.orfao}`);
  check('bloco/CLI: invariante soma dos estados == citações continua de pé',
    rr && (rr.na_lane + rr.orfao + rr.quarentena + rr.arquivo_ausente + rr.sem_teste) === rr.citacoes, JSON.stringify(rr));
} finally {
  rmSync(raiz, { recursive: true, force: true });
}

console.log(`\n${fails === 0 ? 'SELFTEST OK' : 'SELFTEST FALHOU'} — ${fails} falha(s)`);
process.exit(fails === 0 ? 0 : 1);
