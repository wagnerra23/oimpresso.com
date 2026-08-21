---
id: reference-tecnico-dados-multitenant
name: Técnico — Dados e multi-tenant
description: business_id como Tier 0, as regras de escrita que valem pra todo Model e query crua, e quais tenants podem ser tocados por teste — a linha vermelha que nenhum atalho justifica.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: tecnico
nav_order: 20
lente: [construir]
---

# Técnico — Dados e multi-tenant

> **Isolamento entre empresas é Tier 0** ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)).
> Vazar dado de um tenant pra outro é o pior bug possível deste sistema — pior que perder dado,
> porque perder dado se percebe.

## Regras de escrita

- Todo Model com dado de cliente declara o **global scope** de `business_id`. Não existe
  exceção "só neste relatório".
- Query crua (`DB::`) exige `where business_id` explícito **e** revisão humana.
- Migration nova nasce com `business_id` e índice composto.
- Em tool de IA, o `business_id` vem do **construtor da tool, nunca do modelo**
  ([ADR 0141](../decisions/0141-agents-tool-use-pattern-claude-code.md)): se o LLM tentar injetar
  outro, a tool ignora. Não se confia no prompt — confia-se no código.

## Tenants de referência

| `business_id` | Quem | Pode ser tocado por teste? |
|---|---|---|
| `4` | ROTA LIVRE (Larissa) — produção, quase todo o volume | **não** |
| `164` | Martinho (OficinaAuto) — piloto LIVE | **não** |
| `1` | WR2 — empresa real; smoke fiscal manual em homologação | **não** |
| `98` | tenant fictício | **sim — o único** |

A doutrina de teste é o tenant 98 ([ADR 0358](../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)),
e ela supersedeu a anterior justamente porque o `biz=1` é empresa real. Seed ou factory apontada
pra cliente contamina quem paga.

## Vocabulário e enums — não é aqui

Os valores aceitos por cada coluna de domínio vivem nos **dicionários** em
[`memory/dominio/`](../dominio/), e o [`domain-dict-guard`](../../scripts/domain-dict-guard.mjs)
(gate G-4, [ADR 0264](../decisions/0264-governanca-executavel-trio-dominio-e2e.md)) compara dicionário ⇔
migration. Enum novo entra **no dicionário**; documento que repete a lista drifa dela.

## Corpus de conhecimento também tem dono de path

O corpus do MCP (`mcp_memory_documents`) é **cache sincronizado do git**, com PII redigida no
caminho ([ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)). O git é a
fonte; o índice nunca vira dono. Onde cada família de documento pode morar é declarado em
[`document-placement.json`](../../scripts/governance/document-placement.json) — inclusive quais
pastas são `protected` e não movem.
