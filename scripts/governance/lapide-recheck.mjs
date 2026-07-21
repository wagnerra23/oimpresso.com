#!/usr/bin/env node
// @ts-check
/**
 * lapide-recheck.mjs — re-verificação de FRESCOR das lápides §5 (memory/proibicoes.md,
 * "Ideias avaliadas e DESCARTADAS"). O registro de rejeição também apodrece: uma lápide
 * matou uma ideia citando âncoras concretas (um script, um workflow, um arquivo, uma
 * "defesa mecânica que agora existe"); se essas âncoras DRIFTARAM (foram deletadas/
 * renomeadas), a premissa que a matou PODE ter mudado — e ninguém re-lê.
 *
 * ── O QUE FAZ (e o rótulo HONESTO — senão vira o próprio LC-08 "afirmar-sem-medir") ──
 * A DETECÇÃO do drift de âncora é MECÂNICA (existsSync sobre o repo VIVO); o JULGAMENTO
 * — "a premissa ainda vale?" — segue HUMANO. É PROXY, não veredito: uma lápide cujo
 * script/gate citado sumiu NÃO está "stale" automaticamente — está marcada `revisar`, pra
 * um humano re-ler. Espelha EXATAMENTE o auto-feed §5↔ledger (licoes-code-two-strikes): a
 * máquina surfaça, o humano decide.
 *
 * ── O QUE NÃO É (as lápides §5 que este script poderia virar, e não vira) ────────────
 *   • NÃO apaga/edita nada: §5 é append-only Tier 0 (só surfaça pra revisão humana).
 *   • NÃO é presence-gate: não checa "campo presente / seção não-vazia" (lápides §5
 *     07-01/07-09/07-16). Resolve o CONTEÚDO citado contra o repo real (existsSync).
 *   • NÃO é catraca sobre campo auto-declarado: NÃO grava `verificado_em`/`last_validated`
 *     em lugar nenhum (lápides §5 07-01/07-09). Re-deriva do estado do repo a cada corrida —
 *     sem watermark que o próprio agente escreve.
 *   • NÃO bloqueia (report-only, exit 0 SEMPRE — não existe `--check` que morde aqui): a
 *     tarefa proíbe virar gate. Frescor de registro de rejeição é insumo de revisão, não portão.
 *   • NÃO duplica staleness consolidado: `briefing-code-staleness` mede BRIEFING↔código por
 *     mtime; este resolve ÂNCORAS CITADAS do §5 (corpus e sinal diferentes) — extensão do
 *     tema "conhecimento derivado apodrece" pra um corpus novo, não um 3º motor de mtime.
 *
 * Determinístico (sem Date.now/Math.random): `--sample N [--seed K]` seleciona por passada
 * fixa (offset K, espaçamento uniforme), nunca aleatório. Node puro (fs). Sem deps.
 *
 * USO (raiz do repo):
 *   node scripts/governance/lapide-recheck.mjs            # relatório advisory (exit 0)
 *   node scripts/governance/lapide-recheck.mjs --json     # JSON determinístico
 *   node scripts/governance/lapide-recheck.mjs --sample 8 [--seed 0]   # amostra determinística
 *   node scripts/governance/lapide-recheck.mjs --selftest
 */
