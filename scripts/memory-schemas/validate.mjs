#!/usr/bin/env node
// @ts-check
/**
 * validate.mjs — valida frontmatter de memory/** contra os schemas canônicos.
 *
 * ── POR QUE ESTE ARQUIVO EXISTE (2026-07-26) ─────────────────────────────────
 * A cadeia de defesa do schema tinha 4 peças e uma delas NÃO EXISTIA:
 *
 *   schema (.json)           ✓ existe
 *   VALIDADOR                ✗ só dentro do CI, embutido num heredoc `node <<'NODE'`
 *                              de ~100 linhas no memory-schema-gate.yml
 *   skill memory-schema-     ✓ existe, e promete textualmente:
 *   preflight                  "roda validator local antes de commit pra zerar
 *                              o loop CI fail (~10min/iteração)"
 *   gate no CI               ✓ existe
 *
 * Ou seja: a skill mandava rodar uma ferramenta que **não era invocável**. Mesmo
 * se ela tivesse disparado, não havia comando para cumprir a promessa. É o
 * "chokepoint fantasma" do §5 das proibições — mecanismo cujo caminho de
 * invocação não existe no fluxo real.
 *
 * Incidente que revelou (PR #4798): session log e handoff escritos com
 * frontmatter fora do schema (`title` em vez de `topic`; sem `slug`/`tldr`),
 * mergeados no main porque os jobs Session log/Handoff são ADVISORY e o
 * auto-merge só espera os required.
 *
 * FONTE ÚNICA: o workflow passa a chamar ESTE arquivo. Não há segunda cópia da
 * regra — duplicar validador seria a régua paralela que o §5 mata.
 *
 * USO:
 *   node scripts/memory-schemas/validate.mjs <arquivo...>        # valida arquivos dados
 *   node scripts/memory-schemas/validate.mjs --schema S --glob G # modo CI (changed-files.txt)
 *   node scripts/memory-schemas/validate.mjs --selftest          # fixtures herméticas
 *
 * Exit: 0 ok · 1 violação de schema · 2 erro de uso/ambiente.
 * Deps: gray-matter, glob, ajv, ajv-formats (o CI instala; local usa node_modules).
 */
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { basename } from 'node:path';
import { pathToFileURL } from 'node:url';

const ARGS = process.argv.slice(2);
const flag = (n) => { const i = ARGS.indexOf(n); return i >= 0 ? ARGS[i + 1] : null; };

/** Mapa glob→schema espelhando a matriz do memory-schema-gate.yml. */
export const FAMILIAS = [
  { schema: 'scripts/memory-schemas/adr.schema.json', teste: (f) => /^memory\/decisions\/\d{4}-.*\.md$/.test(f) },
  { schema: 'scripts/memory-schemas/spec.schema.json', teste: (f) => /^memory\/requisitos\/[^/]+\/SPEC\.md$/.test(f) },
  { schema: 'scripts/memory-schemas/runbook.schema.json', teste: (f) => /^memory\/requisitos\/.*RUNBOOK[^/]*\.md$/.test(f) },
  { schema: 'scripts/memory-schemas/session.schema.json', teste: (f) => /^memory\/sessions\/\d{4}-.*\.md$/.test(f) },
  { schema: 'scripts/memory-schemas/handoff.schema.json', teste: (f) => /^memory\/handoffs\/\d{4}-.*\.md$/.test(f) },
  { schema: 'scripts/memory-schemas/charter.schema.json', teste: (f) => /^resources\/js\/Pages\/.*\.charter\.md$/.test(f) },
  { schema: 'scripts/memory-schemas/briefing.schema.json', teste: (f) => /^memory\/requisitos\/[^/]+\/BRIEFING\.md$/.test(f) },
  { schema: 'scripts/memory-schemas/reference.schema.json', teste: (f) => /^memory\/reference\/[^/]+\.md$/.test(f) },
  { schema: 'scripts/memory-schemas/topico.schema.json', teste: (f) => /^memory\/requisitos\/[^/]+\/topicos\/[^/]+\.md$/.test(f) },
];

/** Ignorados na matriz do CI — índices e READMEs não carregam frontmatter de família. */
const IGNORADO = (f) => /(^|\/)_[^/]*\.md$|(^|\/)(README|INDEX)\.md$/.test(f);

/** Resolve qual schema se aplica a um path (null = fora de qualquer família). */
export function schemaPara(arquivo) {
  const f = arquivo.replace(/\\/g, '/');
  if (IGNORADO(f)) return null;
  return FAMILIAS.find((x) => x.teste(f))?.schema ?? null;
}

