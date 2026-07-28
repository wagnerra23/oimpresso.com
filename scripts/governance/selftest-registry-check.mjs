#!/usr/bin/env node
// selftest-registry-check.mjs — P15 entrega 3: teste .mjs órfão de workflow (advisory).
//
// POR QUE EXISTE: o anti-padrão "Modules/X/Tests/ sem registrar em phpunit.xml → falsa
// cobertura" (proibicoes.md §Código) REENCARNOU em .mjs — meta-teste do distiller órfão de
// CI, wrapper sem selftest (adversário 2026-07-13 wf_33e38126; chip A3 fechou os casos da
// época, este guard impede o PRÓXIMO). Um `*.test.mjs` que existe no repo mas nenhum
// workflow invoca passa VERDE na máquina do dev e NUNCA roda no CI — cobertura narrada,
// não testada.
//
// O QUE FAZ: varre `scripts/**/*.test.mjs` + `.claude/hooks/*.test.mjs` e confere se o
// path (posix) aparece em algum `.github/workflows/*.yml` — órfão avermelha o advisory.
// Substring simples e determinístico: registrado = o path literal aparece no YAML
// (é como todos os steps invocam: `run: node scripts/...`). Sem parser de YAML de
// propósito — menos superfície, zero deps.
//
// É CONSISTÊNCIA INTERNA (o teste existe mas o CI não o conhece), prima do doneness v2 —
// NÃO gate-de-presença (não exige "teste no diff"; exige que teste EXISTENTE não seja
// letra morta). ADVISORY de nascença (lei ADR 0314: required = só Tier-0): exit 0 no modo
// default; --check (exit 1) é o primitivo de promoção futura por calendário (ADR 0275).
//
// ── MODO --scripts (2026-07-26): órfão de SCRIPT, não de teste ──────────────────
// Auditoria adversarial 07-26 achou o ponto cego: este guard estava VERDE com 0 órfãos
// (125/125 testes registrados) enquanto 12 de 88 scripts não-teste em scripts/governance/
// não têm invocador executável nenhum. Regra certa, superfície errada — o mesmo mecanismo
// (varre dir, cruza com corpus, reporta) só não olhava os scripts.
//
// POR QUE report-only (exit 0 SEMPRE, sem --check): "0 invocador" NÃO é sinônimo de obra
// parada. MEDIDO nos 12: 6+ são CLI manual POR DESIGN (`adr-supersede` é transacional do
// autor da ADR; `doc-id-stamp` é stamper de um PR nomeado; `funcao-scorecard-humano` fecha
// rodada humana) — ter invocador em CI seria ERRADO pra eles. Testei o critério sugerido
// ("header declara gate/cadência + implementa --check"): acusa 3, dos quais 1 é falso
// positivo (`doc-id-stamp`) → precisão 67%. Regex sobre prosa não discrimina "repetível"
// de "execução única", e gate heurístico que avermelha legado é o anti-padrão que o §5
// das proibições mata (allowlist-de-pasta 06-30, guard @scope 07-09).
// Então: lista candidatos COM os sinais medidos e deixa o julgamento pro humano — mesmo
// desenho do `component-registry-check --roles` (report-only aceito pelo projeto).
//
// USO (na raiz do repo):
//   node scripts/governance/selftest-registry-check.mjs             # relatório advisory (exit 0)
//   node scripts/governance/selftest-registry-check.mjs --json      # JSON determinístico
//   node scripts/governance/selftest-registry-check.mjs --check     # exit 1 se houver TESTE órfão
//   node scripts/governance/selftest-registry-check.mjs --scripts   # SCRIPTS sem invocador (report-only)
//   node scripts/governance/selftest-registry-check.mjs --selftest  # fixtures herméticas (CI)
//
// Node puro (fs). Sem deps, sem DB, sem PHP. Idioma: clone de doneness-lint.mjs (ADR 0302).

