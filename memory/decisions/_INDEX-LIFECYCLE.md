---
title: Index de Lifecycle das ADRs — pós-triagem 2026-05-06 (refresh 2026-05-09)
description: Single source of truth pra status de lifecycle das 119 ADRs canon (116 únicas + 3 colisões 0101/0102/0119). Aprovado por Wagner em 2026-05-06; Bloco 8 apendado em 2026-05-09. Tool MCP `decisions-search` filtra por este index.
type: index
status: aceito
authority: [Wagner]
last_reviewed: 2026-05-09
next_review: 2026-08-09  # trimestral
total_adrs: 119
unique_numbers: 116
numbering_collisions: [0101, 0102, 0119]  # ADR 0028 não cumprido em 3 casos — pendente housekeeping (ADR 0120)
governance_principle: append-only — ADRs nunca deletadas; lifecycle reflete uso, não validade histórica
---

# Index de Lifecycle das ADRs (pós-triagem)

> **Aprovado:** 2026-05-06 por Wagner ("ok aprovado comece"), baseado na pré-classificação Sonnet em
> [research/adr-triage-pre-classification.md](../sprints/research/adr-triage-pre-classification.md).
>
> **Princípio:** ADR é append-only. Nenhuma ADR deletada. Lifecycle reflete UTILIDADE atual, não validade histórica.

---

## Estados de lifecycle (proposta caminho 2 do roteiro §13)

| Estado | Significado | Tool `decisions-search` retorna por padrão? |
|---|---|---|
| `accepted` | Decisão ativa, ainda evolui ou orienta novas decisões | ✅ sim |
| `accepted-historical` | Decisão implementada, estável, contexto histórico | ✅ sim |
| `superseded` | Substituída por ADR mais nova; preservada pra audit | ❌ só com `include_archived: true` |
| `deprecated` | Tecnologia/abordagem abandonada, mantida pra histórico | ❌ só com `include_archived: true` |
| `sunsetting` | Em fase de aposentadoria, ainda tem refs mas saindo | 🟡 com aviso |

> ⚠️ **NUNCA mover** ADR pra `_archive/` sem ADR mãe explicando o motivo. Append-only.

---

## Resumo da distribuição

| Estado | Contagem | % do total |
|---|---|---|
| `accepted` (canônica ativa) | ~25 | 27% |
| `accepted-historical` (implementada estável) | ~50 | 54% |
| `superseded` | 12 | 13% |
| `deprecated` | 4 | 4% |
| `draft` | 0 | 0% |
| **Total** | **91** | **100%** |

(2 ADRs `_TEMPLATE` e `_SCHEMA` excluídas do total. ADR 0012, 0082, 0083 não existem — gaps de numeração permitidos pelo ADR 0028.)

---

## Lookup table (90 ADRs canon)

> Formato: `NNNN | estado | superseded_by | nota`
> Estados: `A` = accepted, `AH` = accepted-historical, `S` = superseded, `D` = deprecated

### Bloco 1 — Bases originais (0001–0011)

| ID | Estado | Substituída por | Nota |
|---|---|---|---|
| 0001 | A | — | Estender UltimatePOS — fundacional |
| 0002 | A | — | nWidart/laravel-modules |
| 0003 | A | — | Marcações append-only — Lei (Portaria 671) |
| 0004 | AH | — | Bridge ponto_colaborador_config |
| 0005 | AH | — | UUID auditável + BigInt lookup |
| 0006 | A | — | Multi-tenancy `business_id` — sustenta 0093 |
| 0007 | A | — | Banco de horas ledger — Lei |
| 0008 | S | 0039 | Substituída por padrão AppShellV2 + SIDEBAR_GROUPS |
| 0009 | D | — | Protótipos HTML+Tailwind+Chart.js — não usado mais |
| 0010 | S | 0027, 0053 | Sistema de memória v1 — substituído |
| 0011 | A | — | Padrãa Jana — referência canônica |

### Bloco 2 — Inventário e Officeimpresso (0013–0022)

