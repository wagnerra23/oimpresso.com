# Índice de Planos Vivos — GERADO (não editar à mão)

> ⚙️ **Auto-gerado** por `scripts/governance/plans-index.mjs` a partir do bloco `## Status vivo` de cada plano (ADR 0294). Regenerar: `node scripts/governance/plans-index.mjs --write`.
> Fonte única: o plano é a verdade, este índice é derivado ([ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)). Execução mora no MCP via `parent_plan` ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)). Frescor/órfão = sentinela `plan-health` (memory-health Check J).

## Saúde (derivada)
- **7** planos registrados (com `## Status vivo`) · **23** pendentes de backfill (arquivo *plan* sem bloco)
- reviewed_at preenchido: **4/7** · vinculados a MCP (`parent_plan`): **4/7**
- Por status: ativo 3 · proposto 3 · (vazio) 1
- Inconsistências de schema: 4 — ver final

## Registrados (7)
| Plano | Módulo | Status | Owner | reviewed_at | parent_plan | gate-de-saída |
|---|---|---|---|---|---|---|
| [Plano de aprofundamento das avaliações](../_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md) | _Governanca | — | — | — | — | — |
| [PLANO MESTRE — Programa de Ondas com Adversário por Módulo](../_Governanca/programa-ondas/PLANO-MESTRE.md) | _Governanca | ativo | W | 2026-08-05 | `programa-ondas` | ✅ **BATIDO 2026-07-03** — dente de cálculo red/green no CT10 |
| [Plan — {{título curto da feature}}](../_TEMPLATE_FEATURE/plan.md) | _TEMPLATE_FEATURE | proposto | — | — | — | {{quando todos os ACs estarão provados e a US ancorada}} |
| [Plan — OpenAPI 3.0 seguro para o Connector](../Connector/features/openapi-connector/plan.md) | Connector | ativo | W/F | 2026-08-03 | `connector-openapi` | AC-1..7 provados, smoke de suporte concluído e US-CONN-013 a |
| [Plan — arquitetura do recebimento parcial de parcela](../Financeiro/features/recebimento-parcial-parcela/plan.md) | Financeiro | proposto | W | — | `financeiro-recebimento-parcial-parcela` | — |
| [OBSERVABILITY — Jana](../Jana/OBSERVABILITY.md) | Jana | proposto | W/C | 2026-07-28 | — | um caso real observado percorre trace → avaliação → revisão  |
| [Plano — Atendimento Automático (WhatsApp / Caixa Unificada)](../Whatsapp/PLANO-ATENDIMENTO-AUTOMATICO.md) | Whatsapp | ativo | W | 2026-06-20 | `plano-atendimento-automatico` | E1+E3 com ≥5 clientes pagando JANA Pro (espelha gates da ADR |

## Pendentes de `## Status vivo` (23) — backfill dirigido pela sentinela
| Plano | Módulo |
|---|---|
| [DEPRECATION-PLAN — ADS](../ADS/DEPRECATION-PLAN.md) | ADS |
| [DEPRECATION-PLAN — Accounting](../Accounting/DEPRECATION-PLAN.md) | Accounting |
| [DEPRECATION-PLAN — Admin](../Admin/DEPRECATION-PLAN.md) | Admin |
| [Plano Migração Vargas → Autopecas (planejado — não existe) — 2026-05-1](../Autopecas/PLANO-MIGRACAO-VARGAS.md) | Autopecas |
| [DEPRECATION-PLAN — Brief](../Brief/DEPRECATION-PLAN.md) | Brief |
| [Plano Migração 6 Saudáveis OfficeImpresso → ComunicacaoVisual ](../ComunicacaoVisual/PLANO-MIGRACAO-6-SAUDAVEIS.md) | ComunicacaoVisual |
| [⚰️ LÁPIDE — Product Plan movido pra `requisitos/Jana/`](../Copiloto/JANA-PRO-PRODUCT-PLAN.md) | Copiloto |
| [DEPRECATION-PLAN — Pipeline CRM pré-venda (Crm parte B)](../Crm/DEPRECATION-PLAN-pipeline.md) | Crm |
| [Fase 1 — Conciliação passa a enxergar o extrato da API](../Financeiro/PLANO-FASE1-CONCILIACAO-LE-EXTRATO-API.md) | Financeiro |
| [Fase 2 — Migração de dados: unificar extrato OFX → tabela canônica](../Financeiro/PLANO-FASE2-MIGRACAO-EXTRATO-UNIFICADO.md) | Financeiro |
| [Plano Detalhado — Módulo Financeiro](../Financeiro/PLANO_DETALHADO.md) | Financeiro |
| [Plano de Testes Fiscal — Ondas 1-7](../Fiscal/PLANO-TESTES-FISCAL.md) | Fiscal |
| [Plano de migração das 82 auto-mems → git/MCP (ADR 0061)](../Infra/PLANO-MIGRACAO-AUTOMEM.md) | Infra |
| [Plano · Profissionalizar acesso do time (proteger fonte + controlar no](../Infra/PLANO-profissionalizar-acesso-time.md) | Infra |
| [JANA Pro — Product Plan executivo (32 US, 4 sprints, 90 dias)](../Jana/JANA-PRO-PRODUCT-PLAN.md) | Jana |
| [PLAN MWART — `metas/*` (Jana)](../Jana/PLAN-MWART-metas.md) | Jana |
| [Onda 1 — Vendas, PDV & Caixa · PLANO (MWART Fase 1)](../Mwart/ONDA-1-VENDAS-PDV-CAIXA-PLANO.md) | Mwart |
| [Plano de paralelização — OficinaAuto Fase 1 (pós-Martinho)](../OficinaAuto/demo-martinho-2026-05-13/plano-paralelizacao.md) | OficinaAuto |
| [PaymentGateway Onda 5 SIMPLIFICADA — Dogfooding SaaS via gateway adici](../PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md) | PaymentGateway |
| [Plan — Ativar gateway nas assinaturas dormentes](../RecurringBilling/features/gateway-ativacao/plan.md) | RecurringBilling |
| [DEPRECATION-PLAN — SRS](../SRS/DEPRECATION-PLAN.md) | SRS |
| [ADR ARQ-0001 (TaskRegistry) · Sistema de tasks MCP-native, não Plane s](../TaskRegistry/adr/arq/0001-mcp-native-vs-plane.md) | TaskRegistry |
| [DEPRECATION-PLAN — TeamMcp](../TeamMcp/DEPRECATION-PLAN.md) | TeamMcp |

## Inconsistências de schema (4)
- ⚠️ memory/requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md: status inválido "(vazio)" (enum ADR 0294)
- ⚠️ memory/requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md: sem reviewed_at
- ⚠️ memory/requisitos/_TEMPLATE_FEATURE/plan.md: sem reviewed_at
- ⚠️ memory/requisitos/Financeiro/features/recebimento-parcial-parcela/plan.md: sem reviewed_at
