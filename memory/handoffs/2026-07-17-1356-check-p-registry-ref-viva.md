---
date: "2026-07-17"
time: "13:56 BRT"
slug: check-p-registry-ref-viva
tldr: "Check P no memory-health guarda o AUTOMATIONS.md (e toda canon front-facing) contra ref .claude/** apontando pra arquivo morto — o porte .ps1→.mjs que esquece o registry para de reincidir. 3 PRs mergeados, Check P 0 fail/0 warn em main."
prs: [4434, 4435, 4438]
decided_by: [W]
related_adrs: [0323-governanca-conhecimento-checks-s-w-gov-sync-story-dod, 0234-automation-registry-mcp, 0256-knowledge-survival-meia-vida-catraca-sentinela]
next_steps: ["Nada pendente — loop fechado. Se um porte futuro driftar, o Check P morde no CI (Governance Gate required, umbrella sem path-filter)."]
---

# Check P — registry de automação não aponta mais pra arquivo morto

## Estado MCP no momento do fechamento

- **Cycle:** nenhum ATIVO em COPI (off-cycle).
- **my-work @wagner:** 30 tasks (10 review, 8 blocked, 12 todo) — nenhuma tocada nesta sessão (trabalho de governança fora do backlog de produto).
- **decisions-search:** âncoras = [ADR 0323](../decisions/0323-governanca-conhecimento-checks-s-w-gov-sync-story-dod.md) (Checks S–W do memory-health + registro de letras) · [ADR 0234](../decisions/0234-automation-registry-mcp.md) (automation-registry) · [ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md) (catraca+sentinela+gate). O Check P é a **letra seguinte** na convenção da 0323.

## O que aconteceu

Origem: sessão de segurança-do-agente (PR #4416) achou que o `AUTOMATIONS.md` sofre **drift sistemático** — todo porte `.ps1`→`.mjs` esquece de atualizar a coluna "Arquivo" do registry. O #4416 consertou 4 refs à mão, mas o conserto não impedia a reincidência. Tarefa: um check que **falhe/avise** quando o registry cita path de hook inexistente.

Medição matou o primeiro desenho (estender o Check V pra code-span geral = **36% de falso-positivo**: placeholders `Modules/<X>/SCOPE.md`, templates `NNNN-slug.md`, build `vendor/…`). Restringi a `.claude/**` (único prefixo relativo à raiz inequívoco): **71 refs concretas, 35 casos de prosa filtrados**. Provei que **não é redundante** com o Check V (medido: V acusa 29 links quebrados, zero `.claude/**` — code-span é invisível pro extrator de link markdown do V).

**Check P** nasceu no `memory-health.mjs` (dono do tema, ADR 0256): 🔴 fail no registry `AUTOMATIONS.md` · 🟡 warn no resto da canon front-facing. Severidade espelha o Check G. Nasceu 🔴-verde (0 refs mortas no registry em main pós-#4416). Controle-negativo obrigatório: fixture boa+ruim no `gate-selftest` (GT-G6) — `good` exit 0 (carrega glob/placeholder/dir/template = prova filtro não-cego), `bad` exit 1 `🔴 [P]`. Enforce real verificado: job required `Governance Gate (…memory-health…)` roda o script; umbrella dispara **sem path-filter** → morde no PR que toca só `.claude/hooks/`.

Os 9 warns residuais (fora do registry) foram limpos em 2 PRs, **verificados em git**: skill `baileys-update-procedure` (existiu, morta pela [ADR 0202](../decisions/0202-whatsapp-profissionalizacao-baileys-out.md)) + 3 agents fantasma (`git log --all --diff-filter=A` vazio = nunca commitados). Doutrina aplicada: o fato datado fica, só o ponteiro morto é neutralizado — não re-apontei pra alvo inexistente nem inventei substituto.

## Artefatos gerados

- [scripts/governance/memory-health.mjs](../../scripts/governance/memory-health.mjs) — Check P (+70 linhas: `checkRegistryRefViva` + header + run)
- [scripts/governance/gate-selftest.mjs](../../scripts/governance/gate-selftest.mjs) — runner `memory-health-registry-ref` (GT-G6)
- `tests/governance-fixtures/memory-health-registry-ref/{good,bad}/` — fixture boa+ruim (4 arquivos)
- [memory/governance/AUTOMATIONS.md](../governance/AUTOMATIONS.md) — nota no §"Como manter" (aponta pro dono do enforcement, não restateia)
- 6 refs `.claude/**` mortas re-anotadas em `memory/reference/*.md`

## Persistência

- **git:** #4434 (`417ecdd7`) + #4435 (`f73120fe`) + #4438 (`01731e94`) — todos MERGED, 100% checks verdes antes do merge.
- **MCP:** webhook GitHub→MCP propaga o handoff em ~2min (nada de tasks tocado).
- **BRIEFING:** N/A (governança, não módulo de produto).

## Próximos passos pra retomar

Loop fechado — nada pendente. Verificação: `node scripts/governance/memory-health.mjs` em main = **0 fail, Check P 0 warn**. Resíduo honesto restante na canon: **0** refs `.claude/**` mortas.

## Lições catalogadas

- **Medir antes de generalizar:** a extensão "óbvia" do Check V dava 36% FP — o recorte certo saiu da medição, não do palpite. Mesma família das lápides §5 (guard `@scope`/allowlist-de-pasta: critério sintático que bloqueia o legítimo).
- **Retrato inicial pode estar stale:** o "4 refs mortas em main" do briefing era pré-#4416; remedi contra `origin/main` fresco (o #4416 já tinha mergeado 3h antes) — o registry estava limpo, o que permitiu nascer sem baseline.
- **Ponteiro morto ≠ fato morto:** skill/agents citados por session log seguem "aponta pro dono, não restateia" — anota remoção/efemeridade, não apaga a história nem inventa substituto.

## Pointers detalhados (on-demand)

- Convenção de letras do memory-health + Checks S–W: [ADR 0323](../decisions/0323-governanca-conhecimento-checks-s-w-gov-sync-story-dod.md)
- Idioma fixture boa+ruim (GT-G6, "as catracas mordem"): topo de [gate-selftest.mjs](../../scripts/governance/gate-selftest.mjs)
- Lápides §5 aplicadas (presence-gate proibido · guard sintático): [proibicoes.md §"Ideias avaliadas e DESCARTADAS"](../proibicoes.md)
