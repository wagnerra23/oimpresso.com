# PR-C WORKLIST — fila de migração de controles + FieldError Tipo 1 (resolver 1 por 1)

> **Origem:** Cowork [CC] · 2026-05-30 · **Para:** Claude Code [CL].
> **O que é:** a fila completa de migração DS, ordenada pra você **resolver um módulo por vez** (1 módulo = 1 branch = 1 PR), mergeando **em série**. Substitui o trabalho de ficar caçando qual PR-C é qual.
> **Novidade desta rodada:** entra o **`<FieldError>` Tipo 1** no transform (antes os PR-C deixavam *todo* status-text pra depois). Ver a **Emenda** abaixo — ela vale pra todos os módulos.

---

## ⚠️ Reconciliação de numeração (LER UMA VEZ)

Havia duas numerações "PR-C#" em conflito:
- `MATRIZ_MIGRACAO_DS.md` chamava **Sells de "PR-C2"**.
- Os arquivos `PROMPT_PARA_CODE_PR-C*.md` numeram por onda-de-módulo (**Sells = C5**, Cliente = C2…).

**Decisão:** o **nome do módulo é o identificador canônico.** Os "C#" estão **deprecados** — ignore o número, use o módulo. Esta worklist é a fila oficial; os arquivos `PROMPT_PARA_CODE_PR-C*-<módulo>.md` viram **detalhe/caução por módulo** (você pode abrir pra ver as armadilhas específicas, mas a regra-mãe é esta).

---

## 🧩 Transform canônico (idêntico em todo módulo)

Aplique **só** nestes alvos (acha com eslint/grep, edita só as linhas apontadas — **não** releia arquivo inteiro):

| Padrão hand-rolled | Regra `ds/*` | Destino DS | Import |
|---|---|---|---|
| `<select>` nativo | `ds/no-native-select` | **`<Select>`** — sentinela `__none__` p/ option vazia, `aria-label` no `SelectTrigger` | `@/Components/ui/select` |
| `<input type="checkbox">` | `ds/no-native-checkbox` | **`<Checkbox>`** + `<Label htmlFor>`/`id` | `@/Components/ui/checkbox` |
| `<input type="radio">` | `ds/no-native-radio` | **`<Segmented>`** (2–3 opções) ou **`<RadioGroup>`** | `@/Components/ui/{segmented,radio-group}` |
| `rounded-xl\|2xl\|3xl` | `ds/no-rounded-xl` | **`rounded-lg`** ou **`<Card>`** | `@/Components/ui/card` |
| **`<p className="text-rose-600">{error}</p>` (erro de form)** | `ds/no-adhoc-status-text` | **`<FieldError>`** ← **NOVO** | `@/Components/ui/field-state` |

---

## 🆕 EMENDA 2026-05-30 — FieldError Tipo 1 entra (sobrepõe os PR-C antigos)

**Onde QUALQUER arquivo `PROMPT_PARA_CODE_PR-C*.md` disser _"NÃO tocar em status-text"_, agora vale isto:**

O grep de `text-(rose|red|emerald|green)-(500|600|700)` retorna **dois tipos** — e eles têm destinos diferentes (ver `MATRIZ_MIGRACAO_DS.md §Nuance`):

- ✅ **Tipo 1 — MIGRA AGORA, neste PR.** Texto de **erro/sucesso de campo** num form: `<p|span|div className="text-rose-600">{errors.x}</p>` (mensagem de validação Inertia, lookup CNPJ/CEP, etc.).
  → vira **`<FieldError>{errors.x}</FieldError>`** (role=alert) — ou `<FieldSuccess>` quando for o verde de sucesso (`text-emerald-600` do lookup).
- 🚫 **Tipo 2 — NÃO TOCA, espera a Onda G (lote-badge).** Cor semântica de **status/badge**: dentro de objeto `STATUS_STYLE`, mapa de variantes, ou combinada com `bg-*-50` / `rounded-full` (pílula de estado). Fica pro `<Badge variant>`.

**Heurística rápida pra separar:** se o conteúdo é a **mensagem de erro de um campo** → Tipo 1 (migra). Se é um **rótulo de estado** (`Pago`, `Atrasado`, `Autorizada`) com fundo de cor → Tipo 2 (deixa).

> Resultado: cada PR de módulo agora zera **controles + rounded + FieldError Tipo 1** de uma vez. Só sobra o lote-badge (Tipo 2) pra Onda G.

---

## 📋 A FILA (resolva de cima pra baixo, 1 por vez, merge em série)

