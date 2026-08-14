#!/usr/bin/env node
// ─────────────────────────────────────────────────────────────────────────────
// dtcg-equivalence.test.mjs — self-test do check de equivalência DTCG.
//
// "Quem vigia os vigias" (SDD GT-G6): um gate que não morde é pior que nenhum.
// Este teste prova, sem tocar os arquivos versionados:
//   (1) FIXTURE BOA  → o check passa (rc0) sobre os tokens reais do repo.
//   (2) FIXTURE RUIM → o check FALHA (rc1) quando um token DTCG diverge do CSS.
//   (3) SEM-SOURCE   → token sem `com.oimpresso.source` FALHA (rc1) em vez de sair
//                      do denominador em silêncio (o `skipped` fechado em 2026-08-14).
//   (4) --schema     → forma boa passa (rc0), forma ruim falha (rc1), e os dois
//                      estados de NÃO-MEDIDO (schema ausente · ajv ausente) saem 2.
//
// Roda o check como subprocesso (node), inspeciona rc + saída JSON. As fixturas
// ruins são montadas em diretório temporário, NUNCA mutando
// resources/css/tokens/*.json do repo.
//
// Deps: os blocos (1)-(3) são Node puro. Os de `--schema` precisam de ajv/ajv-formats
// — as mesmas que `scripts/memory-schemas/validate.mjs` já usa. Sem elas o modo sai 2
// e estes asserts FALHAM de propósito: "não consegui medir" nunca é verde (§5
// 2026-07-29), então o self-test não tem caminho de skip silencioso.
//
// rc0 = self-test passou. Uso: node scripts/governance/dtcg-equivalence.test.mjs
// ─────────────────────────────────────────────────────────────────────────────
import { execFileSync, spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdtempSync, mkdirSync, cpSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '..', '..');
const CHECK = join(HERE, 'dtcg-equivalence.mjs');

let failures = 0;
function assert(cond, msg) {
  if (cond) { console.log(`  ✓ ${msg}`); }
  else { console.log(`  ✗ ${msg}`); failures++; }
}

function runCheck(checkPath) {
  // Retorna { rc, json }. IMPORTANTE: o check resolve ROOT via import.meta.url
  // (dirname(check)/../..), NÃO via cwd. Então pra apontar pro sandbox, rodamos
  // a CÓPIA do check que vive dentro do sandbox (checkPath), não o do repo.
  // O check faz process.exit(1) em divergência → execFileSync lança; capturamos status.
  try {
    const out = execFileSync('node', [checkPath, '--json'], { encoding: 'utf8' });
    return { rc: 0, json: JSON.parse(out) };
  } catch (e) {
    const out = e.stdout ? String(e.stdout) : '';
    let json = null;
    try { json = JSON.parse(out); } catch { /* saída não-json */ }
    return { rc: e.status ?? 1, json };
  }
}

console.log('dtcg-equivalence.test.mjs');

// ── (1) FIXTURE BOA — repo real ──────────────────────────────────────────────
console.log('\n[1] fixture boa (tokens reais do repo) → deve PASSAR (rc0)');
{
  const { rc, json } = runCheck(CHECK);
  assert(rc === 0, `rc0 sobre o repo real (rc=${rc})`);
  assert(json && json.summary && json.summary.ok === true, 'summary.ok === true');
  assert(json && json.summary.proven > 0, `provou >0 tokens (proven=${json?.summary?.proven})`);
  assert(json && json.summary.divergences === 0, 'divergences === 0');
}

