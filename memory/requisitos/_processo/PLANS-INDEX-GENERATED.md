# Índice de Planos Vivos — GERADO (não editar à mão)

> ⚙️ **Auto-gerado** por `scripts/governance/plans-index.mjs` a partir do bloco `## Status vivo` de cada plano (ADR 0294). Regenerar: `node scripts/governance/plans-index.mjs --write`.
> Fonte única: o plano é a verdade, este índice é derivado ([ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)). Execução mora no MCP via `parent_plan` ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)). Frescor/órfão = sentinela `plan-health` (memory-health Check J).

## Saúde (derivada)
- **1** planos registrados (com `## Status vivo`) · **16** pendentes de backfill (arquivo *plan* sem bloco)
- reviewed_at preenchido: **1/1** · vinculados a MCP (`parent_plan`): **1/1**
- Por status: ativo 1
- Inconsistências de schema: 0

## Registrados (1)
| Plano | Módulo | Status | Owner | reviewed_at | parent_plan | gate-de-saída |
|---|---|---|---|---|---|---|
| [Plano — Atendimento Automático (WhatsApp / Caixa Unificada)](../Whatsapp/PLANO-ATENDIMENTO-AUTOMATICO.md) | Whatsapp | ativo | W | 2026-06-20 | `plano-atendimento-automatico` | E1+E3 com ≥5 clientes pagando JANA Pro (espelha gates da ADR |

## Pendentes de `## Status vivo` (16) — backfill dirigido pela sentinela
| Plano | Módulo |
|---|---|
| [DEPRECATION-PLAN — Accounting](../Accounting/DEPRECATION-PLAN.md) | Accounting |
| [Plano Migração Vargas → Autopecas (planejado — não existe) — 2026-05-1](../Autopecas/PLANO-MIGRACAO-VARGAS.md) | Autopecas |
| [Plano Migração 6 Saudáveis OfficeImpresso → ComunicacaoVisual ](../ComunicacaoVisual/PLANO-MIGRACAO-6-SAUDAVEIS.md) | ComunicacaoVisual |
| [JANA Pro — Product Plan executivo (32 US, 4 sprints, 90 dias)](../Copiloto/JANA-PRO-PRODUCT-PLAN.md) | Copiloto |
| [Fase 1 — Conciliação passa a enxergar o extrato da API](../Financeiro/PLANO-FASE1-CONCILIACAO-LE-EXTRATO-API.md) | Financeiro |
| [Fase 2 — Migração de dados: unificar extrato OFX → tabela canônica](../Financeiro/PLANO-FASE2-MIGRACAO-EXTRATO-UNIFICADO.md) | Financeiro |
| [Plano Detalhado — Módulo Financeiro](../Financeiro/PLANO_DETALHADO.md) | Financeiro |
| [Plano de Testes Fiscal — Ondas 1-7](../Fiscal/PLANO-TESTES-FISCAL.md) | Fiscal |
| [Plano de migração das 82 auto-mems → git/MCP (ADR 0061)](../Infra/PLANO-MIGRACAO-AUTOMEM.md) | Infra |
| [PLAN MWART — `metas/*` (Jana)](../Jana/PLAN-MWART-metas.md) | Jana |
| [Onda 1 — Vendas, PDV & Caixa · PLANO (MWART Fase 1)](../Mwart/ONDA-1-VENDAS-PDV-CAIXA-PLANO.md) | Mwart |
| [Plano de paralelização — OficinaAuto Fase 1 (pós-Martinho)](../OficinaAuto/demo-martinho-2026-05-13/plano-paralelizacao.md) | OficinaAuto |
| [PaymentGateway Onda 5 SIMPLIFICADA — Dogfooding SaaS via gateway adici](../PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md) | PaymentGateway |
| [DEPRECATION-PLAN — SRS](../SRS/DEPRECATION-PLAN.md) | SRS |
| [ADR ARQ-0001 (TaskRegistry) · Sistema de tasks MCP-native, não Plane s](../TaskRegistry/adr/arq/0001-mcp-native-vs-plane.md) | TaskRegistry |
| [Índice de Planos Vivos (gerado)](_PLANS-INDEX-GENERATED.md) | _processo |