import { readFileSync, existsSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const REPO_ROOT = (() => {
  // raiz = dois níveis acima de scripts/governance/. Override por env pro teste.
  if (process.env.OIMPRESSO_REPO_ROOT) return process.env.OIMPRESSO_REPO_ROOT;
  return dirname(dirname(dirname(fileURLToPath(import.meta.url))));
})();
const PROIBICOES_DEFAULT = join(REPO_ROOT, 'memory', 'proibicoes.md');

// ── parsing do §5 (mantém o CORPO — diferente do parseTombstones do hook, que descarta) ──
/** região §5 = de "## Ideias avaliadas e DESCARTADAS" até o próximo "## " (não-###). */
export function parseTombstones(text) {
  const lines = String(text || '').split('\n');
  let start = -1;
  for (let i = 0; i < lines.length; i++) {
    if (/^##\s+Ideias avaliadas e DESCARTADAS/i.test(lines[i])) { start = i + 1; break; }
  }
  if (start === -1) return [];
  let end = lines.length;
  for (let i = start; i < lines.length; i++) {
    if (/^##\s+/.test(lines[i]) && !/^###/.test(lines[i])) { end = i; break; }
  }
  const tombs = [];
  let cur = null;
  const flush = () => { if (cur) tombs.push(cur); };
  for (let i = start; i < end; i++) {
    const h = /^###\s+(\d{4})-(\d{2})-(\d{2})\s*[—–-]?\s*(.*)$/.exec(lines[i]);
    if (h) { flush(); cur = { date: `${h[1]}-${h[2]}-${h[3]}`, mmdd: `${h[2]}-${h[3]}`, title: h[4].trim(), body: '' }; continue; }
    if (cur) cur.body += lines[i] + '\n';
  }
  flush();
  return tombs;
}

// ── extração de ÂNCORAS DE ARQUIVO concretas (backtick + markdown-link) ─────────
const KNOWN_EXT = /\.(mjs|cjs|mts|js|ts|tsx|jsx|php|json|ya?ml|md|css|scss|sh|blade\.php)$/i;
/** limpa um candidato: tira `:linha`, `?v=`, pontuação/fecha-parêntese/aspas, âncora #. */
function cleanRef(raw) {
  let s = String(raw).trim();
  s = s.replace(/[)\]`'".,;]+$/g, '');    // pontuação/fecho no fim
  s = s.replace(/#.*$/, '');               // âncora markdown (#secao)
  s = s.replace(/\?.*$/, '');              // query (?v=)
  s = s.replace(/:\d+(?:-\d+)?$/, '');     // :linha ou :linha-linha
  return s.trim();
}
/** um candidato é um PATH de arquivo real (não URL, não template, com extensão conhecida). */
function looksLikePath(s) {
  if (!s || /^https?:\/\//i.test(s) || /^mailto:/i.test(s)) return false;
  if (/[<>{}*\s…]/.test(s)) return false;   // template/placeholder (<Mod>, {id}, *, …) — não é arquivo real
  if (!s.includes('/')) return false;        // sem separador → não é path de repo
  return KNOWN_EXT.test(s);
}
/** extrai as âncoras de arquivo únicas de um corpo de lápide. */
export function extractPaths(body) {
  const found = new Set();
  const t = String(body || '');
  // markdown links [txt](path)
  for (const m of t.matchAll(/\]\(([^)]+)\)/g)) { const c = cleanRef(m[1]); if (looksLikePath(c)) found.add(c); }
  // trechos em backtick `...`
  for (const m of t.matchAll(/`([^`]+)`/g)) {
    // um backtick pode conter várias coisas; pega o 1º token que pareça path
    for (const tok of m[1].split(/[\s,]+/)) { const c = cleanRef(tok); if (looksLikePath(c)) found.add(c); }
  }
  return [...found].sort();
}

/** resolve um ref por existência-de-arquivo nas DUAS bases (raiz do repo; e memory/ = dir de
 *  proibicoes.md, base dos markdown-links). Tira `../` líder (link com profundidade errada
 *  aponta pro alvo certo). Resolvido = existe sob QUALQUER caminho. Baixo falso-positivo. */
export function resolveRef(ref, { root, linkBase }) {
  const stripped = ref.replace(/^(?:\.\.?\/)+/, ''); // tira ./ e ../ líderes
  const cands = [resolve(root, stripped), resolve(linkBase, ref), resolve(root, ref)];
  return cands.some((p) => { try { return existsSync(p); } catch { return false; } });
}

/** índice de arquivos trackeados (git ls-files) — determinístico, ordem estável. null se git falhar. */
function gitFileIndex(root) {
  try {
    const out = execSync('git ls-files', { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    return out.split('\n').map((s) => s.trim().replace(/\\/g, '/')).filter(Boolean);
  } catch { return null; }
}

/** resolver ROBUSTO pra o repo vivo. Combina 3 sinais, cada um matando uma classe de
 *  falso-positivo que o §5 condena (lápide 06-30 "guard sintático que barra o legítimo"):
 *    1. existência-de-arquivo (resolveRef) — pega full-path + markdown-link com `../` errado.
 *    2. suffix-match no git ls-files — pega SHORTHAND de backtick (`Sells/Index.casos.md`
 *       cujo caminho real é `resources/js/Pages/Sells/Index.casos.md`).
 *    3. ADR por NÚMERO — link com slug driftado (`decisions/0275-slug-velho.md`) resolve se
 *       QUALQUER `memory/decisions/0275-*.md` existe (ADR é endereçado por número; slug muda,
 *       e slug-drift NÃO é premissa-drift — ADRs são append-only, superseded continua no disco). */
export function makeResolverFromIndex({ root, linkBase, files }) {
  const byBase = new Map();
  const adrNums = new Set();
  if (files) for (const f of files) {
    const base = f.slice(f.lastIndexOf('/') + 1);
    (byBase.get(base) || byBase.set(base, []).get(base)).push(f);
    const m = /(?:^|\/)memory\/decisions\/(\d{4})-.*\.md$/.exec(f);
    if (m) adrNums.add(m[1]);
  }
  return (ref) => {
    if (resolveRef(ref, { root, linkBase })) return true;
    const stripped = ref.replace(/^(?:\.\.?\/)+/, '');
    if (files) {
      const base = stripped.slice(stripped.lastIndexOf('/') + 1);
      for (const f of (byBase.get(base) || [])) if (f === stripped || f.endsWith('/' + stripped)) return true;
    }
    const adr = /(?:^|\/)decisions\/(\d{4})-.*\.md$/.exec(stripped);
    if (adr && adrNums.has(adr[1])) return true;
    return false;
  };
}
export function makeRepoResolver({ root, linkBase }) {
  return makeResolverFromIndex({ root, linkBase, files: gitFileIndex(root) });
}

const CLAIMS_DEFESA = /agora\s+é\s+m[áa]quina|defesa\s+mec[âa]nica|vir(?:ou|a)\s+m[áa]quina|é\s+m[áa]quina[:,]|agora\s+é\s+lei/i;

/** classifica UMA lápide. Puro (recebe um resolver injetável pro teste). */
export function classifyTombstone(t, resolver) {
  const refs = extractPaths(t.body);
  const faltando = refs.filter((r) => !resolver(r));
  const reivindica = CLAIMS_DEFESA.test(t.body);
  // sem âncora de arquivo citada → não há sinal mecânico (não classifica como drift).
  const veredito = refs.length === 0 ? 'sem-ancora-de-arquivo'
    : (faltando.length > 0 ? 'revisar-drift-de-ancora' : 'ancoras-intactas');
  return { date: t.date, mmdd: t.mmdd, title: t.title, ancoras: refs, ancoras_faltando: faltando, reivindica_defesa_mecanica: reivindica, veredito };
}

/** seleção DETERMINÍSTICA de amostra: ordena por data asc, pega N espaçados a partir do
 *  offset (seed % total). Sem aleatoriedade — mesma (N, seed) → mesma amostra. */
export function sampleDeterministic(items, n, seed = 0) {
  if (!n || n >= items.length) return items;
  const sorted = [...items].sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));
  const total = sorted.length;
  const start = ((seed % total) + total) % total;
  const stride = Math.max(1, Math.floor(total / n));
  const out = [];
  for (let i = 0; i < n; i++) out.push(sorted[(start + i * stride) % total]);
  // dedup preservando ordem (stride pode colidir quando n≈total)
  const seen = new Set();
  return out.filter((x) => (seen.has(x.date + x.title) ? false : seen.add(x.date + x.title)));
}

/** roda o re-check. `resolver` injetável (teste); default = resolver robusto do repo vivo
 *  (git ls-files + suffix + ADR-por-número). Sem I/O de arquivo de saída. */
export function recheck(proibicoesText, { root, linkBase, sample = 0, seed = 0, resolver } = {}) {
  let tombs = parseTombstones(proibicoesText);
  const totalTombs = tombs.length;
  if (sample) tombs = sampleDeterministic(tombs, sample, seed);
  const resolve1 = resolver || makeRepoResolver({ root, linkBase });
  const results = tombs.map((t) => classifyTombstone(t, resolve1));
  const revisar = results.filter((r) => r.veredito === 'revisar-drift-de-ancora');
  const intactas = results.filter((r) => r.veredito === 'ancoras-intactas');
  const semAncora = results.filter((r) => r.veredito === 'sem-ancora-de-arquivo');
  // ordena o surface: drift primeiro, defesa-mecânica-reivindicada no topo, data desc
  revisar.sort((a, b) => (a.reivindica_defesa_mecanica !== b.reivindica_defesa_mecanica)
    ? (a.reivindica_defesa_mecanica ? -1 : 1)
    : (a.date < b.date ? 1 : -1));
  return {
    total_lapides_secao5: totalTombs, avaliadas: results.length,
    revisar, intactas: intactas.length, sem_ancora: semAncora.length, resultados: results,
  };
}

// ═══════════════════════════════════════════════════════════════════════════════
const toAscii = (s) => String(s).replace(/[^\x20-\x7E]/g, '.');
const isMain = (() => { try { return process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href; } catch { return false; } })();

if (isMain && !process.argv.includes('--selftest')) {
  const a = process.argv.slice(2);
  const val = (n, d) => { const i = a.indexOf(n); return i >= 0 && a[i + 1] !== undefined ? a[i + 1] : d; };
  const json = a.includes('--json');
  const sample = parseInt(val('--sample', '0'), 10) || 0;
  const seed = parseInt(val('--seed', '0'), 10) || 0;
  const path = val('--proibicoes', PROIBICOES_DEFAULT);
  if (!existsSync(path)) { console.log(`lapide-recheck: proibicoes.md não encontrado (${path}) — nada a re-checar.`); process.exit(0); }
  const text = readFileSync(path, 'utf8');
  const r = recheck(text, { root: REPO_ROOT, linkBase: dirname(path), sample, seed });

  if (json) { console.log(JSON.stringify(r, null, 2)); process.exit(0); }
  console.log('\n  LÁPIDE-RECHECK — frescor do registro de rejeição §5 (proibicoes.md)\n');
  console.log(`  §5 tem ${r.total_lapides_secao5} lápide(s)${sample ? ` · amostra determinística de ${r.avaliadas} (seed ${seed})` : ` · avaliadas todas`}`);
  console.log(`  âncoras intactas: ${r.intactas} · sem âncora de arquivo: ${r.sem_ancora} · REVISAR (drift de âncora): ${r.revisar.length}\n`);
  if (r.revisar.length === 0) {
    console.log('  🟢 nenhuma lápide com âncora driftada — as premissas ancoradas resolvem no repo vivo.');
  } else {
    console.log('  As lápides abaixo citam arquivo(s) que NÃO resolvem mais no repo — a premissa que as');
    console.log('  matou PODE ter mudado. Um humano deve RE-LER e decidir (nada é apagado — §5 é append-only):\n');
    for (const t of r.revisar) {
      const flag = t.reivindica_defesa_mecanica ? ' ⚠ REIVINDICA "defesa mecânica/agora é máquina"' : '';
      console.log(`  🔎 ${t.date} — ${toAscii(t.title).slice(0, 72)}${flag}`);
      for (const f of t.ancoras_faltando) console.log(`        âncora sumida: ${f}`);
    }
    console.log('\n  A detecção do drift é mecânica; o julgamento (a premissa ainda vale?) é HUMANO.');
    console.log('  Se a lápide segue válida → deixa como está. Se a premissa mudou → NOVA lápide/emenda (ADR),');
    console.log('  nunca editar/apagar a antiga (§5 append-only Tier 0).');
  }
  console.log('');
  process.exit(0);
}

if (isMain && process.argv.includes('--selftest')) {
  const test = fileURLToPath(new URL('./lapide-recheck.test.mjs', import.meta.url));
  const { spawnSync } = await import('node:child_process');
  const res = spawnSync(process.execPath, [test], { stdio: 'inherit' });
  process.exit(res.status ?? 1);
}
