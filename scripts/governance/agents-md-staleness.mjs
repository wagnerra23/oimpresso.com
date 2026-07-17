#!/usr/bin/env node
// @ts-check
/**
 * agents-md-staleness.mjs — sentinela: o AGENTS.md ficou atrás do CLAUDE.md?
 *
 * O 5º EIXO de staleness — e o último órfão do CORPUS DE ENTRADA. Os irmãos já
 * cobrem porta×código e doc×tela; ninguém vigiava a porta que os agentes NÃO-Anthropic
 * leem:
 *   · BRIEFING.md                 × Modules/<X> ∪ Pages/<X>  → briefing-code-staleness.mjs
 *   · <tela>-visual-comparison.md × inertia_target .tsx      → visual-comparison-staleness.mjs
 *   · ADR proposta parada                                    → adr-proposto-parado.mjs
 *   · RADAR de frescor por doc (0-100)                       → doc-freshness-score.mjs
 *   · AGENTS.md                   × CLAUDE.md ∪ @imports     → ESTE
 *
 * ── POR QUE ESTE EIXO É CEGO SEM SENTINELA (o buraco estrutural) ──────────────────
 *   O AGENTS.md importa o canon com `@CLAUDE.md` — sintaxe da spec de memória
 *   Anthropic. **Codex/Cursor e agentes que só leem markdown puro NÃO expandem `@`**:
 *   pra eles o resumo do AGENTS.md não é um ponteiro, é O CORPUS INTEIRO. Então o
 *   CLAUDE.md pode evoluir por meses que a porta desses agentes continua servindo o
 *   retrato velho — e nenhum gate percebe, porque o CLAUDE.md está lindo.
 *
 * ── INCIDENTE-ÂNCORA ─────────────────────────────────────────────────────────────
 *   RECIBO (lei §5 2026-07-17 "fato derivado não se restateia" — medição datada, com
 *   a query e o sistema medido; re-rode em vez de editar o número):
 *     sistema: git · medido em 2026-07-17 · em `origin/main`
 *     query:   git log --format="%cs %h %s" -- AGENTS.md
 *              git log -1 --format=%cs --before=2026-07-09 -- CLAUDE.md <cada @import>
 *
 *   · 2026-04-26 (28549a4819) AGENTS.md declara a stack IA "verdade canonica", com
 *     **Vizra ADK** dentro.
 *   · 2026-04-29 (defc3fc4a6) **ADR 0048 REJEITA a Vizra ADK** — 3 dias depois.
 *   · 2026-07-09 (#4017) AGENTS.md corrigido, por AUDITORIA HUMANA.
 *   ⇒ a porta serviu stack REJEITADA por 71 dias (04-29→07-09).
 *
 *   ⚠️ SÃO DUAS GRANDEZAS DIFERENTES — não confunda (eu confundi, ver abaixo):
 *     (a) **quanto a mentira viveu**: 04-29 → 07-09 = **71d**. É história do incidente.
 *     (b) **o que ESTE sentinela teria medido** em 07-09: porta 04-26 → superfície
 *         **07-08** (o `proibicoes.md`, máximo real da superfície naquela data) = **73d**.
 *   As duas deram números vizinhos por coincidência. (b) é o que valida o desenho.
 *
 *   E o incidente prova a escolha do sinal — MESMA assimetria do briefing-code-staleness
 *   (git-date mente pra CIMA). Os toques do AGENTS.md em 2026-06-08 foram **MECÂNICOS**
 *   ("restaura codebase apagado pelo squash" 8cd20a3486 + MapaTelas gerado c3abe6ea51):
 *   não refrescaram nada, o texto seguia de 04-26. Então, medindo em 07-09:
 *     · por DATA DECLARADA (04-26): gap **73d** > 30 → **mordia**.
 *     · por DATA-GIT (06-08):       gap **30d**, e `30 > 30` é **falso** → **passava batido**.
 *   ⚠️ O fallback escapa por **UM DIA EXATO** — não por margem confortável. Duas leituras:
 *   (1) reforça a data declarada; (2) **o limiar 30 é frágil neste caso** — o incidente
 *   real fica em cima da linha. Se [W] quiser folga, `OIMPRESSO_AGENTSMD_STALE_DAYS=21`.
 *   (Eco do "Jana passou por 7 dias exatos" — session 2026-07-17 Achado 6.)
 *
 *   ERRATA (2026-07-17, revisão adversarial): a 1ª versão deste header e do self-test
 *   afirmava **71d/28d** usando `codeDate: 2026-07-06` — data que **NÃO EXISTE** na
 *   superfície (fixture construído, não medido, apresentado como âncora). O real é
 *   **73d/30d**. Era a lápide §5 2026-07-17 (oráculo errado) + 2026-07-16 (medir a
 *   propriedade errada e chamar de verificado), cometidas pelo script que as cita.
 *
 * ── SUPERFÍCIE DINÂMICA (anti-apodrecimento por construção) ───────────────────────
 *   A superfície NÃO é uma lista fixa: é `CLAUDE.md` ∪ os `@imports` LIDOS DO PRÓPRIO
 *   CLAUDE.md em runtime. Se alguém adicionar um `@memory/novo.md`, o sentinela passa
 *   a vigiá-lo sozinho — sem edição aqui. Lista hardcoded seria mais um doc pra
 *   apodrecer (a doença que este script existe pra tratar).
 *
 * ── O QUE ISTO NÃO É (proibicoes.md §5 — não re-propor padrão morto) ──────────────
 *   · NÃO é presence-gate. Em particular, foi DESCARTADO checar se o AGENTS.md cita
 *     `proibicoes.md`/§5/Tier 0 por grep: seria "presence-gate sobre TEXTO", da mesma
 *     família do `last_validated`/§-não-vazio rejeitados em 2026-07-09 — e **não teria
 *     pego o incidente-âncora**: a Vizra passou por CONTEÚDO ERRADO, não por ponteiro
 *     ausente. Um grep de "proibicoes" sai verde com a stack rejeitada dentro.
 *     Medimos a DERIVADA TEMPORAL (porta parada enquanto o canon anda) — que É o
 *     formato do incidente real.
 *   · NÃO é motor novo (§5 2026-07-09 "não nascer 3º motor de staleness"): importa
 *     `classifyCodeStaleness` + `declaredDoorDate` do briefing-code-staleness. Segue
 *     o precedente vivo do `visual-comparison-staleness.mjs` — mesma derivada, alvo
 *     diferente, arquivo próprio, wireado no MESMO workflow agregador.
 *   · NÃO é required — ADR 0314 (required = só Tier 0). Reporter, exit 0 sempre.
 *
 * ── HONESTIDADE ──────────────────────────────────────────────────────────────────
 *   G1 mede TEMPO, não VERDADE: AGENTS.md fresco e errado sai verde. O sentinela
 *      compra prazo (acusa a janela em que a mentira pode viver), não corretude.
 *   G2 data declarada é auto-escrita — mas a assimetria protege: esquecer de bumpar
 *      deixa a porta VELHA (morde). Só engana quem bumpar sem refrescar, e aí é o
 *      teatro do `last_validated` — por isso é reporter, NUNCA catraca (§5 2026-07-09).
 *   G3 `@imports` de 2º nível (import dentro de import) não são seguidos: 1 nível só.
 *
 * USO:
 *   node scripts/governance/agents-md-staleness.mjs            (tabela; exit 0 — reporter)
 *   node scripts/governance/agents-md-staleness.mjs --json     (JSON pro Daily Brief)
 *   node scripts/governance/agents-md-staleness.mjs --strict   (exit 1 se stale — opt-in local)
 *   node scripts/governance/agents-md-staleness.mjs --selftest (bite/release hermético — CI)
 *   OIMPRESSO_AGENTSMD_STALE_DAYS=21 node …                    (limiar tunável)
 *
 * Refs: briefing-code-staleness.mjs (núcleo reusado) · visual-comparison-staleness.mjs
 *       (precedente do padrão) · ADR 0048 (Vizra rejeitada — o incidente) · ADR 0314 ·
 *       arXiv 2602.11988 (context file errado é PIOR que ausente) · proibicoes §5.
 */
