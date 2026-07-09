#!/usr/bin/env node
// block-brl-values-in-memory.mjs — BLOQUEIA Write/Edit/MultiEdit que introduza valor BRL
// (R$<número>) NÃO-redigido em memory/**/*.md. Node puro (.mjs) — roda cross-plataforma
// (o time MCP roda Mac/Linux; os hooks .ps1 legados só rodam no Windows do Wagner).
//
// REGISTRADO em .claude/settings.json no grupo PreToolUse matcher "Write|Edit|MultiEdit"
// (junto de block-automem/block-bom-encoding). Criar o arquivo NÃO ativa nada — o REGISTRO
// é o que ativa; scripts/governance/settings-brl-values-registration.test.mjs guarda contra
// des-registro (mesmo padrão de settings-figma-registration / block-pr-without-approval).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// memory/proibicoes.md §"NUNCA commitar valores BRL": valores monetários (R$, MRR, totais,
// valor por mês) NUNCA vão pra git (memory/, *.md canon, PR body, commit). Só Wagner/Eliana
// veem valores; Felipe/Maiara/Luiz veem ESCOPO/CONTAGENS (108 subs, 1311 invoices, …), não R$.
// Origem: Wagner 2026-06-08. Reincidência custou redact-forward + `git filter-repo
// --replace-text 'R\$\s?\d[\d.,]*' → 'R$ [redacted Tier 0]'` em 5.033 commits + force-push
// em main. A própria proibicoes.md registra: "considerar hook PreToolUse
// block-brl-values-in-memory.ps1 que detecta R\$\s?\d em Edit/Write de memory/**/*.md e
// bloqueia." Este é esse hook (variante .mjs, cross-plataforma).
//
// ── DECISÃO block vs advisory (ADR 0224) ─────────────────────────────────────
// Bloqueio legítimo = DETERMINÍSTICO. Aqui o gatilho de BLOCK é o padrão estrito
// `R\$\s?\d` (o mesmo que o git filter-repo usou; foram 26 hits reais no incidente) numa
// linha SEM o sentinela `[redacted Tier 0]`. NÃO bloqueamos por keyword semântica solta
// (MRR/faturamento/meta) — isso geraria 82+ falsos-positivos em scorecards que legitimamente
// falam de faturamento sem citar valor. Keyword-sem-R$ → NÃO bloqueia; no máximo ADVISORY
// (opt-in, ver OIMPRESSO_BRL_ADVISORY). Fail-closed só no padrão estrito respeita o 0224.
//
// ── WHITELIST (exemplo didático) ─────────────────────────────────────────────
// Valor dentro de bloco de código cercado (``` … ``` ou ~~~ … ~~~) NÃO bloqueia — é exemplo
// (ensinar sobre o próprio hook/regra precisa poder escrever "R$ 1.234" num fence).
//
// ── HONESTIDADE (limitações conhecidas) ──────────────────────────────────────
//   - Edit/MultiEdit só enxergam o `new_string` (fragmento), não o arquivo inteiro. Se o
//     fragmento estiver DENTRO de um fence aberto ANTES dele, o hook não vê esse fence e pode
//     falso-bloquear. Direção fail-closed (dinheiro é Tier 0): na dúvida, bloqueia. Escape:
//     use `R$ [redacted Tier 0]` ou o env OIMPRESSO_BRL_OK=1.
//   - `\s?` casa 0-ou-1 espaço (fiel ao padrão do incidente). "R$  1234" (2 espaços) escapa —
//     gap conhecido, aceitável (a rede pega o formato real R$<número> e R$ <número>).
//   - Escopo é o `Write`/`Edit`/`MultiEdit` local. O corpo do PR sai via `gh` (Bash) e NÃO é
//     coberto aqui — isso é trabalho de um `brl-scan.mjs` no pre-push (FOLLOW-UP, não neste hook).
//   - Não fecha a CLASSE "qualquer moeda" (US$, €). É a rede do R$ do incidente catalogado.
//
// Escape valve emergencial (Tier 0 Wagner): env OIMPRESSO_BRL_OK=1.
// Selftest hermético: `node .claude/hooks/block-brl-values-in-memory.mjs --selftest`.
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { stdin } from 'node:process';

