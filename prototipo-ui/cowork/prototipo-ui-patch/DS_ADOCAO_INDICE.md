# DS — Adoção & Anti-Drift · ÍNDICE

> **Origem:** Cowork [CC] · 2026-05-29 · disparado pelo diagnóstico KB-9.75 do cadastro de Contacts
> **Problema:** a DS cresce mais rápido do que o repo adota. Prova: `Cliente/Create.tsx` hand-rolou `<input type="radio">` **mesmo existindo `@/Components/ui/radio-group.tsx`**. Não foi falta de componente — foi falta de *guard*.
> **Meta:** fechar o gap DS↔repo **e travar** pra não reabrir — sem comissionar auditoria pra sempre.

---

## Os 3 documentos

| Doc | O que é | Cobertura |
|---|---|---|
| **`REGISTRY_DS_COMPONENTES.md`** | Fonte da verdade: cada componente de `@/Components/ui` + Onda F, e *o anti-pattern que ele substitui* | **100%** (finito) |
| **`REGRAS_DS_LINT.md`** | Spec das regras `ds/*` (`no-restricted-syntax`) pro Code colar no `eslint.config.js` | **todos os 745 arquivos** (a máquina varre) |
| **`MATRIZ_MIGRACAO_DS.md`** | Por arquivo: antes → depois migrado | **P0 detalhado** (Create·Index·Sells); resto = contagem do baseline |

---

## Como "todos" é coberto sem auditar 745 arquivos na mão

A cobertura total **não** vem de eu ler 745 telas — vem da **regra de lint que roda em todas**. Eu defino *o que conta como drift* (REGRAS_DS_LINT); o `npm run lint` conta em todo módulo. Hand-detalhe só pros P0.

**E o melhor: o mecanismo já existe no repo.** `eslint.config.js` roda em modo **ratchet** (ADR 0209):
- `.eslintrc-baseline.json` absorve as violações **pré-existentes** (o hand-roll que já está lá não trava ninguém).
- CI `eslint-gate.yml` **falha só em REGRESSÃO** (delta > 0) — todo PR novo que hand-rolar um padrão que a DS já tem é **barrado**.

Ou seja: as regras `ds/*` entram no ratchet, o baseline engole a dívida atual, e o gap **para de crescer no mesmo dia**. A limpeza da dívida vira backlog priorizado (a Matriz), não bloqueio.

---

## Onde a contagem aparece (não construir dashboard novo)

O repo já tem `Pages/Admin/GovernanceV4` + `_components/DriftAlertBanner.tsx` + scorecards YAML (`memory/governance/scorecards/*.yaml`). O drift de DS **alimenta isso**, não um painel paralelo:
- contagem de violações `ds/*` por módulo → vira **dimensão "Adoção DS"** num scorecard
- tendência → o mesmo padrão de banner do `DriftAlertBanner` (verde quando 0)
- métrica de saúde: `% de Pages que importam só de @/Components/ui` + nº de violações `ds/*` (deve tender a 0)

---

## "Componente de DS = pronto" — a nova definição (o lock real)

Antes eu entregava CSS + showcase. **A partir de agora, promover um componente só está pronto com o tripé:**
1. impl React em `@/Components/ui` (não só CSS no `design-system.css`)
2. a regra `ds/*` que **proíbe hand-rolar ele**
3. a story em `Pages/_Showcase/`

O componente nasce com o próprio guard. É isso que impede o contrato de correr na frente da adoção.

---

## Sequência recomendada (1 ciclo "fechar + travar" antes de refino novo)

1. **Cowork (eu):** os 3 docs acima — registry, regras `ds/*`, matriz P0. ✅ índice/registry/regras nesta leva.
2. **Code:** (a) cria os 4 componentes Onda F em `@/Components/ui` (`Segmented`, `FormSection`, `InputGroup`, `FieldState`); (b) cola as regras `ds/*`; (c) gera baseline ratchet; (d) stories no `_Showcase`.
3. **Migração por tela** vira barata **e** fiscalizada (Matriz, P0 primeiro).
4. Só então abrir refino KB-9.75 novo (Sells full, etc.) — já sem dívida nova.

## Ownership

| Quem | Faz |
|---|---|
| **[CC] Cowork** | registry · regras `ds/*` (spec) · matriz · "definição de pronto" |
| **[CL] Code** | componentes em `@/Components/ui` · liga eslint/ratchet/CI · stories · executa migração |
| **[W] Wagner** | torna o `eslint-gate` required check · aprova a sequência |

## Limite honesto

Eu **não escrevo no GitHub**. Produzo os docs + specs; o Code liga o eslint/CI/baseline. Quando eu disser "as regras estão prontas", significa *prontas pra colar* — não *commitadas*.
