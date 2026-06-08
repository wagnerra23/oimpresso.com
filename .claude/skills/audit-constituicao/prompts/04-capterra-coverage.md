# Sub-agent prompt — Dimensão 4: CAPTERRA cobertura

> Prompt canônico do sub-agent #4 da skill `audit-constituicao`.
> Output PT-BR. Limite: ≤600 palavras no diagnóstico final.

## Missão

Auditar cobertura do framework Capterra-driven evolution ([ADR 0089](memory/decisions/0089-capterra-driven-module-evolution.md) + [ADR 0101](memory/decisions/0101-sistema-charter-capterra-governanca-escopo.md)) por módulo. Cada módulo `Modules/<X>/` deveria ter:
- `memory/requisitos/<X>/CAPTERRA-FICHA.md` (input curado — concorrentes + capacidades baseline)
- `memory/requisitos/<X>/CAPTERRA-INVENTARIO.md` (output gerado — 3 buckets ✅🟡❌)
- `memory/requisitos/<X>/SPEC.md` com US apendadas vindo do inventário

## O que fazer (passo a passo)

1. `Glob Modules/*/` → lista de módulos no filesystem (excluir vendor/, exemplos: UltimatePOS core).
2. Pra cada módulo, validar presença dos 3 artefatos.
3. Pra `CAPTERRA-INVENTARIO.md` existente, extrair `> Gerado por skill ... em <YYYY-MM-DD>` ou `created_at` no frontmatter — calcular idade.
4. Cross-check inventário ↔ SPEC: gaps marcados ❌ no inventário têm US correspondente em SPEC?
5. Identificar módulos top (alta prioridade pra cobertura): **Jana, Repair, Project, Financeiro, RecurringBilling, NfeBrasil, Form, PontoWr2**.

## Como entregar

```markdown
# Dimensão 4 — CAPTERRA cobertura

## Saúde: 🟢/🟡/🔴
## Headline (1 frase): <ex: "5 de 8 módulos top com inventário <90d; gap em Form e PontoWr2">

## Métrica
- Total módulos: <N>
- Com FICHA: <N>
- Com INVENTARIO: <N>
- Com FICHA + INVENTARIO + SPEC: <N>
- Inventários frescos (<90d): <N>
- Inventários stale (90-180d): <N>
- Inventários muito stale (>180d): <N>

## Cobertura por módulo (módulos top primeiro)

| Módulo | FICHA | INVENTARIO | Idade INV | SPEC sync? | Status |
|---|---|---|---|---|---|
| Jana | ✅ | ✅ | 30d | ✅ | 🟢 OK |
| Repair | ✅ | ✅ | 45d | 🟡 4 US ❌ sem SPEC | 🟡 |
| Financeiro | ✅ | ❌ | — | — | 🔴 falta INV |
| Form | ❌ | ❌ | — | — | 🔴 sem ficha |
| ... | | | | | |

## Top gaps

- Módulo **<X>**: ficha existe mas inventário stale (200d) — `/comparativo <X>` regerar
- Módulo **<Y>**: sem ficha — Wagner precisa curar ficha primeiro (skill comparativo-do-modulo NÃO roda sem ficha)
- Módulo **<Z>**: inventário fresco mas SPEC não tem US dos gaps ❌ — apender via skill

## Recomendação 3-tiers

- **Tier A (safe agora):** rodar `/comparativo <X>` pra módulos com ficha mas sem inventário ou inventário stale (re-gera, não muda canon)
- **Tier B (precisa ADR):** se módulo top sem ficha — Wagner cura ficha + decide se módulo sai de "top" ou se vira Tier 0 cobertura
- **Tier C (backlog):** módulos peripheral (superadmin/dev tools) sem ficha — não-bloqueante
```

## Heurística de saúde

- 🟢 100% módulos top com FICHA + INVENTARIO <90d + SPEC sync
- 🟡 1-2 módulos top com inventário 90-180d OU 1 sem ficha
- 🔴 >2 módulos top sem ficha OU inventário >180d OU SPEC ↔ inventário dessincronizado em ≥3 módulos

## Restrições

- NÃO criar/editar FICHA (curadoria humana — `comparativo-do-modulo` reprovações).
- NÃO rodar `/comparativo` automaticamente — só listar gap como Tier A sugerido.
- Se módulo NÃO existe no filesystem mas tem FICHA, listar como "ficha órfã" (Tier B).
- Skill `comparativo-do-modulo` (Tier B) é a ferramenta de execução — esta auditoria só identifica onde rodar.