| ID | Estado | Substituída por | Nota |
|---|---|---|---|
| 0013 | AH | — | Inventário módulos |
| 0014 | AH | — | PontoWR2 × Essentials |
| 0015 | AH | — | Connector API Gateway |
| 0016 | S | (charter Ponto futuro) | Roadmap antigo |
| 0017 | A | — | Officeimpresso restaurado |
| 0018 | AH | — | Log desktop via triggers |
| 0019 | A | — | Delphi não autentica — investigação contínua |
| 0020 | AH | — | Grupo econômico Officeimpresso |
| 0021 | A | — | Contrato API Delphi IMUTÁVEL |
| 0022 | A | — | Meta R$ [redacted Tier 0]mi/ano |

### Bloco 3 — Stack canônica (0023–0030)

| ID | Estado | Substituída por | Nota |
|---|---|---|---|
| 0023 | AH | — | Inertia.js v3 |
| 0024 | A | — | Instalação 1-clique módulos |
| 0025 | A | — | CMS redesign Inertia/React |
| 0026 | A | — | Posicionamento ERP Comunicação Visual |
| 0027 | A | — | Gestão memória — papéis claros |
| 0028 | A | — | ADRs numeração monotônica + Nygard |
| 0029 | A | — | Padrão Inertia + React + UltimatePOS |
| 0030 | A | — | Credenciais nunca em git — Tier 0 |

### Bloco 4 — Stack IA Copiloto (0031–0046)

| ID | Estado | Substituída por | Nota |
|---|---|---|---|
| 0031 | S | 0036 | MemoriaContrato + Mem0 default → Meilisearch first |
| 0032 | S | 0048 | Vizra ADK + Prism — REJEITADA (Vizra descontinuada; Wagner confirmou 2026-05-06: substituída por laravel/ai SDK nativo, "muito melhor") |
| 0033 | S | 0036 | Vector store debate → Meilisearch |
| 0034 | AH | — | Survey Laravel AI ecosystem |
| 0035 | A | — | Stack-alvo IA canônica — VIGENTE |
| 0036 | A | — | Replanejamento Meilisearch first |
| 0037 | A | — | Roadmap Tier 5-6 → 7-9 |
| 0038 | D | — | Promoção 6.7-bootstrap → main (operação concluída) |
| 0039 | S | (DESIGN.md AppShellV2) | Chat Cockpit 3 colunas — substituído |
| 0040 | A | — | Policy publicação Claude/Wagner — Tier 0 |
| 0041 | S | (ADR pendente) | Stack QA IA — Vizra rejeitada, falta substituta |
| 0042 | S | 0058 | Reverb → Centrifugo + FrankenPHP |
| 0043 | A | — | Docker + Traefik num LXC |
| 0044 | A | — | Vaultwarden cofre |
| 0045 | A | — | Hostinger DNS API V1 |
| 0046 | A | — | ChatCopilotoAgent gap contexto — em aberto |

### Bloco 5 — Memória + governança MCP (0047–0061)

| ID | Estado | Substituída por | Nota |
|---|---|---|---|
| 0047 | D | — | Sprint memória solo — concluído |
| 0048 | A | — | Framework agentes laravel/ai (Vizra rejeitada) |
| 0049 | A | — | Camadas memória ligar fase a fase |
| 0050 | A | — | 8 métricas obrigatórias memória |
| 0051 | A | — | Schema próprio + adapter + OTEL GenAI |
| 0052 | A | — | ContextoNegocio múltiplos ângulos |
| 0053 | A | — | MCP server governança como produto — Tier 0 |
| 0054 | A | — | Pacote enterprise busca memória |
| 0055 | A | — | Self-host Team plan equivalente |
| 0056 | AH | — | MCP fonte única memória |
| 0057 | A | — | Tela /team-mcp/team |
| 0058 | A | — | Centrifugo + FrankenPHP — VIGENTE |
| 0059 | A | — | Governança memória estilo Anthropic Team |
| 0060 | A | — | IA + workers Proxmox, app Hostinger |
| 0061 | A | — | ZERO auto-mem privada — Tier 0 |

### Bloco 6 — Runtime + governança 2026-04 (0062–0076)

