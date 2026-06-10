---
slug: 0267-whatsapp-queues-tabela-filas-atendimento
number: 267
title: "whatsapp_queues — filas de atendimento persistidas em DB (US-WA-301)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-10"
module: whatsapp
quarter: 2026-Q2
tags: [whatsapp, atendimento, caixa-unificada, filas, per-schema, multi-tenant]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0093-multi-tenant-isolation-tier-0", "0135-omnichannel-inbox-arquitetura", "0114-prototipo-ui-cowork-loop-formalizado"]
pii: false
---

# ADR 0267 — `whatsapp_queues`: filas de atendimento persistidas em DB

> ADR per-schema (padrão charter Caixa Unificada §1: "Tabela DB nova = ADR
> per-schema ANTES da migration"). Origem: brief [CC] Caixa Unificada completa
> 2026-06-10, mandato [W] "vamos aplicar todas" (US-WA-301, roadmap §1 do
> charter `/atendimento/caixa-unificada`).

## Status

Proposto — 2026-06-10 · [CL] sob mandato [W] "aplicar todas" (brief PR-3/10).

## Contexto

Filas da Caixa Unificada hoje são **estáticas** em `config('whatsapp.queues')`
(2 filas hardcoded: comercial/financeiro) com heurística tag→fila derivada
read-only no Controller (`deriveQueueFromTags`). Wagner quer painel admin pra
criar/editar fila sem deploy: label, cor (hue), SLA, tags-gatilho, modo de
distribuição e membros.

## Decisão

Criar tabela `whatsapp_queues`:

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigIncrements | PK |
| `business_id` | unsignedInteger | **Tier 0 ADR 0093** — index + unique com slug |
| `slug` | string(40) | identificador estável (compat `config('whatsapp.default_queue')`) |
| `label` | string(80) | nome exibido |
| `hue` | unsignedSmallInteger | 0-360 OKLCH (chips/border da lista, padrão V4 `oklch(0.62 0.13 hue)`) |
| `sla_minutes` | unsignedInteger nullable | SLA alvo de 1ª resposta; null = sem SLA |
| `dist` | string(20) default 'manual' | `round_robin` \| `sticky` \| `manual` — **só persistência nesta fase**; roteamento automático é US futura |
| `trigger_tags` | json | slugs de tags que disparam a fila (heurística OR já existente) |
| `members` | json | user_ids membros — **só persistência nesta fase** (round-robin futuro) |
| `sort_order` | unsignedInteger default 0 | ordem no painel |
| timestamps | | |

- **Unique** `(business_id, slug)`.
- **Seed lazy idempotente** a partir de `config('whatsapp.queues')` no primeiro
  acesso por business (`ensureDefaultQueues` — mesmo pattern do
  `ensureDefaultTags` US-WA-063). Migração sem quebra: business que nunca abriu
  a tela ganha as filas default na primeira visita.
- **Leitura com fallback**: Controller lê DB; se vazio (corrida/rollback),
  fallback `config('whatsapp.queues')` — confiabilidade com fallback
  (princípio duro 8, ADR 0094).
- CRUD via permission `whatsapp.settings.manage` (mesma do painel Canais);
  leitura via `whatsapp.access`.
- Fila apontada por `config('whatsapp.default_queue')` não pode ser deletada
  (é o fallback da heurística tag→fila).

## Consequências

- `deriveQueueFromTags` passa a usar filas do DB (shape compat com
  `QueueConfig` do frontend — `sla` humanizado de `sla_minutes`).
- `stats.queues_count` reflete DB.
- Habilita US-WA-305 (mover conversa entre filas — `queue_override` na
  conversa, ADR/migration próprio) e SLA pill (polish V2 §1).
- `dist`/`members` ficam **dormentes** até US de roteamento automático —
  TODO honesto, sem fingir round-robin que não roda (anti M-AP-2).

## Alternativas consideradas

1. **Continuar em config** — rejeitado: cada fila nova exige deploy; cliente
   não consegue self-service.
2. **Reusar `whatsapp_tags` com flag `is_queue`** — rejeitado: fila tem
   atributos próprios (SLA, dist, members) e semântica 1:N com tags-gatilho;
   misturar quebraria SoC (princípio duro 5).
