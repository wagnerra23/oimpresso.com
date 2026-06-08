# Governance v4 — Bucket Assignments (34 módulos)

> Canônico desde [ADR 0160](../../memory/decisions/0160-scoped-scorecards-bucket-rubrics.md) (Wave 19) + populado Wave 20 (2026-05-17).
>
> Mudança requer label PR `bucket-change-approved` (Gate CI extension Wave 20 Agent B).
>
> Cada bucket tem rubrica YAML canon em `governance/buckets/<bucket>.yaml` (lida por `ModuleGradeServiceV4` em Wave 21).

## Distribuição

| Bucket | Meta nota | Módulos | YAML rubrica |
|---|---|---|---|
| `vertical_client_facing` | ≥85 | 5 | [`vertical_client_facing.yaml`](vertical_client_facing.yaml) |
| `cross_cutting_infra` | ≥90 | 7 | [`cross_cutting_infra.yaml`](cross_cutting_infra.yaml) |
| `ai_central` | ≥85 | 2 | [`ai_central.yaml`](ai_central.yaml) |
| `functional_horizontal` | ≥80 | 20 | [`functional_horizontal.yaml`](functional_horizontal.yaml) |
| **Total** | — | **34** | — |

---

## vertical_client_facing (meta ≥85) — 5 módulos

Verticais CNAE-específicas com cliente real vendável, UX-driven. Rubrica enfatiza UX/customer-journey/onboarding cliente real.

| Módulo | Status | Cliente real | Justificativa |
|---|---|---|---|
| **Vestuario** | em-prod | ROTA LIVRE biz=4 (Larissa) | Vertical CNAE 4781-4/00, ativa diário 2024+, monitor 1280px |
| **ComunicacaoVisual** | em-construção | candidatos OfficeImpresso (Vargas/Extreme/Gold/Zoom/Fixar/Mhundo/Produart) | Vertical CNAE 1813-0/01, schema multi-vertical, piloto 2026-Q3 |
| **OficinaAuto** | aguarda-sinal | Martinho Caçambas (a confirmar) | Vertical CNAE 4520-0/01, scaffold V0 ADR 0137, pendente confirmação |
| **Officeimpresso** | legacy-coexist | clientes Delphi 6.7 OfficeImpresso | Bridge legacy desktop, licenças vendáveis cliente real |
| **Repair** | shared-infra | qualquer vertical (Vestuario/ComVis/OficinaAuto) | Kanban OS reutilizável shared infrastructure entre verticais |

---

## cross_cutting_infra (meta ≥90) — 7 módulos

Infra interna sem cliente vendável, depende-de-tudo, time MCP usa diário. Rubrica enfatiza governance/observability/security tier 0.

| Módulo | Função | ADR canon | Justificativa |
|---|---|---|---|
| **Governance** | Constituição enforcer + audit dashboard + policies CRUD | [ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) | Meta-módulo governance, time MCP usa diário |
| **Auditoria** | UI rica /auditoria + undo sobre activity_log | [ADR 0127](../../memory/decisions/0127-modulo-auditoria-ui-rica.md) | Camada transversal, depende-de-tudo |
| **Admin** | Centro de Operações Wagner-only @ CT 100 | [ADR 0122](../../memory/decisions/0122-modulo-admin-centro-operacoes-ct100.md) | Tailscale-only read-mostly cross-tenant |
| **Brief** | Daily Brief L7 (6x/dia + tool MCP brief-fetch) | [ADR 0091](../../memory/decisions/0091-daily-brief.md) | Onboarding sessão Claude + time MCP |
| **TeamMcp** | Governança self-host MCP server (tokens/DXT/quotas/Kanban) | [ADR 0055/0057](../../memory/decisions/0055-mcp-team-governanca-self-host.md) | Time MCP usa diário pra gestão |
| **Superadmin** | Backoffice Wagner-only (packages/subscriptions B2B) | — | Cross-tenant intencional, sem cliente vendável final |
| **Connector** | Bridge API external (Passport tokens) provendo POS endpoints | — | Depende-de-tudo (Sells/Contact/Product/CashRegister) |

---

## ai_central (meta ≥85) — 2 módulos

Stack IA canônica (laravel/ai + RAG + Meilisearch). Rubrica adiciona dimensões IA específicas: hallucination rate, recall@k, cost-per-call, prompt-injection-safety.

