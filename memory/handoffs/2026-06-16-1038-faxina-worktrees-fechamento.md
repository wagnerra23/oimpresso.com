---
date: "2026-06-16"
time: "10:38 UTC"
slug: faxina-worktrees-fechamento
tldr: "Faxina do graveyard de worktrees 57→1 com verificação adversarial (1 cético por worktree); cluster de PRs abertos resolvido — #2806/#2528/#2546 merged, #2502 fechado-superseded, #2765 fica CI-travado; #2441/#2611 mantidos abertos por serem trabalho real em voo."
decided_by: ["W"]
prs: [2806, 2528, 2546, 2502, 2765]
---

# Handoff — Faxina de worktrees + fechamento do cluster de PRs abertos

## Estado MCP

`cycles-active` não mexido. **Nenhuma task MCP alterada** — a sessão foi 100% higiene de git (worktrees + PRs abertos), zero código de produto. `main` intacta; `business_id` global scope (ADR 0093) e ADR 0144 (DB canon de estado vivo) **intocados**.

## 1. Leva 2 — fechamento

- [#2806] mergeado: handoff de fechamento "SDD Leva 2 COMPLETA (8/8) + gargalo morto — retomável a frio".

## 2. Faxina do graveyard de worktrees (57 → 1)

O repo acumulava **57 worktrees** (lixo de sessões antigas com PRs já mergeados). Limpei tudo deixando só a checkout canônica `D:/oimpresso.com`.

**Protocolo de segurança (anti-perda de trabalho):** um worktree só foi apagado se passou em 3 condições — (a) árvore limpa, (b) PR realmente MERGED (confirmado via `gh pr view`, não via `git log` — squash-merge gera SHA novo e engana o `git log`), (c) conteúdo confirmado presente em `origin/main`. Nunca apagar a branch `main`.

- **Verificação adversarial:** 1 agente cético por worktree, com a missão de *refutar* "seguro apagar" (default = manter na dúvida). Rodou em 2 levas por causa de rate-limit transiente do servidor (não meu): 42 candidatos → 22 confirmados; depois os 20 que tinham falhado por rate-limit → todos confirmados. **Zero refutações reais** nas duas levas.
- **HOLDs resolvidos depois (a pedido "o resto pode apagar"):**
  - 6 com PR merged → apagados (descartado só lixo: arquivo `R$`, `_pr_body_fx.md`, e cruft de case-fold do Windows `pt-br`↔`pt-BR`/`nfe`↔`Nfe`).
  - 5 closed/sem-PR → cada um verificado **superseded** (CODEOWNERS, GT-G7 scorecard, DEFINER strip, erros PHPStan, e o `mariadb-client` que perdeu pro DEFINER strip — todos já em `main`). SHAs ficam no reflog ~90d.

## 3. Cluster de PRs abertos (escolha do Wagner: docs/test/CI)

- **[#2528]** banner ADR 0269 — rebase na main, CI verde, **merged**.
- **[#2546]** `/mwart-override` — a parte de workflow foi **superseded** pelo rewrite ADR 0271 (a main já matou a promessa morta); reduzido só pra clarificação ainda válida no `mwart-process/SKILL.md` (override = registro humano, não comando de CI) → docs-only, CI verde, **merged**.
- **[#2502]** self-schema sqlite-only — **fechado como superseded**: o trabalho de quarentena SDD floor já está na main e é mais completo (`markTestSkipped` + `if (!Schema::hasTable())` no-drop). Mergear reverteria a direção mais nova. Worktree+branch limpos.
- **[#2765]** proposta MEDIR→GOVERNAR — rebaseada e sem conflito, mas **o CI se recusa a disparar** (0 runs mesmo após force-push + close/reopen). É doc de proposta; mergear exigiria admin-override dos gates endurecidos (ADR 0271, `enforce_admins:true`) — **não fiz**. Fica aberta, pronta.

## 4. Achados úteis

- 2 dos 3 PRs "conflitantes" (#2502, #2546) estavam **superseded por trabalho mais novo já na main**. Investigar antes de mergear evitou churn (#2546) e uma regressão real (#2502).
- O `git log origin/main..branch` **não** prova "não-mergeado" depois de squash-merge — sempre validar pelo estado do PR.

## 5. Pendências / próximos passos

- **[#2765]** segue aberta (CI não dispara). Decisão de Wagner: mergear via admin-override se quiser a proposta na main, ou deixar parada até virar ADR aceita.
- **[#2441]** (fix de bug real — cadastro de filial, PHPStan vermelho) e **[#2611]** (backfill de spec SA-A4, conflitante) — **mantidos abertos** por decisão do Wagner; são trabalho em voo, não lixo.

## Refs

- ADR 0271 (enforcement dos gates + `enforce_admins`), ADR 0144 (DB canon — preservado), ADR 0061 (conhecimento canônico em git), ADR 0093 (multi-tenant Tier 0).
- Lição existente: `licao-no-checkout-worktree-mass-delete` (por isso usei worktree full, nunca `--no-checkout`).