// ── (2) FIXTURE RUIM — token adulterado num sandbox temporário ────────────────
console.log('\n[2] fixture ruim (1 token DTCG divergente) → deve FALHAR (rc1)');
{
  const sandbox = mkdtempSync(join(tmpdir(), 'dtcg-selftest-'));
  try {
    // Espelha a árvore mínima que o check lê: scripts/governance/ + resources/css/{tokens,*.css}
    mkdirSync(join(sandbox, 'scripts', 'governance'), { recursive: true });
    mkdirSync(join(sandbox, 'resources', 'css', 'tokens'), { recursive: true });
    const sandboxCheck = join(sandbox, 'scripts', 'governance', 'dtcg-equivalence.mjs');
    cpSync(CHECK, sandboxCheck);
    // Pós-ativação o check lê os _generated-*.css (SAÍDA do Style Dictionary, o
    // CSS que o build consome), não mais os blocos inline dos .css canônicos.
    // Copiamos os gerados reais pro sandbox; adulteramos só o $value no JSON →
    // JSON diverge do gerado (não-regenerado) → o check deve pegar.
    for (const gen of [
      '_generated-inertia-theme.css', '_generated-inertia-dark.css',
      '_generated-foundations-light.css', '_generated-foundations-dark.css',
      '_generated-cockpit-light.css', '_generated-cockpit-dark.css',
    ]) {
      cpSync(join(ROOT, 'resources', 'css', 'tokens', gen), join(sandbox, 'resources', 'css', 'tokens', gen));
    }
    for (const tj of ['base.tokens.json', 'semantic.tokens.json']) {
      cpSync(join(ROOT, 'resources', 'css', 'tokens', tj), join(sandbox, 'resources', 'css', 'tokens', tj));
    }
    // Adultera UM $value conhecido (color.primary roxo) pra um valor impossível,
    // SEM regenerar o gerado → o JSON deixa de bater com o _generated-inertia-theme.css.
    const semPath = join(sandbox, 'resources', 'css', 'tokens', 'semantic.tokens.json');
    const sem = JSON.parse(readFileSync(semPath, 'utf8'));
    sem.color.primary.$value = 'oklch(0.01 0.99 7)';
    writeFileSync(semPath, JSON.stringify(sem, null, 2));

    const { rc, json } = runCheck(sandboxCheck);
    assert(rc === 1, `rc1 com token adulterado (rc=${rc})`);
    assert(json && json.summary && json.summary.ok === false, 'summary.ok === false');
    assert(json && json.summary.divergences >= 1, `detectou >=1 divergência (divergences=${json?.summary?.divergences})`);
    const hit = json?.errors?.some((e) => e.var === '--color-primary' && e.kind === 'valor-divergente');
    assert(!!hit, 'apontou --color-primary como valor-divergente');
  } finally {
    rmSync(sandbox, { recursive: true, force: true });
  }
}

// ── (3) SEM-SOURCE — o buraco que era `skipped` silencioso ───────────────────
// Até 2026-08-14 um token sem `$extensions.com.oimpresso.source` caía num `continue`
// e sumia do denominador: o resumo dizia "todos fiéis" tendo deixado de comparar
// aquele. Medido na data, o buraco estava VAZIO (0 pulados em 169 folhas) — latente,
// não ativo. Este assert é o que impede ele de voltar a ser silencioso.
console.log('\n[3] token sem com.oimpresso.source → deve FALHAR (rc1), não ser pulado');
{
  const sandbox = mkdtempSync(join(tmpdir(), 'dtcg-selftest-nosrc-'));
  try {
    mkdirSync(join(sandbox, 'scripts', 'governance'), { recursive: true });
    mkdirSync(join(sandbox, 'resources', 'css', 'tokens'), { recursive: true });
    const sandboxCheck = join(sandbox, 'scripts', 'governance', 'dtcg-equivalence.mjs');
    cpSync(CHECK, sandboxCheck);
    for (const gen of [
      '_generated-inertia-theme.css', '_generated-inertia-dark.css',
      '_generated-foundations-light.css', '_generated-foundations-dark.css',
      '_generated-cockpit-light.css', '_generated-cockpit-dark.css',
    ]) {
      cpSync(join(ROOT, 'resources', 'css', 'tokens', gen), join(sandbox, 'resources', 'css', 'tokens', gen));
    }
    for (const tj of ['base.tokens.json', 'semantic.tokens.json']) {
      cpSync(join(ROOT, 'resources', 'css', 'tokens', tj), join(sandbox, 'resources', 'css', 'tokens', tj));
    }
    // Remove SÓ o endereço canônico — o $value continua correto. Antes do fix isto
    // saía rc0 com o token some-sem-aviso; é exatamente esse verde que não pode voltar.
    const semPath = join(sandbox, 'resources', 'css', 'tokens', 'semantic.tokens.json');
    const sem = JSON.parse(readFileSync(semPath, 'utf8'));
    delete sem.color.primary.$extensions['com.oimpresso.source'];
    writeFileSync(semPath, JSON.stringify(sem, null, 2));

    const { rc, json } = runCheck(sandboxCheck);
    assert(rc === 1, `rc1 com token sem source (rc=${rc})`);
    assert(json?.errors?.some((e) => e.kind === 'sem-source' && e.path === 'color.primary'),
      'acusou color.primary como sem-source (não pulou)');
    assert(json && !('skipped' in json.summary),
      'summary não tem mais a categoria `skipped` (denominador fechado: provado ou acusado)');
  } finally {
    rmSync(sandbox, { recursive: true, force: true });
  }
}

