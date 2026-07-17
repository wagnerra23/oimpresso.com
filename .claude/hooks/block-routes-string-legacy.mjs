#!/usr/bin/env node
// block-routes-string-legacy.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1).
// BLOQUEIA Write/Edit em arquivo de rotas que use a string legacy 'Controller@method'.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// .claude/rules/routes.md §"FQCN obrigatório — strings legacy quebram route:cache":
//   "'SellController@method' (string sem namespace) só funcionava em runtime via fallback
//    Laravel; quebrava route:cache/route:list com ReflectionException. Wagner ativou cache em
//    prod sem perceber. 10 strings em routes/web.php linhas 231-239 + 259 — todas convertidas
//    pra [Class::class, 'method']. PR #843 resolveu, route cache 2.5MB ATIVO em prod."
// memory/reference/post-mortem-v4-go-live.md §anti-pattern A cita o mesmo vetor (#843 pré-v4).
// A rule é PASSIVA (só carrega contexto quando o agente lê o arquivo) — este hook é o único
// enforcement em runtime. Por isso strict-by-default: o incidente é catalogado e a regra é clara.
//
// ── POR QUE .mjs (lote B da triagem 2026-07-09) ─────────────────────────────
// memory/sessions/2026-07-09-triagem-hooks-ps1-subtracao.md item #7: "Portar — lote B ·
// Incidente-origem; rule routes.md é passiva, hook é o único enforcement".
// O .ps1 SÓ roda no Windows: em Mac/Linux o `powershell -File` vira "command not found"
// (exit 127) e o Claude Code trata exit≠2 como não-bloqueante — o blocker evapora EM
// SILÊNCIO. O time MCP (Felipe/Maiara/Eliana/Luiz) entra em Mac/Linux e edita rotas.
// Medido 2026-07-17 (harness da casa): o .ps1 BLOQUEIA string legacy e SOLTA FQCN.
//
// ── O QUE ESTE PORTE **NÃO** MUDA ───────────────────────────────────────────
// Enforcement é decisão [W]: segue strict-by-default, mesmos env, mesmas isenções, mesmo
// filtro de comentário. Único delta de forma: exit 2 (convenção .mjs) em vez de deny-JSON.
//
// Modo (env OIMPRESSO_ROUTES_HOOK_MODE): strict (DEFAULT) | warn | off
// Override emergencial (Tier 0 Wagner): env OIMPRESSO_ROUTES_OVERRIDE=1
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Selftest: node .claude/hooks/block-routes-string-legacy.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);

// String legacy: aspas + PascalCase terminando em 'Controller' + '@' + método.
const LEGACY_RE = /['"]([A-Z][A-Za-z0-9_]*Controller)@([a-zA-Z_][a-zA-Z0-9_]*)['"]/g;

/** normaliza path pra comparação (forward slash + lowercase — Windows é case-blind). */
export function normalizePath(p) {
  return String(p || '').replace(/\\/g, '/').toLowerCase();
}

/** é arquivo de rotas? (raiz, Modules/<X>/routes/, Modules/<X>/Http/routes.php) */
export function isRoutesFile(pathLower) {
  return (
    /\/routes\/[^/]+\.php$/.test(pathLower) ||
    /\/modules\/[^/]+\/routes\/[^/]+\.php$/.test(pathLower) ||
    /\/modules\/[^/]+\/http\/routes\.php$/.test(pathLower)
  );
}

/** isento: o próprio hook/teste e a rule que cita o padrão em backtick. */
export function isExempt(pathLower) {
  if (/\/\.claude\/hooks\/block-routes-string-legacy\.(mjs|test\.mjs)$/.test(pathLower)) return true;
  if (/\/\.claude\/rules\/routes\.md$/.test(pathLower)) return true;
  return false;
}

/** conteúdo que o tool vai gravar (Write→content, Edit→new_string, MultiEdit→todos). */
export function extractContent(toolName, toolInput) {
  const ti = toolInput || {};
  if (toolName === 'Write') return String(ti.content || '');
  if (toolName === 'Edit') return String(ti.new_string || '');
  if (toolName === 'MultiEdit') {
    return (Array.isArray(ti.edits) ? ti.edits : []).map((e) => String((e && e.new_string) || '')).join('\n');
  }
  return '';
}

/** a linha que contém o índice é comentário? (doc que ensina o padrão não é violação) */
function isCommentLine(content, index) {
  const start = content.lastIndexOf('\n', index) + 1;   // -1 → 0
  let end = content.indexOf('\n', index);
  if (end < 0) end = content.length;
  const line = content.slice(start, end).trimStart();
  return line.startsWith('//') || line.startsWith('*') || line.startsWith('#') || line.startsWith('/*');
}

/** ocorrências REAIS de string legacy (fora de comentário). */
export function findLegacyMatches(content) {
  const out = [];
  const s = String(content || '');
  for (const m of s.matchAll(LEGACY_RE)) {
    if (isCommentLine(s, m.index)) continue;
    out.push(m[0]);
  }
  return out;
}

/** modo vigente (env). strict = default: incidente catalogado + regra clara. */
export function currentMode(env = process.env) {
  return String(env.OIMPRESSO_ROUTES_HOOK_MODE || 'strict').toLowerCase();
}

/** veredito único: 'block' | 'warn' | 'allow'. */
export function verdict(toolName, filePath, toolInput, env = process.env) {
  if (!WRITE_TOOLS.has(toolName)) return 'allow';
  if (!filePath) return 'allow';
  const pathLower = normalizePath(filePath);
  if (!isRoutesFile(pathLower)) return 'allow';
  if (isExempt(pathLower)) return 'allow';
  const mode = currentMode(env);
  if (mode === 'off') return 'allow';
  if (String(env.OIMPRESSO_ROUTES_OVERRIDE || '') === '1') return 'allow';
  if (findLegacyMatches(extractContent(toolName, toolInput)).length === 0) return 'allow';
  return mode === 'warn' ? 'warn' : 'block';
}

export function blockMessage(toolName, filePath, matches = []) {
  return `[block-routes-string-legacy] ${toolName} em '${filePath}' usa string legacy 'Controller@method'.

Detectado: ${matches.slice(0, 3).join(', ')}${matches.length > 3 ? ` (+${matches.length - 3})` : ''}

Isso QUEBRA route:cache/route:list com ReflectionException — o incidente #843 tinha 10 strings
em routes/web.php e o Wagner ligou o cache em prod sem perceber (404 silencioso).

Use FQCN:  [\\Modules\\X\\Http\\Controllers\\YController::class, 'method']
    ou:    use Modules\\X\\Http\\Controllers\\YController;  +  [YController::class, 'method']

Contrato: .claude/rules/routes.md §"FQCN obrigatório".
Override emergencial (Tier 0): env OIMPRESSO_ROUTES_OVERRIDE=1`;
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);
  let tool = '';
  let path = '';
  let toolInput = {};
  try {
    const payload = JSON.parse(raw);
    tool = String((payload && payload.tool_name) || '');
    toolInput = (payload && payload.tool_input) || {};
    path = String(toolInput.file_path || '');
  } catch { process.exit(0); }        // parse-fail → fail-open
  const v = verdict(tool, path, toolInput);
  if (v === 'allow') process.exit(0);
  const matches = findLegacyMatches(extractContent(tool, toolInput));
  process.stderr.write(blockMessage(tool, path, matches) + '\n');
  if (v === 'warn') { process.stderr.write('[modo warn — prosseguindo]\n'); process.exit(0); }
  process.exit(2);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-routes-string-legacy.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
