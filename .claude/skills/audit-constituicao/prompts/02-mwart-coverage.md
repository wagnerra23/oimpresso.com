# Sub-agent prompt — Dimensão 2: MWART artifact coverage

> Prompt canônico do sub-agent #2 da skill `audit-constituicao`.
> Output PT-BR. Limite: ≤700 palavras no diagnóstico final.

## Missão

Auditar cobertura de artefatos canônicos do **processo MWART** ([ADR 0104](memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)) em todas as Pages Inertia ativas. As 5 fases obrigatórias exigem 3 artefatos por tela: **RUNBOOK** (F1) + **visual-comparison.md** (F1.5) + **charter** (quando aplicável, S4+).

## Regra de exclusão (anti false-positive — descoberto 2026-05-09)

**SÓ contar como Page MWART se:**
1. Arquivo `.tsx` está em `resources/js/Pages/<Mod>/<Tela>.tsx` (raiz do módulo).
2. Existe `Inertia::render('<Mod>/<Tela>', ...)` correspondente em algum controller PHP.

**EXCLUIR explicitamente:**
- `resources/js/Pages/<Mod>/_components/**` (sub-componentes privados)
- `resources/js/Pages/<Mod>/components/**` (idem, variação de pasta)
- Qualquer `.tsx` cujo nome NÃO bate com `Inertia::render('<Mod>/<Tela>')` em controller (ex: `DetailSheet.tsx`, `RowActions.tsx`)
- Qualquer `.tsx` em `resources/js/Components/` (shareds, não Page)

## O que fazer (passo a passo)

1. Listar todas as Pages candidatas: `Glob resources/js/Pages/**/*.tsx`.
2. Filtrar aplicando regra de exclusão acima.
3. Pra cada Page restante, validar coberturas:
   - **RUNBOOK** existe em `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md`?
   - **visual-comparison** existe em `memory/requisitos/<Mod>/<tela>-visual-comparison.md`?
   - **charter** existe em `resources/js/Pages/<Mod>/<Tela>.charter.md` (S4+ — se ainda dormente, marcar N/A não 🔴)?
   - **SPEC US** com status `done` referenciando essa Page?
4. Cross-check com tarefas MCP recentes — `tasks-list module:<Mod>` pra ver se há trabalho ativo na tela (alteraria expectativa de cobertura).

## Como entregar

```markdown
# Dimensão 2 — MWART artifact coverage

## Saúde: 🟢/🟡/🔴
## Headline (1 frase): <X de N Pages cobertas, gap principal em <módulo>>

## Métrica
- Total Pages MWART (após exclusão): <N>
- Com RUNBOOK: <N> (<%>)
- Com visual-comparison.md: <N> (<%>)
- Com charter (S4 only): <N> ou N/A
- Cobertura completa (RUNBOOK + VC + charter quando aplicável): <N> (<%>)

## Pages descobertas/excluídas

- Excluídas (sub-component, sem Inertia::render): <N> — exemplos: DetailSheet.tsx, RowActions.tsx
- Reclassificadas (eram Page mas viraram component): <N>

## Top gaps (≤10, ordenados por core→peripheral)

| Page | Módulo | RUNBOOK | VC | Charter | Status | Ação sugerida |
|---|---|---|---|---|---|---|
| Index | Repair | ✅ | ❌ | N/A | em prod | criar VC retroativo |
| Edit | NfeBrasil | ❌ | ❌ | N/A | em prod | F1 retroativo + VC |
| ... | | | | | | |

## Recomendação 3-tiers

- **Tier A (safe agora):** criar VC/RUNBOOK retroativo de Pages que JÁ estão em prod sem artefato (não muda código, só doc)
- **Tier B (precisa ADR):** se gap apontar pra processo MWART quebrado (ex: PR mergeado violando ADR 0104) — exige ADR de exceção retroativa
- **Tier C (backlog):** Pages de módulo dormente / superadmin
```

## Heurística de saúde

- 🟢 ≥95% Pages cobertas (RUNBOOK + VC) + zero core Page sem artefato
- 🟡 80-94% cobertas, gaps em peripheral
- 🔴 <80% OU core Page (Index/Create/Edit dos módulos top: Jana, Repair, Project, Financeiro, RecurringBilling, NfeBrasil) sem artefato

## Restrições

- Cross-cut com Dimensão 6 (skills audit) é esperado — ambas tocam skills `mwart-*`. Reporte achados próprios sem suprimir.
- NÃO sugerir criar artefato automaticamente — só listar gap.
- Se um módulo inteiro não tem nenhuma Page MWART (ex: módulos legacy só Blade), marcar como "N/A — pré-MWART" e excluir do denominador.
