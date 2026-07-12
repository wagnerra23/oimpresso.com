---
date: "2026-07-12"
time: "19:00 BRT"
slug: matriz-onboarding-maquina-canario-chips
tldr: "Auditoria SDD (69/100) virou sessão sobre a régua 'máquina ou reincide': matriz gerada (PAINEL+COMECE-AQUI), regra de path determinística testada, onboarding-canary, e 17 PRs — o fix OOM inteiro landado por chips paralelos."
prs: [4150, 4154, 4157, 4160, 4161, 4162, 4163, 4164, 4165, 4166, 4168, 4169, 4170, 4171, 4172, 4173, 4176]
related_adrs: [0091-daily-brief, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0314-poda-gates-onda-2-lei-fusoes]
next_steps: ["Rodar brief-fetch (MCP down no fechamento)", "Fechar auto-merges #4160/#4178", "Wagner clica o chip task_51d52dd1 (canário determinístico)", "Nightly válida no CT100 pra descongelar o floor"]
---

# Handoff 2026-07-12 19:00 — Matriz + canário + chips do OOM

## Estado MCP no momento do fechamento

MCP server **indisponível** no fechamento (`cycles-active` → "Server unavailable"). Snapshot via git/gh:
- `origin/main` HEAD = `1e7e8e7b58` (feat(ct100): harness consome sharding #4172 — **a correção-raiz do OOM landou**).
- **17 PRs mergeados** pós-21:00 UTC (chips paralelos + meus).
- Abertos: #4160 (V5 watchdog, MERGEABLE, auto-merge) · #4178 (distiller-watchdog, chip). #4176 (consertos do canário) auto-merge armado.

## O que aconteceu

Wagner rodou `/sdd-avaliar` (69/100, gargalo OOM). Pediu "entender o sistema / IA nova entender tudo" → fiz artifacts, ele cravou *"vai apodrecer em 3 dias"*. Resposta: **matriz gerada** (`system-map.mjs` → `PAINEL-SISTEMA.md` + `COMECE-AQUI.md`, derivados, não apodrecem). Ele cobrou *"caminhos errados sem fonte sem teste"* → virou **regra mecânica** (`assertLinksLive`, testada, pega até path inline). Cobrou *"quero máquina não confio"* → **onboarding-canary** (re-testa o onboarding). Decompus o fix OOM em **9 chips**; sessões paralelas executaram e mergearam sozinhas.

## Artefatos gerados

- `scripts/governance/system-map.mjs` (matriz, ~370 linhas) + `system-map.yml` + `PAINEL-SISTEMA.md`/`COMECE-AQUI.md`.
- `.claude/workflows/onboarding-canary.js` (canário).
- Fix OOM: V6-A/V1-node/V1-CT100/V2-C2/V4/V5 + P06 + get-secret (chips).
- `proibicoes.md §5`: normalização de legado em massa = bloqueada (forward-only).
- Session log: `memory/sessions/2026-07-12-matriz-onboarding-maquinas-canario.md`.

## Persistência

Git (este PR) → webhook propaga pro MCP em ~2min. BRIEFING não aplicável (mudanças de governança/infra, não módulo cliente).

## Próximos passos pra retomar

`brief-fetch` → conferir #4160/#4178 mergearam → (se Wagner clicou) ver o PR do chip `task_51d52dd1`. Fix OOM está no código; a régua real agora é **relógio CT100** (1 nightly válida descongela o floor 291→medível).

## Lições catalogadas

- **Máquina ou reincide** (Wagner, régua da sessão) — prosa é teatro; só executável aguenta. Aplicada contra meus próprios erros de path.
- **LLM-juiz: defeito-finder confiável, scorer ruidoso** (88 vs 78) — confie na camada determinística + achados, não no número.
- **Não afirmar sem testar** — reincidi, corrigi o modus.

## Pointers

Detalhe integral no session log acima. Auditoria: `2026-07-12-sdd-avaliacao-adversarial-processo.md`. Matriz: `proposals/painel-sistema-matriz-gerada.md`. Normalização: `proposals/estrutura-canon-memoria.md`.
