---
name: ADRs separados por categoria (arq/tech/ui), não monolíticos
description: Wagner prefere ADR-per-tópico em adr/{arq,tech,ui}/ ao invés de ADRs monolíticos cobrindo múltiplos assuntos
type: feedback
originSessionId: dbbb392d-952f-4d8d-9a4a-c93f6603c171
---
Quando criar/promover módulo em `memory/requisitos/{Modulo}/`, organizar ADRs em **3 subpastas** por categoria com **numeração separada**:

```
adr/
├── arq/   # decisões de arquitetura (módulo isolado, eventos, integração com core)
├── tech/  # decisões técnicas (idempotência, lockForUpdate, embeddings, criptografia)
└── ui/    # decisões de interface (layout, fluxo, componente, padrão visual)
```

Numeração: `ARQ-0001`, `TECH-0001`, `UI-0001` (separadas por categoria, **não** sequencial única misturando).

**Why:** Wagner disse explicitamente em 2026-04-24: "acho bom separar em adrs os assuntos. assim fica molhor saber como trabalhar as ideias novas". Antes disso, MemCofre tinha ADRs misturados em `adr/` plano (0001-0008 cobrindo arq/tech/ui sem distinção) — sentiu necessidade de organização melhor ao promover 4 módulos novos.

**How to apply:**
- Cada ADR cobre **1 decisão = 1 assunto**. Não juntar "idempotência + soft delete" em 1 ADR.
- Padrão fixo: `Status / Data / Decisores / Categoria / Relacionado / Contexto / Decisão / Consequências / Alternativas / Referências`
- Cross-reference entre ADRs via path relativo (`Financeiro/adr/tech/0001`)
- PontoWr2 e MemCofre já seguem este pattern parcialmente (têm `adr/arq/`, `adr/tech/`, `adr/ui/`)
- Quando promover ideia → módulo, criar todos os ADRs cardinais ANTES do scaffold de código (decisões tomadas, alternativas registradas)

**Não confundir com:**
- ADRs do projeto inteiro (`memory/decisions/0001-0022`) — esses são cross-cutting, não modulares
- ADRs de auto-memória (`reference_*`, `feedback_*`) — esses são preferências/contexto, não decisões formais
