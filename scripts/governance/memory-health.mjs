#!/usr/bin/env node
// @ts-check
/**
 * memory-health.mjs — sentinela de saúde da base de conhecimento (ADR 0256, Onda 1).
 *
 * O "batimento cardíaco" da memória: roda as checagens MECÂNICAS que a auditoria
 * 2026-06-07 fez à mão, agora automáticas. Espelha `jana:health-check` (dados) e
 * `screen-grades-ratchet` (telas), apontado pra a própria base de conhecimento.
 * Determinístico, sem LLM, sem dependência — roda em CI (PR toca memory/**) e local.
 *
 * Checagens:
 *   A · COLISÃO ADR não-registrada — número de ADR duplicado que NÃO aparece em
 *       _INDEX-LIFECYCLE.md. (🔴 fail — espelha AdrNumberCollisionTest sem vendor.)
 *   B · SCORECARD FANTASMA — scorecard cuja tela (.tsx) foi commitada DEPOIS do
 *       graded_at → nota provavelmente stale. (🟡 warn — é sinal, não bloqueio.)
 *   C · SEGREDO EM memory/ — secret pattern em memory/** sem entry no _INDEX-SECRETS.
 *       (🔴 fail — defense-in-depth do secrets:scan PHP; este pega senha PT-BR/UUID.)
 *   D · DOC STALE — doc canon com "última atualização"/reviewed_at > LIMIAR meses
 *       que se diz canon/estado-atual. (🟡 warn.)
 *   E · ADR ENUM-DRIFT — status/lifecycle de ADR fora do enum canônico
 *       (accepted vs aceito, active vs ativo, canon/feature_wish inválidos).
 *       É o que impede a contagem limpa de "ADRs ativos". (🟡 warn.)
 *
 * Uso:
 *   node scripts/governance/memory-health.mjs            (CI: exit 1 se algum 🔴)
 *   node scripts/governance/memory-health.mjs --json     (saída JSON pra Daily Brief)
 *   node scripts/governance/memory-health.mjs --warn-only (nunca exit 1; só relata)
 *
 * Refs: ADR 0256 (Knowledge Survival) · ADR 0215 (secrets) · ADR 0180 (colisão ADR)
 *       · ADR 0250 (screen-qa) · AUDITORIA-CONFLITOS-MEMORIA-2026-06-07.md
 */
