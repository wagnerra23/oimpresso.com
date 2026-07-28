#!/usr/bin/env node
// @ts-check
// memory-schema-guard.mjs — PreToolUse:Write|Edit|MultiEdit em memory/** e charters.
//
// ── POR QUE EXISTE (incidente 2026-07-26, PR #4798) ──────────────────────────
// Session log + handoff foram gravados com frontmatter fora do schema (`title` em vez
// de `topic`; sem `slug`/`tldr`) e MERGEARAM no main. As 3 camadas falharam juntas:
//   1. skill `memory-schema-preflight` (description-match) NÃO disparou;
//   2. o validador nem existia como comando — vivia num heredoc do CI (consertado:
//      scripts/memory-schemas/validate.mjs);
//   3. os jobs Session log/Handoff são ADVISORY e o auto-merge só espera required.
//
// Este hook é o backstop DETERMINÍSTICO da camada 1 — por path e por tool, não por
// intenção. É o mesmo par que o §5 das proibições descreve (2026-07-20): a skill cobre
// o objetivo, o hook cobre o vetor "o agente estava distraído" — que é exatamente
// quando o erro acontece.
//
// ── O QUE FAZ ────────────────────────────────────────────────────────────────
// Só age quando CONSEGUE ver o frontmatter inteiro:
//   Write            → valida `content` completo.
//   Edit/MultiEdit   → valida SÓ se o novo texto começa com `---` (ou seja: o próprio
//                      bloco de frontmatter está sendo reescrito). Edit no corpo não é
//                      avaliado — validar pedaço de arquivo daria falso-positivo.
// Fora disso: silêncio. Nunca adivinha.
//
// DENY por default: o erro é DETERMINÍSTICO (AJV sobre o schema canônico), a mensagem
// diz o campo exato, e o custo de corrigir na hora é ~10s contra um loop de CI. Não é
// gate semântico — não há julgamento envolvido.
// FAIL-OPEN: sem deps / sem schema / qualquer exceção → allow silencioso. Um hook que
// quebra o fluxo por causa de si mesmo é pior que a ausência dele.
// Escape: MEMORY_SCHEMA_GUARD_OFF=1.
//
// Selftest: node .claude/hooks/memory-schema-guard.mjs --selftest
// @see scripts/memory-schemas/validate.mjs (dono da regra — este hook só a invoca)

import { existsSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { relative, isAbsolute } from 'node:path';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);
const VALIDATOR = 'scripts/memory-schemas/validate.mjs';