/**
 * NÚCLEO: valida um arquivo contra um schema já compilado.
 * Devolve {level, errors[]} ou null quando está tudo certo.
 * Espelha 1:1 o comportamento do heredoc que vivia no workflow — inclusive:
 *  - YAML malformado vira violação por-arquivo (não derruba o processo);
 *  - frontmatter ausente é WARN (legacy), não erro.
 */
export function validarConteudo(raw, validate, matter) {
  let data;
  try { ({ data } = matter(raw)); } catch (e) {
    const reason = e && e.message ? String(e.message).split('\n')[0] : String(e);
    return { level: 'error', errors: [`YAML parse error: ${reason}`] };
  }
  if (!data || Object.keys(data).length === 0) {
    return { level: 'warn', errors: ['frontmatter ausente (legacy)'] };
  }
  if (validate(data)) return null;
  return {
    level: 'error',
    errors: (validate.errors || []).map((e) => `${e.instancePath || '/'} ${e.message}`),
  };
}

async function carregarDeps() {
  try {
    const [{ default: matter }, { default: Ajv }, { default: addFormats }] = await Promise.all([
      import('gray-matter'), import('ajv/dist/2020.js'), import('ajv-formats'),
    ]);
    return { matter, Ajv, addFormats };
  } catch (e) {
    console.error('✗ deps ausentes (gray-matter / ajv / ajv-formats).');
    console.error('  Local: `npm i` na raiz. CI: o step de install cobre.');
    console.error('  Detalhe: ' + (e && e.message));
    process.exit(2);
  }
}

function compilar(schemaPath, { Ajv, addFormats }) {
  if (!existsSync(schemaPath)) return null;
  const ajv = new Ajv({ allErrors: true, strict: false });
  addFormats(ajv);
  return ajv.compile(JSON.parse(readFileSync(schemaPath, 'utf8')));
}

/** Modo LOCAL/HOOK: recebe paths e resolve o schema de cada um. */
async function modoArquivos(arquivos, deps) {
  const cache = new Map();
  let erros = 0, avaliados = 0;
  for (const f of arquivos) {
    const sp = schemaPara(f);
    if (!sp) { console.log(`  —    ${f} (fora de família com schema)`); continue; }
    if (!existsSync(f)) { console.log(`  —    ${f} (não existe)`); continue; }
    if (!cache.has(sp)) cache.set(sp, compilar(sp, deps));
    const validate = cache.get(sp);
    if (!validate) { console.log(`  —    ${f} (schema ausente: ${sp})`); continue; }
    avaliados++;
    const v = validarConteudo(readFileSync(f, 'utf8'), validate, deps.matter);
    if (!v) { console.log(`  OK   ${f}  [${basename(sp)}]`); continue; }
    if (v.level === 'warn') { console.log(`  WARN ${f} — ${v.errors.join(' | ')}`); continue; }
    erros++;
    console.error(`  FAIL ${f}  [${basename(sp)}]`);
    for (const e of v.errors) console.error(`         ${e}`);
  }
  if (erros) {
    console.error(`\n✗ ${erros} arquivo(s) com frontmatter fora do schema.`);
    console.error('  Corrija os campos `required` antes de commitar — o gate do CI valida o mesmo.');
  } else if (avaliados) {
    console.log(`\n✓ ${avaliados} arquivo(s) conformes.`);
  }
  return erros ? 1 : 0;
}