import { readdirSync, readFileSync, existsSync, writeFileSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const WARN_ONLY = process.argv.includes('--warn-only');
const STALE_MONTHS = 6; // doc canon parado > 6 meses = candidato a revisão

// GAP 2 (ADR 0258) — baseline ratchet do Check C (segredos): só falha em segredo NOVO
// acima do teto por arquivo. Aceitos (ex: default Firebird "masterkey") ficam no baseline.
const BASELINE_FILE = 'scripts/governance/.memory-health-baseline.json';
const UPDATE_BASELINE = process.argv.includes('--update-baseline');
const baseline = existsSync(join(ROOT, BASELINE_FILE)) ? JSON.parse(readFileSync(join(ROOT, BASELINE_FILE), 'utf8')) : { checkC: {}, checkL: [], checkM: [] };
let checkCByFile = {};
let checkLSlugs = []; // Check L (ADR vivo-mas-proposto): slugs detectados nesta run
let checkMKeys = []; // Check M (teto de governança): keys de workflow do registry nesta run

const fails = []; // 🔴 bloqueia CI
const warns = []; // 🟡 só sinaliza

// ── helpers ────────────────────────────────────────────────────────────────
const read = (p) => readFileSync(join(ROOT, p), 'utf8');
const exists = (p) => existsSync(join(ROOT, p));
function gitLastDate(relPath) {
  try {
    return execSync(`git log -1 --format=%cs -- "${relPath}"`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch { return ''; }
}
function listFiles(dir, filterFn) {
  const out = [];
  const walk = (d) => {
    let entries;
    try { entries = readdirSync(join(ROOT, d), { withFileTypes: true }); } catch { return; }
    for (const e of entries) {
      const rel = `${d}/${e.name}`;
      if (e.isDirectory()) walk(rel);
      else if (filterFn(rel)) out.push(rel);
    }
  };
  walk(dir);
  return out;
}

// ── Check A: colisão de número de ADR não-registrada ────────────────────────
function checkAdrCollisions() {
  const dir = 'memory/decisions';
  if (!exists(dir)) return;
  const byNum = {};
  for (const f of readdirSync(join(ROOT, dir))) {
    const m = f.match(/^(\d{4})-.+\.md$/);
    if (m) (byNum[m[1]] ??= []).push(f);
  }
  const dups = Object.entries(byNum).filter(([, fs]) => fs.length > 1);
  // Registro de colisões conhecidas: _INDEX-LIFECYCLE.md (loose — número aparece no doc).
  const idxPath = 'memory/decisions/_INDEX-LIFECYCLE.md';
  const registry = exists(idxPath) ? read(idxPath) : '';
  for (const [num, files] of dups) {
    if (!registry.includes(num)) {
      fails.push({ check: 'A', kind: 'colisao-adr-nao-registrada', num, files,
        msg: `ADR ${num} colidiu (${files.length} arquivos) e NÃO está registrado em _INDEX-LIFECYCLE.md — registre a colisão (ADR 0180) ou referencie por slug.` });
    }
  }
}

// ── Check B: scorecard fantasma (tela mudou depois da nota) ─────────────────
function checkScorecardFantasma() {
  const dir = 'memory/governance/scorecards/screens';
  if (!exists(dir)) return;
  let stale = 0; const worst = [];
  for (const f of readdirSync(join(ROOT, dir))) {
    if (!f.endsWith('.yaml')) continue;
    const txt = read(`${dir}/${f}`);
    const graded = (txt.match(/^graded_at:\s*["']?([\d-]+)/m) || [])[1];
    const path = (txt.match(/^path:\s*(.+)$/m) || [])[1]?.trim();
    if (!graded || !path) continue;
    const tsxDate = gitLastDate(path);
    if (tsxDate && tsxDate > graded) { stale++; worst.push({ screen: f.replace('.yaml', ''), graded, tsxDate }); }
  }
  if (stale > 0) {
    warns.push({ check: 'B', kind: 'scorecard-fantasma', count: stale,
      sample: worst.slice(0, 8),
      msg: `${stale} scorecard(s) com nota possivelmente STALE (a tela mudou depois do graded_at). Re-gradear pra a nota refletir o código atual.` });
  }
}

// ── Check C: segredo em memory/ sem entry no índice ─────────────────────────
// Só padrões ANCORADOS EM CONTEXTO (prefixo de segredo). Padrões genéricos de
// alta-entropia (base64/uuid soltos) foram REMOVIDOS — davam 3000+ falso-positivos
// (todo hash/ID em doc casava). Como Check C é GATE (🔴), precisa ser preciso.
const SECRET_PATTERNS = {
  bearer: /Authorization:\s*Bearer\s+([A-Za-z0-9_.\-]{20,})/i,
  aws: /AKIA[0-9A-Z]{16}/,
  assign_token: /\b(?:API_KEY|API_TOKEN|SECRET|SECRET_KEY|ADMIN_TOKEN|ACCESS_KEY|MASTER_KEY|CLIENT_SECRET|PRIVATE_KEY)\s*[:=]\s*["']?([A-Za-z0-9!@#$%^&*_.\-]{16,})["']?/i,
  password_assign: /\b(?:PASSWORD|PASSWD|senha)\s*[:=]\s*["']?([^\s"'<>]{6,})["']?/i,
};
// valores que NÃO são segredo (reduz falso-positivo dos padrões ancorados).
const SECRET_FALSE_POSITIVE = /placeholder|example|exemplo|REDACTED|\bxxx|\.\.\.|<[a-z_.]+>|\$\{|\$[A-Z_]+|env\(|getenv|valor no Vault|no Vaultwarden|\*{4,}/i;

function checkSecretsInMemory() {
  const idxPath = 'memory/_INDEX-SECRETS.md';
  const index = exists(idxPath) ? read(idxPath) : '';
  const files = listFiles('memory', (rel) => rel.endsWith('.md') && rel !== idxPath);
  const hits = [];
  for (const rel of files) {
    const lines = read(rel).split('\n');
    lines.forEach((line, i) => {
      if (SECRET_FALSE_POSITIVE.test(line)) return;
      for (const [name, re] of Object.entries(SECRET_PATTERNS)) {
        const m = line.match(re);
        if (!m) continue;
        const value = m[1] || m[0];
        // "coberto pelo índice" = valor OU caminho do arquivo citado no _INDEX-SECRETS.
        const coveredByValue = index.includes(value);
        const coveredByPath = index.includes(rel);
        if (!coveredByValue && !coveredByPath) {
          hits.push({ file: rel, line: i + 1, pattern: name });
        }
        break; // 1 hit por linha basta
      }
    });
  }
  // Ratchet por arquivo (GAP 2): só segredo NOVO acima do baseline falha. Remover é livre.
  for (const h of hits) checkCByFile[h.file] = (checkCByFile[h.file] || 0) + 1;
  if (UPDATE_BASELINE) return; // no modo update só capturamos; nada de fail
  const novos = [];
  for (const [f, n] of Object.entries(checkCByFile)) {
    const teto = (baseline.checkC || {})[f] || 0;
    if (n > teto) novos.push(`${f} (${n - teto} novo acima do teto ${teto})`);
  }
  if (novos.length) {
    fails.push({ check: 'C', kind: 'segredo-novo-em-memory', count: novos.length, sample: novos.slice(0, 15),
      msg: `segredo(s) NOVO(s) em memory/** acima do baseline. Mova pra CT100/Vault + ponteiro (ADR 0061/0215). Se legítimo (ex: default doc), rode --update-baseline.` });
  }
}

// ── Check D: doc canon stale por idade ──────────────────────────────────────
function checkStaleCanon() {
  const today = new Date(gitLastDate('.') || '2026-06-07'); // evita Date.now (determinismo CI)
  const cutoff = new Date(today); cutoff.setMonth(cutoff.getMonth() - STALE_MONTHS);
  const cutoffStr = cutoff.toISOString().slice(0, 10);
  const files = listFiles('memory/reference', (rel) => rel.endsWith('.md'));
  let staleN = 0; const sample = [];
  for (const rel of files) {
    const txt = read(rel);
    const m = txt.match(/(?:reviewed_at|última atualização|ultima atualizacao)["':\s]*([\d]{4}-[\d]{2}-[\d]{2})/i);
    if (m && m[1] < cutoffStr) { staleN++; if (sample.length < 8) sample.push({ file: rel, date: m[1] }); }
  }
  if (staleN > 0) {
    warns.push({ check: 'D', kind: 'doc-stale', count: staleN, sample,
      msg: `${staleN} doc(s) reference/ sem revisão há > ${STALE_MONTHS} meses — revisar ou marcar lifecycle.` });
  }
}

// ── Check E: drift de enum status/lifecycle em ADR ──────────────────────────
// Enums canônicos do scripts/memory-schemas/adr.schema.json. Append-only bloqueia
// editar ADR ratificada in-place — então normalizar é no leitor OU override
// consciente; este check só IMPEDE PIORAR (flagga grafia/enum novo).
const STATUS_OK = new Set(['rascunho', 'proposto', 'aceito', 'recusado', 'deprecated', 'superseded']);
const LIFECYCLE_OK = new Set(['ativo', 'arquivado', 'substituido', 'historical']);
function checkAdrEnumDrift() {
  const dir = 'memory/decisions';
  if (!exists(dir)) return;
  const badStatus = {}, badLifecycle = {};
  let n = 0;
  for (const f of readdirSync(join(ROOT, dir))) {
    if (!/^\d{4}-.+\.md$/.test(f)) continue;
    const txt = read(`${dir}/${f}`);
    const st = (txt.match(/^status:\s*["']?([^\s"'#]+)/m) || [])[1]?.toLowerCase();
    const lc = (txt.match(/^lifecycle:\s*["']?([^\s"'#]+)/m) || [])[1]?.toLowerCase();
    if (st && !STATUS_OK.has(st)) { badStatus[st] = (badStatus[st] || 0) + 1; n++; }
    if (lc && !LIFECYCLE_OK.has(lc)) { badLifecycle[lc] = (badLifecycle[lc] || 0) + 1; n++; }
  }
  if (n > 0) {
    warns.push({ check: 'E', kind: 'adr-enum-drift', count: n,
      sample: [{ status_invalido: badStatus }, { lifecycle_invalido: badLifecycle }],
      msg: `${n} ADR(s) com status/lifecycle fora do enum canônico (adr.schema.json). Normalizar (accepted→aceito, active→ativo; canon/feature_wish não são lifecycle válidos). Append-only bloqueia editar in-place: normalizar no leitor (decisions-search) ou override consciente.` });
  }
}

// ── Check F: anti-ressurreição da auto-mem (GAP 3, ADR 0258/0061) ───────────
// O legado memory/claude/ foi purgado e o cron memcofre:sync-memories desativado
// (PR #2383). Este invariante IMPEDE a volta: se o dir reaparecer OU o schedule
// for re-ativado (linha não-comentada), 🔴 fail. Mata a classe "rebaixei/apaguei
// e voltou" de raiz.
function checkAntiResurrection() {
  if (exists('memory/claude')) {
    fails.push({ check: 'F', kind: 'automem-ressuscitou',
      msg: `memory/claude/ REAPARECEU — auto-mem legada (ADR 0061 proíbe). Apague + investigue o que recriou (cron memcofre? sync manual?).` });
  }
  const kernel = 'app/Console/Kernel.php';
  if (exists(kernel)) {
    const active = read(kernel).split('\n').some((l) => /^\s*\$schedule->command\(\s*['"]memcofre:sync-memories['"]/.test(l));
    if (active) {
      fails.push({ check: 'F', kind: 'cron-automem-reativado',
        msg: `cron memcofre:sync-memories foi RE-ATIVADO no Kernel.php (linha não-comentada) — era a fonte do vazamento/ressurreição (ADR 0258). Só volta via ADR que reverta o 0061.` });
    }
  }
}

// ── Check G: registry canônico de gates (Onda Q5 — o processo se autocobra) ─
// "Regra que ninguém cobra morre" — gate novo entrava em .github/workflows sem
// censo nenhum. TODO workflow DEVE estar em scripts/governance/gates-registry.json
// (nome + classe + propósito). Workflow fora do registry = 🔴 (pega gate novo
// mecanicamente); entrada órfã (workflow apagado) = 🟡.
function checkGatesRegistry() {
  const REGISTRY = 'scripts/governance/gates-registry.json';
  const wfDir = '.github/workflows';
  if (!exists(wfDir)) return;
  if (!exists(REGISTRY)) {
    fails.push({ check: 'G', kind: 'registry-ausente',
      msg: `${REGISTRY} não existe — o censo de gates é obrigatório (Onda Q5). Recriar a partir do main.` });
    return;
  }
  let reg;
  try { reg = JSON.parse(read(REGISTRY)).workflows || {}; } catch {
    fails.push({ check: 'G', kind: 'registry-ilegivel', msg: `${REGISTRY} não parseia como JSON.` });
    return;
  }
  const files = readdirSync(join(ROOT, wfDir)).filter((f) => f.endsWith('.yml') || f.endsWith('.yaml'));
  const fora = files.filter((f) => !(f in reg));
  if (fora.length) {
    fails.push({ check: 'G', kind: 'workflow-fora-do-registry', count: fora.length,
      msg: `workflow(s) NOVO(s) sem registro no censo de gates (${REGISTRY}): ${fora.join(', ')} — registre nome+classe+propósito no MESMO PR.` });
  }
  const orfas = Object.keys(reg).filter((f) => !files.includes(f));
  if (orfas.length) {
    warns.push({ check: 'G', kind: 'registry-entrada-orfa', count: orfas.length,
      msg: `entrada(s) do registry sem workflow correspondente: ${orfas.join(', ')} — remova do censo.` });
  }
}

// ── Check M: teto de governança (anti-proliferação de gates · ADR 0298) ─────
// "A torneira, não o balde": poda manual não vence a taxa de criação (sessão
// 2026-06-22 — removidos 7 workflows, outras sessões criaram ~10 em 24h; contador
// 81→85 APESAR da poda). Workflow NOVO (fora do baseline grandfather) DEVE declarar
// no registry: `terminal` (required|cron|automacao|advisory) + `anchor` (ADR/incidente/
// PR de custo); se advisory, `promote_by` (data — o vencimento ≤14d é cobrado pelo
// ZELADOR, não aqui, pra manter o check determinístico). Os gates pré-existentes ficam
// isentos (baseline checkM, igual ao ratchet dos Checks C/L). ADR 0105 aplicado a gates.
const TERMINAL_VALIDO = new Set(['required', 'cron', 'automacao', 'advisory']);
function checkGovernanceCeiling() {
  const REGISTRY = 'scripts/governance/gates-registry.json';
  if (!exists(REGISTRY)) return; // Check G já trata ausência
  let reg;
  try { reg = JSON.parse(read(REGISTRY)).workflows || {}; } catch { return; } // Check G já trata ilegível
  checkMKeys = Object.keys(reg);
  if (UPDATE_BASELINE) return; // no modo update só capturamos as keys atuais
  const grandfathered = new Set(baseline.checkM || []);
  const violacoes = [];
  for (const [wf, meta] of Object.entries(reg)) {
    if (grandfathered.has(wf)) continue; // gate pré-existente — isento (ratchet)
    const t = String(meta.terminal || '').trim().toLowerCase();
    const faltas = [];
    if (!TERMINAL_VALIDO.has(t)) faltas.push(`terminal∈{required,cron,automacao,advisory} (tem: ${meta.terminal ?? '—'})`);
    if (!meta.anchor || !String(meta.anchor).trim()) faltas.push('anchor (ADR/incidente/PR de custo)');
    if (t === 'advisory' && (!meta.promote_by || !String(meta.promote_by).trim())) faltas.push('promote_by (advisory não nasce eterno — ADR 0275 §5)');
    if (faltas.length) violacoes.push(`${wf}: faltam [${faltas.join(' · ')}]`);
  }
  if (violacoes.length) {
    fails.push({ check: 'M', kind: 'gate-novo-sem-teto', count: violacoes.length, sample: violacoes.slice(0, 15),
      msg: `workflow(s) NOVO(s) sem o teto de governança (ADR 0298): todo gate novo declara terminal+anchor no gates-registry (advisory exige promote_by). "A torneira, não o balde" — só nasce gate com fim-de-vida e sinal de custo. Preencha os campos, ou rode --update-baseline se for grandfather consciente.` });
  }
}

// ── Check H: frescor de doc-cache "✓lido @main <data>" (Onda Q5) ────────────
// Censos/tabelas derivadas carregam carimbo de leitura contra o main. Carimbo
// >14 dias = a "verdade cacheada" provavelmente driftou → 🟡 revalidar.
function checkLidoFreshness() {
  const LIMIT_DAYS = 14;
  const stamps = [];
  for (const dir of ['memory', 'prototipo-ui']) {
    for (const f of listFiles(dir, (p) => p.endsWith('.md'))) {
      let content; try { content = read(f); } catch { continue; }
      for (const m of content.matchAll(/✓\s*lido\s*@?main[^\d]{0,20}(\d{4}-\d{2}-\d{2})/gi)) {
        stamps.push({ file: f, date: m[1] });
      }
    }
  }
  const today = new Date();
  const old = stamps.filter((s) => (today - new Date(s.date)) / 86400000 > LIMIT_DAYS);
  if (old.length) {
    const sample = old.slice(0, 5).map((s) => `${s.file} (${s.date})`).join(' · ');
    warns.push({ check: 'H', kind: 'doc-cache-stale', count: old.length,
      msg: `carimbo(s) "✓lido @main" com mais de ${LIMIT_DAYS} dias: ${sample}${old.length > 5 ? ` … +${old.length - 5}` : ''} — revalidar contra o main e re-carimbar.` });
  }
}

// ── Check I: lição sem asserção (Onda Q5) ───────────────────────────────────
// Lição em memory/LICOES_CC.md que não aponta gate/G#/IT# nem se declara
// `não-mecanizável:` é lição que vai morrer no tempo (DESIGN.md §16.2 provou).
function checkLicaoSemAssercao() {
  const FILE = 'memory/LICOES_CC.md';
  if (!exists(FILE)) return;
  const content = read(FILE);
  const blocks = content.split(/^## (?=L-\d)/m).slice(1);
  const sem = [];
  for (const b of blocks) {
    const id = (b.match(/^L-\d+[a-z]?/) || ['?'])[0];
    if (!/\bG-?\d|\bIT-?\d|gate|guard|ratchet|catraca|não-mecanizável\s*:|nao-mecanizavel\s*:/i.test(b)) sem.push(id);
  }
  if (sem.length) {
    warns.push({ check: 'I', kind: 'licao-sem-assercao', count: sem.length,
      msg: `lição(ões) sem gate/G#/IT# nem marcador \`não-mecanizável:\`: ${sem.slice(0, 8).join(', ')}${sem.length > 8 ? ` … +${sem.length - 8}` : ''} — toda lição aponta o check que a mecaniza OU se declara não-mecanizável.` });
  }
}

// ── Check J: plan-health (ADR 0294 — planos vivos) ─────────────────────────
// Espelha a catraca/sentinela do ADR 0256 apontada pra PLANOs. Warn-only (advisory):
// plano sem `## Status vivo`, sem reviewed_at / stale (>30d), status fora do enum, ou
// `em-execução` sem `parent_plan` (a membrana — task MCP). NUNCA bloqueia (só sinaliza).
const PLAN_STATUS_OK = new Set(['proposto', 'ativo', 'em-execução', 'em-execucao', 'pausado', 'concluído', 'concluido', 'abandonado', 'superseded', 'revisar']);
const PLAN_STALE_DAYS = 30;
function checkPlanHealth() {
  const base = 'memory/requisitos';
  if (!exists(base)) return;
  const isPlan = (rel) => rel.endsWith('.md')
    && /plan/i.test(rel.split('/').pop())
    && !/PLANS-INDEX|_TEMPLATE/i.test(rel)
    && !/\/(adr|arq)\//.test(rel);
  const files = listFiles(base, isPlan);
  if (!files.length) return;
  const today = new Date(gitLastDate('.') || '2026-06-20');
  const cutoff = new Date(today); cutoff.setDate(cutoff.getDate() - PLAN_STALE_DAYS);
  const cutoffStr = cutoff.toISOString().slice(0, 10);
  const issues = [];
  for (const rel of files) {
    let txt; try { txt = read(rel); } catch { continue; }
    if (!/\n##\s*Status vivo/i.test(txt)) { issues.push(`${rel}: sem bloco \`## Status vivo\` (ADR 0294)`); continue; }
    const block = (txt.split(/\n##\s*Status vivo/i)[1] || '').split(/\n##\s/)[0];
    const status = (block.match(/(?:^|\n)[-*\s]*\**status:\**\s*([^\s<·*\n]+)/i) || [])[1]?.toLowerCase();
    const rev = (block.match(/reviewed[_ -]?at:?\**\s*["']?(\d{4}-\d{2}-\d{2})/i) || [])[1];
    const hasParent = /parent_plan\s*[=:]\s*[a-z0-9-]+/i.test(block);
    if (!status) issues.push(`${rel}: Status vivo sem \`status\``);
    else if (!PLAN_STATUS_OK.has(status)) issues.push(`${rel}: status "${status}" fora do enum (ADR 0294)`);
    if (!rev) issues.push(`${rel}: Status vivo sem \`reviewed_at\``);
    else if (rev < cutoffStr) issues.push(`${rel}: reviewed_at ${rev} > ${PLAN_STALE_DAYS}d — revisar + bump`);
    if ((status === 'em-execução' || status === 'em-execucao') && !hasParent) issues.push(`${rel}: \`em-execução\` sem \`parent_plan\` (membrana ADR 0294)`);
  }
  if (issues.length) {
    warns.push({ check: 'J', kind: 'plan-health', count: issues.length, sample: issues.slice(0, 12),
      msg: `${issues.length} achado(s) de plano-vivo (ADR 0294): plano sem \`## Status vivo\` / \`reviewed_at\` stale / \`em-execução\` órfão. Edita o plano no lugar + bump reviewed_at (fonte única).` });
  }
}

// ── Check K: decisão em session log sem âncora (detector dos "155 perdidos") ─
// Adversário 2026-06-20 (memory/sessions/2026-06-20-adversario-convergencia-sistema.md):
// decisão/rollout escrito num session log que NUNCA virou ADR aceito nem entrou num
// BRIEFING "se perde" — converge só pela atenção manual. Este check flagga session log
// >30d com marcador de decisão (`## Decisão`, `US-`, `rollout`, `### Passo`) que NÃO
// referencia nenhum ADR ACEITO nem um BRIEFING. Warn-only (advisory): é fila de triagem,
// não bloqueio — promover a ADR/BRIEFING OU registrar resolução. Complementa Check J
// (que cuida de PLANOs vivos, ADR 0294); aqui o alvo é o session log histórico.
const SESSION_DECISION_STALE_DAYS = 30;
function acceptedAdrNums() {
  const dir = 'memory/decisions';
  const set = new Set();
  if (!exists(dir)) return set;
  for (const f of readdirSync(join(ROOT, dir))) {
    const m = f.match(/^(\d{4})-.+\.md$/);
    if (!m) continue;
    let txt; try { txt = read(`${dir}/${f}`); } catch { continue; }
    const st = (txt.match(/^status:\s*["']?([^\s"'#]+)/mi) || [])[1]?.toLowerCase();
    if (st && /^(aceito|accepted|aceita)/.test(st)) set.add(m[1]);
  }
  return set;
}
function checkSessionDecisionAnchor() {
  const dir = 'memory/sessions';
  if (!exists(dir)) return;
  const accepted = acceptedAdrNums();
  const today = new Date(gitLastDate('.') || '2026-06-20'); // determinismo CI (sem Date.now)
  const cutoff = new Date(today); cutoff.setDate(cutoff.getDate() - SESSION_DECISION_STALE_DAYS);
  const cutoffStr = cutoff.toISOString().slice(0, 10);
  const files = listFiles(dir, (rel) => rel.endsWith('.md') && !/_TEMPLATE|README/i.test(rel));
  const lost = [];
  for (const rel of files) {
    let txt; try { txt = read(rel); } catch { continue; }
    // marcador de decisão/rollout (lista do adversário 2026-06-20)
    const hasDecision = /^##\s*Decis[aã]o\b/im.test(txt)
      || /^###\s*Passo\b/im.test(txt)
      || /\bUS-[A-Z0-9]{2,}/.test(txt)
      || /\brollout\b/i.test(txt);
    if (!hasDecision) continue;
    // idade: nome do arquivo `YYYY-MM-DD-…` é a fonte PRIMÁRIA — ~50% dos logs não têm
    // `date:` no frontmatter, e `gitLastDate` "rejuvenesce" o doc no touch em massa
    // (mascarava ~46 logs antigos como recentes). Ordem: slug → frontmatter `date:` → git.
    const when = (rel.match(/(?:^|\/)(\d{4}-\d{2}-\d{2})-/) || [])[1]
      || (txt.match(/^date:\s*["']?(\d{4}-\d{2}-\d{2})/mi) || [])[1]
      || gitLastDate(rel);
    if (!when || when >= cutoffStr) continue; // só >30d
    // âncora ESTRUTURAL (não menção solta em prosa, que premiava name-dropping):
    //   ADR aceito referenciado no FRONTMATTER (related_adrs/supersedes/superseded_by)
    //   OU link `decisions/NNNN-…` no corpo  ·  BRIEFING pelo arquivo real (BRIEFING.md).
    const fmEnd = txt.startsWith('---') ? txt.indexOf('\n---', 3) : -1;
    const fm = fmEnd === -1 ? '' : txt.slice(0, fmEnd);
    const refs = new Set();
    for (const m of fm.matchAll(/\b(\d{4})-[a-z]{2,}/g)) refs.add(m[1]);         // slug em related_adrs/supersedes
    for (const m of txt.matchAll(/decisions\/(\d{4})-[a-z]/gi)) refs.add(m[1]);  // link pro arquivo do ADR
    const anchoredByAdr = [...refs].some((n) => accepted.has(n));
    const anchoredByBriefing = /BRIEFING\.md/i.test(txt);
    if (!anchoredByAdr && !anchoredByBriefing) lost.push(`${rel} (${when})`);
  }
  if (lost.length) {
    warns.push({ check: 'K', kind: 'session-decisao-sem-ancora', count: lost.length, sample: lost.slice(0, 12),
      msg: `${lost.length} session log(s) >${SESSION_DECISION_STALE_DAYS}d com marcador de decisão (\`## Decisão\`/\`US-\`/\`rollout\`/\`### Passo\`) SEM âncora ESTRUTURAL (related_adrs/link decisions pra ADR aceito, ou BRIEFING.md) — os "planos perdidos" (adversário 2026-06-20). Triagem: promover a ADR/BRIEFING ou registrar resolução.` });
  }
}

// ── Check L: ADR vivo-mas-proposto (proposto vs realizado) ──────────────────
// "Declarado ≠ realizado": ADR com status proposto/rascunho cujo NÚMERO já é citado
// por código que RODA (scripts/** ou .github/workflows/**) — o processo depende da
// decisão, mas a metadata diz que ela não foi aceita. Ratchet (como Check C): os
// offenders conhecidos ficam no baseline (.checkL); só ADR NOVO vivo-mas-proposto
// acima do baseline 🔴 falha. Ratificar (proposto→aceito) tira do offender list
// sozinho — o débito encolhe à vista. É o teste de integridade do proposto vs
// realizado pedido por Wagner (2026-06-21). Refs: ADR 0256/0258.
const UNRATIFIED_STATUS = new Set(['proposto', 'rascunho', 'proposed', 'draft']);
function checkAdrVivoMasProposto() {
  const dir = 'memory/decisions';
  if (!exists(dir)) return;
  // corpus = "código que roda". NÃO inclui memory/** (lá é doc, não execução) nem o
  // próprio baseline (senão o grandfather vira citação circular auto-confirmante).
  const isCode = (rel) => /\.(mjs|js|ts|php|json)$/.test(rel) && !rel.includes('.memory-health-baseline');
  const corpusFiles = [
    ...listFiles('scripts', isCode),
    ...(exists('.github/workflows') ? listFiles('.github/workflows', (p) => /\.ya?ml$/.test(p)) : []),
  ];
  let corpus = '';
  for (const f of corpusFiles) { try { corpus += '\n' + read(f); } catch {} }
  for (const f of readdirSync(join(ROOT, dir))) {
    const m = f.match(/^(\d{4})-.+\.md$/);
    if (!m) continue;
    const num = m[1];
    let txt; try { txt = read(`${dir}/${f}`); } catch { continue; }
    const st = (txt.match(/^status:\s*["']?([^\s"'#]+)/mi) || [])[1]?.toLowerCase();
    if (!st || !UNRATIFIED_STATUS.has(st)) continue;
    // citado como dependência viva: "ADR 0256" · "0256-slug" · "decisions/0256-"
    const cited = new RegExp(`(ADR[ _-]?${num}\\b|\\b${num}-[a-z]|decisions/${num}-)`, 'i').test(corpus);
    if (cited) checkLSlugs.push(f.replace(/\.md$/, ''));
  }
  if (UPDATE_BASELINE) return; // no modo update só capturamos; nada de fail
  const grandfathered = new Set(baseline.checkL || []);
  const novos = checkLSlugs.filter((slug) => !grandfathered.has(slug));
  if (novos.length) {
    fails.push({ check: 'L', kind: 'adr-vivo-mas-proposto', count: novos.length, sample: novos.slice(0, 15),
      msg: `ADR(s) com status proposto/rascunho mas JÁ citado(s) por código que roda (scripts/** ou .github/workflows/**) — "proposto vs realizado": o processo já depende da decisão mas a metadata diz que não foi aceita. Ratifique (proposto→aceito via PR) ou corte a dependência. Se legítimo, rode --update-baseline. (ADR 0256 Check L · Wagner 2026-06-21)` });
  }
}

// ── run ─────────────────────────────────────────────────────────────────────
checkAdrCollisions();
checkScorecardFantasma();
checkSecretsInMemory();
checkStaleCanon();
checkAdrEnumDrift();
checkAntiResurrection();
checkGatesRegistry();
checkGovernanceCeiling(); // Check M (teto de governança — ADR 0298)
checkLidoFreshness();
checkLicaoSemAssercao();
checkAdrVivoMasProposto(); // Check L (fail-class) — proposto vs realizado
try { checkPlanHealth(); } catch (e) { warns.push({ check: 'J', kind: 'plan-health-error', msg: 'plan-health falhou (não bloqueia): ' + e.message }); }
try { checkSessionDecisionAnchor(); } catch (e) { warns.push({ check: 'K', kind: 'session-anchor-error', msg: 'session-anchor falhou (não bloqueia): ' + e.message }); }

if (UPDATE_BASELINE) {
  writeFileSync(join(ROOT, BASELINE_FILE), JSON.stringify({ checkC: checkCByFile, checkL: checkLSlugs.slice().sort(), checkM: checkMKeys.slice().sort() }, null, 2) + '\n');
  console.log(`✓ baseline atualizado: ${BASELINE_FILE} (Check C: ${Object.keys(checkCByFile).length} arquivos · Check L: ${checkLSlugs.length} ADRs vivo-mas-proposto · Check M: ${checkMKeys.length} workflows grandfathered)`);
  process.exit(0);
}

if (JSON_OUT) {
  console.log(JSON.stringify({ fails, warns, ok: fails.length === 0 }, null, 2));
  process.exit(fails.length && !WARN_ONLY ? 1 : 0);
}

console.log(`\n🩺 memory-health — ${fails.length} 🔴 fail · ${warns.length} 🟡 warn\n`);
for (const f of fails) {
  console.error(`🔴 [${f.check}] ${f.msg}`);
  if (f.files) f.files.forEach((x) => console.error(`     - ${x}`));
  if (f.sample) f.sample.forEach((x) => console.error(`     - ${JSON.stringify(x)}`));
}
for (const w of warns) {
  console.log(`🟡 [${w.check}] ${w.msg}`);
  if (w.sample) w.sample.forEach((x) => console.log(`     - ${JSON.stringify(x)}`));
}
if (!fails.length && !warns.length) console.log('✓ base de conhecimento saudável (0 fail, 0 warn).');

if (fails.length && !WARN_ONLY) {
  console.error(`\n✗ memory-health: ${fails.length} problema(s) 🔴 — corrija ou justifique. (--warn-only pra não bloquear)`);
  process.exit(1);
}
process.exit(0);
