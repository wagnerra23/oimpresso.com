---
date: "2026-07-03"
time: "12:45 BRT"
slug: us-rb-056-materializada
tldr: "Follow-up do dente RecurringBilling (#3737) materializado: US-RB-056 (unificar as 3 impls de próximo-vencimento) criada no backlog MCP + bloco no SPEC canônico via PR #3747 (merged). Não executei a mudança de cálculo — é REGRA MESTRE."
prs: [3747]
decided_by: [W]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0101-tests-business-id-1-nunca-cliente]
next_steps: ["Executar US-RB-056 quando priorizada — sob REGRA MESTRE (dupla confirmação + tabela antes→depois das datas de cobrança afetadas + OK [W]); o golden do #3737 vira RED conscientemente na unificação"]
---

# US-RB-056 materializada (follow-up do dente RecurringBilling)

Continuação curta do handoff [2026-07-03 12:15](2026-07-03-1215-dente-calculo-recurringbilling.md). Wagner disse "vai" → materializei a pendência que aquele handoff deixou aberta.

## Estado MCP no momento do fechamento
- **cycles-active:** off-cycle (nenhum cycle ATIVO em COPI).
- **tasks-list RecurringBilling todo:** 28 tasks. **US-RB-006 "Proração mid-cycle" já existia** (p2) — confirma que proração é feature *planejada*, não gap silencioso; o teste #3737 corretamente não a testa (não existe no código). Não havia task pra a divergência de próximo-vencimento → criei.

## O que aconteceu
1. `tasks-create` → **US-RB-056** (p1, todo, unowned) no módulo RecurringBilling.
2. O `tasks-create` escreve no filesystem do MCP server (CT100), não no git canon → pra evitar drift ("mexeu, registra"), apliquei o mesmo bloco no SPEC canônico via PR e mergeei.

## Artefatos gerados
- Backlog MCP: **US-RB-056**.
- `memory/requisitos/RecurringBilling/SPEC.md` — bloco US-RB-056 + `last_updated: "2026-07-03"` (PR **#3747** merged, schema-gate verde).

## Persistência
- **git:** PR #3747 squash-merged em `main` (`cf372ca543`). Confirmado `US-RB-056` em `origin/main:SPEC.md`.
- **MCP:** task no backlog + webhook propaga o SPEC ~2min após merge.

## Próximos passos pra retomar
- US-RB-056 é a próxima ação do tema, mas **gated por REGRA MESTRE** (muda data de cobrança em prod). Não iniciar sem: dupla confirmação por 2 caminhos + tabela antes→depois das assinaturas com anchor dia-31 / ciclo editado + OK [W]. Ao unificar B/C→A (NoOverflow), os goldens `divergencia_*` do #3737 viram RED conscientemente (atualizar junto).

## Lições catalogadas
- **`tasks-create` MCP grava no server, não no git canon** — sempre espelhar o bloco gerado no SPEC canônico via PR pra não deixar drift entre backlog MCP e git (regra "mexeu, registra").

## Pointers detalhados
- Teste que caracteriza a divergência: `tests/Feature/Calculo/CalculoRecurringBillingTest.php` (#3737).
- Alvos: `Modules/RecurringBilling/Services/{InvoiceGeneratorService,AssinaturaService,AssinaturaCobrancaService}.php`.
