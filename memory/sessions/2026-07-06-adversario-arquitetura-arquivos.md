---
date: "2026-07-06"
topic: "Adversário da arquitetura de arquivos de governança — 8 vetores, 5 achados alta, veredicto e fixes"
authors: [C]
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-catraca-sentinela-gate-cadencia
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Adversário da arquitetura de arquivos (2026-07-06)

> **TL;DR:** auditoria adversarial 100% verificada em `origin/main@2dbdceed9d` (78 tool calls,
> prova por file:line). A metade determinística (baselines, catracas de contagem, dominio,
> tamper-guard) resistiu. **O que quebra primeiro é a metade LLM+humana: a NOTA.**
> Companion do [mapa de responsabilidade](2026-07-06-responsabilidade-arquivos-governanca.md).
> Fixes 0-3+6 executados no MESMO PR que landou este doc; fix 4 (precedência) aguarda texto
> Wagner; fix 5 (re-grade cego) em sessão separada.

## Achados ALTA

### [V4] Catraca da nota NÃO existia no CI — e três documentos juravam que existia
`scripts/qa/screen-grades-ratchet.mjs` existia sem NENHUM workflow rodá-lo (grep workflows =
vazio; ausente do package.json e do gate-selftest). `vital-signs.mjs:30` e os 224 YAMLs
("a nota não pode cair abaixo de baseline_anterior") afirmavam proteção que não rodava. A fila
inteira do metabolismo MV prioriza por essa nota indefesa.
**Fix aplicado:** workflow `screen-grades-ratchet.yml` (advisory, lei 0314; `promote_by`
2026-07-20 no censo) + frase corrigida no vital-signs.mjs.

### [V4b] Regrade 2026-07-05 com viés estrutural pra cima
217 notas em 1 dia, 16 agentes **ancorados na nota antiga**, zero telas renderizadas (grep
smoke/screenshot no session log = 0 hits). Resultado 125↑ 92= **0↓** — atrito assimétrico
(subir é barato, descer exige justificar) produz exatamente esse output. Notas infladas viram
piso do ratchet e SUPRIMEM telas da fila ("verde+fresca pula").
**Fix em andamento:** re-grade CEGO de ~10 telas sorteadas (sem âncora); divergência >3 =
Onda 1 suspeita. Futuro: campo `evidence: git-log-only|code-blind|browser` no scorecard.

### [V5] "Merge do Wagner = aprovação" é inverificável
O agente compartilha a identidade `wagnerra23` (`gh pr view 3858 --json mergedBy,reviews` →
`{mergedBy: wagnerra23, reviews: []}`). O gate humano do MV e o R10 dependem desse sinal.
Pedido ad-hoc numa sessão: escopo aprovado vive só no chat, zero artefato git.
**Fix estrutural (pendente decisão):** required review ≥1 quando o time ganhar write
(antecipar); interim: trailer `Approved-by-Wagner: <data/canal>` nos merges de mv-batch.

### [V1] Charter do Fiscal/Cockpit mandava criar vazamento cross-tenant
Anti-hook dizia *"Não cachear KPIs por business — cache só agregado"*; o código (correto) faz
o OPOSTO (`fiscal:cockpit:kpis:biz:{id}`, CockpitController:313, provado por CockpitCacheTest).
Charter é a "lei" (princípio 3) — anti-hook errado é instrução ativa pra regressão Tier 0.
Nenhum gate valida CONTEÚDO de charter contra código. Bônus: charter diz "6 KPI cards",
teste asserta 7.
**Fix aplicado:** anti-hook corrigido no charter (com lápide explicando).
**Pendente (fix 4):** regra de precedência de 1 frase — proposta: *teste verde citando UC >
casos.md > charter > SPEC; conflito detectado = corrigir o perdedor no mesmo PR*.

