#!/usr/bin/env node
// redact.mjs — redação de segredo na FRONTEIRA DE INGEST do cc-watcher.
//
// ── POR QUE AQUI (o chokepoint, não outro) ───────────────────────────────────
// O transcript (~/.claude/projects/**/*.jsonl) tem canal PRÓPRIO de saída: este
// watcher lê `tool_result` verbatim e envia pra /api/cc/ingest → `mcp_cc_messages`,
// legível pelo time via `cc-search`. Medir "vazou?" no git responde com o oráculo
// errado — o arquivo que vazou em 2026-09-15 (`.claude/settings.local.json`) é
// gitignored e NUNCA foi trackeado; o git não era o canal.
//
// O campo `Gate:` da LC-35 (memory/LICOES_CODE.md) nomeia esta fronteira como o
// único chokepoint onde a defesa faz sentido, e diz por quê: é onde existe corpus
// real pra medir falso-positivo. Os hooks `PreToolUse` não servem — por construção
// eles veem o *input* da tool, nunca o *output* (medido: ZERO hooks do repo leem
// `tool_response`/`tool_result`).
//
// Regra violada: ADR 0057 §10 — "Token raw: nunca em git, log, screenshot,
// transcript, slack history" (desde 2026-04-30).
//
// ── O PREDICADO É SHAPE, NUNCA NOME DE ARQUIVO ───────────────────────────────
// Acusar `cat` de arquivo cujo nome sugere credencial é guard sintático por nome
// — a família com 8 lápides medidas em memory/proibicoes.md §5 — e puniria `cat`
// de `.env.example`, template, config sem segredo. Aqui o predicado olha só a
// FORMA do valor na saída.
//
// ── DE ONDE VEM CADA PADRÃO (vocabulário reusado, não inventado) ─────────────
// `mcp_token`  : DERIVADO DO GERADOR — `'mcp_' . bin2hex(random_bytes(32))`
//                (Modules/Jana/Entities/Mcp/McpToken.php::gerar), tamanho fixado
//                por teste (`expect(strlen($raw))->toBe(68)`). É `mcp_` + 64 HEX.
//                ⚠️ MEDIDO: a forma frouxa `mcp_[A-Za-z0-9_-]{16,}` dá 10.959 hits
//                no corpus e ~99,97% são FALSO-POSITIVO — ela casa NOME DE TOOL
//                (`mcp__ccd_session_mgmt__list_sessions`). Hex-exato casa 3, todos
//                verdadeiros. Não afrouxe este padrão sem re-medir.
// demais       : shapes canônicos que o repo já trata em outros chokepoints —
//                app/Console/Commands/SecretsScanCommand.php (ADR 0215 camada 1,
//                pergunta "secret no repo sem entry no índice") e .gitleaks.toml
//                (`useDefault = true`, ~170 regras). Perguntas diferentes, corpus
//                diferente, ação diferente — por isso módulo próprio e não extensão
//                daqueles; o que se REUSA aqui é o vocabulário, não o mecanismo.
// PLACEHOLDER  : herdado do allowlist de `.gitleaks.toml`, que já é tunado contra
//                os falso-positivos DESTE repo.
//
// Selftest: node scripts/cc-watcher/redact.test.mjs