/** Path do tool_input → relativo posix ao repo (o hook roda na raiz). */
export function normalizar(p) {
  if (!p) return '';
  const fwd = String(p).replace(/\\/g, '/');
  if (!isAbsolute(fwd)) return fwd.replace(/^\.\//, '');
  return relative(process.cwd(), fwd).replace(/\\/g, '/');
}

/**
 * Texto que será gravado, ou null quando não dá pra ver o frontmatter inteiro.
 * Edit só conta quando o novo texto ABRE com `---` — aí o bloco está sendo reescrito.
 */
export function textoValidavel(tool, input) {
  if (!input) return null;
  if (tool === 'Write') return typeof input.content === 'string' ? input.content : null;
  const cand = tool === 'Edit'
    ? input.new_string
    : (Array.isArray(input.edits) && input.edits.length ? input.edits[0].new_string : null);
  if (typeof cand !== 'string') return null;
  return cand.trimStart().startsWith('---') ? cand : null;
}

function deny(msg) {
  return { hookSpecificOutput: { hookEventName: 'PreToolUse', permissionDecision: 'deny', permissionDecisionReason: msg } };
}

async function main() {
  if (process.env.MEMORY_SCHEMA_GUARD_OFF === '1') process.exit(0);

  let raw = '';
  try {
    const chunks = [];
    for await (const c of process.stdin) chunks.push(c);
    raw = Buffer.concat(chunks).toString('utf8');
  } catch { process.exit(0); }
  if (!raw) process.exit(0);

  let tool = '', arquivo = '', input = null;
  try {
    const p = JSON.parse(raw);
    tool = String(p?.tool_name || '');
    input = p?.tool_input || null;
    arquivo = normalizar(input?.file_path);
  } catch { process.exit(0); }

  if (!WRITE_TOOLS.has(tool) || !arquivo) process.exit(0);
  if (!existsSync(VALIDATOR)) process.exit(0);           // fail-open: validador ausente

  let schemaPara, validarConteudo;
  try {
    ({ schemaPara, validarConteudo } = await import(pathToFileURL(process.cwd() + '/' + VALIDATOR).href));
  } catch { process.exit(0); }                            // fail-open: import quebrado

  const schemaPath = schemaPara(arquivo);
  if (!schemaPath || !existsSync(schemaPath)) process.exit(0);

  const texto = textoValidavel(tool, input);
  if (texto === null) process.exit(0);                    // não dá pra ver o frontmatter

  let matter, validate;
  try {
    matter = (await import('gray-matter')).default;
    const Ajv = (await import('ajv/dist/2020.js')).default;
    const addFormats = (await import('ajv-formats')).default;
    const ajv = new Ajv({ allErrors: true, strict: false });
    addFormats(ajv);
    validate = ajv.compile(JSON.parse(readFileSync(schemaPath, 'utf8')));
  } catch { process.exit(0); }                            // fail-open: deps ausentes

  let v;
  try { v = validarConteudo(texto, validate, matter); } catch { process.exit(0); }
  if (!v || v.level !== 'error') process.exit(0);         // ok, ou WARN (legacy) → passa

  const msg =
    `[memory-schema] ${tool} em '${arquivo}' com frontmatter fora do schema — BLOQUEADO.\n\n` +
    v.errors.map((e) => `  • ${e}`).join('\n') +
    `\n\nSchema: ${schemaPath}\n` +
    `Confira os campos \`required\` e rode:  node ${VALIDATOR} ${arquivo}\n\n` +
    `Por que bloqueia: em 2026-07-26 (#4798) um session log e um handoff foram gravados\n` +
    `assim e MERGEARAM — os jobs dessas 2 famílias são advisory e o auto-merge não espera\n` +
    `advisory. Corrigir agora custa ~10s; depois custa um PR de errata (e handoff é\n` +
    `append-only, então nem dá pra consertar no lugar).\n` +
    `Escape: MEMORY_SCHEMA_GUARD_OFF=1.`;

  process.stdout.write(JSON.stringify(deny(msg)) + '\n');
  process.exit(0);
}

// ── selftest: núcleo puro (o veredito de schema é do validate.mjs, testado lá) ──
function selftest() {
  let falhas = 0;
  const ok = (c, m) => { console.log((c ? '  PASS ' : '  FAIL ') + m); if (!c) falhas++; };

  ok(textoValidavel('Write', { content: '---\na: 1\n---\n' }) !== null, 'Write: valida o content inteiro');
  ok(textoValidavel('Write', { content: '# sem frontmatter' }) !== null, 'Write: passa o conteúdo mesmo sem frontmatter (validador decide)');
  ok(textoValidavel('Write', {}) === null, 'Write sem content → null');

  ok(textoValidavel('Edit', { new_string: '---\ndate: "x"\n---\n' }) !== null,
    'Edit: valida quando o novo texto ABRE o frontmatter');
  ok(textoValidavel('Edit', { new_string: 'só um parágrafo do corpo' }) === null,
    'Edit no corpo → null (validar pedaço daria falso-positivo)');
  ok(textoValidavel('MultiEdit', { edits: [{ new_string: '---\na: 1\n---' }] }) !== null,
    'MultiEdit: olha o 1º edit');
  ok(textoValidavel('MultiEdit', { edits: [] }) === null, 'MultiEdit vazio → null');

  ok(normalizar('memory/sessions/x.md') === 'memory/sessions/x.md', 'normaliza path relativo');
  ok(normalizar('memory\\sessions\\x.md') === 'memory/sessions/x.md', 'normaliza backslash do Windows');
  ok(normalizar('') === '', 'path vazio → vazio');

  const d = deny('m');
  ok(d.hookSpecificOutput.permissionDecision === 'deny', 'deny tem o shape do contrato PreToolUse');

  console.log(falhas ? `\n✗ selftest: ${falhas} falha(s)` : '\n✓ selftest: só age quando enxerga o frontmatter inteiro');
  process.exit(falhas ? 1 : 0);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) selftest();
  else await main();
}