/** Modo CI: schema+glob da matriz, interseccionado com changed-files.txt. */
async function modoCI(schemaPath, pattern, deps) {
  if (!existsSync(schemaPath)) { console.error(`[SKIP] schema não existe: ${schemaPath}`); return 0; }
  const { globSync } = await import('glob');
  const validate = compilar(schemaPath, deps);
  // CEGO ≠ LIMPO. O modo CI é diff-aware POR CONSTRUÇÃO: sem `changed-files.txt`
  // não existe conjunto a interseccionar, então "0 arquivos" não significa "nada a
  // corrigir" — significa que NADA FOI MEDIDO. Antes isto caía no `[OK]` de baixo e
  // saía 0: verde indistinguível de "perguntei e não há violação" (§5 2026-07-29,
  // "instrumento afirmar verde quando não conseguiu MEDIR"). No CI o arquivo sempre
  // existe (o step `Compute changed files` roda sempre, sem `if:`), então este ramo
  // só alcança quem roda à mão — que era justamente quem era enganado.
  if (!existsSync('changed-files.txt')) {
    console.error(`⛔ NÃO MEDIDO — 'changed-files.txt' ausente: NADA foi validado contra ${pattern}.`);
    console.error(`   Este modo (--schema/--glob) é o do CI e depende do diff. Local, use:`);
    console.error(`     node scripts/memory-schemas/validate.mjs <arquivo...>   # valida o que você passar`);
    console.error(`   ou gere o diff antes:`);
    console.error(`     git diff --name-only origin/main...HEAD > changed-files.txt`);
    return 2;  // contrato do cabeçalho: 2 = erro de uso/ambiente (≠ 1 = violação)
  }
  const changedRaw = readFileSync('changed-files.txt', 'utf8').trim().split('\n').filter(Boolean);
  // posix() é obrigatório: no Windows o globSync devolve `memory\sessions\x.md`
  // enquanto o `git diff --name-only` (changed-files.txt) devolve `memory/sessions/x.md`
  // — a interseção dava VAZIO e o validador reportava "[OK] nenhum arquivo" sobre
  // arquivos que estavam ali. No heredoc antigo isso nunca apareceu porque ele só
  // rodava no CI (Linux); é o defeito que nasce ao tornar a ferramenta local.
  const posix = (p) => p.replace(/\\/g, '/');
  const allMatching = new Set(globSync(pattern, { ignore: ['**/_*.md', '**/README.md', '**/INDEX.md'] }).map(posix));
  const files = changedRaw.map(posix).filter((f) => allMatching.has(f));

  if (files.length === 0) {
    console.log(`[OK] nenhum arquivo NOVO/MODIFICADO bate ${pattern}`);
    // `.size`, não `.length`: allMatching é um Set — `.length` dava `undefined` e a
    // linha saía "undefined bateriam o glob", que lê como bug do glob e não é.
    console.log(`(${changedRaw.length} no diff, ${allMatching.size} bateriam o glob, sem interseção)`);
    writeFileSync('violations.json', '[]');
    return 0;
  }
  console.log(`Validando ${files.length} arquivo(s) novo(s)/modificado(s) que batem ${pattern}`);

  let failed = 0;
  const violations = [];
  for (const file of files) {
    const v = validarConteudo(readFileSync(file, 'utf8'), validate, deps.matter);
    if (!v) continue;
    if (v.level === 'error') failed++;
    violations.push({ file, ...v });
  }
  console.log(`Arquivos validados: ${files.length} (apenas novos/modificados)`);
  console.log(`Falharam (erro): ${failed}`);
  console.log(`Avisos (legacy sem frontmatter): ${violations.filter((v) => v.level === 'warn').length}`);
  for (const v of violations) {
    const tag = v.level === 'error' ? `::error file=${v.file}::` : `::warning file=${v.file}::`;
    console.log(tag + v.errors.join(' | '));
  }
  writeFileSync('violations.json', JSON.stringify(violations, null, 2));
  return failed > 0 ? 1 : 0;
}

