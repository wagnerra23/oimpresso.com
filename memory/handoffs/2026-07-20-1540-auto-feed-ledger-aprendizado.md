---
date: "2026-07-20"
slug: auto-feed-ledger-aprendizado
tldr: "Auto-feed do ledger (follow-up #1 da ADR 0344) construído: hook two-strikes reconcilia §5↔LICOES_CODE no SessionStart e surfaça recorrência DECLARADA não-contada — advisory, forward-only. Desenho sobreviveu a workflow adversarial 3 lentes; dry-run contado pegou bug real. PR #4599 ABERTA, aguarda ratificação [W] (R10 — não mergeei)."
---

# Handoff 2026-07-20 15:40 — auto-feed do ledger de aprendizado (reconciliação §5↔ledger)

## O que fechou nesta sessão

- **Auto-feed construído** (o elo manual que a [ADR 0344](../decisions/0344-two-strikes-cobre-processo.md) §Escopo adiou — follow-up #1, `task_d2c3d9be`). O hook `licoes-code-two-strikes.mjs` passa a **reconciliar** `LICOES_CODE.md` ↔ §5 do `proibicoes.md` no SessionStart:
  - **S3 (núcleo):** surfaça as lápides do §5 com **marcador de recorrência do autor** ("reincidência/mesma família/EMENDA da lápide/…") **> frontier e fora do ledger**. `frontier = max(datas do §5 que a linha Ocorrências cita)` — **derivado, não watermark**.
  - **S2 (secundário):** recibo pendurado — data citada resolve a uma lápide real (forma Check-T).
  - `--reconcile` = dry-run contado (evidência + ferramenta [W]).
- **Desenho gated por workflow adversarial de 3 lentes** (arquiteto·cético·escopo → síntese, ~657k tok). O cético **corrigiu o placement** (hook, não Check novo em memory-health) e matou S1-estrita / cadeia-família / watermark / fontes CI-red·reverts·degradação / big-bang — **cada um com lápide §5**. Só codei o que sobreviveu.
- **PR [#4599](https://github.com/wagnerra23/oimpresso.com/pull/4599)** aberta (4 arquivos, +400 linhas). **NÃO mergeada** — R10, [W] ratifica.

## Rótulo honesto (crítico — senão vira o próprio LC-08)

Isto **não** "lê o erro real", **não** "fecha o loop 100%", **não** auto-classifica. Reconcilia **dois docs curados à mão** → detecta a **nota do humano sobre o erro**. O elo erro→lápide e o **julgamento** seguem humanos. Encolhe **[detectar+julgar+registrar] → [julgar+registrar]**. O banner, a ADR e a PR carregam esse rótulo literal.

## Evidência (não narração)

- `--reconcile` real: `frontier 07-17 · 34 lápides · 15 marcadas · 3/3 recibos · surface 07-19+07-20 (provável ruído) · 0 cauda · 13 backlog`.
- **O dry-run pegou um bug** que a predição do workflow não viu: o `**Ref:**` do LC-08 ("raio-X 2026-07-20") contaminava o frontier → fix "só linha Ocorrências", travado por teste.
- `--selftest` **28/28** (bite-test morde). `memory-health.mjs` **0 fail** (12 warns pré-existentes, 0 dos meus arquivos).
- Drive-by: `--selftest` cross-plataforma (`fileURLToPath` vs `url.pathname` — `MODULE_NOT_FOUND` no Windows).

## Próximo passo (para o próximo agente / [W])

1. **[W] ratifica o PR #4599** (merge = aceite da proposal). **Não mergear sozinho.** Antes: `gh pr checks 4599` verde.
2. **Ao ratificar:** adicionar **1** tombstone consolidado ao §5 do `proibicoes.md` cobrindo os rejeitados (S1-estrita·watermark·CI-red→classe·Check-em-memory-health·big-bang) — forward-only; §5 fica intocado enquanto proposta.
3. **NÃO promover a required** sem uma sonda que MORDE (surface de triagem nunca bloqueia — guarda-corpo 0344).
4. **Gap residual documentado** (elo a montante humano · recorrência sem marcador = FN silencioso · frontier por-ledger não por-classe · utilidade viva não-validada) — na ADR.

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ativo em COPI** (off-cycle).
- `my-work` (@wagner): **30 tasks** — 10 REVIEW, 8 BLOCKED, 12 TODO (P0: US-RECURRINGBILLING-002/003, US-OFICINA-026, US-PROD-021, US-FISCAL-018, US-SELL-009, US-COM-008, FORJA-142). Nenhuma é este auto-feed (é off-cycle governance).
- `decisions-search "two-strikes ledger aprendizado auto-feed"`: 4 ADRs (ADS learning-loop ARQ-0006/0007/0009 + 0293) — nenhuma cobre este auto-feed; a mãe é a 0344 (não indexada nessa busca por termos).
- Branch `claude/auto-feed-ledger-aprendizado` @ origin/main `7b2d98ad75`. Worktree `blissful-cori-9e0703`.