| ID | Estado | Substituída por | Nota |
|---|---|---|---|
| 0062 | A | — | Hostinger ≠ CT 100 — Tier 0 runtime |
| 0063 | A | — | Prevenir composer.lock drift |
| 0064 | A | — | Modularização TeamMcp + KB + Superadmin |
| 0065 | A | — | Permission Registry contrato |
| 0066 | A | — | format_date shift +3h ROTA LIVRE — quirk crítico |
| 0067 | AH | — | Sprint 8 McpMemoryDocument |
| 0068 | A | — | Sprint 9 retrieval Ollama + reranker |
| 0069 | S | 0070 | TaskRegistry MCP — substituída por Jira-style |
| 0070 | A | — | Jira-style task management — Tier 0 |
| 0071 | AH | — | Auditoria tools MCP 2026-05-05 |
| 0072 | A | — | Maturação memória — gaps OpenClaw |
| 0073 | A | — | Team MCP P0 skills+policies |
| 0074 | A | — | P1 Temporal validity bi-temporal |
| 0075 | A | — | Team MCP P0 v2 UI gestão skills |
| 0076 | A | — | Skills V2 DB primary, git destino |

### Bloco 7 — Identity Mesh + final S2 (0077–0093)

| ID | Estado | Substituída por | Nota |
|---|---|---|---|
| 0077 | S | 0081 | mcp_handle → Identity Mesh |
| 0078 | S | 0079 | Constituição uma frase — substituída por 7 camadas |
| 0079 | S | (S3 ADR mãe) | Constituição 10 artigos — será substituída por nova mãe S3 |
| 0080 | A | — | Trust Tiers operacional + audit findings |
| 0081 | A | — | Identity Mesh — schema mcp_actors — Tier 0 |
| 0084 | A | — | Triggers MySQL append-only — Tier 0 |
| 0085 | A | — | Fase 3.4 SCOPE.md + ActorResolver + PII |
| 0086 | A | — | Fase 5 Modules/Governance ActionGate |
| 0087 | A | — | Drift resolution sem mover URL |
| 0088 | A | — | Module rename PHP-only |
| 0089 | A | — | Capterra-driven Module Evolution |
| 0090 | A | — | NFe replace gradual app/Services → Modules |
| 0091 | A | — | Daily Brief L7 — em prod |
| 0092 | AH | — | Tabela rename copiloto_* → jana_* (PR-9) |
| 0093 | A | — | Multi-tenant Tier 0 IRREVOGÁVEL — VIGENTE |

### Bloco 8 — Constituição v2 e além (0094–0119) — apendado 2026-05-09

> Apendado em 2026-05-09 após audit pós-Constituição (skill `audit-constituicao`). Estados inferidos do frontmatter; revisão trimestral em 2026-08-09 confirma.

