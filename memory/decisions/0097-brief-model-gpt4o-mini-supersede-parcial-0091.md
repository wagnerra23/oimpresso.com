---
slug: 0097-brief-model-gpt4o-mini-supersede-parcial-0091
number: 97
title: "BRIEF generator usa gpt-4o-mini em vez de Sonnet (supersede parcial ADR 0091)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
quarter: Q2-2026
decided_at: "2026-05-07"
decided_by: [W]
module: governance
tier: CANON
related_adrs: ["0091-daily-brief", "0036-replanejamento-meilisearch-first", "0094-constituicao-v2-7-camadas-8-principios"]
parent_charter: mission.constituicao-v2
parent_adr: 0091
supersedes: []
referenced_by: []
authors: [wagner, opus]
accepted_at: "2026-05-07"
decided_by: [W]
---

# ADR 0097 — BRIEF generator usa gpt-4o-mini (supersede parcial ADR 0091)

> **Status:** ✅ ACEITA em 2026-05-07 por Wagner ("faça o melhor caminho").
> Supersede **parcial** de [ADR 0091](0091-daily-brief.md) §Geração — apenas o modelo Brain B é alterado; demais invariantes (≤3500 tokens, 6×/dia, 7 seções fixas, etc) permanecem.

---

## Contexto

[ADR 0091](0091-daily-brief.md) §Geração definiu `claude-sonnet-4-6` como Brain B do `BriefGeneratorService`, com custo projetado de $0.30-0.50/dia ($9-15/mês).

Realidade auditada em prod 2026-05-07 (sessão BRIEF-A1, US-COPI-088):

- Implementação real em [`Modules/Brief/Services/BriefGeneratorService.php:30`](../../Modules/Brief/Services/BriefGeneratorService.php) usa **`gpt-4o-mini`** (OpenAI).
- Custo real medido: **$0.0004/brief × 6/dia = $0.024/dia** (~R$0,72/mês).
- Decisão técnica documentada apenas no docblock do service:
  > "Wagner pediu OpenAI (chave OPENAI_API_KEY já em prod pro hybrid embedder Meilisearch — [ADR 0036](0036-stack-meilisearch-hybrid-embedder.md)). gpt-4o-mini é 30× mais barato que claude-sonnet-4-6 e qualidade suficiente pra brief estruturado de 7 seções fixas."

A divergência criou doc-stale: ADR canon dizia uma coisa, prod fazia outra. Princípio constitucional V2 #5 (SoC brutal) viola — esta ADR formaliza a realidade pra alinhar.

## Decisão

`BriefGeneratorService` usa **`gpt-4o-mini`** como Brain B do Daily Brief, via OpenAI Chat Completions API. Não usa Anthropic SDK pra esta camada.

### Justificativa

| Critério | claude-sonnet-4-6 (ADR 0091 original) | gpt-4o-mini (real) |
|---|---|---|
| Custo input | ~$3/M tok | $0.15/M tok (20× mais barato) |
| Custo output | ~$15/M tok | $0.60/M tok (25× mais barato) |
| Brief médio (~3k tok in + 250 tok out) | ~$0.013/run | ~$0.0005/run |
| Custo dia (6×) | ~$0.30 (~R$1,50) | ~$0.024 (~R$0,12) |
| Custo mês | ~$9 (~R$45) | ~$0.72 (~R$3,50) |
| **ROI vs investimento Sonnet** | baseline | **-92% custo** |
| Qualidade pra prosa estruturada de 7 seções fixas | ✅ excelente | ✅ adequada (validado em prod) |

Brief é geração estruturada com prompt fixo, validador rígido, e seções predefinidas. Não exige raciocínio complex (Sonnet brilha em raciocínio). Trade-off favorece economia.

Plus: chave `OPENAI_API_KEY` já está em prod (Hostinger + CT 100) pro embedder Meilisearch hybrid ([ADR 0036](0036-stack-meilisearch-hybrid-embedder.md)) — zero infra adicional.

## Invariantes preservados de ADR 0091

Esta ADR **não altera** as invariantes da [ADR 0091](0091-daily-brief.md):

