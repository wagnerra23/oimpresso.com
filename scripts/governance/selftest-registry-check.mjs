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
// ── AMPLIAÇÃO DE ESCOPO (2026-08-05): scripts/governance/ → scripts/** ──────────
// A superfície ainda estava errada, um nível acima: o escopo era `scripts/governance/`
// APENAS, então todo script fora dessa pasta ficava invisível POR CONSTRUÇÃO — não por
// estar são. Custo REAL medido: `scripts/perf-static-guard.mjs` (ratchet de performance
// COM baseline commitado, Onda 4 / AUDITORIA-PERFORMANCE-2026-07) estava sem invocador
// nenhum desde 2026-07-05 e ninguém avisou, porque mora em `scripts/` (raiz). No mês
// parado entrou 1 regressão nova (paginate_sem_eager 28→29: +Modules/KB/…/KbController
// :102, +Modules/VozDoCliente/…/SinalController:50, −Modules/SRS/…/InboxController:29 —
// diff das listas COMPLETAS entre a árvore de 07-05 e o HEAD) e 2 contadores MELHORARAM
// sem ninguém travar o ganho (8→7 e 20→15). A catraca existia, tinha baseline, e não
// mordia: é o "gate mudo" do §Sempre fazer #5 das proibições.
//
// FP MEDIDO ANTES de ampliar (corpus real, 2026-08-05): 107 → 196 scripts varridos e
// 5 → 13 órfãos (+8). Sem explosão — a lista nova é toda plausível e triável à mão.
// A ampliação corrigiu junto 1 FP de CRITÉRIO: `tests/` faltava em PREFIXOS_INVOCADOR,
// então `deploy-wave-z2-integracao-vendas-oficina.sh` saía como órfão embora o
// `tests/Feature/Docs/WaveZ2DocumentationGuardTest.php` o valide (shebang + readable).
// Teste Pest É invocador executável. Com o prefixo corrigido: 14 → 13.
//
// SEGUE REPORT-ONLY — ampliar o RADAR não muda a natureza do julgamento (ver abaixo), e
// a lista nova traz categoria que este detector não consegue ver nem em princípio: script
// que roda FORA do repo (`scripts/infra/get-secret.sh` é deployado em /root/bin/ no CT 100).
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
// ── SELFTEST EMBUTIDO (2026-07-29): a segunda FORMA do mesmo órfão ──────────────
// Selftest vem em duas formas, e este guard só via uma: arquivo `*.test.mjs` (via) e
// MODO `--selftest` dentro do próprio script (não via). Medido: 78 scripts implementam
// o modo embutido. O `cron-watchdog` era um deles — 9 asserts que nenhum workflow
// rodava — e foi justamente por isso que o eixo 1 dele pôde afirmar "todos os crons
// vivos" tendo medido ZERO (proibicoes §5 2026-07-29). Regra certa, superfície
// faltando: estendido o dono, sem abrir régua paralela (§5 2026-07-09).
//
// A REGRA DO IRMÃO é o que torna isto usável, e foi MEDIDA antes de armar: acusar todo
// script com `--selftest` sem invocador dá 46 acusados, dos quais 39 (85%) são FALSO-
// POSITIVO — já cobertos por um `*.test.mjs` irmão wirado. Ela é CONSERVADORA de
// propósito: prefere deixar passar a acusar o legítimo, que é a direção certa pra um
// detector que não pode gritar lobo (§5 mata 4 guards sintáticos que reprovavam o
// legítimo). Fila zerada no mesmo PR que a criou → `--check` cobre as duas formas sem
// grandfathering: a catraca nasce segurando a linha, não perdoando dívida.
//
// USO (na raiz do repo):
//   node scripts/governance/selftest-registry-check.mjs             # relatório advisory (exit 0)
//   node scripts/governance/selftest-registry-check.mjs --json      # JSON determinístico
//   node scripts/governance/selftest-registry-check.mjs --check     # exit 1 se houver órfão (arquivo OU embutido)
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

