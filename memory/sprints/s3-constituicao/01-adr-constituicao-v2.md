# ADR — Constituição v2 Oimpresso (mãe das 7 camadas)

> **Status:** 🔴 ESQUELETO — Sonnet aguarda Wagner aprovar §13 do ROTEIRO antes de preencher.

---

## Frontmatter (pra preencher)

```yaml
---
adr_id: NEXT  # próximo número livre (após 0092)
title: Constituição v2 Oimpresso — 7 camadas + 8 princípios duros
status: proposed  # proposed → accepted após aprovação Wagner
tier: CANON
last_reviewed: 2026-05-06
review_due: 2027-05-06
related_adrs: [0035, 0040, 0053, 0070, 0091]
supersedes: []
referenced_by: []  # vai crescer com ADRs subsequentes
authors: [wagner, sonnet]
---
```

## Seções (a preencher após aprovação §13)

### 1. Contexto

(POR QUÊ a Constituição existe — situação atual + dor)

- Estado atual: 92 ADRs ativas (deveria ser ≤30), 19 skills sem tiering formal, CLAUDE.md ~390 linhas, sem firewall de decisões custosas.
- Dor: 5–8 tool calls de orientação por sessão, custo ~$X/dia em tokens desperdiçados, regressões silenciosas em mudanças que tocam Tier 0.

### 2. As 7 camadas

(Diagrama L1–L7 do ROTEIRO §1, expandido)

| Camada | Nome | Dono | Contrato |
|---|---|---|---|
| L1 | MCP CORE | (preencher) | tools + memória + audit |
| L2 | ADS Universal | (preencher) | firewall: code/design/produto/memória/runtime |
| L3 | SKILLS | (preencher) | Tier A/B/C (convenção interna) |
| L4 | PLAYBOOKS | (preencher) | runbooks + playbooks separados |
| L5 | ADRs canon | (preencher) | ≤30 ativas, append-only |
| L6 | CHARTERS | (preencher) | page/feature/mission contracts |
| L7 | DAILY BRIEF | (preencher) | 3k tokens, 6x/dia |

### 3. Princípios duros (8)

(Já listados em ROTEIRO §1; copiar com justificativa pra cada)

1. Context as a product
2. Tiered cost
3. Charter > Spec
4. Loop fechado por métrica
5. Separation of concerns brutal
6. Multi-tenant by default — Tier 0 IRREVOGÁVEL
7. **Transparência (Explainability)** — adicionado pós deep-dive S5
8. **Confiabilidade com fallback** — adicionado pós deep-dive S5

### 4. Como propor mudança

(Workflow ADR canon vs HISTORICAL — diferenciar)

- ADR canon: criação requer aprovação Wagner via PR
- ADR histórico: documentar mudança feita, aprovação opcional
- Append-only: nunca editar; criar nova com `supersedes: [N]`

### 5. Onde NÃO inventar

(Lista de Tier 0 — irrevogáveis sem ADR mãe nova)

- Tokens MCP (geração, rotação)
- Schema mcp_audit_log (append-only por trigger)
- ADRs CANON existentes
- business_id global scope (princípio #6)
- Centrifugo + FrankenPHP runtime (CT 100, ADR 0058)

### 6. Métricas de saúde da Constituição

(Como saber se a Constituição está viva)

- ADRs canon ativas ≤30
- Skills Tier A em uso ≥80% das sessões
- Brief uptime ≥99%
- Cobertura charters ≥80% das páginas críticas

---

## Notas pra Sonnet preencher (quando autorizado)

- Ler [ROTEIRO §1, §13](../ROTEIRO-MESTRE.md), [s3-constituicao-deep-dive.md](../research/s3-constituicao-deep-dive.md)
- Manter telegráfico (este ADR é canônica, não tutorial)
- Cada princípio duro com 1 parágrafo: enunciado + justificativa + mecanismo de enforcement
- Cada camada: 1 parágrafo + link pra ADR específica que a governa
- Total alvo: ~300–400 linhas (vs 390 do CLAUDE.md atual)
