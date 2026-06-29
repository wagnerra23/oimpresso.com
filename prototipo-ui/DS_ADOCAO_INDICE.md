# DS — Adoção & Anti-Drift · ÍNDICE

<!-- ds:worklist:start (auto · npm run ds:report -- --write) -->
## Status da fila — placar de execução (auto)

> Gerado por `npm run ds:report -- --write` · 2026-06-29 11:11 UTC · **total `ds/*` = 270** · fila 0/10 ✅.
> Derivado do `ds/*` real por módulo: **✅ = 0 (concluído)** · **☐ = pendente**. `[CC]` lê isto (Sync now) pra saber o que `[CL]` JÁ executou e o que falta — sem regerar o já-feito.

| # | Módulo (fila) | `ds/*` | Status |
|---|---|---:|---|
| 1 | Sells | 5 | ☐ pendente |
| 2 | RecurringBilling | 9 | ☐ pendente |
| 3 | OficinaAuto | 5 | ☐ pendente |
| 4 | Repair | 10 | ☐ pendente |
| 5 | Purchase | 14 | ☐ pendente |
| 6 | Admin | 10 | ☐ pendente |
| 7 | Whatsapp | 19 | ☐ pendente |
| 8 | Settings | 8 | ☐ pendente |
| 9 | Financeiro | 63 | ☐ pendente |
| 10 | Cliente | 18 | ☐ pendente |

**Fora da fila (pendentes · ordem por contagem):** Fiscal (14) · Jana (13) · Ponto (11) · ProjectMgmt (10) · governance (10) · Atendimento (7) · Produto (6) · StockTransfer (6) · NfeBrasil (5) · kb (5) · Home (4) · Site (4) · ads (4) · MemCofre (3) · StockAdjustment (3) · Compras (2) · ConsultaOs (1) · Nfse (1)

**Próximo da fila:** Sells (5)
<!-- ds:worklist:end -->

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
| **`ds-v6/`** (kit DS v6) | A **régua visual** aprovada [W] (2026-06-03): [`showcase.html`](ds-v6/showcase.html) (11 componentes em token, claro/escuro) · [`receita.html`](ds-v6/receita.html) (montar tela em 6 passos) · [`gabarito-vendas.html`](ds-v6/gabarito-vendas.html) (kit aplicado) · [`REUSE_MAPPING.md`](ds-v6/REUSE_MAPPING.md) (kit `c-*` → React no repo) | **8/11 reusam** o REGISTRY; 3 são buraco do DS (Tier-0) |

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

## Baseline medido — o drift inicial (2026-05-29, pós-PR #1979)

> Preenche a promessa da seção acima com o número real. Medido por **[CL] Code** rodando `eslint.config.js` (merge `fe9a182d6`) sobre `resources/js/Pages/**`. Total: **639 violações `ds/*` em 197 arquivos** (em `config/eslint-baseline.json`; total geral do baseline = 1455). É o número-base do drift — cada PR-C abaixa, meta `ds/*` → 0.
>
> ⚠️ O ratchet guarda as 6 regras como um único `no-restricted-syntax`, então o split por seletor **não está no JSON** — veio de re-rodar o ESLint e agrupar pela mensagem `ds/…`.

### Por regra

| Regra `ds/*` | Violações | % | Destino |
|---|---:|---:|---|
| `no-adhoc-status-text` | 410 | 64.2% | `<FieldError>`/`<FieldSuccess>`/`<Alert>` (Tipo 1) · `<Badge variant>` (Tipo 2) |
| `no-native-select` | 103 | 16.1% | `<Select>` (@/ui) |
| `no-rounded-xl` | 66 | 10.3% | `rounded-lg` / `<Card>` / `<Dialog>` |
| `no-native-checkbox` | 53 | 8.3% | `<Checkbox>` |
| `no-native-radio` | 7 | 1.1% | `<Segmented>` / `<RadioGroup>` |
| `no-arbitrary-color` | 0 | 0% | — (já limpo) |

> Os 410 `no-adhoc-status-text` casam `text-(rose/red/emerald/green)-(500/600/700)` e misturam os **dois tipos** da nuance da Matriz: Tipo 1 (form `<FieldError>` — migra primeiro) e Tipo 2 (badge `STATUS_STYLE` — baseline absorve, migra por último). O seletor não separa; só o contexto.

### Por área (`Pages/<X>`) — por contagem

| Área | `ds/*` | | Área | `ds/*` |
|---|---:|---|---|---:|
| Financeiro | 107 | | Admin | 26 |
| Cliente | 77 | | Whatsapp | 24 |
| RecurringBilling | 58 | | Settings | 22 |
| OficinaAuto | 44 | | Atendimento | 19 |
| Sells | 42 | | ProjectMgmt | 17 |
| Repair | 35 | | Ponto | 17 |
| Purchase | 31 | | Fiscal | 14 |

> _(top 14 = 533; demais áreas ≈ 106; pior arquivo único: `Pages/Financeiro/Unificado/Index.tsx` = 25.)_
>
> **Contagem crua ≠ ordem do P0.** Financeiro lidera por volume, mas o P0 da Matriz é **Cliente + Sells** (telas mais tocadas, origem do diagnóstico KB-9.75). Estado real medido: **Cliente/Create + Edit já zerados** (migrados no PR-A). Próximos alvos reais: **Sells Create (6) + Edit (12)** e **Cliente/Index (12)**.

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