import { execSync } from 'node:child_process';
import { existsSync, readFileSync, realpathSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { classifyCodeStaleness, declaredDoorDate } from './briefing-code-staleness.mjs';

const ROOT = process.cwd();
// 30d — MESMO limiar dos eixos irmãos (não introduzir número mágico novo).
const DEFAULT_STALE_DAYS = Number(process.env.OIMPRESSO_AGENTSMD_STALE_DAYS) || 30;

export const PORTA = 'AGENTS.md';
export const CANON = 'CLAUDE.md';

/**
 * parseClaudeImports — extrai os `@caminho` do CLAUDE.md. NÚCLEO PURO e testável.
 * Só linhas que COMEÇAM com `@` (a sintaxe de import da spec) — `@` no meio de prosa
 * ou dentro de bloco de código não conta.
 * @param {string} content
 * @returns {string[]} caminhos relativos, deduplicados e ordenados.
 */
export function parseClaudeImports(content) {
  if (!content) return [];
  const fora = content.replace(/```[\s\S]*?```/g, ''); // ignora blocos de código
  const out = new Set();
  for (const l of fora.split('\n')) {
    const m = /^@([^\s`]+\.md)\s*$/.exec(l.trim());
    if (m) out.add(m[1]);
  }
  return [...out].sort();
}

// ── camada git/FS (impura — só no run real, nunca no self-test) ──────────────
function gitDate(relPath) {
  try {
    return execSync(`git log -1 --format=%cs -- "${relPath}"`, {
      cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'],
    }).toString().trim() || null;
  } catch { return null; }
}

/**
 * scan — monta a linha do eixo AGENTS.md × (CLAUDE.md ∪ @imports).
 * Mesma forma de linha dos eixos irmãos (o run() decide formato/exit).
 */
export function scan(staleDays = DEFAULT_STALE_DAYS) {
  const hasDoor = existsSync(join(ROOT, PORTA));
  const hasCanon = existsSync(join(ROOT, CANON));

  let doorDate = null, doorSource = null;
  if (hasDoor) {
    let content = '';
    try { content = readFileSync(join(ROOT, PORTA), 'utf8'); } catch { /* ilegível */ }
    doorDate = declaredDoorDate(content);
    doorSource = doorDate ? 'declarado' : null;
    if (!doorDate) { doorDate = gitDate(PORTA); doorSource = doorDate ? 'git-fallback' : null; }
  }

  // superfície DINÂMICA: CLAUDE.md + os @imports que ele próprio declara.
  let surface = [];
  if (hasCanon) {
    let canonContent = '';
    try { canonContent = readFileSync(join(ROOT, CANON), 'utf8'); } catch { /* ilegível */ }
    surface = [CANON, ...parseClaudeImports(canonContent).filter((p) => existsSync(join(ROOT, p)))];
  }

  let codeDate = null, culpado = null;
  for (const p of surface) {
    const d = gitDate(p);
    if (d && (!codeDate || d > codeDate)) { codeDate = d; culpado = p; }
  }

  const { evaluated, stale, gapDays } = classifyCodeStaleness({
    hasDoor, moduleCodeExists: hasCanon, doorDate, codeDate, staleDays,
  });
  return { porta: PORTA, hasDoor, doorDate, doorSource, codeDate, culpado, evaluated, stale, gapDays, surface };
}

// ── self-test (hermético — núcleo puro, zero git/FS) ─────────────────────────
function selftest() {
  let fails = 0;
  const check = (n, c) => { console.log(`${c ? '[OK]  ' : '[FAIL]'} ${n}`); if (!c) fails++; };

  // parser de @imports
  const CLAUDE_FAKE = [
    '# CLAUDE.md', '## Por que existe', '@memory/why-oimpresso.md', '',
    'texto com email@dominio.md no meio (não é import)', '@memory/proibicoes.md',
    '```', '@memory/dentro-de-bloco.md', '```', '@memory/why-oimpresso.md',
  ].join('\n');
  const imports = parseClaudeImports(CLAUDE_FAKE);
  check('parseClaudeImports pega os 2 imports reais', imports.length === 2);
  check('parseClaudeImports dedup', new Set(imports).size === imports.length);
  check('parseClaudeImports ignora @ no meio de prosa', !imports.some((i) => i.includes('dominio')));
  check('parseClaudeImports ignora bloco de código', !imports.some((i) => i.includes('dentro-de-bloco')));
  check('parseClaudeImports vazio → []', parseClaudeImports('').length === 0);

  // MORDE/SOLTA — núcleo reusado
  check('MORDE: porta 30+d atrás do canon → stale',
    classifyCodeStaleness({ hasDoor: true, moduleCodeExists: true, doorDate: '2026-04-26', codeDate: '2026-07-06', staleDays: 30 }).stale === true);
  check('SOLTA: porta refrescada depois do canon → fresco',
    classifyCodeStaleness({ hasDoor: true, moduleCodeExists: true, doorDate: '2026-07-17', codeDate: '2026-07-16', staleDays: 30 }).stale === false);
  check('SOLTA: gap dentro do limiar → fresco',
    classifyCodeStaleness({ hasDoor: true, moduleCodeExists: true, doorDate: '2026-07-01', codeDate: '2026-07-16', staleDays: 30 }).stale === false);
  check('sem porta → não avaliado (cobertura ≠ staleness, sem falso-positivo)',
    classifyCodeStaleness({ hasDoor: false, moduleCodeExists: true, doorDate: null, codeDate: '2026-07-16', staleDays: 30 }).evaluated === false);

  // O INCIDENTE-ÂNCORA como fixture: declarado morde, git-fallback NÃO.
  // É a prova de que a escolha do sinal não é estética — é o que separa pegar de não
  // pegar a Vizra (stack rejeitada servida a Codex/Cursor por 71d).
  //
  // AS 3 DATAS SÃO MEDIDAS, NÃO ESCOLHIDAS (recibo no header — git, 2026-07-17):
  //   04-26 = último refresh de CONTEÚDO do AGENTS.md (28549a4819)
  //   06-08 = data-git dos toques MECÂNICOS (8cd20a3486 restore + c3abe6ea51 MapaTelas)
  //   07-08 = MÁXIMO REAL da superfície em 07-09 (proibicoes.md) — NÃO invente outra.
  // ERRATA: a 1ª versão usava codeDate 07-06 (inexistente) → dava 71d/28d fabricados.
  const CODE_EM_0709 = '2026-07-08'; // medido: max(CLAUDE.md ∪ @imports) antes de 07-09
  const vizraDeclarado = classifyCodeStaleness({ hasDoor: true, moduleCodeExists: true, doorDate: '2026-04-26', codeDate: CODE_EM_0709, staleDays: 30 });
  const vizraGitFallback = classifyCodeStaleness({ hasDoor: true, moduleCodeExists: true, doorDate: '2026-06-08', codeDate: CODE_EM_0709, staleDays: 30 });
  check(`ÂNCORA Vizra: data DECLARADA (04-26) morde — gap ${vizraDeclarado.gapDays}d (medido, não escolhido)`,
    vizraDeclarado.stale === true && vizraDeclarado.gapDays === 73);
  check(`ÂNCORA Vizra: data-git dos commits MECÂNICOS (06-08) NÃO morderia — gap ${vizraGitFallback.gapDays}d`,
    vizraGitFallback.stale === false && vizraGitFallback.gapDays === 30);
  // A margem é de UM DIA (30 > 30 é falso). Este check existe pra que, se alguém mexer
  // no limiar ou no operador (>= vs >), a fragilidade apareça no teste em vez de virar
  // surpresa: o incidente-âncora está EM CIMA da linha, não folgado.
  check('ÂNCORA Vizra: o fallback escapa por UM DIA EXATO (30 > 30 = falso) — limiar frágil, documentado',
    vizraGitFallback.gapDays === 30 && classifyCodeStaleness({ hasDoor: true, moduleCodeExists: true, doorDate: '2026-06-07', codeDate: CODE_EM_0709, staleDays: 30 }).stale === true);
  // As duas grandezas que a coincidência numérica escondeu na 1ª versão.
  const diasDaMentira = Math.round((Date.parse('2026-07-09') - Date.parse('2026-04-29')) / 86400000);
  check(`DESCONFLAÇÃO: "mentira viveu" (04-29→07-09 = ${diasDaMentira}d) ≠ "gap do sentinela" (${vizraDeclarado.gapDays}d)`,
    diasDaMentira === 71 && vizraDeclarado.gapDays === 73 && diasDaMentira !== vizraDeclarado.gapDays);

  // extrator de data declarada (reusado) reconhece o carimbo que o AGENTS.md usa
  check('declaredDoorDate lê o rodapé **Atualizado:**', declaredDoorDate('**Atualizado:** 2026-07-17') === '2026-07-17');
  check('declaredDoorDate sem carimbo → null (cai no fallback git)', declaredDoorDate('# AGENTS.md\nsem data') === null);

  // A mensagem NÃO restateia os números à mão (a 1ª versão restateava — e restateava
  // os FABRICADOS, 71/28, no exato texto que declarava sucesso). Deriva dos objetos.
  console.log(fails
    ? `\nSELFTEST FALHOU (${fails})`
    : `\nSELFTEST OK — parser de @imports + núcleo morde/solta; âncora Vizra: declarado ${vizraDeclarado.gapDays}d morde > git-fallback ${vizraGitFallback.gapDays}d passa (por 1 dia).`);
  return fails ? 1 : 0;
}

// ── run (CLI) ────────────────────────────────────────────────────────────────
function run() {
  const JSON_OUT = process.argv.includes('--json');
  const STRICT = process.argv.includes('--strict');
  const staleDays = DEFAULT_STALE_DAYS;
  const r = scan(staleDays);

  if (JSON_OUT) {
    console.log(JSON.stringify({
      gate: 'agents-md-staleness',
      axis: 'AGENTS.md data declarada (fallback git) vs CLAUDE.md ∪ @imports data-git',
      staleDays, ...r,
    }, null, 2));
    return r.stale && STRICT ? 1 : 0;
  }

  console.log(`\n  AGENTS.md × CLAUDE.md — a porta dos agentes NÃO-Anthropic ficou atrás do canon? (limiar ${staleDays}d)`);
  console.log(`  eixo: ${PORTA}  vs  ${CANON} ∪ @imports (${r.surface.length} arquivo(s), lidos do próprio CLAUDE.md)`);
  console.log('  ' + '─'.repeat(74));
  if (!r.hasDoor) {
    console.log(`  ⚪ ${PORTA} não existe — fora de escopo (cobertura, não staleness).`);
  } else if (!r.evaluated) {
    console.log(`  ⚪ não avaliável (porta ${r.doorDate || 'sem data'} · canon ${r.codeDate || 'sem data'}).`);
  } else if (!r.stale) {
    console.log(`  🟢 fresco — porta ${r.doorDate} (${r.doorSource}) · canon ${r.codeDate} · gap ${r.gapDays}d`);
  } else {
    console.log(`  🟡 STALE — porta ${r.doorDate} (${r.doorSource}) · canon ${r.codeDate} (${r.culpado}) · atraso ${r.gapDays}d`);
    console.log(`     Codex/Cursor NÃO expandem @CLAUDE.md: pra eles o resumo do ${PORTA} É o corpus.`);
    console.log(`     Ação: reconciliar ${PORTA} com ${CANON} e bumpar o rodapé **Atualizado:**.`);
  }
  console.log('  ' + '─'.repeat(74));
  console.log('  ADVISORY (ADR 0314 — higiene, nunca required). Mede TEMPO, não verdade (G1).');
  console.log('  NÃO é presence-gate: não exige que o AGENTS.md CITE X — grep de ponteiro sairia');
  console.log('  verde com a Vizra dentro. Mede a derivada porta×canon (proibicoes §5 + L-24).\n');

  if (process.env.GITHUB_ACTIONS === 'true' && r.stale) {
    console.log(`::warning title=AGENTS.md atrás do CLAUDE.md::${PORTA} está ${r.gapDays} dias atrás do canon (porta ${r.doorDate} vs ${r.culpado} ${r.codeDate}). Agentes que não expandem @CLAUDE.md (Codex/Cursor) leem só o resumo do ${PORTA} — reconcilie e bumpe o rodapé **Atualizado:**. Precedente: stack rejeitada (Vizra, ADR 0048) serviu 71 dias por este buraco.`);
  }

  return r.stale && STRICT ? 1 : 0;
}

// ── main (só quando executado direto; importável p/ self-test sem rodar) ──────
const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

if (isMain) process.exit(process.argv.includes('--selftest') ? selftest() : run());