// ── Padrões (o gatilho de BLOCK e o sentinela de redação) ────────────────────
// Estrito: "R$" seguido de 0-ou-1 espaço e um dígito. Mesmo padrão do git filter-repo.
const BRL_STRICT = /R\$\s?\d/;
// Sentinela canônico de redação — a linha que o tem está OK (é o remédio, não a doença).
const REDACTION = '[redacted Tier 0]';
// Advisory (NÃO bloqueia): termos financeiros específicos. "receita" solta fica de fora
// de propósito (colide com "receita federal"/"receita de bolo" → ruído).
const KEYWORD_ADVISORY = /\b(mrr|arr|faturamento|meta financeira|valor por m[êe]s|receita bruta|receita l[íi]quida)\b/i;
// Marcador de bloco de código cercado (abre/fecha alternadamente).
const FENCE = /^\s*(```|~~~)/;

// ── isMemoryMarkdownPath: alvo = memory/**/*.md do git canônico ──────────────
// (lógica pura — importada pelo selftest). Exclui a auto-mem privada do ~/.claude/projects
// (block-automem já cuida daquilo) e o oimpresso-local (zona pessoal, ADR 0131).
export function isMemoryMarkdownPath(filePath) {
  if (!filePath) return false;
  const p = String(filePath).replace(/\\/g, '/').toLowerCase();
  if (!p.endsWith('.md')) return false;
  if (p.includes('.claude/projects/')) return false; // auto-mem legada (home) — outro hook
  if (p.includes('.claude/oimpresso-local/')) return false; // zona pessoal (ADR 0131)
  // "memory" como SEGMENTO de path em qualquer profundidade (evita casar "memory-bank/").
  return /(^|\/)memory\/.+\.md$/.test(p);
}

// ── scanBrlLeak: varre o texto novo linha-a-linha, respeitando fences ────────
// Retorna { blocked, lineNumber?, line?, advisory:[{lineNumber,line}] }.
// Regra de BLOCK (contrato): linha casa BRL_STRICT E não contém o sentinela de redação,
// FORA de bloco de código. Keyword-só vira advisory (não bloqueia).
export function scanBrlLeak(text) {
  const lines = String(text ?? '').split(/\r?\n/);
  let inFence = false;
  const advisory = [];
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (FENCE.test(line)) {
      inFence = !inFence;
      continue;
    }
    if (inFence) continue; // exemplo didático em ``` … ``` — whitelist
    if (BRL_STRICT.test(line)) {
      if (!line.includes(REDACTION)) {
        return { blocked: true, lineNumber: i + 1, line: line.trim(), advisory };
      }
      // tem R$<número> MAS também o sentinela na linha → considerado redigido (passa).
    } else if (KEYWORD_ADVISORY.test(line)) {
      advisory.push({ lineNumber: i + 1, line: line.trim() });
    }
  }
  return { blocked: false, advisory };
}

// ── collectSegments: extrai o texto NOVO que a tool vai gravar ────────────────
// (lógica pura — importada pelo selftest). Write=content; Edit=new_string;
// MultiEdit=cada edits[].new_string (varridos independentemente).
export function collectSegments(toolName, toolInput) {
  if (!toolInput) return [];
  if (toolName === 'Write') return [toolInput.content];
  if (toolName === 'Edit') return [toolInput.new_string];
  if (toolName === 'MultiEdit') return (toolInput.edits || []).map((e) => e && e.new_string);
  return [];
}

// Mascara o número pra NÃO re-emitir o valor no stderr (mesmo sendo efêmero).
function maskValue(line) {
  return line.replace(/R\$\s?\d[\d.,]*/g, 'R$ <valor>');
}

function denyMessage(filePath, r) {
  return [
    '[BLOCKED: valor BRL em memory/ — proibicoes.md §"NUNCA commitar valores BRL" (Tier 0 dinheiro)]',
    '',
    `Arquivo: ${filePath}`,
    `Linha ${r.lineNumber}: ${maskValue(r.line)}`,
    '',
    'REGRA: valores monetários (R$…) NUNCA vão pra git (memory/, *.md canon, PR, commit).',
    'Só Wagner/Eliana veem valores; Felipe/Maiara/Luiz veem escopo/contagens, não R$.',
    'Origem: Wagner 2026-06-08 — reincidência custou redact de 5.033 commits (git filter-repo).',
    '',
    'COMO RESOLVER:',
    '  - Troque o valor pelo sentinela:  R$ [redacted Tier 0]',
    '  - Ou comunique o valor fora-banda (chat direto com Wagner), não no git.',
    '  - Contagens/escopo (108 subs, 1311 invoices) são OK — o bloqueio é só de R$<número>.',
    '  - Se for exemplo DIDÁTICO, ponha dentro de um bloco de código (``` … ```).',
    '',
    'Escape emergencial (Tier 0 Wagner): env OIMPRESSO_BRL_OK=1.',
  ].join('\n');
}

function advisoryMessage(filePath, hits) {
  const n = hits.length;
  return [
    `[block-brl-values · advisory] ${filePath}: ${n} linha(s) com termo financeiro ` +
      '(MRR/faturamento/…) sem R$ — NÃO bloqueado.',
    'Confirme que não há valor monetário implícito indo pro git. Contagens/escopo são OK.',
  ].join('\n');
}

async function readStdin() {
  const chunks = [];
  for await (const c of stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  let raw;
  try {
    raw = await readStdin();
  } catch {
    process.exit(0);
  }
  if (!raw) process.exit(0);

  let p;
  try {
    p = JSON.parse(raw);
  } catch {
    process.exit(0);
  }

  // Só PreToolUse de Write/Edit/MultiEdit interessa.
  if (p.hook_event_name && p.hook_event_name !== 'PreToolUse') process.exit(0);
  const tool = p.tool_name;
  if (!['Write', 'Edit', 'MultiEdit'].includes(tool)) process.exit(0);

  const filePath = p.tool_input && p.tool_input.file_path;
  if (!isMemoryMarkdownPath(filePath)) process.exit(0); // fora de memory/**/*.md → segue

  if (process.env.OIMPRESSO_BRL_OK === '1') process.exit(0); // escape Tier 0 Wagner

  const segments = collectSegments(tool, p.tool_input).filter((s) => typeof s === 'string' && s.length);
  let advisoryAll = [];
  for (const seg of segments) {
    const r = scanBrlLeak(seg);
    if (r.blocked) {
      process.stderr.write(denyMessage(filePath, r) + '\n');
      process.exit(2); // BLOCK — stderr vira a razão pro Claude
    }
    advisoryAll = advisoryAll.concat(r.advisory || []);
  }

  // Keyword-só: NÃO bloqueia. Advisory é opt-in (default silencioso p/ não spammar scorecards).
  if (advisoryAll.length && process.env.OIMPRESSO_BRL_ADVISORY === '1') {
    process.stderr.write(advisoryMessage(filePath, advisoryAll) + '\n');
  }
  process.exit(0);
}

// ── Selftest hermético (node puro, sem git/rede) — bite/release ancorados no ──
// contrato (proibicoes.md), NUNCA no output da própria implementação.
function selftest() {
  let fails = 0;
  const ok = (name, cond) => {
    console.log((cond ? '[OK]   ' : '[FAIL] ') + name);
    if (!cond) fails++;
  };

  // BITE (bad) — contrato: R$<número> não-redigido em memory = vazamento → BLOQUEIA.
  ok('R$ 1.234 literal → bloqueia', scanBrlLeak('O total foi R$ 1.234 no mês.').blocked);
  ok('R$5000 (sem espaço) → bloqueia', scanBrlLeak('receita R$5000 apurada').blocked);
  ok('R$ 1.234.567,89 → bloqueia', scanBrlLeak('MRR consolidado R$ 1.234.567,89').blocked);

  // RELEASE (good) — contrato: sentinela de redação é o remédio → passa.
  ok('R$ [redacted Tier 0] → passa', !scanBrlLeak('MRR de R$ [redacted Tier 0] no mês.').blocked);
  // fence didático — contrato: exemplo em bloco de código não vaza → passa.
  ok('R$ 1.234 dentro de ``` fence → passa', !scanBrlLeak('exemplo:\n```\nR$ 1.234\n```\nfim').blocked);
  ok('R$ 1.234 dentro de ~~~ fence → passa', !scanBrlLeak('~~~\nfoi R$ 1.234\n~~~').blocked);
  // keyword-só — contrato: 82+ falsos-positivos ⇒ keyword sem R$ NÃO bloqueia.
  ok('MRR cresceu (sem R$) → não bloqueia', !scanBrlLeak('MRR cresceu 20% no trimestre').blocked);
  ok('faturamento (sem R$) → não bloqueia', !scanBrlLeak('faturamento subiu vs mês anterior').blocked);
  // contagens/escopo — contrato: "pode saber o que migrou (108 subs, 1311 invoices)".
  ok('108 subs / 1311 invoices → passa', !scanBrlLeak('migrou 108 subs e 1311 invoices').blocked);
  // o texto do PRÓPRIO padrão (R\$\s?\d como literal) NÃO é R$<dígito> → passa.
  ok('literal do padrão "R\\$\\s?\\d" (doc) → não bloqueia', !scanBrlLeak('o padrão é R\\$\\s?\\d aqui').blocked);

  // advisory detecta (mas não bloqueia) — prova que a keyword é vista, não barrada.
  ok('faturamento vira advisory (detecção sem bloqueio)', scanBrlLeak('faturamento do mês').advisory.length === 1);
  ok('linha redigida NÃO vira falso-block', !scanBrlLeak('era R$ [redacted Tier 0] antes').blocked);

  // path matcher — contrato: alvo é memory/**/*.md canônico.
  ok('memory/decisions/x.md é alvo', isMemoryMarkdownPath('memory/decisions/0001-x.md'));
  ok('worktree/.../memory/x.md é alvo', isMemoryMarkdownPath('D:/o/.claude/worktrees/w/memory/sessions/x.md'));
  ok('memory-bank/x.md NÃO é alvo (segmento diferente)', !isMemoryMarkdownPath('resources/memory-bank/x.md'));
  ok('Modules/X/Foo.php NÃO é alvo (só memory/*.md)', !isMemoryMarkdownPath('Modules/X/Foo.php'));
  ok('memory/x.txt NÃO é alvo (só .md)', !isMemoryMarkdownPath('memory/x.txt'));
  ok('auto-mem ~/.claude/projects/.../memory/x.md NÃO é alvo (block-automem cuida)',
    !isMemoryMarkdownPath('C:/Users/w/.claude/projects/D--o/memory/x.md'));

  // collectSegments — extração por tool.
  ok('Write → [content]', collectSegments('Write', { content: 'R$ 5' })[0] === 'R$ 5');
  ok('Edit → [new_string]', collectSegments('Edit', { new_string: 'R$ 5' })[0] === 'R$ 5');
  ok('MultiEdit → [new_string, …]', collectSegments('MultiEdit', { edits: [{ new_string: 'a' }, { new_string: 'b' }] }).length === 2);

  console.log('');
  if (fails === 0) {
    console.log('[PASS] block-brl-values: bloqueia R$<número> não-redigido; libera redação/fence/keyword-só.');
    process.exit(0);
  }
  console.log(`[FAIL] ${fails} caso(s).`);
  process.exit(1);
}

// Só executa quando rodado como script (permite import puro no selftest/registration test).
const invokedDirectly =
  process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('block-brl-values-in-memory.mjs');
if (invokedDirectly) {
  if (process.argv.includes('--selftest')) selftest();
  else main();
}
