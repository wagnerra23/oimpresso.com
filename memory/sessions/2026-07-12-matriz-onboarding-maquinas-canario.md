---
date: "2026-07-12"
topic: "Matriz gerada (PAINEL + COMECE-AQUI) + onboarding-canary + tempestade de chips do fix OOM — a régua 'máquina ou reincide'"
authors: [W, C]
related_adrs: [0091-daily-brief, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0314-poda-gates-onda-2-lei-fusoes]
---

# Sessão 2026-07-12 — Matriz + onboarding gerado + canário + chips do OOM

## TL;DR

Começou como auditoria SDD (`/sdd-avaliar` → composto **69/100**, gargalo-mãe = OOM da nightly CT100) e virou uma sessão sobre **a régua de máquina do Wagner**: *"quero uma máquina, não confio; toda lição que não é máquina vai voltar a errar"*. Produtos duráveis: a **matriz gerada** (`system-map.mjs` emite `PAINEL-SISTEMA.md` + `COMECE-AQUI.md` das fontes vivas, não apodrece), uma **regra de path determinística** (`assertLinksLive` — recusa link/path morto, testada), o **onboarding-canary** (workflow que re-testa o onboarding), e a **tempestade de chips** que executou quase todo o plano do fix OOM em sessões paralelas. **17 PRs mergeados.** Lição perene: LLM-juiz é bom achando defeito, ruim de placar (canário deu 88 e 78 no mesmo alvo) — confie na camada determinística + nos achados, não no número.

## O arco (narrativa)

1. **Auditoria SDD** (`sdd-avaliador-processo`, 7 streams adversariais) → composto **69/100**; achado-mãe: nightly full-suite CT100 morre por OOM → junit 0-byte 9/10 noites → floor congelado → estrangula promoções. Session log dedicado: [2026-07-12-sdd-avaliacao-adversarial-processo](2026-07-12-sdd-avaliacao-adversarial-processo.md).
2. **Wagner: "estou perdido com os comandos, quero uma IA nova entender tudo".** Fiz 3 artifacts (Mapa/Guia/Manual em claude.ai) — e Wagner cravou o furo: *"isso vai apodrecer em 3 dias"*. Certo: artifact hand-made apodrece.
3. **A matriz** (`scripts/governance/system-map.mjs`, irmão do `sdd-scorecard.mjs`): gera `memory/reference/PAINEL-SISTEMA.md` (índice derivado: módulos+frescor git, ADRs, §5, Tier0 gaps, scorecard) **apontando pros donos, não recopiando**. Determinístico, `--check` no CI, workflow de regen. Depois estendido pra emitir também `COMECE-AQUI.md` (onboarding: prompt estável + 2 comandos + ponteiros — zero fato volátil à mão).
4. **"os caminhos indicados são errados, sem fonte, sem teste — isso deveria ser regra".** Virou máquina: `assertLinksLive()` varre todo path emitido e recusa emitir link morto (exit 1). Testado com path quebrado. Depois estendido pra pegar path de repo em `code` inline (pegou meu erro `Modules/Project`, que não existe — canon stale).
5. **"quero uma máquina não confio".** O onboarding "funciona (74/100)" era prosa (rodei o teste 1× à mão). Virou **`.claude/workflows/onboarding-canary.js`** — re-testa: IA cega segue o COMECE-AQUI, juiz confere, PASS/FAIL. Rodado contra o main: **88/100, zero paths quebrados** (a IA cega confirmou). Re-medição pós-conserto: **78/100** — juiz mais duro; pegou defeito que EU introduzi (fallback `ls -t` pega README/template). Consertei.
6. **Chips.** Decompus o plano do fix OOM (workflow de 13 agentes) + outras frentes em **9 chips** (`spawn_task`). Wagner clicou; sessões paralelas independentes executaram e mergearam sozinhas: V6-A/V1-node/V1-CT100/V2-C2/V4/V5 (OOM) + P06 cron + get-secret Vaultwarden + schemas grace. A correção-raiz do OOM (#4172) landou.

## Artefatos / PRs principais

- **Matriz:** `scripts/governance/system-map.mjs` + `.github/workflows/system-map.yml` + `memory/reference/{PAINEL-SISTEMA,COMECE-AQUI}.md` (`authority: generated`). PRs #4150/#4163/#4169/#4176.
- **Canário:** `.claude/workflows/onboarding-canary.js` (#4173).
- **Fix OOM (chips):** #4161 V6-A · #4166 V1-node · #4172 V1-CT100 · #4168 V2-C2 · #4170 V4 · #4160 V5 · #4171 P06 · #4165 get-secret.
- **Estrutura-canon Fase 0:** schemas briefing+reference (#4154) + grace (#4164); **Fase 1 SPEC bulk BLOQUEADA** (achado §5) → só template forward-only (#4157).
- **Decisões:** CORTE T1/T2 (#4162); as 4 decisões Wagner delegadas ("decida todas").

## Lições catalogadas (perenes)

- **Máquina ou reincide** (Wagner): lição em prosa é teatro; só gate/gerador/check executável aguenta. Aplicado a mim inclusive (meus 2 erros de path viraram regra mecânica).
- **Normalização mecânica de LEGADO em massa é bloqueada por design** pelos gates diff-aware (anchor-lint + distiller_freshness) — registrado em `proibicoes.md §5`. Conceito único chega ao legado forward-only, não big-bang.
- **LLM-juiz = defeito-finder confiável, scorer ruidoso** (88 vs 78 mesmo alvo). Arquitetura honesta: camada determinística (path-rule) + achados do canário; não perseguir o número.
- **Não afirmar sem testar** — reincidi ("não apodrece" sem teste); Wagner cobrou; corrigi o modus (testo, depois afirmo).

## Próximos passos pra retomar

Rodar `brief-fetch` (MCP estava down no fechamento). Fechar auto-merges pendentes (#4160 V5, #4178 distiller-watchdog). O chip **task_51d52dd1** ("canário determinístico") aguarda clique do Wagner. Fix OOM landado → próxima régua real é rodar 1 nightly válida no CT100 (relógio) pra o floor descongelar.

## Pointers

Handoff do fechamento: `memory/handoffs/2026-07-12-*`. Auditoria: session log irmão acima. Matriz: `memory/decisions/proposals/painel-sistema-matriz-gerada.md`.