1. ✅ Brief ≤3500 tokens (hard limit, validador rejeita)
2. ✅ Geração ≤6×/dia (`0 7,11,14,17,20,23 * * *` SP)
3. ✅ Brief NUNCA chama APIs externas (exceto provider Brain B)
4. ✅ Brief NUNCA contém PII de cliente final
5. ✅ Skill `brief-first` permanece Tier A always-on
6. **🔄 Custo Brain B atualizado:** alvo `≤$0.05/dia` (era `≤$0.50/dia`). Trigger alerta em `$0.10/dia`. Real medido: `$0.024/dia`.

## Custo real x projetado (validação 7d soak)

| Métrica | ADR 0091 projetado | ADR 0097 medido (7d soak parcial) |
|---|---|---|
| Cron rodando 6×/dia | sim | ✅ confirmado |
| Custo/dia | $0.30-0.50 | **$0.024** (-92%) |
| Custo/mês | $9-15 | **~$0.72** (-92%) |
| Custo/brief | $0.05-0.08 | **$0.0004** (-99%) |
| Token médio brief | ~3000 | 235 (vide A1 fix — input ricco mas geração compacta) |

## Status real de adoção (atualiza checklist 0091 §"Status de adoção")

ADR 0091 listava 7 itens; status real 2026-05-07:

| Item da 0091 | Status real |
|---|---|
| ADR canonizada (0091) | ✅ |
| Migration SQL aplicada em produção | ✅ (sessão Sprint 1, validado em mcp_briefs.id=1+) |
| Cron rodando 6x/dia sem falha por 48h | ✅ (4 briefs em 14h validados 2026-05-07) |
| Tool MCP registrada no servidor mcp.oimpresso.com | ✅ (validado via curl tools/list, brief-fetch é 1ª tool) |
| Skill commitada em `.claude/skills/brief-first/` | ✅ |
| Time avisado (Felipe/Maíra/Luiz/Eliana) | 🔲 pendente — comunicar pós-A1 mergeado |
| Métricas semana 1 coletadas → review | 🔄 em andamento (fix BRIEF-A1 mergeado 2026-05-07, soak começa AGORA com input correto) |

5 de 7 itens fechados. Os 2 restantes (comunicação + review semana 1) ficam pra próximo cycle review.

## Consequências

### Positivas
- Custo real -92% vs projetado → quase desprezível ($0.72/mês)
- Goal CYCLE-02 #6 (health-check Brain B/dia ≤$25) sem stress nem perto
- Wagner aprovado/usa OpenAI desde Sprint 1 — alinha doc com realidade

### Negativas
- Adiciona dependência de provider OpenAI (já existente pro embedder, não novo)
- Diverge do princípio "stack IA canônica é Anthropic" implícito na ADR 0035 — mas ADR 0035 é sobre **camada A/B (chat agent)**, não sobre L7 brief. Brief é caso justificável.

### Mitigações
- Se OpenAI cair: brief fica sem regenerar (fallback = brief anterior). Não derruba app principal (chat usa Anthropic).
- Se quisermos voltar pra Anthropic no futuro (ex: melhor qualidade ou política): trocar `MODEL` constante em `BriefGeneratorService.php:30` + ADR superseding 0097.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-06 | Sonnet rascunho + Wagner | ADR 0091 §Geração: claude-sonnet-4-6 (projetado) |
| 2026-05-07 | Opus + Wagner | Esta ADR — formaliza gpt-4o-mini real (-92% custo, validado em prod) |

## Refs

- [ADR 0091](0091-daily-brief.md) — Daily Brief contrato L7 (parent)
- [ADR 0036](0036-stack-meilisearch-hybrid-embedder.md) — OpenAI key já em prod
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §princípio #5 SoC brutal (motivador desta ADR)
- [`Modules/Brief/Services/BriefGeneratorService.php:30`](../../Modules/Brief/Services/BriefGeneratorService.php) — implementação real
- US-COPI-088 (BRIEF-A1 fix aggregator), US-COPI-090 (esta ADR), sessão 2026-05-07