| # | Módulo | Folder | Faz | Peso | Detalhe/cauções | Status |
|---|--------|--------|-----|------|------|--------|
| 1 | **Sells** ⭐ | `Pages/Sells` | controles + rounded + **FieldError T1** | ⚠️ pesado (Create 68KB, Index 77KB) | `PROMPT_PARA_CODE_PR-C5-sells.md` — não quebrar fluxo do balcão; autocompletes já são custom; `PaymentRow`/`QuickPaymentDialog` críticos; **screenshot [W2] obrigatório** | ☐ |
| 2 | **RecurringBilling** | `Pages/RecurringBilling` | controles + rounded + **FieldError T1** | médio | `PROMPT_PARA_CODE_PR-C3-recurringbilling.md` — Planos Create/Edit (ciclo/intervalo/gateway); billing não pode quebrar | ☐ |
| 3 | **OficinaAuto** | `Pages/OficinaAuto` | controles + rounded + **FieldError T1** | médio | `PROMPT_PARA_CODE_PR-C4-oficinaauto.md` — cuidado DnD Kanban; `ServiceOrderStatusBadge` = Tipo 2 (deixa) | ☐ |
| 4 | **Repair** | `Pages/Repair` | controles + rounded + **FieldError T1** | médio | `PROMPT_PARA_CODE_PR-C6-repair.md` — mobile-first, touch ≥44px; JobSheet/DeviceModels | ☐ |
| 5 | **Purchase** | `Pages/Purchase` | controles + rounded + **FieldError T1** | leve | `PROMPT_PARA_CODE_PR-C7-purchase.md` — Create/Edit/Index/Show | ☐ |
| 6 | **Admin** | `Pages/Admin` | controles + rounded + **FieldError T1** | a medir | sem prompt dedicado — descobre com eslint | ☐ |
| 7 | **Whatsapp** (Inbox) | `Pages/Whatsapp` | controles + rounded + **FieldError T1** | a medir | sem prompt dedicado — descobre com eslint | ☐ |
| 8 | **Settings** | `Pages/Settings` | controles + rounded + **FieldError T1** | a medir | toggles → `<Switch>`; sem prompt dedicado | ☐ |
| 9 | **Financeiro** | `Pages/Financeiro` + `Modules/Financeiro` | **só FieldError T1** (controles ✅ no PR-C1) | leve | `PROMPT_PARA_CODE_PR-C1-controles.md` — só os `<p text-rose>{error}</p>` que sobraram | ☐ |
| 10 | **Cliente** | `Pages/Cliente` | **só FieldError T1** (controles ✅ no PR-C2) | leve | `PROMPT_PARA_CODE_PR-C2-cliente.md` — confere se PR-A já migrou o erro do `ClienteForm`; migra o que restar | ☐ |

> Itens 6–8 podem ir juntos num **SWEEP** se a contagem de hits for baixa (ver `PROMPT_PARA_CODE_SWEEP-controles-restantes.md`, agora **+ FieldError T1**).
> Itens 9–10 são PRs leves de só-FieldError — pode até juntar num único PR `feat/ds-fielderror-t1-financeiro-cliente`.

---

## ▶️ Receita por módulo (cola, troca só `<MOD>` e o folder)

Roda **um** destes blocos por vez. `<MOD>` = nome do módulo em minúsculo (ex.: `sells`).

````bash
git checkout main && git pull origin main
git checkout -b feat/ds-migrate-<MOD>

# 1. ACHAR os hits (controles + rounded + status-text). NÃO leia arquivo inteiro.
npx eslint resources/js/Pages/<Folder> 2>&1 \
  | grep -E 'ds/no-native|ds/no-rounded-xl|ds/no-adhoc-status-text' || true

# 2. MIGRAR só as linhas apontadas:
#    <select>            -> <Select>   (sentinela __none__ + aria-label)
#    type=checkbox       -> <Checkbox> (+ <Label htmlFor>/id)
#    type=radio          -> <Segmented> (2-3) ou <RadioGroup>
#    rounded-xl|2xl|3xl  -> rounded-lg ou <Card>
#    <p text-rose-600>{error}</p>  -> <FieldError>{error}</FieldError>   (TIPO 1 — migra)
#    STATUS_STYLE / badge bg-*-50  -> NÃO toca (TIPO 2 — Onda G)
#    (módulos grandes: migre por arquivo e rode `npm run build` no meio)

# 3. baseline cai
npm run lint:baseline:write
npm run lint:baseline:check          # verde (delta <= 0)

git add resources/js/Pages/<Folder> config/eslint-baseline.json
git commit -m "refactor(ds): migra controles + FieldError T1 do <MOD> -> componentes DS

select->Select(+__none__/aria-label), checkbox->Checkbox(+htmlFor),
radio->Segmented, rounded-xl->lg/Card, <p text-rose>{error}> -> <FieldError> (Tipo 1).
Status-badge (Tipo 2) fica pro lote-badge (Onda G). Baseline cai. Sem mudanca de comportamento.

Refs: PR-C-WORKLIST.md, MATRIZ_MIGRACAO_DS.md, REGISTRY_DS_COMPONENTES.md"

git push -u origin feat/ds-migrate-<MOD>
gh pr create --title "refactor(ds): migra controles + FieldError T1 do <MOD> -> DS" \
  --body "Migra select/checkbox/radio/rounded-xl + erros de form (FieldError Tipo 1) do <MOD> pros componentes DS. Status-badge (Tipo 2) fica pra Onda G. Baseline cai." \
  --base main --head feat/ds-migrate-<MOD>
# >>> NÃO mergeie com --admin. [W2] dá Approve + screenshot. PARA NO GATE VISUAL.
````

### Se outro PR-C mergeou antes deste (colisão do baseline) — antes do merge:
````bash
git fetch origin main && git rebase origin/main
npm run lint:baseline:write && git add config/eslint-baseline.json && git commit --amend --no-edit && git push -f
````

---

## 🛑 Gates (não pule)

1. **Por PR:** `lint:baseline:check` verde (delta ≤ 0) + build passa.
2. **Gate visual [W2]:** abre o PR, **NÃO mergeia**. Wagner confere screenshot (Sells é obrigatório; demais se a tela mudou). Só mergeia com Approve dele.
3. **Merge em série:** um PR por vez na main. O 2º+ roda o refresh de baseline (rebase) acima antes de mergear.

## ✅ Definição de pronto (a fila inteira)
- `ds/no-native-select|checkbox|radio` + `ds/no-rounded-xl` → **0** em todos os `Pages/*`.
- `ds/no-adhoc-status-text` **Tipo 1** → **0** (erros de form migrados).
- Sobra só **Tipo 2** (status-badge) → vai pra **Onda G — lote-badge** (`ONDA_G_BADGE_VARIANTS.md`), etapa separada.
- Zero mudança de comportamento. Baseline monotonicamente menor a cada PR.