// ── selftest: o validador morde e libera ────────────────────────────────────
async function selftest() {
  const deps = await carregarDeps();
  let falhas = 0;
  const ok = (c, m) => { console.log((c ? '  PASS ' : '  FAIL ') + m); if (!c) falhas++; };

  ok(schemaPara('memory/sessions/2026-07-26-x.md')?.includes('session'), 'roteia sessions → session.schema');
  ok(schemaPara('memory/handoffs/2026-07-26-1700-x.md')?.includes('handoff'), 'roteia handoffs → handoff.schema');
  ok(schemaPara('memory/decisions/0160-x.md')?.includes('adr'), 'roteia decisions → adr.schema');
  ok(schemaPara('memory/handoffs/_INDEX.md') === null, 'ignora _prefixados (índices)');
  ok(schemaPara('README.md') === null, 'ignora arquivo fora de família');

  const vh = compilar('scripts/memory-schemas/handoff.schema.json', deps);
  // MORDE: o frontmatter exato que passou batido no #4798 (title/type em vez de slug/tldr)
  const ruim = validarConteudo('---\ntitle: "x"\ndate: "2026-07-26"\ntype: handoff\n---\n\n# corpo\n', vh, deps.matter);
  ok(ruim?.level === 'error', 'MORDE: handoff sem slug/tldr é erro (o caso real do #4798)');
  // LIBERA: o mesmo arquivo com os required presentes e válidos.
  // (`tldr` tem minLength 10 — a 1ª versão deste fixture usava "z" e o selftest
  // reprovou o TESTE, não o validador. Fica registrado: o schema exige resumo
  // de verdade, não placeholder.)
  const bom = validarConteudo('---\ndate: "2026-07-26"\nslug: "x-y"\ntldr: "resumo com tamanho suficiente"\n---\n\n# corpo\n', vh, deps.matter);
  ok(bom === null, 'LIBERA: handoff com date+slug+tldr válidos passa');
  // e o minLength do tldr morde (placeholder não passa por required preenchido)
  ok(validarConteudo('---\ndate: "2026-07-26"\nslug: "x-y"\ntldr: "z"\n---\n', vh, deps.matter)?.level === 'error',
    'MORDE: tldr placeholder (<10 chars) não conta como preenchido');
  // legacy sem frontmatter = warn, nunca erro (contrato herdado do heredoc)
  ok(validarConteudo('# só corpo\n', vh, deps.matter)?.level === 'warn', 'frontmatter ausente = WARN, não erro');
  // YAML quebrado vira violação do arquivo, não crash do processo
  ok(validarConteudo('---\na: [unclosed\n b: "x\n---\n', vh, deps.matter)?.level === 'error',
    'YAML malformado vira violação por-arquivo (não derruba o processo)');

  // ── bite-test do MODO CI, pelo CLI de FORA ────────────────────────────────
  // Os asserts acima exercitam helpers puros (`schemaPara`/`validarConteudo`) — eles
  // não alcançam o `modoCI`, que é onde vive o contrato de pipeline. Foi por esse vão
  // que o "verde sem medir" passou. Aqui roda-se o próprio script como processo, em
  // cwd temporário, conferindo os TRÊS estados (§5 2026-07-30).
  const { spawnSync } = await import('node:child_process');
  const { mkdtempSync, mkdirSync, writeFileSync: escrever, rmSync } = await import('node:fs');
  const { tmpdir } = await import('node:os');
  const { join, resolve } = await import('node:path');

  const box = mkdtempSync(join(tmpdir(), 'validate-modoci-'));
  const eu = resolve('scripts/memory-schemas/validate.mjs');
  const schemaAbs = resolve('scripts/memory-schemas/handoff.schema.json');
  const GLOB = 'memory/handoffs/*.md';
  const rodar = () => spawnSync(process.execPath, [eu, '--schema', schemaAbs, '--glob', GLOB],
    { cwd: box, encoding: 'utf8' });

  try {
    // (a) MORDE — sem changed-files.txt: cego, e cego não pode sair verde.
    const cego = rodar();
    ok(cego.status === 2, 'MORDE: modo CI sem changed-files.txt = exit 2 (NÃO MEDIDO), nunca 0');
    ok(/NÃO MEDIDO/.test(cego.stderr), 'CEGO declara explicitamente que nada foi validado');

    // Um arquivo que BATE o glob, pra a contagem do glob ser não-trivial.
    mkdirSync(join(box, 'memory/handoffs'), { recursive: true });
    escrever(join(box, 'memory/handoffs/2026-01-01-1200-x.md'),
      '---\ntitle: "sem slug nem tldr"\ntype: handoff\n---\n\n# corpo\n');

    // (b) CONTROLE NEGATIVO — arquivo presente e diff vazio: verde LEGÍTIMO.
    escrever(join(box, 'changed-files.txt'), '');
    const vazio = rodar();
    ok(vazio.status === 0, 'LIBERA: changed-files.txt presente + sem interseção = verde legítimo');
    ok(/1 bateriam o glob/.test(vazio.stdout), 'conta o glob com Set.size (não imprime `undefined`)');
    ok(!/undefined/.test(vazio.stdout), 'nenhum `undefined` na mensagem do modo CI');

    // (c) MORDE — o arquivo ruim ENTRA no diff: tem de virar violação (exit 1, não 2).
    escrever(join(box, 'changed-files.txt'), 'memory/handoffs/2026-01-01-1200-x.md\n');
    const ruimCI = rodar();
    ok(ruimCI.status === 1, 'MORDE: arquivo no diff fora do schema = exit 1 (violação, ≠ 2 de ambiente)');
  } finally {
    rmSync(box, { recursive: true, force: true });
  }

  console.log(falhas ? `\n✗ selftest: ${falhas} falha(s)` : '\n✓ selftest: valida, morde e libera certo');
  process.exit(falhas ? 1 : 0);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (ARGS.includes('--selftest')) { await selftest(); }
  else {
    const deps = await carregarDeps();
    const schemaPath = flag('--schema'), pattern = flag('--glob');
    const code = (schemaPath && pattern)
      ? await modoCI(schemaPath, pattern, deps)
      : await modoArquivos(ARGS.filter((a) => !a.startsWith('--')), deps);
    process.exit(code);
  }
}
