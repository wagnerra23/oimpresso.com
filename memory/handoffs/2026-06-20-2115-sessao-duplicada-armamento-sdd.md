---
date: 2026-06-20
time: "2115 BRT"
slug: "sessao-duplicada-armamento-sdd"
tldr: "Rodei o mesmo prompt da sessão #3088 (armar a casca-mole) em paralelo e dupliquei: PR #3087 (Check K) repetiu o #3084; Frente B já era o #3085 dela; hook R10 já estava armado (#3058/#3065); ADR 0172->0001 era não-bug (meu medidor confundia path de ARQ-ADR de módulo com canon — 0 double-supersede reais). Dei um falso alarme de regressão do Check K (grep de marcador gerado em runtime; diff vazio = intacto). Lição: checar git log + sessões paralelas ANTES de codar."
decided_by: [W]
cycle: "CYCLE-08"
prs: [3087]
next_steps:
  - "NÃO rodar o mesmo prompt em 2 sessões juntas — consolidar na sessão do #3088 (armar a casca-mole)"
  - "Promoções a required pendentes de [W] (ADR 0275 §5): #2 anchor-lint (bloq: 15 anchors mortos), #3 SDD scorecard (bloq: 3a métrica + janela 14d verde), #5 strict=true (precisa ADR + ok)"
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0256-knowledge-survival-meia-vida-catraca-sentinela", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo"]
---

# Handoff 2026-06-20 21:15 BRT — Sessão duplicada do armamento SDD (lição de método)

## TL;DR
[W] "como está o plano SDD? quero otimizar tudo, precisa reindexar?" → esta sessão rodou **em paralelo** com a do handoff [#3088](../sessions/2026-06-20-armar-gates-casca-mole.md), que já executava o MESMO backlog de armamento (adversário da convergência). Não detectei a paralela no início e **dupliquei o trabalho**. Artefato líquido ≈ zero.

## Resposta à pergunta-mãe (vale pra retomar)
**Otimizar o SDD ≠ reindexar.** O armamento é gate/config (advisory→required, registrar hook). Reindex (re-seed Meili) é fatia **KL** separada e exige o MCP server — fora do escopo de "armar".

## O que aconteceu
- Cherry-pick do Check K preso na `arm-gates` → **PR #3087** (mergeado). **Duplicata do #3084** (conteúdo idêntico, sem dano).
- Frentes investigadas evaporaram contra o real:
  - **A — corrigir ADR 0172→0001:** NÃO-BUG. Meu medidor extraía `\d{4}` cru de paths de ARQ-ADR de módulo (`Accounting/adr/arq/0001`) e confundia com canon. Re-medido por identidade: **0 double-supersede reais**.
  - **B — double-supersede gate:** já é o **[PR #3085](https://github.com/wagnerra23/oimpresso.com/pull/3085)** da paralela.
  - **C — hook R10:** já armado no main (#3058 registrou + #3065 endureceu); o "settings sem hook" era cópia stale de branch atrás de main.
- **Falso alarme retratado:** declarei "lost-update / regressão do Check K" baseado em grep de marcador `[K]` que é **gerado em runtime** (não literal no source). Diff `d9b1571ac↔origin/main` = **vazio** → Check K intacto e LIVE.

## Artefatos gerados
- **Líquido ≈ zero.** PR #3087 mergeado mas duplica #3084. Worktrees temporárias (`check-k`, `dbl-supersede`) + script de medição removidos. Esta sessão não deixou conhecimento canônico novo — o canon do tema é o #3088.

## Estado real do armamento SDD (fonte: #3088 — não duplicar)
- ✅ no main: Check K (#3084), R10 (#3058/#3065), plans-index (#3082/#3086), 22 US plano-perdido (#3090).
- 🔄 PR aberto: #3085 (double-supersede).
- ⏳ pra [W]: #2 anchor-lint→required (bloq: 15 anchors mortos) · #3 scorecard→required (bloq: 3a métrica + janela 14d) · #5 strict=true (precisa ADR).

## Próximos passos pra retomar
- Consolidar na sessão #3088. Próxima ação real é de [W]: decidir #2/#3/#5 (cada uma com bloqueador técnico documentado no #3088).

## Lições catalogadas
- **(reincidência) Checar `git log -5` + sessões paralelas ANTES de codar.** Já está em [sessoes-paralelas-mesma-branch]; violei. [W] replica prompts em 2-3 sessões → a 1a que landa vence, as outras devem virar no-op cedo. Custo desta violação: uma sessão inteira de retrabalho.
- **Medir supersede por número solto é furado** — `supersedes:` mistura canon-por-número e ARQ-ADR-de-módulo-por-path; normalizar por identidade (basename do path vs número canon).
- **Não detectar feature por grep de marcador gerado em runtime** — `[K]`/`[E]` do memory-health são montados no output, não literais no source; o método correto é diff de commit.

## Pointers detalhados (on-demand)
- Plano canon do armamento: [2026-06-20-armar-gates-casca-mole.md](../sessions/2026-06-20-armar-gates-casca-mole.md) (#3088)
- Diagnóstico raiz: [2026-06-20-adversario-convergencia-sistema.md](../sessions/2026-06-20-adversario-convergencia-sistema.md)
