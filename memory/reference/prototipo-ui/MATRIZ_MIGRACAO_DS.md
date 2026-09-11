# MATRIZ DE MIGRAÇÃO — DS adoption (P0)

> Por arquivo: padrão hand-rolled (com linha real @ main) → regra `ds/*` que pega → componente destino → esforço.
> Base: grep das assinaturas de drift nos arquivos reais (2026-05-29). **P0 = Cliente (Create/Edit/Index) + Sells (Create/Edit/FiscalSection)** — as telas mais tocadas.
> Resto dos 745: coberto pela contagem do baseline quando o `ds/*` rodar (não detalhado à mão — seria slop).

## Nuance importante (ler antes)

O grep de `text-rose/emerald-600` retorna **dois tipos** bem diferentes:
1. **`<p className="text-rose-600">{error}</p>` em form** → drift limpo. Vira `<FieldError>`. **Migrar primeiro.**
2. **`STATUS_STYLE` / badge de status** (`bg-emerald-50 text-emerald-700 …`) → padrão legítimo (cor semântica de estado), mas hardcoded na paleta crua. Devia ser `<Badge variant>` / token. **Baseline absorve; migra por último.**

O `ds/*` pega os dois; o **ratchet** garante que nenhum NOVO entra. A matriz abaixo prioriza o tipo 1.

---

## P0-1 · `Cliente/Create.tsx` + `_form/DadosFiscaisBRSection.tsx` *(o F3 do diagnóstico Contacts)*

| Padrão hoje | Linha | Regra | Destino | Esforço |
|---|---|---|---|---|
| `<input type="radio">` PF/PJ (×2) | Create 163,173 | `ds/no-native-radio` | **`<Segmented>`** (tipo + pessoa) | baixo |
| `<select>` Tipo + Grupo (×2) | Create 148,304 | `ds/no-native-select` | **`<Select>`** (@/ui) | baixo |
| `<p className="text-rose-600">{error}</p>` | Create 364 | `ds/no-adhoc-status-text` | **`<FieldError>`** | baixo |
| `<Section>` hand-rolled (p-4) | Create (componente local) | `ds/no-handrolled-form-section` | **`<FormSection>`** | baixo |
| `<Field>` hand-rolled | Create + DadosFiscais (2 cópias) | — | **`<Field>`** único de `@/ui` (consolidar) | médio |
| CNPJ `<div flex>` input+botão | DadosFiscais | `ds/no-handrolled-form-section`* | **`<InputGroup>`** + `ig-btn` (loading/done) | baixo |
| `<p text-rose-600>` / `text-emerald-600` lookup | DadosFiscais | `ds/no-adhoc-status-text` | **`<FieldError>` / `<FieldSuccess>`** | baixo |
| `<select>` indicador IE / regime | DadosFiscais | `ds/no-native-select` | **`<Select>`** | baixo |
| `<input type="checkbox">` flags | DadosFiscais | `ds/no-native-checkbox` | **`<Checkbox>`** | baixo |

**+ ganho 9.75:** envolver com `.form-layout` + `.form-rail` (preview vivo · copiloto dedup · prontidão fiscal). É o PR-A "aplica no Create".

## P0-2 · `Cliente/Edit.tsx` *(espelho do Create — mesma migração, de graça)*

| Padrão | Linha | Destino |
|---|---|---|
| `<input type="radio">` (×2) | 180,190 | `<Segmented>` |
| `<select>` Tipo + Grupo | 165,262 | `<Select>` |
| `<p text-rose-600>{error}</p>` | 312 | `<FieldError>` |
| `<Section>`/`<Field>` locais | — | `<FormSection>`/`<Field>` |

> Create e Edit compartilham 90% — migrar juntos, extrair um `ClienteForm` comum (fecha a duplicação `<Section>` p-4 vs p-5).

## P0-3 · `Cliente/Index.tsx`

| Padrão | Linha | Regra | Destino | Esforço |
|---|---|---|---|---|
| `rounded-xl` KPI cards + skeleton | 1352,1534 | `ds/no-rounded-xl` | `rounded-lg` (card) / **`<Card>`** | baixo |
| modais `rounded-xl` + `fixed inset-0 bg-black/40` hand-rolled (×2) | 2301,2445 | `ds/no-rounded-xl` | **`<Dialog>`** / **`<CommandDialog>`** | médio |
| `<select>` per-page | 2150 | `ds/no-native-select` | **`<Select>`** | baixo |
| `STATUS_STYLE` rose/sky/stone + KPI danger | 258,1534,1881… | (badge) | **`<Badge variant>`** / token semântico | médio (lote) |

## P0-4 · `Sells/Create.tsx` *(melhor da turma — já usa `<Select>`)*

| Padrão | Linha | Regra | Destino |
|---|---|---|---|
| `rounded-xl` KPI cards (×4) | 916,924,932,942 | `ds/no-rounded-xl` | `rounded-lg` / `<Card>` |
| status text emerald/blue (pagamento) | 964,1211 | (semântico) | token / `<Badge>` |
| `<Select>` (status, local, desconto, fatura, imposto, comissão…) | 1014,1032… | ✅ **já DS** | manter |

> Sells/Create já consome `<Select>` certo. Drift só visual (rounded-xl + cores de status). Migração leve.

## P0-5 · `Sells/Edit.tsx` *(mais drift que o Create)*

| Padrão | Linha | Regra | Destino |
|---|---|---|---|
| `rounded-xl` KPI (×4) | 518,522,526,532 | `ds/no-rounded-xl` | `rounded-lg`/`<Card>` |
| `<select>` nativo (status, desconto, comissão, por-produto) | 661,681,824,889 | `ds/no-native-select` | `<Select>` |
| `<input type="checkbox">` is_recurring | 909 | `ds/no-native-checkbox` | `<Checkbox>` / `<Switch>` |
| status text emerald/amber | 544 | (semântico) | token/`<Badge>` |

> Edit ficou pra trás do Create. Alinhar os dois no mesmo PR de Sells.

## P0-6 · `Sells/_components/FiscalSection.tsx`

| Padrão | Linha | Regra | Destino |
|---|---|---|---|
| `STATUS_STYLE` rose/emerald/amber (badge fiscal) | 36–41 | (badge) | `<Badge variant>` / token |
| error/success `<div>` rose/emerald | 110,121 | `ds/no-adhoc-status-text` | **`<Alert>`** |

---

## Ordem de execução (PR-C+, depois do guard vivo)

1. **PR-C1 — Cliente Create+Edit** (já feito visualmente no PR-A; aqui é só confirmar baseline zerado no módulo)
2. **PR-C2 — Sells Create+Edit** (alinhar, trocar `<select>` nativo, rounded-xl)
3. **PR-C3 — Cliente Index** (modais→Dialog, badges→variant)
4. **PR-C4 — FiscalSection + lote de badges** (Badge variants semânticas, fecha a paleta crua)
5. Resto dos módulos: por ordem de contagem do `ds:report`.

Cada PR-C baixa o baseline. Meta visível no `ds:report`: total `ds/*` → 0.
