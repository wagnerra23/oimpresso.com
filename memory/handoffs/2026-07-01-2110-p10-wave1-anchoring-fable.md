---
date: "2026-07-01"
time: "21:10"
slug: p10-wave1-anchoring-fable
tldr: "P10 wave 1 — anchoring em lotes com refutador Fable tier superior: 226 US ancoradas em 4 módulos (Financeiro/Whatsapp/Jana/OficinaAuto), 10 PRs aguardando merge Wagner, fila A6 materializada; 2 lotes reprovados na rodada 1 (6 erros reais) e re-aprovados pós-correção"
decided_by: "[CC] Claude (Fable 5)"
prs: [3530, 3539, 3540, 3541, 3542, 3543, 3544, 3546, 3549]
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# Handoff — P10 wave 1 (anchoring batches + refutador Fable)

## O que aterrissou (aguardando merge Wagner — R10)

| PR | Conteúdo | Estado |
|---|---|---|
| [#3530](https://github.com/wagnerra23/oimpresso.com/pull/3530) | tooling: ledger-check reconhece fable/mythos + regra refutador TIER SUPERIOR (protocolo G5 endurecido) | CI 100% verde |
| [#3539](https://github.com/wagnerra23/oimpresso.com/pull/3539) + [#3540](https://github.com/wagnerra23/oimpresso.com/pull/3540) | Financeiro: 38 US ancoradas + ledger (r1 reprovado 7,5% → r2 aprovado) + 1 charter | CI em fila |
| [#3541](https://github.com/wagnerra23/oimpresso.com/pull/3541) + [#3542](https://github.com/wagnerra23/oimpresso.com/pull/3542) | OficinaAuto: 48 US + ledger (r1 reprovado 3,7% → r2 aprovado) + 1 charter | CI em fila |
| [#3543](https://github.com/wagnerra23/oimpresso.com/pull/3543) + [#3544](https://github.com/wagnerra23/oimpresso.com/pull/3544) | Jana: 68 US + ledger (aprovado 1,35%, correção charter aplicada) + 1 charter | CI em fila |
| [#3546](https://github.com/wagnerra23/oimpresso.com/pull/3546) | Whatsapp: 72 US + ledger (aprovado 0%) — charter #3547 FECHADO (gate live-signal) | CI em fila |
| [#3549](https://github.com/wagnerra23/oimpresso.com/pull/3549) | fila A6 `_ANCHOR-REVIEW-QUEUE.md` + session log da campanha | CI em fila |

**Ordem de merge sugerida:** #3530 primeiro (tooling; sem ele o ledger-check advisory flaga `fable` como modelo desconhecido) → depois os 4 de âncora em qualquer ordem (ledger conflita por append no mesmo array — conflito trivial, re-rebasear) → charters e fila por último.

**Coverage projetado pós-merge:** anchor_coverage 16,1% → **~42,6%** (sem_campo 717→491, dead=0, zombie=0).

## Decisões que ficam pro Wagner (fila A6, #3549)

1. §3: 6 telas órfãs de spec (charter sem US confiável).
2. §3-bis: 13 `related_us` verificados mas DEFERIDOS pelo gate charter-live-signal (required) — precisam smoke datado ou prod-flags por tela.
3. §4: batch 1 Sells (#3483) sem entry no ledger + 17 sem_campo restantes (stashes de outra sessão na branch `claude/p10-batch1-sells`).
4. §5: triagem 3-baldes da dívida entry-gate (A teste real / B status-truth / C `_lacuna_`) — zero teste tautológico.

## Próxima sessão (wave 2)

Ordem valor×buraco: Sells-completion (17) + NfeBrasil (18) → RecurringBilling (36)+Compras (8)+PaymentGateway (6) → Crm (22)+Pcp (21)+Fiscal (19) → Governance (35)+Mwart (13) → Infra (45) → wish verticais → cauda. NÃO ancorar os gated (TaskRegistry/Inventory/EvolutionAgent/LaravelAI/MemoriaAutonoma — trilha E). Receita comprovada: gerador Opus isolado → refutador Fable sessão fresca 100% → PR pareado + ledger (2 lotes de 4 foram REPROVADOS na r1 — a refutação tier superior NÃO é teatro).

Trilho floor/nightly (outra sessão): armar `anchor_coverage` após 3 medições consecutivas do cron; promoção ledger-check a `--enforce`+required segue ADR 0275 §5.

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ativo** em COPI.
- `my-work` (wagner): 30 tasks (8 review / 8 blocked / 14 todo) — inalteradas por esta sessão (campanha é docs/governança, nenhuma US de produto tocada).
- `decisions-search "anchor SDD scorecard refutador"`: 0273, 0275, 0303, 0307, 0291 ativas — nenhuma ADR nova exigida (a regra do refutador tier superior é emenda de PROTOCOLO operacional, não ADR; se Wagner quiser formalizar, cabe ADR curta citando a avaliação 2026-07-01).
- `sessions-recent` (equivalente local): 7 session logs em 2026-07-01, incl. `sdd-avaliacao-adversarial` (gatilho desta campanha) e `sdd-p10-wave1-anchoring-batches` (esta sessão).