import { readdirSync, readFileSync, existsSync, mkdtempSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { spawnSync, execSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

const posix = (p) => p.replace(/\\/g, '/');

/** varre dir recursivamente por *.test.mjs — retorna paths RELATIVOS posix ao root. */
export function collectTestFiles(root) {
  const out = [];
  const walk = (dir) => {
    let entries;
    try { entries = readdirSync(join(root, dir), { withFileTypes: true }); } catch { return; }
    for (const e of entries) {
      if (e.name === 'node_modules' || e.name.startsWith('.git')) continue;
      const rel = dir ? `${dir}/${e.name}` : e.name;
      if (e.isDirectory()) walk(rel);
      else if (e.name.endsWith('.test.mjs')) out.push(posix(rel));
    }
  };
  walk('scripts');
  // hooks: não-recursivo de propósito (testes de hook vivem flat em .claude/hooks/)
  try {
    for (const e of readdirSync(join(root, '.claude', 'hooks'))) {
      if (e.endsWith('.test.mjs')) out.push(`.claude/hooks/${e}`);
    }
  } catch { /* sem hooks dir — ok */ }
  return out.sort();
}

/** concatena o conteúdo de todos os workflows (.yml/.yaml). */
export function collectWorkflowText(root) {
  const dir = join(root, '.github', 'workflows');
  if (!existsSync(dir)) return '';
  return readdirSync(dir)
    .filter((f) => /\.ya?ml$/.test(f)).sort()
    .map((f) => readFileSync(join(dir, f), 'utf8'))
    .join('\n');
}

/** órfão = test file cujo path literal não aparece em nenhum workflow. */
export function findOrphans(testFiles, workflowText) {
  return testFiles.filter((f) => !workflowText.includes(f));
}

// ── SCRIPTS órfãos (report-only) ────────────────────────────────────────────────

/** Prefixos onde um invocador EXECUTÁVEL pode viver (doc não conta — .md só cita). */
const PREFIXOS_INVOCADOR = ['.github/', 'package.json', 'composer.json', '.claude/hooks/',
  '.claude/settings', 'scripts/', 'tools/', 'docker/', 'bin/', 'app/', 'Modules/'];

/** Scripts não-teste de scripts/governance/ (paths relativos posix). */
export function collectScriptFiles(root) {
  try {
    return readdirSync(join(root, 'scripts', 'governance'))
      .filter((f) => f.endsWith('.mjs') && !f.endsWith('.test.mjs')).sort();
  } catch { return []; }
}

/**
 * NÚCLEO PURO: dado o basename e um mapa path→conteúdo, quais arquivos o citam.
 * Separa executável (yml/json/mjs/sh/php…) de doc (.md) — doc citar não é invocar.
 */
/**
 * Uma linha é COMENTÁRIO no idioma do arquivo que a contém?
 *
 * Isto existe porque a versão anterior classificava por EXTENSÃO
 * (`p.endsWith('.md') ? doc : exec`): qualquer menção dentro de um `.mjs`/`.yml`/`.php`
 * contava como invocador EXECUTÁVEL, mesmo sendo comentário. Medido em 2026-07-28 no
 * corpus real: o detector ficava verde para 3 scripts cujo único "invocador" era um
 * comentário — `governance-audit.mjs` (comentário em app/Console/Kernel.php:461),
 * `hook-bites.mjs` (comentário em .claude/hooks/modulo-preflight-warning.mjs:69) e
 * `reguas-cross-model.mjs` (docblock em scripts/pr-critic/critica.mjs:320).
 * Custo real: na triagem dos 13 de 2026-07-27 ([W]: "eu quero que ligue... faça todos"),
 * esses 3 NUNCA chegaram à mesa — e um deles é o `hook-bites`, o dead-man's-switch dos
 * hooks, ele próprio morto. Mesma família do `plans-index` (2026-07-24).
 */
export function ehComentario(linha, path) {
  const t = linha.trim();
  if (t === '') return true;
  if (/\.(ya?ml|toml|sh|ps1)$/.test(path)) return t.startsWith('#');
  if (/\.(mjs|js|cjs|php)$/.test(path)) return t.startsWith('//') || t.startsWith('*') || t.startsWith('/*');
  return false;                                   // .json/.md: sem sintaxe de comentário
}

/**
 * Invocadores de `base`, separados em EXECUTÁVEL vs DOC.
 *
 * Regra: a menção precisa aparecer em pelo menos uma linha que NÃO seja comentário —
 * e o arquivo não pode ser `.md` (doc nunca executa). Sem isso, "tem invocador" mede
 * a presença do nome no repo, não a invocação.
 */
export function acharInvocadores(base, conteudo) {
  const self = `scripts/governance/${base}`;
  const selfTest = `scripts/governance/${base.replace('.mjs', '.test.mjs')}`;
  const exec = [], doc = [];
  for (const [p, txt] of conteudo) {
    if (p === self || p === selfTest || !txt.includes(base)) continue;
    if (p.endsWith('.md')) { doc.push(p); continue; }
    const linhas = txt.split(/\r?\n/).filter((l) => l.includes(base));
    const executavel = linhas.some((l) => !ehComentario(l, p));
    (executavel ? exec : doc).push(p);            // menção só-em-comentário => DOC
  }
  return { exec, doc };
}

/** Lê o corpus de invocadores sem shell (pathspec com aspas quebra no cmd.exe do Windows). */
function lerCorpus(root) {
  const conteudo = new Map();
  let todos = [];
  try {
    todos = execSync('git ls-files', { cwd: root, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 })
      .split('\n').filter(Boolean);
  } catch { return conteudo; }
  for (const p of todos) {
    if (!PREFIXOS_INVOCADOR.some((pre) => p.startsWith(pre))) continue;
    if (!/\.(ya?ml|json|mjs|js|cjs|sh|ps1|php|md|toml)$/.test(p)) continue;
    try { conteudo.set(p, readFileSync(join(root, p), 'utf8')); } catch { /* ignora */ }
  }
  return conteudo;
}

function reportScripts(root, { json = false } = {}) {
  const scripts = collectScriptFiles(root);
  const conteudo = lerCorpus(root);

  // GUARDA ANTI-VÁCUO. Sem ela, qualquer falha ao montar o corpus (git ausente, erro
  // de leitura, import faltando) vira "corpus vazio" → NENHUM script tem invocador →
  // 100% acusado de órfão. Foi exatamente o que aconteceu ao escrever este modo:
  // `execSync` não estava importado, o try/catch engoliu o ReferenceError e a saída
  // dizia "88 de 88 órfãos" com toda a cara de resultado legítimo. Medir a AUSÊNCIA de
  // algo exige provar que a fonte foi lida — senão o instrumento reporta o próprio
  // defeito como achado. (§5: `cmd || echo "não tem"` mente quando o cmd nem roda.)
  if (conteudo.size === 0) {
    console.error('✗ corpus de invocadores VAZIO — `git ls-files` falhou ou nenhum arquivo foi lido.');
    console.error('  NÃO é "todos órfãos": é o instrumento quebrado. Rode na raiz de um clone git.');
    process.exit(2);
  }

  const orfaos = [];
  for (const f of scripts) {
    const { exec, doc } = acharInvocadores(f, conteudo);
    if (exec.length) continue;
    let txt = '';
    try { txt = readFileSync(join(root, 'scripts', 'governance', f), 'utf8'); } catch { /* ignora */ }
    const header = txt.split('\n').slice(0, 45).join('\n').toLowerCase();
    orfaos.push({
      script: f,
      citado_em_doc: doc.length,
      // SINAIS (não veredito): ajudam o humano a separar "CLI manual por design" de
      // "foi construído pra rodar sozinho e não roda". Precisão medida ~67% — por isso
      // report-only. Ver docblock do modo.
      implementa_check: /--check/.test(txt),
      header_cita_gate: /\b(gate|sentinela|advisory|catraca|watchdog|cron|nightly)\b/.test(header),
    });
  }
  const out = {
    _meta: {
      guard: 'script órfão de invocador (report-only · auditoria 2026-07-26)',
      generator: 'scripts/governance/selftest-registry-check.mjs --scripts',
      regra: 'órfão = .mjs não-teste em scripts/governance/ que nenhum arquivo executável (yml/json/mjs/sh/php) cita. Citação só em .md NÃO conta como invocador.',
      fase: 'REPORT-ONLY — exit 0 sempre. "0 invocador" não é sinônimo de obra parada: CLI manual por design é legítimo. Os campos implementa_check/header_cita_gate são SINAIS, não veredito.',
    },
    summary: { scripts_total: scripts.length, orfaos: orfaos.length },
    orfaos,
  };
  if (json) { process.stdout.write(JSON.stringify(out, null, 2) + '\n'); return out; }
  console.log(`\n  SCRIPT ÓRFÃO (report-only) · ${scripts.length} scripts não-teste · ${orfaos.length} sem invocador executável\n`);
  for (const o of orfaos) {
    const sinais = [o.implementa_check ? '--check' : null, o.header_cita_gate ? 'header cita gate/cadência' : null]
      .filter(Boolean).join(' · ') || 'sem sinal de automação';
    console.log(`  ⚪ ${o.script.padEnd(38)} ${sinais}`);
  }
  console.log(`\n  Os 2 sinais juntos sugerem "construído pra rodar sozinho e não roda" — mas é PALPITE,`);
  console.log(`  não veredito (medido: ~67% de precisão). CLI manual por design é legítimo e comum aqui.`);
  console.log(`  Decisão é humana: ligar (agendar/step de CI) ou aposentar (remover + lápide no §5).\n`);
  return out;
}

function report(root, { json = false } = {}) {
  const tests = collectTestFiles(root);
  const wfText = collectWorkflowText(root);
  const orphans = findOrphans(tests, wfText);
  const out = {
    _meta: {
      guard: 'selftest-registry — teste .mjs órfão de workflow (P15 entrega 3). Reencarnação .mjs do "Tests/ sem phpunit.xml = falsa cobertura" (proibicoes §Código).',
      generator: 'scripts/governance/selftest-registry-check.mjs',
      regra: 'órfão = *.test.mjs em scripts/** ou .claude/hooks/ cujo path NÃO aparece em .github/workflows/*.yml. Registrado = path literal no YAML.',
      fase: 'ADVISORY (lei ADR 0314) — exit 0 no default; --check (exit 1) é o primitivo de promoção (calendário ADR 0275).',
      determinismo: 'sem timestamps/sha — re-run sem mudança = diff vazio',
    },
    summary: { tests_total: tests.length, registrados: tests.length - orphans.length, orfaos: orphans.length },
    orfaos: orphans,
  };
  if (json) { process.stdout.write(JSON.stringify(out, null, 2) + '\n'); return out; }
  console.log(`\n  SELFTEST-REGISTRY — teste .mjs órfão de workflow (P15) · ${tests.length} testes · ${orphans.length} órfão(s)\n`);
  if (orphans.length) {
    for (const o of orphans) console.log(`  🔴 ÓRFÃO: ${o} — existe no repo, nenhum workflow invoca (cobertura narrada, não testada)`);
    console.log(`\n  Fix: adicionar step em .github/workflows/governance-script-tests.yml (\`run: node <path>\`)`);
    console.log(`  ou remover o teste morto. Origem: proibicoes §"Tests/ sem phpunit.xml" + P15 entrega 3.`);
  } else {
    console.log('  🟢 zero órfãos — todo *.test.mjs está invocado em algum workflow.');
  }
  console.log('');
  return out;
}

// ── selftest hermético (fixtures em tmp — CI roda com --selftest) ────────────────
function selftest() {
  let fails = 0;
  const check = (n, c, extra = '') => { console.log((c ? '[OK]   ' : '[FAIL] ') + n + (c ? '' : '  → ' + extra)); if (!c) fails++; };
  const tmp = mkdtempSync(join(tmpdir(), 'selftest-registry-'));
  mkdirSync(join(tmp, 'scripts', 'governance'), { recursive: true });
  mkdirSync(join(tmp, '.claude', 'hooks'), { recursive: true });
  mkdirSync(join(tmp, '.github', 'workflows'), { recursive: true });
  writeFileSync(join(tmp, 'scripts', 'governance', 'registrado.test.mjs'), '// ok\n');
  writeFileSync(join(tmp, 'scripts', 'governance', 'orfao.test.mjs'), '// órfão\n');
  writeFileSync(join(tmp, '.claude', 'hooks', 'hook-orfao.test.mjs'), '// órfão hook\n');
  writeFileSync(join(tmp, 'scripts', 'governance', 'nao-teste.mjs'), '// não é .test.mjs\n');
  writeFileSync(join(tmp, '.github', 'workflows', 'ci.yml'), 'jobs:\n  t:\n    steps:\n      - run: node scripts/governance/registrado.test.mjs\n');

  const tests = collectTestFiles(tmp);
  check('coleta os 3 .test.mjs (scripts recursivo + hooks flat)', tests.length === 3, JSON.stringify(tests));
  check('não coleta .mjs comum', !tests.includes('scripts/governance/nao-teste.mjs'));
  const orphans = findOrphans(tests, collectWorkflowText(tmp));
  check('registrado NÃO é órfão', !orphans.includes('scripts/governance/registrado.test.mjs'));
  check('órfão de scripts detectado', orphans.includes('scripts/governance/orfao.test.mjs'), JSON.stringify(orphans));
  check('órfão de hooks detectado', orphans.includes('.claude/hooks/hook-orfao.test.mjs'));

  // bite/release E2E via exit code (--check morde, default advisory não)
  const me = process.argv[1];
  const run = (...args) => spawnSync(process.execPath, [me, ...args], { cwd: tmp, encoding: 'utf8' });
  check('default (advisory): exit 0 mesmo com órfão', run().status === 0);
  check('--check: exit 1 com órfão (bite)', run('--check').status === 1);
  writeFileSync(join(tmp, '.github', 'workflows', 'ci.yml'),
    'jobs:\n  t:\n    steps:\n      - run: node scripts/governance/registrado.test.mjs\n      - run: node scripts/governance/orfao.test.mjs\n      - run: node .claude/hooks/hook-orfao.test.mjs\n');
  check('--check: exit 0 quando todos registrados (release)', run('--check').status === 0);
  const j = JSON.parse(run('--json').stdout);
  check('--json determinístico com summary', j.summary.tests_total === 3 && j.summary.orfaos === 0, JSON.stringify(j.summary));

  // ── modo --scripts (report-only): núcleo puro acharInvocadores ──────────────
  const corpus = new Map([
    ['.github/workflows/ci.yml', 'run: node scripts/governance/usado.mjs --check\n'],
    ['memory/reference/doc.md', 'o `orfao-citado-em-doc.mjs` faz X\n'],
    ['scripts/governance/usado.mjs', '// self — deve ser ignorado\n'],
    // ── fixtures do bug de 2026-07-28: menção SÓ em comentário ────────────────
    // A versão anterior classificava por EXTENSÃO, então estes 3 davam "tem invocador
    // executável" e o script sumia do relatório. Custo real: 3 órfãos (incl. o
    // `hook-bites`, dead-man's-switch dos hooks) ficaram fora da triagem que [W] mandou
    // fazer em 2026-07-27. Cada linha abaixo é um dos idiomas onde isso acontecia.
    ['app/Console/Kernel.php', "        // roda via so-em-comentario-php.mjs no cron\n"],
    ['.claude/hooks/algum-hook.mjs', '// pareado com so-em-comentario-js.mjs (ver ADR)\n'],
    ['.github/workflows/x.yml', '      # so-em-comentario-yml.mjs roda na nightly\n'],
    // controle POSITIVO no mesmo idioma: código real na mesma linguagem TEM que contar
    ['.claude/hooks/outro-hook.mjs', "spawnSync('node', ['scripts/governance/exec-real-js.mjs']);\n"],
    ['.github/workflows/y.yml', '      - run: node scripts/governance/exec-real-yml.mjs\n'],
  ]);
  check('--scripts: script citado por workflow NÃO é órfão',
    acharInvocadores('usado.mjs', corpus).exec.length === 1);
  check('--scripts: citação só em .md NÃO conta como invocador (doc ≠ execução)',
    acharInvocadores('orfao-citado-em-doc.mjs', corpus).exec.length === 0
    && acharInvocadores('orfao-citado-em-doc.mjs', corpus).doc.length === 1);
  check('--scripts: script sem nenhuma citação → 0 exec e 0 doc',
    acharInvocadores('inexistente.mjs', corpus).exec.length === 0);
  check('--scripts: self-referência não conta como invocador',
    !acharInvocadores('usado.mjs', corpus).exec.includes('scripts/governance/usado.mjs'));

  // ── MORDIDA do fix 2026-07-28 (classificar por LINHA, não por extensão) ─────
  for (const [nome, idioma] of [['so-em-comentario-php.mjs', 'PHP //'],
    ['so-em-comentario-js.mjs', 'JS //'], ['so-em-comentario-yml.mjs', 'YAML #']]) {
    const r = acharInvocadores(nome, corpus);
    check(`--scripts: menção só em comentário ${idioma} NÃO é invocador executável`,
      r.exec.length === 0 && r.doc.length === 1, JSON.stringify(r));
  }
  // CONTROLE POSITIVO — sem ele o teste acima passaria com um classificador que diz
  // "tudo é comentário", que é o erro simétrico e igualmente inútil.
  for (const [nome, idioma] of [['exec-real-js.mjs', 'JS'], ['exec-real-yml.mjs', 'YAML']]) {
    check(`--scripts: código REAL em ${idioma} continua contando como invocador`,
      acharInvocadores(nome, corpus).exec.length === 1);
  }
  // Guarda de idioma: `#` é comentário em YAML/sh, NÃO em JS (lá é privado de classe).
  check('--scripts: `#` no meio de JS não vira comentário (idioma importa)',
    !ehComentario('  this.#privado = usa.mjs;', 'x.mjs'));
  check('--scripts: linha vazia conta como comentário (não é invocação)',
    ehComentario('   ', 'x.yml'));
  // CONTROLE-NEGATIVO da guarda anti-vácuo: o tmp NÃO é repo git, então o corpus não
  // pode ser montado. O certo é sair 2 ("instrumento quebrado"), JAMAIS reportar
  // "todos órfãos" — que foi o bug real ao escrever este modo (execSync não importado
  // → catch engoliu → 88 de 88 acusados, com cara de resultado legítimo).
  const semCorpus = run('--scripts');
  check('--scripts: sem corpus (fora de repo git) → exit 2, não "todos órfãos"',
    semCorpus.status === 2, `status=${semCorpus.status}`);
  check('--scripts: a mensagem diz que é o INSTRUMENTO, não achado',
    /instrumento quebrado/i.test(semCorpus.stderr || ''), (semCorpus.stderr || '').slice(0, 80));

  rmSync(tmp, { recursive: true, force: true });
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — órfão morde em --check, registrado solta; advisory default exit 0 (P15 entrega 3 · ADR 0314).');
  process.exit(fails ? 1 : 0);
}

// ── entry-point ──────────────────────────────────────────────────────────────────
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) selftest();
  else if (process.argv.includes('--scripts')) {
    // report-only: exit 0 SEMPRE, mesmo com órfãos (ver docblock do modo).
    reportScripts(process.cwd(), { json: process.argv.includes('--json') });
    process.exit(0);
  } else {
    const out = report(process.cwd(), { json: process.argv.includes('--json') });
    process.exit(process.argv.includes('--check') && out.summary.orfaos > 0 ? 1 : 0);
  }
}
