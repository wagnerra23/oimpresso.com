---
module: SRS
purpose: "System Rules Spec — regras imutáveis pra IA programar. Append-only por trigger MySQL. Toda IA L2+ lê SRS antes de qualquer ação no código. Renomeado de MemCofre em Fase 3.7 PR-2 (2026-05-06) — rename PHP-only; URLs/permissions/config keys mantêm prefixo legacy `memcofre.*`. Repurpose funcional (cofre→SRS) ainda em transição via tabelas srs_entries futuras."
contains:
  # Estado atual (cofre evidências) — controllers que existem em prod
  - "ChatController — chat com cofre"
  - "DashboardController — dashboard MemCofre"
  - "InboxController — inbox de evidências"
  - "IngestController — ingestão de novos docs"
  - "MemoriaController — admin do cofre"
  - "ModuloController — gerenciamento por módulo"
  # Estado-alvo (SRS — após repurpose Fase 3.7)
  - "(futuro) SRS browser repurposing controllers acima"
  - "Entities/Doc* — entidades (a renomear pra SRS* na Fase 3.7)"
  - "Services/* — ingestão evidências (legacy — manter durante transição, depois deprecar)"
  - "DataController + InstallController (boilerplate)"
not_contains:
  - "Knowledge browsing (ADRs/sessions/specs canônicos) → Modules/KB"
  - "Skills (.claude/skills/*) → Modules/ADS"
  - "Regras de policy runtime → Modules/Governance (NOVO Fase 5)"
  - "Tokens MCP → Modules/TeamMcp"
trust_required: L1
owner: wagner
permission_prefix: memcofre.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /memcofre/* (legacy preservada na Fase 3.7 PR-2 — rename PHP-only)
db_tables_owned:
  - docs_evidences (legacy — repurpose ou deprecar)
  - srs_entries (NOVA — Fase 3.7, append-only com trigger MySQL)
  - srs_entries_history
drift_alerts: []
transition_plan:
  current_state: "Cofre evidências — DocController + entidades Doc*"
  target_state: "SRS browser + entidades SRS* + trigger MySQL append-only"
  migration_phase: "3.7"
  preservation: "Tabelas docs_* mantém prefixo legacy (não rename DB) — apenas namespace + URLs + entities trocam"
---

# Modules/SRS — System Rules Spec (ex-MemCofre)

## Missão (estado-alvo após Fase 3.7)

**SRS — System Rules Spec.** Regras imutáveis (append-only) que toda IA L2+ deve ler **antes de programar**. Implementam a Constituição em regras detalhadas:

- Multi-tenancy (`business_id` global scope obrigatório)
- Imutabilidade de categorias (Ponto, Audit, ADRs, SRS, Mensagens)
- Conventions de codificação (PT-BR, Pest tests, módulos modulares)
- Compliance regulatório (LGPD Art. 7º, Portaria 671, NF-e)

Cada SRS entry é **append-only**: edição = nova entry com `supersedes: <old_slug>`. Trigger MySQL `BEFORE UPDATE/DELETE` enforça (mesmo pattern de `mcp_audit_log`).

## Missão (estado atual — em transição)

Cofre de evidências (DocVault renamed em 2026-04-24). Mantém durante Fase 3.7 → vira SRS.

## Quando este módulo é tocado (após Fase 3.7)

| Trigger | Quem | Ação |
|---|---|---|
| IA L2 vai programar com `business_id` | sistema | lê SRS-0001 (multi-tenancy) antes de escrever |
| Wagner cria nova SRS via ADR | L1 | INSERT em `srs_entries` |
| Wagner supersede SRS antiga | L1 | nova entry com `supersedes` apontando antiga |
| `srs/*` push em git | webhook | sync DB cache |

## Quando NÃO é tocado

- ❌ Browsing de ADRs/sessions → Modules/KB (KB é histórico; SRS é regra ativa)
- ❌ Skills (.claude/skills/*) → Modules/ADS (skills são how-to; SRS são regras invariantes)
- ❌ Policies executáveis em runtime → Modules/Governance (Fase 5; SRS é regra escrita, Policy é regra executada)

## Drift atual

Nenhum drift de controller hoje. Repurpose vira refactor cuidadoso em Fase 3.7.

## Plano de transição (Fase 3.7)

1. Migration: `srs_entries` table com trigger MySQL append-only
2. Seed inicial SRS-0001 a SRS-0010 (multi-tenancy, imutabilidade, compliance, append-only)
3. Repurpose DocController → SRS browser
4. Rename Entities Doc* → SRS*
5. Rename namespace `Modules\MemCofre` → `Modules\SRS` (preservar tabelas)
6. Skills referenciam slugs SRS pra contexto

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial documentando transição cofre→SRS prevista pra Fase 3.7.
- **v1.1.0** (2026-05-06) — Fase 3.7 PR-2: rename PHP-only MemCofre→SRS. Pasta + namespace + class names. URLs/permissions/config/lang legacy `memcofre.*` mantidos. Repurpose funcional (cofre→SRS) ainda em transição.
