---
date: "2026-07-20"
slug: two-strikes-processo-0344
tldr: "Loop two-strikes passou a cobrir erro de PROCESSO (ADR 0344 aceita+mergeada, PRs #4589+#4591). Auto-feed (fechar o loop 100%) spawnado em sessão local dedicada task_d2c3d9be — em voo."
---

# Handoff 2026-07-20 14:57 — two-strikes cobre PROCESSO + ADR 0344 + auto-feed spawnado

## O que fechou nesta sessão

- **Mecanismo two-strikes-cobre-processo LIVE em main** (PR #4589): o loop de aprendizado (hook `licoes-code-two-strikes.mjs` + `memory/LICOES_CODE.md`) agora cobre **erro de PROCESSO/comportamento de agente**, não só código. Cobertura só-`advisory` conta como "sem defesa mecânica" (segue alarmando). **LC-08 `afirmar-sem-medir-fonte-certa` (Ocorr 5)** já acende no SessionStart.
- **ADR 0344 aceita e mergeada** (PR #4591): promoção da proposal→top-level via workflow adversarial de 3 lentes (veredito `proceed_with_changes`, `governance_violations: []`). Refinamentos: opt-out `advisory-terminal (0224)` no `semGate`, crédito ao `block-ancora-no-olho`, reconciliação 0224, guarda-corpos. Verificado em `origin/main` + searchável via `decisions-search`.

## Próximo passo (JÁ EM VOO — não re-spawnar)

- **Auto-feed erros→ledger** = follow-up #1 da ADR 0344 (§Escopo NÃO incluído). É o único elo ainda manual (alguém registra `Ocorrências` à mão). **Sessão local exclusiva `task_d2c3d9be` está rodando** com prompt auto-contido + guarda-corpos (não presence-gate, não auto-declarar frescor, não duplicar régua consolidada, workflow adversarial antes de codar, prefere honesto-e-parcial a mágico-frágil). Vai virar ADR proposal + PR (merge [W]).
- Follow-ups menores documentados na ADR 0344: a **sonda que MORDE** a classe fonte-errada (bloquear "a raiz é X" sem varredura contada); estado rico "gateado-mas-vazando".

## Avisos pro próximo

- **Guarda-corpo Tier 0 da ADR 0344:** o contador `Ocorrências` é manual → **NUNCA promover a gate `required`** sem antes resolver o auto-feed (cairia nas lápides `last_validated`/`verificado_em` do §5).
- Branches órfãs mergeadas a limpar quando quiser: `claude/two-strikes-processo`, `claude/ratifica-0344` (esta sessão está em `claude/session-log-two-strikes-0344`).
- Reforço da lição MSYS: `git show origin/main:<path>` no Git Bash mangla o `:` → use `MSYS_NO_PATHCONV=1` (quase gerou um falso "não está em main" nesta sessão — ironicamente a própria classe do LC-08).

## Estado MCP no momento do fechamento

Consultado 2026-07-20 ~14:57 BRT (token [W]):

- **`cycles-active`**: nenhum cycle ATIVO em COPI.
- **`my-work` (@wagner)**: 30 tasks — 10 REVIEW, 8 BLOCKED (incl. FIN-4/FIN-004 + trilha Gold US-NFE-043..048 dormentes), 12 TODO (P0s: US-RECURRINGBILLING-002/003, US-OFICINA-026 Martinho, US-PROD-021 Kardex, US-FISCAL-018 cockpit Larissa biz=4, US-SELL-009 cutover ROTA LIVRE, US-COM-008, FORJA-142 Sells/Create). Nada desta sessão estava trackeado (foi pedido espontâneo).
- **`decisions-search "two-strikes"`**: retorna **0344-two-strikes-cobre-processo** (prova de sync webhook→MCP), + 0293 + 0329 (família governança de decisão/documentação executável).
- **`sessions-recent`**: session log desta sessão criado em `memory/sessions/2026-07-20-two-strikes-cobre-processo-0344.md`.
