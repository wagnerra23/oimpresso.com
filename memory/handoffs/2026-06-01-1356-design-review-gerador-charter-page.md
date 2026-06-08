---
date: 2026-06-01
hour: 13:56 BRT
topic: Gerador design:review por tela (charter page viva) + gate de frescor — fila Cowork #2
duration: ~2h
authors: [claude, wagner]
---

# Handoff — `design:review` por tela (fila Cowork #2) MERGED

## Estado MCP no momento
- Cycle **CYCLE-08 Receita — Onda A** (27d restantes; goals: pricing público, 5 migrações-demo, MRR R$ [redacted Tier 0]k, ComVis V1, Agrosys de-risk). Este trabalho é **infra de loop de design**, não goal direto do cycle.
- Tasks novas: **US-COPI-118** (p1, fix ui:lint Pro.tsx) + **US-COPI-119** (p2, Fase 2 juiz-LLM) — criadas via `tasks-create`, apendadas em `Jana/SPEC.md`.
- main pós-sessão: `cd071d86a` (#2079) sobre `98566bfb4` (#2078) sobre `43c9ce563` (#2077).

## O que aconteceu
Wagner mandou *"fetch this design file, read its readme, implement... readme leia"* apontando o bundle Cowork (`api.anthropic.com/v1/design/h/ifDna…`). **Decodifiquei**: o `README` (HANDOFF-ENTRY) diz que o open-file (`oimpresso.com.html`) **não é a tarefa** — a fila real é `COWORK_NOTES → Pendentes` (5 itens, **todos Tier 0**). Wagner: *"escolha e teste para ver qual é melhor"* → escolhi **#2 (Gerador `design:review`)** com mini-comparativo (nota 9.0 vs #1 5.0 / #5 7.5), provei rodando o gate.

**Passo 0 §10.4:** base `feat/staging-ct100` estava **−89 vs origin/main** (ADR local ≤0236; main 0242; `Jana/Pro.tsx` do #2069 ausente local) → trabalhei em **worktree off origin/main fresco** (anti recriar-merged / colidir-ADR).

Estendi (NÃO recriei — anti L-11) o `prototipo-ui/audit/`: o `score-mechanized.mjs` (Fase 1) já existia; faltava o **renderer por tela + frescor**. 1ª execução gerou `Jana/Pro.review.md` (nota 88), fechando o gap do #2069 (tela live sem review). Gate provou: 1 fresh (Jana/Pro) · 36 stale advisory · 21 missing baselined · exit 0.

**`ui:lint` vermelho** apareceu na CI — diagnostiquei **pré-existente do #2069** (`Pro.tsx` 2 R1 cor-crua fora do `ui-lint-baseline.json`, byte-idêntico ao main, não no meu diff). Documentei em comment no PR; meu `design:review` flagou o mesmo dente (sinergia). Wagner *"merge"* → admin-merge sobre o vermelho-conhecido (#2078). Depois *"complete merge"* → fechei o loop (#2079) + *"salve tudo e guarde nas tarefas"* → este handoff + 2 US.

## Artefatos gerados
- **PR #2078** (`98566bfb4`, +822, 9 arq.): `review-gen.mjs` · `review-freshness.mjs` · `review-freshness-baseline.json` (21 herdadas) · `DesignReviewFreshnessTest.php` (Pest, verde CI 1m55s) · `Jana/Pro.review.md` (88) · `PROTOCOL §6` (+2 checks) · `audit/README.md` · `package.json` (`design:review[:check|:baseline]`) · proposta ADR `proposals/design-review-por-tela-charter-page.md` (sem número).
- **PR #2079** (`cd071d86a`): retorno do loop — `SYNC_LOG.md` + `CODE_NOTES.md` + `HANDOFF.md`.
- **US-COPI-118 / US-COPI-119** em `Jana/SPEC.md`.

## Persistência (3 canais)
- **git:** #2078 + #2079 em main; este handoff + SPEC US + índice neste PR de fechamento.
- **MCP:** US-COPI-118/119 (`tasks-create`) — webhook sincroniza no push.
- **BRIEFING:** n/a (infra de loop, não capability de módulo).

## Próximos passos pra retomar
> `brief-fetch` → atacar **US-COPI-118** (fix ui:lint `Pro.tsx`, p1, destrava CI verde de todo PR) OU seguir a fila Cowork **#1 Método Migration→Tela** (worktree off origin/main fresco). Fase 2 (US-COPI-119) espera decisão de custo [W].

## Lições catalogadas
- **README de bundle Cowork manda ler os chats / HANDOFF-ENTRY** — open-file é porta, não tarefa (repetiu o padrão dos 2 handoffs anteriores).
- **Passo 0 §10.4 não-negociável**: base estava −89; sem o worktree-off-origin/main eu teria recriado #2069/#2073 e colidido ADR.
- **CI vermelho ≠ meu diff**: provar (não no commit + idêntico ao main + ausente do baseline) antes de atribuir; documentar no PR pro loop 0-humano não mis-culpar.
- **Dogfooding**: o `design:review` e o `ui:lint` concordaram no mesmo dente de `Pro.tsx` independentemente — o gate funciona.

## Pointers detalhados (on-demand)
- `prototipo-ui/audit/README.md` §"Review por tela" · `GOLDEN-REFERENCE.md` (10 regras)
- `memory/decisions/proposals/design-review-por-tela-charter-page.md` (mãe 0114/0236/0239)
- PR [#2078](https://github.com/wagnerra23/oimpresso.com/pull/2078) (+ comment ui:lint) · [#2079](https://github.com/wagnerra23/oimpresso.com/pull/2079)
- Cumprindo **R12 PROTOCOLO via skill `encerrar-sessao`** (ativação lazy).
