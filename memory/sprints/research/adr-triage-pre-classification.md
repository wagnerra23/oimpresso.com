---
name: ADR Triage — pré-classificação 92 ADRs (rascunho Sonnet)
description: Pré-classificação das 92 ADRs ativas em lifecycle 5 estados (proposed/accepted/superseded/deprecated/sunsetting). Wagner aprova bloco a bloco no S7. Economiza ~6h Wagner.
type: project
created: 2026-05-06
related_sprint: S7
status: draft  # → reviewed após Wagner passar bloco a bloco
total_adrs: 92
---

# ADR Triage — Pré-classificação 92 ADRs

> **Status:** 🟡 DRAFT — Sonnet pré-classificou. Wagner revisa bloco a bloco no S7.
>
> **Lifecycle estados** ([ADR best practices 2026](https://github.com/pogopaule/architecture_decision_record/blob/master/adr_lifecycle.md)):
> - `accepted` (KEEP CANON) — em uso, ativa, não tem substituta
> - `superseded` — foi substituída por ADR mais nova (linkar `superseded_by`)
> - `deprecated` — tecnologia/abordagem abandonada, mantida pra histórico
> - `sunsetting` — em fase de aposentadoria, ainda tem refs mas saindo
> - DELETE — **ABOLIDO** (ADR é append-only, ver deep-dive S7)

> **Proposta global:** ~30 KEEP CANON, ~40 superseded, ~15 deprecated, ~7 sunsetting.
> Resultado pós-S7: 30 ADRs canônicas ativas (alvo do roteiro).

---

## Bloco 1 — Bases originais Ponto/UltimatePOS (ADRs 0001–0011)

> Decisões fundacionais. Maioria KEEP CANON.

| ADR | Título | Estado proposto | Justificativa |
|---|---|---|---|
| 0001 | Estender UltimatePOS em vez de build próprio | **accepted** | Decisão fundacional. Ainda ativa. |
| 0002 | Usar nWidart/laravel-modules como sistema de módulos | **accepted** | Stack confirmada em todos sprints. |
| 0003 | Marcações append-only com triggers MySQL | **accepted** | Lei (Portaria 671/2021). Tier 0. |
| 0004 | Tabela bridge `ponto_colaborador_config` | **accepted** | Padrão de extensão UltimatePOS. |
| 0005 | UUID para entidades auditáveis, BigInt para lookups | **accepted** | Convenção atual. |
| 0006 | Multi-tenancy lógica via `business_id` | **accepted** + reforçada | **Antecede e sustenta ADR 0093 (Tier 0)**. |
| 0007 | Banco de horas como ledger append-only | **accepted** | Lei. |
| 0008 | Sidebar 1 item + menu horizontal abas | **superseded** | Substituída pelo padrão AppShellV2 + SIDEBAR_GROUPS (ADR 0039 + skill `sidebar-menu-arch`). |
| 0009 | Protótipos visuais HTML+Tailwind+Chart.js | **deprecated** | Não usado mais; tudo em React/Inertia agora. |
| 0010 | Sistema de memória (CLAUDE.md + /memory/) | **superseded** | Substituída por governança ADR 0027 + 0053 + Constituição S3. |
| 0011 | Alinhamento padrão Jana (UltimatePOS) | **accepted** | Padrão de referência citado em criação de novos módulos. |

---

## Bloco 2 — Inventário e integrações (ADRs 0013–0020)

| ADR | Título | Estado proposto | Justificativa |
|---|---|---|---|
| 0013 | Ecossistema de Módulos: Inventário, Categorias e Padrões | **accepted** | Inventário canônico. |
| 0014 | Integração PontoWR2 × Essentials (HRM) | **accepted** | Spec ainda relevante. |
| 0015 | Connector: API Gateway para Integrações Externas | **accepted** | Módulo ativo. |
| 0016 | Plano de Otimização e Roadmap PontoWR2 | **superseded** | Roadmap antigo. Substituir por charter de mission Ponto. |
| 0017 | Officeimpresso restaurado (3.7→6.7) Superadmin exclusivo | **accepted** | Decisão arquitetural ativa. |
| 0018 | Log de acesso desktop via triggers MySQL (passivo) | **accepted** | Implementação canônica. |
| 0019 | Delphi legado não autentica após upgrade | **accepted** + investigação contínua | Histórico de incidente; mantém. |
| 0020 | Grupo econômico (matriz + filiais) no Officeimpresso | **accepted** | Modelagem de domínio ainda relevante. |
| 0021 | Contrato real da API consumida pelo Delphi | **accepted** | Imutável (auto-mem confirma "Delphi contrato IMUTÁVEL"). |
| 0022 | Meta financeira oimpresso: R$ 5 milhões/ano | **accepted** | Meta de produto. |

---

## Bloco 3 — Stack canônica e padrões UI (ADRs 0023–0030)

| ADR | Título | Estado proposto | Justificativa |
|---|---|---|---|
| 0023 | Upgrade para Inertia.js v3 | **accepted** | Stack atual. |
| 0024 | Instalação 1-clique padronizada para todos os módulos | **accepted** | Skill `criar-modulo` referencia. |
| 0025 | Redesign da landing pública (CMS) em Inertia/React | **accepted** | Em prod parcial. |
| 0026 | Posicionamento estratégico: ERP de Comunicação Visual com IA | **accepted** | Posicionamento de produto. |
| 0027 | Gestão de memória do projeto: papéis claros por função | **accepted** | Citada em CLAUDE.md §6 atual. |
| 0028 | ADRs com numeração monotônica e formato Nygard | **accepted** | Princípio canônico. |
| 0029 | Padrão Inertia + React + UltimatePOS pra módulos novos | **accepted** | Skill `criar-modulo`. |
| 0030 | Credenciais sensíveis: nunca em git | **accepted** | Tier 0. |

---

## Bloco 4 — Stack de IA Copiloto (ADRs 0031–0046)

> Maior bloco de mudanças. Vizra ADK foi rejeitada (ADR 0048). Várias antigas foram superseded.

| ADR | Título | Estado proposto | Justificativa |
|---|---|---|---|
| 0031 | `MemoriaContrato` interface PHP + driver default Mem0 | **superseded** | Substituída pelo MeilisearchDriver canônico (ADR 0036). |
| 0032 | Vizra ADK + Prism PHP como camada de orquestração | **superseded** | **REJEITADA por ADR 0048**. Manter como histórico. |
| 0033 | Vector store: pgvector vs Meilisearch+Scout vs Mem0 | **superseded** | Substituída por ADR 0036 (Meilisearch first). |
| 0034 | Laravel AI ecosystem 2026 (SDK + Boost + MCP + Vizra + alternativas) | **accepted** | Survey ainda relevante; mas alguns itens superseded individualmente. |
| 0035 | Stack-alvo IA Copiloto canônica (Wagner 2026-04-26) | **accepted** | **Stack canônica vigente.** |
| 0036 | Replanejamento canônico: Meilisearch first, Mem0 último | **accepted** | Strategy vigente. |
| 0037 | Roadmap evolução pós-Sprint 5: Tier 5-6 → Tier 7-9 LongMemEval | **accepted** | Roadmap de retrieval. |
| 0038 | Promoção 6.7-bootstrap para main | **deprecated** | Branch operation já feita; histórico. |
| 0039 | Padrão UI "Chat Cockpit" (3 colunas) | **superseded** | Substituída por AppShellV2 (DESIGN.md). |
| 0040 | Policy publicação: Claude supervisiona, Wagner escala | **accepted** | Skill `publication-policy`. |
| 0041 | Stack QA IA: Vizra ADK + Langfuse + DeepEval (Caminho B) | **superseded** | Vizra rejeitada; substituir por ADR de eval atualizada (ainda não criada). |
| 0042 | Reverb (self-hosted) substitui Pusher Cloud | **superseded** | **Substituída por ADR 0058 (Centrifugo + FrankenPHP)**. |
| 0043 | Docker + Traefik + Portainer num LXC, em vez de N LXCs nativos | **accepted** | Padrão CT 100. |
| 0044 | Vaultwarden self-hosted como cofre de credenciais | **accepted** | Em prod. |
| 0045 | Endpoint canônico Hostinger DNS API V1 | **accepted** | Skill DNS uses. |
| 0046 | `ChatCopilotoAgent` precisa contexto rico + tools (gap) | **accepted** | Gap ainda em aberto. |

---

## Bloco 5 — Sprint memória agente + governança (ADRs 0047–0061)

| ADR | Título | Estado proposto | Justificativa |
|---|---|---|---|
| 0047 | Wagner solo: sprint memória do agente | **deprecated** | Sprint operacional concluído; histórico. |
| 0048 | Framework de agentes: `laravel/ai` (Vizra ADK rejeitada) | **accepted** | Decisão canônica. |
| 0049 | Camadas de memória do agente: ligar fase por fase | **accepted** | Padrão de evolução. |
| 0050 | 8 métricas obrigatórias de memória + tabela `memory_metrics` | **accepted** | Métricas em prod. |
| 0051 | Schema próprio + adapter pattern + emissão OpenTelemetry GenAI | **accepted** | Implementado parcialmente. |
| 0052 | `ContextoNegocio` deve expor múltiplos ângulos por métrica | **accepted** | Implementado. |
| 0053 | MCP server: governança como produto, não overhead | **accepted** | **Tier 0.** |
| 0054 | Pacote enterprise busca memória: por que + como evolui | **accepted** | Roadmap de retrieval. |
| 0055 | Self-host Team plan equivalente Anthropic Team/Enterprise | **accepted** | Direção estratégica. |
| 0056 | MCP server como fonte única memória pro Copiloto + Claude Code | **accepted** | Em prod. |
| 0057 | Tela `/team-mcp/team`: governança tokens + distribuição via `.dxt` | **accepted** | Implementado parcialmente. |
| 0058 | Reverb substituído por Centrifugo + FrankenPHP | **accepted** | **Stack realtime atual.** |
| 0059 | Governança da memória estilo Anthropic Team (10 pilares) | **accepted** | Princípios canônicos. |
| 0060 | IA + workers pesados na rede interna (Proxmox), app principal Hostinger | **accepted** | Padrão arquitetural. |
| 0061 | Conhecimento canônico em git/MCP, ZERO auto-mem privada | **accepted** | **Tier 0.** Hook bloqueador ativo. |

---

## Bloco 6 — Runtime + governança 2026-04 (ADRs 0062–0076)

| ADR | Título | Estado proposto | Justificativa |
|---|---|---|---|
| 0062 | Separação dura runtime: Hostinger ≠ CT 100 Proxmox | **accepted** | **Tier 0** runtime. Skill `runtime-rules-hostinger-ct100`. |
| 0063 | Prevenir composer.lock drift permanentemente | **accepted** | Auto-mem confirma. |
| 0064 | Modularização — split TeamMcp + KB + Superadmin 360° | **accepted** | Direção arquitetural. |
| 0065 | Permission Registry — contrato declarativo per-módulo | **accepted** | Implementado parcialmente. |
| 0066 | `format_date` shift +3h preservado — quirk legacy ROTA LIVRE | **accepted** | **Crítico não regredir.** |
| 0067 | Sprint 8 — McpMemoryDocument Searchable + retrieval hybrid RAGAS | **accepted** | Implementado. |
| 0068 | Sprint 9 — Estratégia retrieval: Ollama embedder + reranking | **accepted** | Plano técnico. |
| 0069 | Governança de tasks: TaskRegistry MCP tools, TASKS.md ASCII deprecated | **superseded** | Substituída por ADR 0070. |
| 0070 | Jira-style task management — CURRENT.md/TASKS.md removidos | **accepted** | **Decisão vigente.** |
| 0071 | Auditoria tools MCP 2026-05-05 — bugs + workarounds | **accepted** | Documentação operacional. |
| 0072 | Maturação memória + Team MCP — gaps vs OpenClaw/Mem0/Letta/Zep/A-Mem | **accepted** | Análise comparativa. |
| 0073 | Team MCP P0 — skills e policies como entidades governadas | **accepted** | Direção arquitetural. |
| 0074 | P1 — Temporal validity bi-temporal: event-time vs system-time | **accepted** | Pendente implementação. |
| 0075 | Team MCP P0 v2 — UI gestão skills estilo prompt-management | **accepted** | Direção implementada parcialmente. |
| 0076 | Skills V2 — DB primary, git destino auditável; drift alert | **accepted** | Implementado parcialmente. |

---

## Bloco 7 — Identity Mesh + governança final (ADRs 0077–0093)

| ADR | Título | Estado proposto | Justificativa |
|---|---|---|---|
| 0077 | MCP resolver via `users.mcp_handle` | **superseded** | Auto-marcada como "SUPERSEDED por ADR 0081". |
| 0078 | Constituição uma frase — skill+missão como unidade | **superseded** | Auto-marcada como "parcialmente superseded por ADR 0079". |
| 0079 | Constituição do Oimpresso ERP — 10 artigos sobre 7 camadas | **superseded** | **Será substituída pela ADR mãe da Constituição v2 (S3).** |
| 0080 | Trust Tiers operacional + Architecture & Scope + audit findings v1.1.0 | **accepted** | Documento operacional. |
| 0081 | Identity Mesh — schema mcp_actors + manifest pattern | **accepted** | **Direção arquitetural Tier 0.** |
| 0084 | Triggers MySQL append-only em mcp_audit_log + correção P0.1 | **accepted** | **Tier 0.** Skill fundacional. |
| 0085 | Fase 3.4 SCOPE.md + ActorResolver + PII Redactor + roadmap | **accepted** | Implementado parcialmente. |
| 0086 | Fase 5 MVP — Modules/Governance scaffold + ActionGate (warn-only) | **accepted** | Em desenvolvimento. |
| 0087 | Drift resolution sem mover URL — pattern de migration safe | **accepted** | Padrão operacional. Skill `migrar-modulo`. |
| 0088 | Module rename PHP-only — fachada legacy mantida | **accepted** | Implementado em PR-9 (Jana rename). |
| 0089 | Capterra-driven Module Evolution (skill + 3 artefatos) | **accepted** | Skill `comparativo-do-modulo`. |
| 0090 | NFe replace gradual: app/Services → Modules/NfeBrasil | **accepted** | Em desenvolvimento. |
| 0091 | Daily Brief: contrato de contexto consolidado L7 | **accepted** | **Em prod (S1).** |
| 0092 | Tabela rename copiloto_* → jana_* (PR-9) | **accepted** | Implementado. |
| 0093 | Multi-tenant isolation by default — Tier 0 IRREVOGÁVEL | **draft → accepted (S3)** | **Rascunho criado pré-S3 a pedido Wagner.** |

---

## Resumo da pré-classificação

| Estado | Contagem | % do total |
|---|---|---|
| `accepted` (KEEP CANON) | 71 | 77% |
| `superseded` | 12 | 13% |
| `deprecated` | 4 | 4% |
| `sunsetting` | 0 | 0% |
| `draft` | 1 | 1% (ADR 0093) |
| **Total ativo (não-archive)** | **88** | **96%** |
| Archived (auto-detectados na pré-class) | 4 | 4% |

> ⚠️ **Atenção:** o alvo do roteiro era ≤30 ADRs canônicas ativas. Pré-classificação produz 71 accepted — muito acima. Possíveis caminhos:
>
> 1. **Apertar critério**: só decisões "irrevogáveis" ou "fundacionais" viram `accepted`. Várias podem virar `historical` (mantém em raiz mas marca como contexto).
> 2. **Criar tier `accepted-historical`**: decisões já implementadas, ainda relevantes mas não orientam decisão futura.
> 3. **Aceitar 70 ativas** e ajustar alvo do roteiro (>30 ok se governança ainda funciona).

**Recomendação Sonnet:** caminho 2 — adicionar status `accepted-historical` na taxonomia. ADRs em prod implementadas, ainda relevantes mas estáveis (vd 0001-0011, 0013-0030, várias de 0044+) viram `accepted-historical`. Decisões ainda em movimentação ou estratégia ativa ficam `accepted`. Wagner aprova esta proposta (S7 §1).

---

## Como Wagner aprova

Procedimento sugerido:

1. Wagner abre este arquivo
2. Lê 1 bloco (~10–15 ADRs)
3. Pra cada ADR onde discorda da proposta, marca: ❌ wagner-recusa: <novo estado> + razão
4. Quando termina o bloco, marca `bloco-N: APROVADO @ YYYY-MM-DD`
5. Avança pro próximo bloco

Tempo estimado: ~10 min/bloco × 7 blocos = **~70 min total** (não 8h da estimativa original).

Após aprovação completa, Sonnet:
- Atualiza frontmatter de cada ADR com `status: <estado>` + `superseded_by` quando aplicável
- Move ADRs com `deprecated`/`sunsetting`/`superseded` que Wagner explicitar pra `memory/decisions/_archive/2026/`
- Atualiza `mcp_memory_documents` via webhook
- Tool `decisions-search` passa a filtrar por `status` (default: só `accepted`+`accepted-historical`)

---

## Notas pra Sonnet/Wagner

- ADRs com `status: superseded` **NUNCA SÃO DELETADAS** — append-only (ADR best practice 2026)
- `deprecated` indica abordagem abandonada (ex: Vizra), mantém o histórico do "por que abandonamos"
- `accepted-historical` (proposta nova) — ADR ativa mas estável, não esperando mudança
- Frontmatter pra adicionar:
  ```yaml
  status: accepted | accepted-historical | superseded | deprecated | sunsetting
  superseded_by: ~ # ou ID da ADR substituta
  last_reviewed: 2026-05-XX
  review_due: 2027-05-XX
  ```
