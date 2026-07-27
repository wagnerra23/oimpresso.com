---
date: "2026-07-27"
slug: orfaos-ligados-elo-hitl
tldr: "13 scripts órfãos de governança ligados (13→2, medido pela porta viva) + o elo detectar→decidir: 12 de 12 sentinelas agendados criavam ZERO task, e o handoff-stale repetia o mesmo alerta há 38 dias. #4834 e #4841 MERGED; #4846 (o transporte) aguarda CI. O pedido original — reconciliar âncora por símbolo — foi REPROVADO por medição e virou US-GOV-058."
time: "14:45"
topic: "13 scripts órfãos ligados (13→2) + elo detectar→decidir (12 de 12 sentinelas criavam ZERO task) + o pedido da âncora morto por medição"
authors: [C, W]
type: handoff
module: governance
pii: false
related_adrs:
  - 0070-jira-style-task-management-current-md-removed
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0303-anchor-lint-wired-testado-sa-a2-bis
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Handoff — órfãos ligados + elo HITL

## Estado no fechamento

| PR | o quê | estado |
|---|---|---|
| [#4834](https://github.com/wagnerra23/oimpresso.com/pull/4834) | liga os 13 scripts órfãos (13→2) | ✅ **MERGED** 14:36 |
| [#4841](https://github.com/wagnerra23/oimpresso.com/pull/4841) | US-GOV-056/057/058 no canal HITL | ✅ **MERGED** 14:36 |
| [#4846](https://github.com/wagnerra23/oimpresso.com/pull/4846) | `HitlEscalationService` — o transporte | 🟡 MERGEABLE, CI rodando |

## O que o próximo precisa saber

**1. O pedido original não virou código — e isso é o resultado.** A reconciliação de âncora por símbolo foi investigada e **reprovada por medição**: 0 de 469 renames em 180d quebraram âncora; símbolo existe em 4,3% dos campos e nenhum está morto; o P6 rename-proof já tinha sido cortado em 2026-06-23. O eixo real (65% do `verificado@sha` é cego por squash-merge) virou **US-GOV-058**. Não re-propor o reconciliador de símbolo sem número novo.

**2. A régua de órfão é `selftest-registry-check --scripts`** — não invente varredura própria (eu inventei duas, deram 5 e 31; a verdade era 13). Ela é report-only por design; o que faltava era **julgar**, e a triagem está no comentário do `governance-script-tests.yml`.

**3. `charter-promote-signal` e `agent-corpus-counterfactual` ficaram órfãos DE PROPÓSITO** — dar porta npm os tiraria da lista e esconderia a pendência de [W]. É a US-GOV-056.

**4. Teste que toca `mcp_tasks`/`activity_log` no CT 100 é campo minado.** O meu fazia `dropIfExists` e teria apagado a tabela real de tasks; o `sqlite-test-corruptors` pegou (score 105, tier S). Padrão seguro: shim só-se-ausente, mock de facade para o caso "tabela ausente", `activity()->disableLogging()` em vez de fabricar `activity_log` (alto raio).

**5. Tocar SPEC/módulo acorda o `distiller_freshness` (ratchet ARMADO).** Aconteceu 2× nesta sessão (Governance e depois o merge do main). O remédio é **delta declarado** no BRIEFING — nunca carimbar `distilled_at` fingindo releitura.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` (@wagner) → **8 tasks**, todas em REVIEW: US-TR-309/310/305/306/311, US-PG-008, US-PROD-027/025
- `my-inbox` → 6 unread; 5 são o **mesmo** `handoff-stale` repetindo (30d→38d) — o defeito que o #4846 conserta
- `sessions-recent` → session log desta sessão: [`2026-07-27-orfaos-ligados-elo-hitl.md`](../sessions/2026-07-27-orfaos-ligados-elo-hitl.md)

## Próximos passos (na ordem)

1. **Mergear [#4846](https://github.com/wagnerra23/oimpresso.com/pull/4846)** quando o CI fechar (o conflito com o main já foi resolvido; `distiller_freshness` limpo, medido).
2. **Decidir US-GOV-056** — `agent-corpus-counterfactual`: porta npm ou aposentar (+ lápide §5). Soberania [W].
3. **Decidir US-GOV-057** — os 3 `PROMPT_PARA_CODE` órfãos. Evidência já levantada na própria US: 2 pousaram no essencial, o resíduo é `prototipo-ui/cowork/ds-v6/tokens.css` ainda no git, e a fila está congelada (elimina a saída "citar na fila ativa").
4. **US-GOV-058** — carimbo ancestral forward-only (emenda ao ADR 0303).
5. **Fora do escopo desta sessão, mas medido:** `Governance Drift (ADR 0216)` está vermelho no main há 8 dias porque o `secrets:audit` acha drift real (token Hostinger EXPIRED há 2 meses). A máquina está certa; falta o ato.
6. **Os outros 11 sentinelas** — ligar o `HitlEscalationService` um por vez, com o caso medido. Nunca os 12 de uma vez.