// ── (4) --schema: forma dos *.tokens.json contra o JSON Schema ────────────────
// Bite-test pelo CLI DE FORA (não por helper exportado · §5 2026-07-30), usando o
// seam `--tokens-dir`. NÃO se cria junction para node_modules aqui: junction em
// worktree Windows já esvaziou `vendor/` e `node_modules` reais (CLAUDE.md §Ambiente).
console.log('\n[4] --schema (forma) → boa passa, ruim falha, não-medido sai 2');
{
  const runSchema = (args) => {
    const r = spawnSync(process.execPath, [CHECK, '--schema', '--json', ...args], { encoding: 'utf8' });
    let json = null;
    try { json = JSON.parse(r.stdout); } catch { /* saída não-json (ex. exit 2) */ }
    return { rc: r.status, json, stderr: r.stderr || '' };
  };

  // (a) FIXTURE BOA — o corpus canônico real.
  const boa = runSchema([]);
  assert(boa.rc === 0, `rc0 sobre resources/css/tokens (rc=${boa.rc}) ${boa.rc === 2 ? '— ajv ausente? rode `npm i` na raiz' : ''}`);
  assert(boa.json?.summary?.arquivos >= 2, `validou >=2 arquivos (arquivos=${boa.json?.summary?.arquivos})`);
  assert(boa.json?.summary?.arquivosComViolacao === 0, 'nenhuma violação de forma no corpus real');

  const box = mkdtempSync(join(tmpdir(), 'dtcg-schema-'));
  try {
    const boxTokens = join(box, 'tokens');
    mkdirSync(boxTokens, { recursive: true });
    cpSync(join(ROOT, 'resources', 'css', 'tokens', 'dtcg.schema.json'), join(boxTokens, 'dtcg.schema.json'));
    const semOrig = JSON.parse(readFileSync(join(ROOT, 'resources', 'css', 'tokens', 'semantic.tokens.json'), 'utf8'));

    // (b) MORDE — token sem `source`: é a forma que produz o `sem-source` do bloco (3).
    // O schema pega na ORIGEM, antes de virar valor não-provado.
    const semSrc = JSON.parse(JSON.stringify(semOrig));
    delete semSrc.color.primary.$extensions['com.oimpresso.source'];
    writeFileSync(join(boxTokens, 'x.tokens.json'), JSON.stringify(semSrc, null, 2));
    const ruim = runSchema(['--tokens-dir', boxTokens]);
    assert(ruim.rc === 1, `rc1 com token sem source (rc=${ruim.rc})`);
    assert(/com\.oimpresso\.source/.test(JSON.stringify(ruim.json?.violations ?? [])),
      'apontou com.oimpresso.source como o campo faltante');

    // (c) MORDE — $value composite (objeto). O spec DTCG permite; este repo não, porque
    // o Style Dictionary emite o $value verbatim e o CSS tem de sair byte-idêntico.
    const composite = JSON.parse(JSON.stringify(semOrig));
    composite.color.primary.$value = { colorSpace: 'oklch', components: [0.55, 0.15, 295] };
    writeFileSync(join(boxTokens, 'x.tokens.json'), JSON.stringify(composite, null, 2));
    assert(runSchema(['--tokens-dir', boxTokens]).rc === 1, '$value objeto (composite) reprova');

    // (d) MORDE — typo em $value: a folha deixa de ser reconhecida como token e vira
    // grupo sem filhos. Sem o `not` do schema isso passava calado e o token sumia.
    const typo = JSON.parse(JSON.stringify(semOrig));
    typo.color.primary.$valeu = typo.color.primary.$value;
    delete typo.color.primary.$value;
    writeFileSync(join(boxTokens, 'x.tokens.json'), JSON.stringify(typo, null, 2));
    assert(runSchema(['--tokens-dir', boxTokens]).rc === 1, 'typo em $value ($valeu) reprova (folha virou grupo vazio)');

    // (e) LIBERA — o legado sem `$description` (141 das 169 folhas) NÃO pode reprovar.
    // É a ressalva forward-only: o schema cobre o que nasce, o legado passa (§5 2026-07-12).
    const semDesc = JSON.parse(JSON.stringify(semOrig));
    for (const k of Object.keys(semDesc.color)) if (semDesc.color[k]?.$value) delete semDesc.color[k].$description;
    writeFileSync(join(boxTokens, 'x.tokens.json'), JSON.stringify(semDesc, null, 2));
    assert(runSchema(['--tokens-dir', boxTokens]).rc === 0, 'folha sem $description passa (grandfather forward-only)');

    // (f) NÃO-MEDIDO — diretório sem *.tokens.json. Zero arquivo não é "forma conforme".
    const vazio = join(box, 'vazio');
    mkdirSync(vazio, { recursive: true });
    cpSync(join(ROOT, 'resources', 'css', 'tokens', 'dtcg.schema.json'), join(vazio, 'dtcg.schema.json'));
    const semArquivo = runSchema(['--tokens-dir', vazio]);
    assert(semArquivo.rc === 2, `dir sem *.tokens.json = rc2, nunca 0 (rc=${semArquivo.rc})`);
    assert(/NÃO MEDIDO/.test(semArquivo.stderr), 'declara NÃO MEDIDO em vez de sair verde');

    // (g) NÃO-MEDIDO — schema ausente. Era o caminho clássico de gate mudo.
    const semSchema = join(box, 'sem-schema');
    mkdirSync(semSchema, { recursive: true });
    writeFileSync(join(semSchema, 'x.tokens.json'), JSON.stringify(semOrig, null, 2));
    const r7 = runSchema(['--tokens-dir', semSchema]);
    assert(r7.rc === 2, `schema ausente = rc2, nunca 0 (rc=${r7.rc})`);
    assert(/NÃO MEDIDO/.test(r7.stderr), 'schema ausente declara que nada foi validado');

    // (g2) NÃO-MEDIDO — schema que NÃO COMPILA. Defeito da régua não pode sair 1
    // (violação), senão lê como "os tokens estão errados" e culpa o corpus.
    const schemaQuebrado = join(box, 'schema-quebrado');
    mkdirSync(schemaQuebrado, { recursive: true });
    writeFileSync(join(schemaQuebrado, 'dtcg.schema.json'), '{ "type": "object", "properties": { "x": { "type": "naoExisteEsseTipo" } } }');
    writeFileSync(join(schemaQuebrado, 'x.tokens.json'), JSON.stringify(semOrig, null, 2));
    const r8 = runSchema(['--tokens-dir', schemaQuebrado]);
    assert(r8.rc === 2, `schema que não compila = rc2 (ambiente), não 1 (rc=${r8.rc})`);
    assert(/NÃO MEDIDO/.test(r8.stderr), 'schema quebrado declara NÃO MEDIDO, não culpa o corpus');

    // (h) NÃO-MEDIDO — ajv ausente. Roda uma CÓPIA do check em tmpdir: a resolução ESM
    // parte do arquivo importador, e de lá não há `node_modules` — é o cenário real de
    // "a dependência não está instalada". Tem de sair 2, jamais 0 (§5 2026-08-11).
    mkdirSync(join(box, 'iso', 'scripts', 'governance'), { recursive: true });
    mkdirSync(join(box, 'iso', 'resources', 'css', 'tokens'), { recursive: true });
    cpSync(CHECK, join(box, 'iso', 'scripts', 'governance', 'dtcg-equivalence.mjs'));
    cpSync(join(ROOT, 'resources', 'css', 'tokens', 'dtcg.schema.json'),
      join(box, 'iso', 'resources', 'css', 'tokens', 'dtcg.schema.json'));
    writeFileSync(join(box, 'iso', 'resources', 'css', 'tokens', 'x.tokens.json'), JSON.stringify(semOrig, null, 2));
    const isolado = spawnSync(process.execPath,
      [join(box, 'iso', 'scripts', 'governance', 'dtcg-equivalence.mjs'), '--schema'], { encoding: 'utf8' });
    // Se o tmpdir por acaso resolver ajv (raro, mas possível), o rc legítimo é 0 —
    // o que NUNCA pode acontecer é sair 0 sem ter validado. Ambos os casos declarados.
    assert(isolado.status === 2 || isolado.status === 0,
      `ajv ausente sai 2 (não-medido) ou 0 se resolveu (rc=${isolado.status})`);
    if (isolado.status === 2) {
      assert(/NÃO MEDIDO/.test(isolado.stderr), 'ajv ausente declara NÃO MEDIDO (não sai verde calado)');
    }
  } finally {
    rmSync(box, { recursive: true, force: true });
  }
}

console.log(`\n${failures === 0 ? '✓ self-test PASSOU' : `✗ self-test FALHOU (${failures} asserts)`}`);
process.exit(failures ? 1 : 0);
