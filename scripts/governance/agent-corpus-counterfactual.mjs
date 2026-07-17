#!/usr/bin/env node
// @ts-check
/**
 * agent-corpus-counterfactual.mjs — QUANTO CUSTA descobrir se o corpus ajuda?
 *
 * ── O QUE ESTE ARQUIVO É (leia isto antes de qualquer coisa) ─────────────────────
 *   É a **aritmética de viabilidade** do contrafactual de corpus — NÃO o contrafactual.
 *   Ele responde UMA pergunta, sem rodar experimento nenhum:
 *       "pra enxergar um efeito de δ, quantas rodadas de agente eu preciso?"
 *   E a resposta dele MATOU o experimento que ele ia servir (ver ACHADO abaixo).
 *
 * ── PODA 2026-07-17 (autorizada por [W]) — este arquivo já foi 2× maior ───────────
 *   A 1ª versão trazia junto o harness de execução: `armStats`, `classificarContraste`,
 *   `buildReport`, `renderHuman`, `renderBriefMd`, agregação de custo via JSONL e o modo
 *   `--runs`. **Tudo apagado.** Motivo, medido e não opinado:
 *     · `grep -rn -- "--power" .github/ .claude/ package.json app/Console/` → ZERO.
 *       Nem `--runs` nem `--power` tinham invocador; nada produzia `runs.json`; nenhum
 *       runner existia. Era mecanismo impecável que nenhum caminho real executa —
 *       lápide §5 2026-07-09 ("correção-do-mecanismo ≠ invocação"), cometida pelo
 *       arquivo que a cita. O PR irmão #4409 (C11), do mesmo dia, mediu o próprio
 *       chokepoint (30/30 commits por PR) e passou; este não tinha chokepoint nenhum.
 *     · ADR 0105 ("backlog só recebe item se cliente paga + reporta OU métrica detecta
 *       drift"): o experimento que aquele código servia NÃO foi autorizado. Nasceu antes
 *       do sinal.
 *     · ADR 0334 (77% governança / 23% negócio, `alarme: true`): a régua era a própria
 *       frase do session log — *"se virar mais uma régua que só soma, falhou."* Somava.
 *   ⚠️ ERRATA (2026-07-17): a 1ª versão deste comentário afirmava "o código vive no git,
 *   recuperar é git log --diff-filter=D". FALSO — `git log -S "export function armStats"`
 *   volta VAZIO: o harness NUNCA foi commitado (só existiu no working tree, sobrescrito
 *   pelo Write da poda). Quando [W] escolheu "gastar" (2026-07-17), o harness foi
 *   RECONSTRUÍDO (do histórico da conversa, não recuperado do git) em
 *   `.claude/governance-eval/corpus-counterfactual/harness.mjs` — que agora IMPORTA as
 *   primitivas daqui, dando a elas um invocador real. Ver o README de lá + o smoke que
 *   reproduziu o achado do paper (+22% de custo / ~0 benefício em skill redundante).
 *
 * ── A RÉGUA PUBLICADA (verificada 2026-07-17 na fonte, não herdada de briefing) ────
 *   arXiv 2602.11988 (fev/2026) — "Evaluating AGENTS.md: Are Repository-Level Context
 *   Files Helpful for Coding Agents?" · 438 tarefas (SWE-bench Lite + AGENTbench 138/12
 *   repos Python):
 *     · context file **LLM-generated DERRUBA** resolução em **0,5–2pp**  ← o arm que somos
 *     · context file **developer-written** sobe ~4pp (modesto)
 *     · **ambos custam +20%** de inferência
 *   ⚠️ ERRATA DE PROPAGAÇÃO: o briefing do chip dizia "derruba ~3%". O paper diz 0,5–2pp.
 *   Erro material — efeito ~2× MENOR ⇒ replicação ~2-4× MAIS CARA (n cresce com 1/δ²).
 *
 * ── O ACHADO (o `--power` rodado 1× em 2026-07-17) ────────────────────────────────
 *   Replicar o achado do paper custa **dezenas a centenas de milhares** de runs de agente.
 *   Não é falta de disciplina — é aritmética. O que CABE medir é efeito GRANDE (≥20pp):
 *   "o nosso corpus é nocivo o bastante pra podar já?" — e isso exige aceite de [W] +
 *   task-set ratificado. Rode `--power` pros números; eles NÃO são restateados em doc
 *   nenhum (lei §5 2026-07-17 "fato derivado não se restateia" — aponte pro dono).
 *
 * ── DIAGNÓSTICO QUE ORIGINOU O CHIP (recibo — medido, datado, com a query) ─────────
 *   sistema: git, em `origin/main` · medido 2026-07-17
 *     tamanho do corpus:  git ls-tree -d --name-only origin/main .claude/skills/ | wc -l
 *                         git ls-tree --name-only origin/main .claude/rules/ | grep -c '\.md$'
 *                         git ls-tree --name-only origin/main .claude/hooks/ | wc -l
 *                         → 74 skills · 11 rules · 64 hooks (quase tudo escrito por agente)
 *        ⚠️ ERRATA: a 1ª versão dizia "75 skills" — `ls | wc -l` contava o
 *        `_SKILLS-INDEX.md` (o índice GERADO) como skill. Conte DIRETÓRIOS.
 *     ausência de contrafactual: git grep -ril -E "counterfactual|contrafactual|ablation|
 *                         ablacao|uplift|a/b test|ab-test" origin/main -- scripts/ .claude/ .github/
 *                         → 16 arquivos, 16/16 counterfactual DE GATE ("o gate morde?"),
 *                           0 de corpus ("o corpus ajuda?"). Zero braço sem CLAUDE.md/skills.
 *
 * ── POR QUE O DESENHO OBSERVACIONAL FOI DESCARTADO (não re-propor) ────────────────
 *   Tentador: ler PRs históricos e comparar change-failure entre "a sessão carregou a
 *   skill X" e "não carregou". **Não é contrafactual — é confundidor pintado de número.**
 *   As skills auto-disparam por PATH (`preflight-modulo` em `Modules/`, `charter-first`
 *   em `.tsx` com charter): PR que carrega a skill É PR de módulo/tela. O contraste
 *   mediria TIPO DE TAREFA, não efeito de corpus, e sairia com cara de evidência. É a
 *   lápide §5 2026-07-15 (achado sem arm) + 2026-06-05 (métrica derivada do que o sistema
 *   FAZ, não de contrato). Contrafactual exige arm ATRIBUÍDO, com a mesma tarefa nos braços.
 *
 * ── ESTADO DE INVOCAÇÃO (honesto) ────────────────────────────────────────────────
 *   · `--selftest` → INVOCADO (governance-script-tests.yml). Defende a aritmética.
 *   · `--power`    → **one-shot analítico, sem invocador — e assim deve ficar.** A saída
 *     é aritmética pura (não lê o mundo): cron repetiria a mesma tabela pra sempre =
 *     ruído, não sinal. Rodável ≠ invocado; aqui a distinção é deliberada, não descuido.
 *
 * ── NÃO É GATE, E NÃO PODE VIRAR ─────────────────────────────────────────────────
 *   Vira presence-gate proibido se alguém exigir `context_helped` em frontmatter ou grep
 *   de skill no diff do PR (lápide §5 2026-07-09: presence-gate sobre texto/campo/seção).
 *   Informa PODA de corpus; não bloqueia merge de ninguém. ADR 0314: required = só Tier 0.
 *
 * USO:
 *   node scripts/governance/agent-corpus-counterfactual.mjs --power
 *   node scripts/governance/agent-corpus-counterfactual.mjs --selftest
 *
 * Refs: arXiv 2602.11988 · ADR 0105 (sinal antes do item) · 0271/0275 (advisory) ·
 *       0314 (required = Tier 0) · 0334 (anti-atrofia) · proibicoes §5 ·
 *       session 2026-07-17-contrafactual-corpus-c1-agents-md (o achado + a poda).
 */
import { spawnSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';

// ── constantes ────────────────────────────────────────────────────────────────
/** z de 95% bilateral (α=0.05) e de 80% de poder (β=0.20). */
export const Z_ALPHA_95 = 1.959963985;
export const Z_POWER_80 = 0.8416212336;

/**
 * Efeito medido pelo paper (arXiv 2602.11988) em PONTOS PERCENTUAIS.
 * LLM-generated: −0,5pp (SWE-bench Lite) a −2pp (AGENTbench). É o arm que somos.
 */
export const ETH_EFEITO_LLM_PP = { min: 0.5, max: 2.0 };
export const ETH_EFEITO_HUMANO_PP = 4.0;
export const ETH_CUSTO_OVERHEAD_PCT = 20;

const round1 = (v) => (v == null ? null : Math.round(v * 10) / 10);

// ── o decisor: a DEFINIÇÃO OPERACIONAL do "80% de poder" ──────────────────────
// Por que estas 2 funções sobreviveram à poda, se o `--power` não as chama:
// `nNeededFor` promete "80% de poder" — mas poder DE QUAL TESTE? Sem um decisor no
// repo, a promessa é infalsificável e o número vira artigo de fé. Elas são a
// definição operacional da promessa, e o self-test as usa pra PROVAR o `nNeededFor`
// por simulação (Monte Carlo determinístico) em vez de confiar na fórmula.
// Consumidor real = o self-test. Não são código de produção órfão.

/**
 * wilsonCI — IC score de Wilson pra uma proporção. Escolhido (em vez de Wald) porque
 * n é pequeno por construção: Wald dá [0,0] pra 0/5 e estoura pra fora de [0,1].
 * @returns {{p:number, lo:number, hi:number}|null} proporções 0..1; null se n<=0.
 */
export function wilsonCI(k, n, z = Z_ALPHA_95) {
  if (!Number.isFinite(n) || n <= 0) return null;
  const p = k / n;
  const z2 = z * z;
  const denom = 1 + z2 / n;
  const centro = (p + z2 / (2 * n)) / denom;
  const meio = (z / denom) * Math.sqrt((p * (1 - p)) / n + z2 / (4 * n * n));
  return { p, lo: Math.max(0, centro - meio), hi: Math.min(1, centro + meio) };
}

/**
 * newcombeDiffCI — IC da DIFERENÇA de duas proporções (Newcombe hybrid score,
 * method-10). Não se decide por "os 2 ICs se tocam?" — ler sobreposição no olho erra
 * nos dois sentidos. Este é o IC da própria diferença.
 * @returns {{delta:number, lo:number, hi:number}|null} em proporção (×100 = pp).
 */
export function newcombeDiffCI(k1, n1, k2, n2, z = Z_ALPHA_95) {
  const a = wilsonCI(k1, n1, z);
  const b = wilsonCI(k2, n2, z);
  if (!a || !b) return null;
  const delta = a.p - b.p;
  const lo = delta - Math.sqrt((a.p - a.lo) ** 2 + (b.hi - b.p) ** 2);
  const hi = delta + Math.sqrt((a.hi - a.p) ** 2 + (b.p - b.lo) ** 2);
  return { delta, lo: Math.max(-1, lo), hi: Math.min(1, hi) };
}

// ── a aritmética de viabilidade (o produto deste arquivo) ─────────────────────

/**
 * minDetectableEffect — menor δ (em pp) detectável com `nPorArm` por braço.
 *   δ = (z_α + z_β) · sqrt(2·p̄(1−p̄)/n)
 * `pBase=0.5` é o pior caso (variância máxima) → MDE sai CONSERVADOR de propósito:
 * prometer menos poder do que se tem é o erro seguro.
 */
export function minDetectableEffect(nPorArm, { pBase = 0.5, zAlpha = Z_ALPHA_95, zPower = Z_POWER_80 } = {}) {
  if (!Number.isFinite(nPorArm) || nPorArm <= 0) return null;
  const delta = (zAlpha + zPower) * Math.sqrt((2 * pBase * (1 - pBase)) / nPorArm);
  return round1(Math.min(delta, 1) * 100);
}

/**
 * nNeededFor — n POR BRAÇO pra detectar um δ (pp) dado. Inversa do MDE.
 *   n = (z_α + z_β)² · 2·p̄(1−p̄) / δ²
 * É a função que responde a pergunta cara ANTES de gastar. Foi ela que matou a versão
 * cara deste chip.
 */
export function nNeededFor(deltaPP, { pBase = 0.5, zAlpha = Z_ALPHA_95, zPower = Z_POWER_80 } = {}) {
  const d = Number(deltaPP) / 100;
  if (!Number.isFinite(d) || d <= 0) return null;
  return Math.ceil(((zAlpha + zPower) ** 2 * 2 * pBase * (1 - pBase)) / (d * d));
}

// ── render ────────────────────────────────────────────────────────────────────

/**
 * renderPower — o produto. Custo zero, sem run nenhum: quanto custa ver δ?
 */
export function renderPower({ ns = [5, 10, 20, 50, 100, 200, 500], deltas = null } = {}) {
  const ds = deltas || [ETH_EFEITO_LLM_PP.min, ETH_EFEITO_LLM_PP.max, 5, 10, 20, 30];
  const rotulo = {
    [ETH_EFEITO_LLM_PP.min]: 'piso do paper (SWE-bench Lite)',
    [ETH_EFEITO_LLM_PP.max]: 'topo do paper (AGENTbench) ← o efeito real do arm LLM-generated',
    5: 'efeito moderado',
    10: 'efeito grande',
    20: 'efeito que sozinho justifica poda',
    30: 'efeito catastrófico (corpus claramente nocivo)',
  };
  const L = [];
  L.push('═══════════════════════════════════════════════════════════════');
  L.push(' PODER DO CONTRAFACTUAL — quanto custa ver o efeito? (α=5% · poder=80%)');
  L.push('═══════════════════════════════════════════════════════════════');
  L.push('');
  L.push('▸ SE eu rodar n por braço, o MENOR efeito que consigo ver é:');
  L.push(`  ${'n/braço'.padEnd(10)} MDE (menor efeito detectável)`);
  for (const n of ns) L.push(`  ${String(n).padEnd(10)} ≥ ${minDetectableEffect(n)}pp`);
  L.push('');
  L.push('▸ SE eu quero ver um efeito de δ, preciso de:');
  L.push(`  ${'δ alvo'.padEnd(10)} ${'n/braço'.padEnd(12)} ${'runs totais (×3 braços)'.padEnd(24)} o que é esse tamanho`);
  for (const d of ds) {
    const n = nNeededFor(d);
    L.push(`  ${(d + 'pp').padEnd(10)} ${String(n).padEnd(12)} ${String(n * 3).padEnd(24)} ${rotulo[d] || ''}`);
  }
  L.push('');
  L.push('▸ LEITURA (o que este quadro decide)');
  L.push(`  · Replicar o achado do paper (${ETH_EFEITO_LLM_PP.min}–${ETH_EFEITO_LLM_PP.max}pp) exige DEZENAS a CENTENAS DE MILHARES de runs.`);
  L.push('    Não é questão de disciplina — é aritmética: n cresce com 1/δ².');
  L.push('  · O que CABE medir é efeito GRANDE (≥20pp): "o corpus é nocivo o bastante pra podar já?".');
  L.push(`  · Piloto pequeno (n≤10) NÃO responde nada: MDE ≥ ${minDetectableEffect(10)}pp. "Empatou" aí é`);
  L.push('    ausência de evidência, NÃO evidência de neutralidade — ler ao contrário é o veredito que mente.');
  L.push('');
  L.push(`  Régua: arXiv 2602.11988 — LLM-generated derruba ${ETH_EFEITO_LLM_PP.min}–${ETH_EFEITO_LLM_PP.max}pp · developer-written +${ETH_EFEITO_HUMANO_PP}pp · ambos +${ETH_CUSTO_OVERHEAD_PCT}% de custo.`);
  L.push('  Conservador de propósito: p̄=0.5 (variância máxima). Prometer menos poder que o real é o erro seguro.');
  L.push('  ADVISORY (ADR 0271/0275/0314) — informa PODA de corpus, nunca bloqueia merge.');
  L.push('═══════════════════════════════════════════════════════════════');
  return L.join('\n');
}

// ── entry-point ───────────────────────────────────────────────────────────────
if (import.meta.url === pathToFileURL(process.argv[1] || '').href) {
  const argv = process.argv.slice(2);

  if (argv.includes('--selftest')) {
    const test = new URL('./agent-corpus-counterfactual.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }

  if (argv.includes('--power')) {
    process.stdout.write(renderPower() + '\n');
    process.exit(0);
  }

  console.error('[agent-corpus-counterfactual] use --power (aritmética de viabilidade) ou --selftest.');
  console.error('  O harness de execução (--runs) foi PODADO em 2026-07-17 (autorizado por [W]):');
  console.error('  zero invocador + experimento não autorizado (ADR 0105/0334). Vive no git se voltar a ser preciso.');
  process.exit(1);
}