// ── SELFTEST EMBUTIDO órfão (2026-07-29) ───────────────────────────────────────
// O ponto cego: selftest vem em DUAS formas — arquivo `*.test.mjs` (o que este guard já
// via) e MODO `--selftest` dentro do próprio script (que ele não via). Medido no corpus
// real: 77 scripts implementam o modo embutido. O `cron-watchdog` era um deles, com 9
// asserts que nunca rodaram em CI — e foi por isso que o eixo 1 dele pôde afirmar "todos
// os crons vivos" tendo medido zero (proibicoes §5 2026-07-29). Mesma regra do guard,
// superfície que faltava — estender o dono, não abrir paralelo (§5 2026-07-09).

/**
 * O script IMPLEMENTA um modo `--selftest`? Casa o DESPACHO (leitura de argv), nunca a
 * palavra solta: quase todo script cita `--selftest` no bloco USO do cabeçalho, e contar
 * a menção classificaria como "tem selftest" quem só documenta.
 */
export function implementaSelftest(src) {
  return /(?:ARGS|args|flags)\s*\.has\(\s*['"]--selftest['"]\s*\)/.test(src)
    || /argv[\s\S]{0,40}?\.includes\(\s*['"]--selftest['"]\s*\)/.test(src);
}

/** Scripts NÃO-teste (scripts/** + .claude/hooks/*) que implementam o modo embutido. */
export function collectEmbeddedSelftests(root, lerArquivo = (p) => readFileSync(join(root, p), 'utf8')) {
  const cands = [];
  const walk = (dir) => {
    let entries;
    try { entries = readdirSync(join(root, dir), { withFileTypes: true }); } catch { return; }
    for (const e of entries) {
      if (e.name === 'node_modules' || e.name.startsWith('.git')) continue;
      const rel = dir ? `${dir}/${e.name}` : e.name;
      if (e.isDirectory()) walk(rel);
      else if (e.name.endsWith('.mjs') && !e.name.endsWith('.test.mjs')) cands.push(posix(rel));
    }
  };
  walk('scripts');
  try {
    for (const e of readdirSync(join(root, '.claude', 'hooks'))) {
      if (e.endsWith('.mjs') && !e.endsWith('.test.mjs')) cands.push(`.claude/hooks/${e}`);
    }
  } catch { /* sem hooks dir — ok */ }
  const out = [];
  for (const p of cands) {
    let src; try { src = lerArquivo(p); } catch { continue; }
    if (implementaSelftest(src)) out.push(p);
  }
  return out.sort();
}

/**
 * `<path> --selftest` aparece numa linha NÃO-COMENTADA de algum invocador executável?
 * Comment-aware pelo mesmo motivo do `ehComentario`: um `# node x.mjs --selftest` dentro
 * de comentário de YAML documenta, não invoca.
 */
export function invocaSelftest(path, arquivos) {
  for (const { path: p, conteudo } of arquivos) {
    for (const linha of conteudo.split('\n')) {
      if (ehComentario(linha, p)) continue;
      const i = linha.indexOf(path);
      if (i >= 0 && linha.slice(i + path.length).includes('--selftest')) return true;
    }
  }
  return false;
}

/**
 * Órfão = implementa `--selftest` E ninguém o invoca com `--selftest` E não há
 * `.test.mjs` irmão que o CI rode.
 *
 * ⚠️ A REGRA DO IRMÃO É O QUE TORNA ISTO USÁVEL — e ela foi MEDIDA, não suposta. Sem
 * ela: 48 acusados de 77, dos quais 40 são FALSO-POSITIVO (83%) — scripts cujo núcleo
 * já é coberto por um `.test.mjs` irmão wirado (ex.: `module-group-resolve`,
 * `lapide-recheck`). Um gate com 83% de ruído é o guard sintático que o §5 das
 * proibições mata quatro vezes (allowlist-de-pasta · @scope · vocabulário · toHaveKey).
 */
export function findEmbeddedOrphans(scripts, arquivos, workflowText, existeIrmao) {
  return scripts.filter((p) => {
    if (invocaSelftest(p, arquivos)) return false;
    const irmao = p.replace(/\.mjs$/, '.test.mjs');
    if (existeIrmao(irmao) && workflowText.includes(irmao)) return false;
    return true;
  });
}

// ── SCRIPTS órfãos (report-only) ────────────────────────────────────────────────

/**
 * Prefixos onde um invocador EXECUTÁVEL pode viver (doc não conta — .md só cita).
 * `tests/` entrou em 2026-08-05: teste Pest que valida um script É invocador dele
 * (FP medido: WaveZ2DocumentationGuardTest.php × deploy-wave-z2-…sh).
 */
const PREFIXOS_INVOCADOR = ['.github/', 'package.json', 'composer.json', '.claude/hooks/',
  '.claude/settings', 'scripts/', 'tools/', 'docker/', 'bin/', 'app/', 'Modules/', 'tests/'];

/**
 * Scripts executáveis não-teste de `scripts/**` (paths relativos posix, recursivo).
 *
 * Escopo AMPLIADO em 2026-08-05 (era `scripts/governance/` raso — ver header): script
 * fora daquela pasta era invisível por construção, e foi assim que o `perf-static-guard`
 * ficou 1 mês desligado sem ninguém avisar. Exclui `node_modules/` (dependência vendorada,
 * não obra nossa) e `*.test/*.spec` (o eixo de teste órfão é outro modo deste mesmo guard).
 */
export function collectScriptFiles(root) {
  const out = [];
  const walk = (relDir) => {
    let entries;
    try { entries = readdirSync(join(root, relDir), { withFileTypes: true }); } catch { return; }
    for (const e of entries) {
      const rel = `${relDir}/${e.name}`;
      if (e.isDirectory()) { if (e.name !== 'node_modules') walk(rel); continue; }
      if (!/\.(mjs|js|cjs|sh)$/.test(e.name)) continue;
      if (/\.(test|spec)\.(mjs|js|cjs)$/.test(e.name)) continue;
      out.push(rel);
    }
  };
  walk('scripts');
  return out.sort();
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
export function acharInvocadores(alvo, conteudo) {
  // `alvo` aceita path relativo (`scripts/x/y.mjs`, escopo ampliado 2026-08-05) OU
  // basename puro (`y.mjs`), caso em que assume scripts/governance/ — compat com as
  // fixtures do --selftest, que passam só o nome.
  const self = alvo.includes('/') ? alvo : `scripts/governance/${alvo}`;
  const base = self.slice(self.lastIndexOf('/') + 1);
  const selfTest = self.replace(/\.(mjs|js|cjs)$/, '.test.$1');
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
    try { txt = readFileSync(join(root, f), 'utf8'); } catch { /* ignora */ }
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
      regra: 'órfão = script executável não-teste em scripts/** (recursivo, sem node_modules) que nenhum arquivo executável (yml/json/mjs/sh/php, incl. tests/) cita. Citação só em .md ou só em comentário NÃO conta como invocador.',
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
    console.log(`  ⚪ ${o.script.padEnd(52)} ${sinais}`);
  }
  console.log(`\n  Os 2 sinais juntos sugerem "construído pra rodar sozinho e não roda" — mas é PALPITE,`);
  console.log(`  não veredito (medido: ~67% de precisão). CLI manual por design é legítimo e comum aqui.`);
  console.log(`  Decisão é humana: ligar (agendar/step de CI) ou aposentar (remover + lápide no §5).\n`);
  return out;
}

/** Corpus de invocadores executáveis, por arquivo (comment-aware precisa da linha + idioma). */
function arquivosInvocadores(root) {
  const out = [];
  const dir = join(root, '.github', 'workflows');
  if (existsSync(dir)) {
    for (const f of readdirSync(dir).filter((x) => /\.ya?ml$/.test(x)).sort()) {
      out.push({ path: `.github/workflows/${f}`, conteudo: readFileSync(join(dir, f), 'utf8') });
    }
  }
  for (const f of ['package.json', 'composer.json']) {
    try { out.push({ path: f, conteudo: readFileSync(join(root, f), 'utf8') }); } catch { /* opcional */ }
  }
  return out;
}

function report(root, { json = false } = {}) {
  const tests = collectTestFiles(root);
  const wfText = collectWorkflowText(root);
  const orphans = findOrphans(tests, wfText);
  const arquivos = arquivosInvocadores(root);
  const embutidos = collectEmbeddedSelftests(root);
  const embOrfaos = findEmbeddedOrphans(embutidos, arquivos, wfText, (p) => existsSync(join(root, p)));
  const out = {
    _meta: {
      guard: 'selftest-registry — teste .mjs órfão de workflow (P15 entrega 3). Reencarnação .mjs do "Tests/ sem phpunit.xml = falsa cobertura" (proibicoes §Código).',
      generator: 'scripts/governance/selftest-registry-check.mjs',
      regra: 'órfão = *.test.mjs em scripts/** ou .claude/hooks/ cujo path NÃO aparece em .github/workflows/*.yml. Registrado = path literal no YAML.',
      regra_embutido: 'órfão embutido = script que IMPLEMENTA modo --selftest, ninguém o invoca com --selftest (linha não-comentada) e não há *.test.mjs irmão invocado. A regra do irmão é CONSERVADORA de propósito: prefere deixar passar a acusar o legítimo (sem ela, 85% de falso-positivo no corpus real).',
      fase: 'ADVISORY (lei ADR 0314) — exit 0 no default; --check (exit 1) é o primitivo de promoção (calendário ADR 0275).',
      determinismo: 'sem timestamps/sha — re-run sem mudança = diff vazio',
    },
    summary: {
      tests_total: tests.length, registrados: tests.length - orphans.length, orfaos: orphans.length,
      selftest_embutido_total: embutidos.length, orfaos_embutidos: embOrfaos.length,
    },
    orfaos: orphans,
    orfaos_embutidos: embOrfaos,
  };
  if (json) { process.stdout.write(JSON.stringify(out, null, 2) + '\n'); return out; }
  console.log(`\n  SELFTEST-REGISTRY — selftest órfão de workflow (P15) · ${tests.length} arquivo(s) *.test.mjs · ${embutidos.length} modo(s) --selftest embutido\n`);
  if (orphans.length) {
    for (const o of orphans) console.log(`  🔴 ÓRFÃO: ${o} — existe no repo, nenhum workflow invoca (cobertura narrada, não testada)`);
    console.log(`\n  Fix: adicionar step em .github/workflows/governance-script-tests.yml (\`run: node <path>\`)`);
    console.log(`  ou remover o teste morto. Origem: proibicoes §"Tests/ sem phpunit.xml" + P15 entrega 3.`);
  } else {
    console.log('  🟢 zero órfãos de arquivo — todo *.test.mjs está invocado em algum workflow.');
  }
  if (embOrfaos.length) {
    console.log('');
    for (const o of embOrfaos) {
      const irmao = o.replace(/\.mjs$/, '.test.mjs');
      const nota = existsSync(join(root, irmao)) ? ' (o irmão .test.mjs também está órfão — listado acima)' : '';
      console.log(`  🔴 ÓRFÃO EMBUTIDO: ${o} — implementa --selftest e nenhum workflow o roda${nota}`);
    }
    console.log(`\n  Fix: \`run: node <path> --selftest\`. Se o script depender de npm (gray-matter/ajv),`);
    console.log(`  a casa é um job que já instala — governance-script-tests.yml não instala nada de propósito.`);
  } else {
    console.log('  🟢 zero órfãos embutidos — todo modo --selftest é rodado por alguém.');
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
  // ── MORDIDA da ampliação de escopo 2026-08-05 (scripts/governance/ → scripts/**) ──
  // Sem estes, a ampliação seria "escrita e lembrada": o escopo raso passaria igual.
  // ⚠️ NOMES FICTÍCIOS OBRIGATÓRIOS (convenção do arquivo — `usado.mjs`, `exec-real-js.mjs`).
  // Fixture que cite o nome REAL de um script do repo vira invocador executável dele e o
  // ABSOLVE do relatório: o guard passa a se auto-silenciar. Aconteceu ao escrever estes
  // asserts — citei `perf-static-guard.mjs` e ele sumiu da lista (12 em vez de 13), logo
  // no PR cujo argumento era ele. Mesma assinatura da lápide §5 2026-07-26.
  const corpusAmpliado = new Map([
    ['.github/workflows/perf.yml', '      - run: node scripts/ratchet-ficticio-raiz.mjs\n'],
    ['tests/Feature/Docs/Guard.php', "    \$s = ROOT . '/scripts/deploy-ficticio.sh';\n"],
    ['memory/reference/doc.md', 'o `scripts/fora-de-governance.mjs` faz X\n'],
  ]);
  check('--scripts: alvo com path relativo (fora de scripts/governance/) é resolvido',
    acharInvocadores('scripts/ratchet-ficticio-raiz.mjs', corpusAmpliado).exec.length === 1);
  check('--scripts: invocador em tests/ conta como executável (teste Pest invoca)',
    acharInvocadores('scripts/deploy-ficticio.sh', corpusAmpliado).exec.length === 1);
  check('--scripts: script fora de governance citado só em .md segue órfão (doc ≠ exec)',
    acharInvocadores('scripts/fora-de-governance.mjs', corpusAmpliado).exec.length === 0
    && acharInvocadores('scripts/fora-de-governance.mjs', corpusAmpliado).doc.length === 1);
  check('--scripts: basename puro segue assumindo scripts/governance/ (compat)',
    acharInvocadores('usado.mjs', corpus).exec.length === 1);
  {   // o escopo varre RECURSIVO e ignora node_modules — senão a ampliação não pegaria nada
    const dirTmp = mkdtempSync(join(tmpdir(), 'scope-'));
    mkdirSync(join(dirTmp, 'scripts', 'infra'), { recursive: true });
    mkdirSync(join(dirTmp, 'scripts', 'x', 'node_modules'), { recursive: true });
    writeFileSync(join(dirTmp, 'scripts', 'raiz.mjs'), '// x\n');
    writeFileSync(join(dirTmp, 'scripts', 'infra', 'fundo.sh'), '#!/bin/sh\n');
    writeFileSync(join(dirTmp, 'scripts', 'raiz.test.mjs'), '// teste, fora do eixo\n');
    writeFileSync(join(dirTmp, 'scripts', 'x', 'node_modules', 'dep.mjs'), '// vendorado\n');
    const achados = collectScriptFiles(dirTmp);
    check('--scripts: escopo pega subpasta e .sh, ignora node_modules e *.test',
      achados.join(',') === 'scripts/infra/fundo.sh,scripts/raiz.mjs', achados.join(','));
    rmSync(dirTmp, { recursive: true, force: true });
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

  // ── SELFTEST EMBUTIDO órfão (2026-07-29) ─────────────────────────────────────
  // O `cron-watchdog` tinha 9 asserts que nenhum workflow rodava, e foi por isso que ele
  // pôde afirmar "todos os crons vivos" sem ter medido nada (proibicoes §5 2026-07-29).
  // Detectar a FORMA (implementa o modo) tem que ser separado de detectar a MENÇÃO.
  check('embutido: despacho por ARGS.has() é implementação',
    implementaSelftest("const ARGS = new Set(argv);\nif (ARGS.has('--selftest')) rodar();"));
  check('embutido: despacho por argv.includes() é implementação',
    implementaSelftest("if (process.argv.includes('--selftest')) rodar();"));
  // CONTROLE NEGATIVO — quase todo script CITA `--selftest` no bloco USO do cabeçalho.
  // Contar a menção classificaria "documenta" como "tem selftest" e inflaria o universo.
  check('embutido: só CITAR --selftest no cabeçalho NÃO é implementar',
    !implementaSelftest('// USO:\n//   node x.mjs --selftest   # fixtures herméticas\n'));

  const arqs = (pares) => pares.map(([path, conteudo]) => ({ path, conteudo }));
  const wfTexto = (objs) => objs.map((o) => o.conteudo).join('\n');

  // BITE: implementa, ninguém roda, sem irmão → acusado.
  const soOrfao = arqs([['.github/workflows/ci.yml', 'run: node scripts/governance/outro.mjs\n']]);
  check('BITE embutido: implementa --selftest e ninguém invoca → órfão',
    findEmbeddedOrphans(['scripts/governance/solto.mjs'], soOrfao, wfTexto(soOrfao), () => false).length === 1);

  // LIBERA 1: invocado com --selftest.
  const invocado = arqs([['.github/workflows/ci.yml', '        run: node scripts/governance/solto.mjs --selftest\n']]);
  check('LIBERA embutido: invocado com --selftest → não é órfão',
    findEmbeddedOrphans(['scripts/governance/solto.mjs'], invocado, wfTexto(invocado), () => false).length === 0);

  // LIBERA 2: a regra do irmão (o que mata 85% de FP) — irmão existe E é invocado.
  const irmao = arqs([['.github/workflows/ci.yml', '        run: node scripts/governance/solto.test.mjs\n']]);
  check('LIBERA embutido: irmão .test.mjs invocado cobre o script (regra que mata 85% de FP)',
    findEmbeddedOrphans(['scripts/governance/solto.mjs'], irmao, wfTexto(irmao), (p) => p === 'scripts/governance/solto.test.mjs').length === 0);

  // CONTROLE NEGATIVO da regra do irmão: irmão que EXISTE mas ninguém roda não cobre
  // nada. Sem este assert, a regra viraria perdão cego — "tem arquivo irmão, então tá
  // coberto" é presença, não comportamento (LC-11).
  check('embutido: irmão que EXISTE mas NÃO é invocado não absolve (presença ≠ cobertura)',
    findEmbeddedOrphans(['scripts/governance/solto.mjs'], soOrfao, wfTexto(soOrfao), () => true).length === 1);

  // Comment-aware: `# node x --selftest` dentro de comentário documenta, não invoca.
  const soComentario = arqs([['.github/workflows/ci.yml', '      # run: node scripts/governance/solto.mjs --selftest\n']]);
  check('embutido: invocação só em COMENTÁRIO de YAML não conta',
    findEmbeddedOrphans(['scripts/governance/solto.mjs'], soComentario, wfTexto(soComentario), () => false).length === 1);

  // Ordem importa: o `--selftest` tem que vir DEPOIS do path na mesma linha, senão
  // "node a.mjs --selftest && node b.mjs" absolveria o b.mjs de carona.
  const carona = arqs([['.github/workflows/ci.yml', '        run: node scripts/governance/outro.mjs --selftest && node scripts/governance/solto.mjs\n']]);
  check('embutido: --selftest de OUTRO comando na mesma linha não absolve por carona',
    findEmbeddedOrphans(['scripts/governance/solto.mjs'], carona, wfTexto(carona), () => false).length === 1);

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
    // `--check` cobre as DUAS formas de selftest órfão. O embutido entrou junto porque a
    // fila dele foi zerada no mesmo PR (2026-07-29): sem legado pendurado, não há nada a
    // grandfatherizar — a catraca nasce segurando a linha, não perdoando dívida.
    const divida = out.summary.orfaos + out.summary.orfaos_embutidos;
    process.exit(process.argv.includes('--check') && divida > 0 ? 1 : 0);
  }
}