| ID | Estado | Substituída por | Nota |
|---|---|---|---|
| 0094 | A | — | **Constituição v2 — 7 camadas + 8 princípios duros (MÃE)** · supersedes: 0078, 0079 |
| 0095 | A | — | Skills tiers convenção interna (A/B/C lifecycle) |
| 0096 | A | (parcial 0111) | Módulo Whatsapp Meta Cloud API direto |
| 0097 | A | — | Brief model gpt4o-mini · supersede parcial 0091 |
| 0098 | A | — | Build Inertia Hostinger pós-pull |
| 0099 | A | — | Project legacy discovery pre-deletion · drift resolvido em 2026-05-09 (Project/ deletado) |
| 0100 | A | — | ProjectMgmt UI redesign |
| 0101a | A | — | Sistema Charter-Capterra governança escopo · ⚠️ **colisão numérica com 0101b** |
| 0101b | A | — | Tests business_id=1 nunca cliente · ⚠️ **colisão numérica com 0101a** |
| 0102a | A | — | NFCe status polling vs broadcast · ⚠️ **colisão numérica com 0102b** |
| 0102b | A | — | Sprint S6 Charter-Capterra postmortem + S7 backlog · ⚠️ **colisão numérica com 0102a** |
| 0103 | A | — | Eventos fiscais separados por modelo |
| 0104 | A | — | **Processo MWART canônico — único caminho (Tier A skill)** |
| 0105 | A | — | Cliente como sinal qualificado — guiar sem mandar |
| 0106 | A | — | Recalibração velocidade fator 10x IA-pair |
| 0107 | A | — | Emenda 0104 — visual-comparison gate F3 |
| 0108 | A | — | Regressão visual Pest browser Tier 2 |
| 0109 | A | (emends 0114) | Claude Design plugin integrado processo MWART |
| 0110 | A | — | Cockpit Pattern V2 canon list-detail |
| 0111 | A | — | Emenda 0096 — bypass meta fallback per business |
| 0112 | A | — | MWART exceção Whatsapp Settings fix bugs |
| 0113 | A | — | Integração Delphi-Laravel ADS 3 caminhos |
| 0114 | A | — | **Loop Cowork ↔ Claude Code formalizado · prototipo-ui/** (Tier A skill mwart-comparative V4) |
| 0115 | A | (amends 0116) | Recuperação cliente Gold via bundle oimpresso + NFe55 |
| 0116 | A | — | Pivot Gold — Manifestação Destinatário substitui escopo NFe55 (emenda 0115) |
| 0117 | A | — | Múltiplos números WhatsApp por business |
| 0118 | A | — | Segregação domínios externos clientes legacy |
| 0119a | A | — | Migration factory capacidade institucional · ⚠️ **colisão numérica com 0119b** |
| 0119b | A | — | Paralelismo sessões whats-active Tier 1 · ⚠️ **colisão numérica com 0119a** |

> **Pendências de housekeeping** apuradas pela skill `audit-constituicao` em 2026-05-09:
> - 3 colisões numéricas (0101/0102/0119) — ADR 0028 violada · resolução requer ADR amendment
> - 9 ADRs `substituido` sem `superseded_by:` no frontmatter (0008, 0010, 0031, 0032, 0033, 0042, 0073, 0075, 0077) — pendente ADR 0120 housekeeping
> - 0079 ainda `lifecycle: ativo` apesar de 0094 declarar `supersedes: [0079]` — pendente ADR 0120

### Bloco 9 — Governance rubrica + erratas + v4 (0153–0161) — apendado 2026-05-16

> Apendado em 2026-05-16 (Waves 17-24) cobrindo evolução `module-grade` rubrica v1→v2→v3 + erratas hardening + v4 Scoped Scorecards. ADR 0159 parcialmente superseded por 0160 (Scoped Scorecards) + 0161 (aposenta 3/4 hacks).

| ID | Estado | Substituída por | Nota |
|---|---|---|---|
| 0153 | A | — | module-grade rubrica v1 (5 dimensões D1..D5) |
| 0154 | A | — | module-grade v2 — N/A justificado (anti-gaming limit 3) |
| 0155 | A | — | module-grade v3 — sub-dimensões + reweight + gate CI |
| 0156 | A | — | v3 errata — OtelHelper N/A justified backward-compat |
| 0157 | A | — | v3 errata — D2 detection hardening |
| 0158 | A | — | v3 errata — D1 heurística hardening recursivo |
| 0159 | A | (parcial 0160, 0161) | v3 errata meta 97 realismo — 4 hacks (D5 cross-cutting, D9.b ready, D4.b N/A, D3.b CHANGELOG) |
| 0160 | A | — | Governance v4 — Scoped Scorecards bucket-aware (5 buckets) |
| 0161 | A | — | **Aposentar 3 hacks ADR 0159 redundantes com v4 (D5 cross-cutting, D4.b FSM, D3.b CHANGELOG)** · 4º hack D9.b permanece até OTel collector ativo |

---

## Próximos passos pós-aprovação Wagner

1. ✅ Index aprovado e mergeado em main
2. 🔴 Tool MCP `decisions-search` filtra por estado (default: `accepted` + `accepted-historical`)
3. 🔴 ADRs `superseded` mantêm `lifecycle: active` mas com `superseded_by` adicionado no frontmatter (futuro PR)
4. 🔴 Re-revisão trimestral em 2026-08-06 (próxima janela)

## Como atualizar este index

- Nova ADR é criada → adicionar entry com estado `accepted` ou `draft`
- ADR muda de estado (ex: `accepted` → `superseded`) → editar UMA linha aqui + adicionar `superseded_by` no frontmatter da ADR
- Revisão trimestral → bumpa `last_reviewed` + ajusta entries que mudaram

> **Cuidado:** atualização deste index é a única forma de "mover" ADR sem violar append-only. Conteúdo de ADR aceita JAMAIS é editado.