| Módulo | Função | ADR canon | Justificativa |
|---|---|---|---|
| **Jana** | Chat IA conversacional + LaravelAiSdkDriver + 4 Agents próprios | [ADR 0035](../../memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) + [ADR 0048](../../memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) | LLM wrapper + agents canon, dimensões hallucination/cost |
| **KB** | Knowledge Base (mcp_memory_documents + FULLTEXT + Meilisearch hybrid embedder) | [ADR 0053](../../memory/decisions/0053-mcp-server-governanca-como-produto.md) | Consumido por Jana RAG, dimensões recall/relevance |

---

## functional_horizontal (meta ≥80) — 20 módulos

Útil cross-vertical sem dono cliente único, padrão CRUD/Service. Rubrica enfatiza qualidade engenharia (multi-tenant + LGPD + Pest + observability) sem peso de UX vertical-específica.

| Módulo | Função | Justificativa |
|---|---|---|
| **Crm** | Lead/contact/proposal/follow-up | CRM cross-vertical sem dono cliente único |
| **Financeiro** | Contas pagar/receber/fluxo caixa/DRE | Foundation BR horizontal útil em qualquer empresa |
| **Ponto** | Ponto eletrônico CLT/Portaria 671 | Cross-vertical útil pra qualquer empresa com funcionários |
| **RecurringBilling** | Assinaturas/Pix Auto/boleto recorrente/dunning | Cross-vertical útil em cobrança recorrente |
| **NfeBrasil** | NFe/NFC-e/SPED | Foundation fiscal BR cross-vertical |
| **NFSe** | NFSe federal LC 214/2025 | Foundation fiscal BR (serviços) cross-vertical |
| **Manufacturing** | Recipes/BOM/produção legacy UltimatePOS | Cross-vertical onde houver produção |
| **Cms** | Mini CMS (landing/blog/contact/lead) | Cross-vertical útil em qualquer empresa |
| **Spreadsheet** | Planilha compartilhada employees/roles/todos | Cross-vertical horizontal |
| **Arquivos** | DMS backbone polimórfico (ADR 0123) | Cross-vertical (HasArquivos trait opt-in) |
| **Accounting** | Bookkeeping/DRE/cashflow contábil | Cross-vertical horizontal |
| **AssetManagement** | Asset allocação/manutenção | Cross-vertical horizontal |
| **Essentials** | HRM/Documents/ToDo | Cross-vertical pra empresas com funcionários |
| **ADS** | Adaptive Decision System (Risk/Confidence/Policy/Router) | Cross-vertical pra decisões automatizadas |
| **ConsultaOs** | Portal público consulta OS read-only | Cross-vertical (Repair/oficina/gráfica) |
| **SRS** | System Requirements Spec doc-ingest (ex-MemCofre) | Cross-vertical pra documentação viva |
| **Whatsapp** | Z-API/Meta Cloud (status OS/boleto/NFe/dunning/bot Jana) | Cross-vertical (transacional WhatsApp) |
| **Woocommerce** | Bridge POS ↔ WooCommerce externo | Cross-vertical (e-commerce) |
| **ProductCatalogue** | Catálogo público read-only de produtos | Cross-vertical |
| **ProjectMgmt** | Project Mgmt Jira-style (Kanban/Backlog/Roadmap) | Cross-vertical (ADR 0070) |

---

## Como mudar bucket de um módulo

1. Abrir PR alterando `Modules/<X>/module.json` campo `governance.bucket`
2. Atualizar `governance.bucket_assigned_at` (data atual) + `bucket_assigned_by` (sigla time)
3. Atualizar `bucket_justificativa` (1 linha por que mudou)
4. Atualizar **esta tabela** (`governance/buckets/_INDEX.md`) movendo módulo entre seções
5. Adicionar label PR `bucket-change-approved` (Gate CI Wave 20 Agent B enforça)
6. Wagner aprova merge

**Bucket NÃO pode ser removido** — escolher entre os 4 canon ADR 0160. Bucket novo requer nova ADR.

---

**Wave 20 (2026-05-17)** — Agent A populou 34 module.json + criou este INDEX. Agent B (paralelo) implementou Gate CI extension exigindo label `bucket-change-approved`. Wave 21 (próxima) implementa `ModuleGradeServiceV4` consumindo `governance.bucket` + carregando rubrica YAML correspondente.