/** Marcadores de valor fake/template — NÃO redigir (vocabulário do .gitleaks.toml). */
const PLACEHOLDER = /(example|exemplo|fake|dummy|placeholder|changeme|your[-_]?token|xxx+|cole_seu|seu[-_]?token|\$\{|process\.env|getenv|env\(|config\(|\[REDACTED|redacted|fixture|<[a-z_]+>)/i;

/**
 * Regras de shape. `group` = índice do grupo de captura a redigir; 0 = casamento
 * inteiro. Redigir só o VALOR (e não o rótulo) mantém o transcript legível:
 * `DB_PASSWORD=[REDACTED:assign_generic]` ainda diz QUAL variável era.
 */
export const SECRET_RULES = [
  { name: 'mcp_token',     re: /\bmcp_[0-9a-f]{64}\b/g,                                   group: 0 },
  { name: 'anthropic_key', re: /\bsk-ant-[A-Za-z0-9_-]{20,}/g,                            group: 0 },
  { name: 'openai_key',    re: /\bsk-(?:proj-)?[A-Za-z0-9_-]{32,}/g,                      group: 0 },
  { name: 'github_pat',    re: /\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{36}\b/g,            group: 0 },
  { name: 'github_fine',   re: /\bgithub_pat_[A-Za-z0-9_]{60,}/g,                         group: 0 },
  { name: 'aws_akia',      re: /\bAKIA[0-9A-Z]{16}\b/g,                                   group: 0 },
  { name: 'slack_token',   re: /\bxox[baprs]-[A-Za-z0-9-]{10,}/g,                         group: 0 },
  { name: 'private_key',   re: /-----BEGIN [A-Z ]*PRIVATE KEY-----/g,                     group: 0 },
  { name: 'jwt',           re: /\beyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/g, group: 0 },
  // Bearer nas DUAS formas: header (`Authorization: Bearer x`) e JSON
  // (`"Authorization": "Bearer x"`). ⚠️ A regex herdada do SecretsScanCommand
  // exige `Authorization:` colado e por isso NÃO casa a forma JSON — que é
  // exatamente a forma do incidente de 2026-09-15. Medido, não suposto.
  { name: 'bearer',        re: /(Bearer\s+)([A-Za-z0-9_.\-]{20,})/g,                      group: 2 },
  // Genérico nome=valor. É aqui que o FP mora — por isso o floor de 20 chars,
  // o charset e a exclusão de placeholder.
  { name: 'assign_generic', re: /([A-Z][A-Z0-9_]*(?:TOKEN|SECRET|APIKEY|API_KEY|PASSWORD|PASSWD|PWD|ACCESS_KEY|PRIVATE_KEY)\s*["']?\s*[=:]\s*["']?)([A-Za-z0-9_\-\/+=.]{20,})/g, group: 2 },
];

/** true se o valor é template/fixture/placeholder — redigir seria só perda de sinal. */
export function isPlaceholder(value) {
  return PLACEHOLDER.test(String(value ?? ''));
}

/**
 * Redige shapes de segredo num texto.
 * @returns {{text: string, hits: Record<string, number>}}
 */
export function redactText(text) {
  if (typeof text !== 'string' || text.length === 0) return { text, hits: {} };
  let out = text;
  const hits = {};
  for (const { name, re, group } of SECRET_RULES) {
    const rx = new RegExp(re.source, re.flags);
    out = out.replace(rx, (match, ...groups) => {
      const value = group === 0 ? match : groups[group - 1];
      if (value === undefined) return match;
      // placeholder/template → devolve intacto (não é segredo, e redigir
      // destruiria o sinal de quem está debugando justamente o template)
      if (isPlaceholder(value)) return match;
      hits[name] = (hits[name] || 0) + 1;
      const prefix = group === 0 ? '' : match.slice(0, match.length - value.length);
      return `${prefix}[REDACTED:${name}]`;
    });
  }
  return { text: out, hits };
}

/**
 * Redige recursivamente TODA string de um payload. Aplicado imediatamente antes
 * do `JSON.stringify` que vai pra rede: qualquer campo novo que alguém adicione
 * ao payload no futuro nasce coberto, sem precisar lembrar de nada.
 * Redige o VALOR já desserializado — nunca o JSON serializado — então não há
 * risco de corromper a sintaxe.
 */
export function redactPayload(value, hits = {}) {
  if (typeof value === 'string') {
    const r = redactText(value);
    for (const [k, n] of Object.entries(r.hits)) hits[k] = (hits[k] || 0) + n;
    return r.text;
  }
  if (Array.isArray(value)) return value.map(v => redactPayload(v, hits));
  if (value && typeof value === 'object') {
    const out = {};
    for (const [k, v] of Object.entries(value)) out[k] = redactPayload(v, hits);
    return out;
  }
  return value;
}

/** Soma total de hits (para log operacional — nunca loga o valor). */
export function totalHits(hits) {
  return Object.values(hits).reduce((a, b) => a + b, 0);
}
