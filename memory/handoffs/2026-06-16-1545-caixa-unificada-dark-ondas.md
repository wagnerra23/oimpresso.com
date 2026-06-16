---
date: 2026-06-16
time: "1545 BRT"
slug: "caixa-unificada-dark-ondas"
tldr: "Caixa Unificada V4: dark mode CONSERTADO de verdade — o furo era arquitetural (tokens Tailwind --color-* só flipavam sob .dark, mas o AppShellV2 usa data-theme=dark; bridge em ADR 0281). + 3 ondas de polish ao protótipo Cowork. 6 PRs mergeados+deployados, verificados na prod nos 2 temas. PARTE 4 (chips de canal 'em breve') resolvida. Aberto: OS vinculada (decisão de vínculo conversa↔OS) + tom-por-canal."
decided_by: [W]
cycle: "CYCLE-08"
prs: [2818, 2826, 2838, 2839, 2841, 2845]
us: ["US-WA-308"]
next_steps:
  - "Decidir vínculo conversa↔OS (C1 heurística vs C2 coluna+ADR) pra fechar a 3ª section do Contexto (OS vinculada)"
  - "Endereçar drift de baseline TeamMcp 81→80 (task sinalizada) — bloqueia PRs com label até rebaselinar"
related_adrs: ["0281-dark-mode-bridge-data-theme-tokens", "0114-prototipo-ui-cowork-loop-formalizado", "0093-multi-tenant-isolation-tier-0"]
---

# Handoff 2026-06-16 15:45 BRT — Caixa Unificada V4: dark mode real + 3 ondas de polish

## TL;DR

O pedido nasceu como um patch de dark da Caixa Unificada (brief [CC] Cowork). Investigando contra o `main`, achei que o dark **nunca funcionou** em telas Tailwind: o `AppShellV2` ativa tema com `data-theme="dark"` no `.cockpit`, mas os tokens `--color-*` e a variant `dark:` só estavam sob `.dark` (classe nunca aplicada). A tokenização sozinha era inerte → o bridge (`.dark, [data-theme="dark"]`) em **ADR 0281** é o que conserta de verdade — e conserta TODAS as telas Tailwind. Depois disso, 3 ondas de polish ao protótipo. Tudo live e verificado na prod.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| — | Brief [CC] dark-mode fetch via curl; validação §10.4 contra `main` (origin/main à frente do que o [CC] leu) |
| — | #2818 tokenização (bg-white→bg-card, âmbar→warning-*, empty-state Customer 360) — mergeado, mas dark seguia branco na prod |
| — | Diagnóstico: `data-theme` (não `.dark`) é o mecanismo real → #2826 bridge + ADR 0281; verificado live (--color-card flipa) |
| — | Deploy travou ~1h em **SSH timeout do Hostinger** (infra) → recuperado (cancel + re-run) quando SSH voltou |
| — | Wagner pediu relação comparativa prod vs protótipo → 3 ondas de polish aprovadas e entregues |

## Estado atual dos artefatos / PRs

| PR | Status | Conteúdo |
|---|---|---|
| #2818 | merged+deploy | Tokenização dark (ConversationThreadV4 + ContextSidebarV4 chip) + empty-state Customer 360 + charter v11 |
| #2826 | merged+deploy | **Bridge `.dark, [data-theme=dark]`** no inertia.css + **ADR 0281** (foundation Tier-0) |
| #2838 | merged+deploy | Batch 1 visual: bolha 78%→68% · timestamp block-esquerda · fundo thread verde-tint dark-aware + doc gate F3 |
| #2839 | merged+deploy | Onda 2.1: 6 botões de ação do Contexto → baseline `.os-btn` (12.5px/font-medium/px-3) |
| #2841 | merged+deploy | Onda 2.2: SLA pill 4 estados (fresh/aging/late/expired) + dot animado + tempo (helpers+lista+header) |
| #2845 | merged+deploy | Onda 3 (US-WA-308): Saldo + Histórico do cliente no Contexto (transactions UPOS, Tier 0, Pest R-WA-CAIXA-UNIF-013) + charter v13 |

PARTE 4 do brief (chips de canal todos "em breve" — catálogo sem `whatsapp_whatsmeow`) **resolvida** (parallel/task) → chip "WhatsApp 91" ativo na prod.

## Decisões tomadas

| Pergunta | Decisão [W] | Referência |
|---|---|---|
| Dark bridge é foundation app-wide — como proceder? | Bridge + verificar Caixa Unif + spot-check Sells/Financeiro/Cliente | ADR 0281 |
| Fonte do Saldo cliente | **transactions UPOS** (= painel Cliente; não diverge na bridge) vs fin_titulos | #2845 |
| OS vinculada (sem vínculo no schema) | **Pular por agora** (TODO honesto; não M-AP-1) | — |
| Sidebar escura do protótipo | NÃO mexer (contradiz UI-0009/0014; só com ADR) | — |

## Bloqueios / pendências

- [ ] **OS vinculada** — decidir vínculo conversa↔OS (C1 heurística "última OS do contact" vs C2 coluna `repair_jobsheet_id`+ADR+UI) — owner: W
- [ ] **Drift baseline TeamMcp 81→80** — não-meu (main drift); todo PR pega `module-grades-gate` até rebaselinar/corrigir (task sinalizada) — owner: W/F
- [ ] tom-por-canal (baixo valor — só WhatsApp ativo) · label "SLA · alternada" (precisa `queue.dist` no payload) — owner: W

## Próximos passos (ordem)

1. Smoke vivo das 3 ondas na prod conforme o deploy assenta (botões/SLA/Saldo já confirmados; Saldo/Histórico populam quando a conversa tiver Contact CRM vinculado — provado por Pest 013)
2. Decidir OS vinculada (C1/C2) pra fechar a Onda 3
3. Endereçar o drift TeamMcp pra destravar `module-grades-gate` repo-wide

## Estado MCP no momento do fechamento

> Obrigatório (ADR 0130 §6) — snapshot real, não promessa.

### cycles-active
```
CYCLE-08 — Receita Onda A (monetizar carteira legacy) · 2026-05-31→06-28 · 57% · 12 dias restantes.
Goals: pricing público · 5 migrações-demo carteira · MRR R$2000 · ComVis V1 live · Agrosys de-riscado.
(Caixa Unificada dark/polish = qualidade de produto, fora dos goals trackados deste cycle.)
```

### my-work (@wagner — 30 ativas)
```
REVIEW(5): FIN-4 · US-TR-305/306/307 (Inbox team-mcp) · US-FIN-023
BLOCKED(6): US-NFE-043..048 (Gold on-prem, dormentes)
TODO(19 destaques): US-SELL-036 FSM rollout · US-OFICINA-026 Martinho · US-FISCAL-018 cockpit Larissa ·
  US-SELL-009 cutover ROTA LIVRE · US-INFRA-011 rotacionar senha MySQL · US-WA-058/059 inbox omnichannel
```

### whats-active
```
N/A nesta verificação — porém houve EVIDÊNCIA de sessões paralelas no main durante a sessão
(charter Caixa Unificada saltou v11→v12 fora do meu PR; PARTE 4 resolvida por outra mão; muito merge-churn).
Branchei sempre de origin/main fresco; staging por path explícito.
```

## Referências

- ADR 0281: [Dark mode bridge data-theme](../decisions/0281-dark-mode-bridge-data-theme-tokens.md)
- Doc gate F3: [CaixaUnificadaV4-visual-comparison.md](../requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md) (seção "Revisão 2026-06-16 — deltas REAIS")
- Charter: [Index.charter.md](../../resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md) (v13)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