### [V3] 84% dos UCs são 🧪/⬜ eternos — G-7 só policia ✅
90 UCs declarados na frota, 14 execução-backed (16%). 🧪/⬜ são "não-afirmações honestas"
PARA SEMPRE (sem relógio). Caminho 🧪→✅ estruturalmente quebrado: manifesto e2e commitado
manualmente + e2e-gate demovido de required. G-6 burlável por prosa (last_run bumpado por
argumento, caso real Financeiro/Unificado 2026-06-16/18). Estado absorvente: ✅ caro, 🧪 grátis.
**Fix aplicado:** memory-health **Check Z** — warn UC 🧪/⬜ em casos.md sem toque >30d.
**Pendente:** e2e-gate commitar o manifesto via auto-PR existente do sdd-scorecard-publish.

## Achados MÉDIA (resumo)

- **[V3b] 11 de 26 casos.md têm ZERO UC declarado** (só "Backlog sem id") — trio "completo"
  invisível a G-2/G-5/G-7. Superconta contrato. Fix proposto: classe advisory no `--report`.
- **[V2] `status:` do mv-batch sem defensor — mentiu no 1º dia**: batch `proposto` em main com
  scorecard `mv-batch-2026-07-06` já mergeado (#3858); o batch #1 nem nasceu do fluxo (entrou
  embutido no PR #3857). **Fix aplicado:** `batchesInconsistentes()` no metabolismo — falha
  ALTO se scorecard existe com batch não-executado + 4 counterfactuals no selftest.
- **[V6] visual-comparison "OBRIGATÓRIO" com consumidor deletado**: 96 .tsx editados em 2
  semanas vs ~7 comparisons tocados; mwart-gate.yml deletado (ADR 0271) mas a skill ainda
  exige. Regra 100% violada corrói as obrigações Tier 0. Fix proposto: emenda na SKILL.md
  restringindo a migração Blade→React real.
- **[V6b] Hook MWART destravado por RUNBOOK genérico**: `block-mwart-violation.ps1` resolve
  pelo último segmento do path; `RUNBOOK-index.md` genérico destrava todos os `*/Index.tsx`
  do módulo (e o arquivo DOCUMENTA a brecha). ADR 0104 promete per-tela; máquina cobra outra
  coisa. Decisão pendente: formalizar RUNBOOK-índice OU endurecer o hook.
- **[V7] Metabolismo sem watchdog**: cron falha/PAT expira = morte silenciosa (sintoma =
  ausência de PR — ninguém nota). `COWORK_BOT_PAT` sustenta 5 automações sem data de expiração
  catalogada. **Fix aplicado:** memory-health **Check Y** — warn `vital-signs.json` >2d.
- **[V1b] BRIEFING Financeiro não menciona Impostos** (live desde 06-10, scorecard 78) — o
  sentinela de staleness mede DATA, não conteúdo. Limite honesto registrado; incluir Impostos
  no próximo brief-update.

## BAIXA
- Precedência entre artefatos existe em TRÊS formulações que não se resolvem (CLAUDE.md
  "Charter > Spec" · casos.md "charter (lei)" · proibições "contrato vive no casos.md").
- O contrato normativo da máquina MV é um session log (narrativa como lei) — promover
  §3.2-§3.6 pros arquivos MV1/MV2 do roadmap (1 tema = 1 doc).

## Refutações honestas (o que AGUENTOU)
dominio-gate (defendido de verdade) · G-7 no estado atual (0 lies, 0 unverified — os ✅ têm
prova) · personas (documentadas) · CAPTERRA (vivo, 6/11 atualizados 07-03) · baseline do
casos-gate (BASELINE-GROW auditável bem desenhado).

## Veredicto
> A arquitetura aguenta a metralhada? **Parcialmente.** A cadeia do Módulo Vivo
> (vital-signs → prioridade → batch → aprovação) estava de pé sobre um número que (1) foi
> gerado ancorado sem olhar a tela, (2) não tinha catraca ligada apesar de 3 docs jurarem
> que tinha, (3) alimenta um gate humano cuja "aprovação" o próprio agente consegue executar.
> O segundo colapso é lento: UCs 🧪/⬜ sem relógio → museu de intenções honestas.
> Os fixes que mais pagam foram aplicados no mesmo PR (ratchet ligado + 2 relógios novos +
> consistência de batch); identidade humano/agente é o estrutural restante.
