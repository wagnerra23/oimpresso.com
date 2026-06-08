# DS ROADMAP ATÉ ZERO — sequência completa de adoção (`ds/* → 0`)

> **Origem:** Cowork [CC] · 2026-05-30 · **Para:** Claude Code [CL].
> **O que é:** o caminho INTEIRO até zerar o lint `ds/*`, em fases ordenadas por dependência. Cada fase = uma ou mais filas 1-por-1, merge em série, **parando no gate visual**. Este é o único arquivo que você precisa abrir primeiro — ele aponta as filas.
> **Regra-mãe (vale em tudo):** módulo é o ID canônico; "C#" deprecado. Eu (Cowork) **não comito** — você transporta. **NÃO mergeie com `--admin`**; [W2] dá Approve + screenshot.

---

## As 8 regras `ds/*` e onde cada uma fecha

| Regra | Fase que fecha | Estado |
|---|---|---|
| `ds/no-native-select` | **A** | fila ativa |
| `ds/no-native-checkbox` | **A** | fila ativa |
| `ds/no-native-radio` | **A** | fila ativa |
| `ds/no-rounded-xl` | **A** | fila ativa |
| `ds/no-adhoc-status-text` **Tipo 1** (erro de form) | **A** | fila ativa (Emenda 2026-05-30) |
| `ds/no-arbitrary-color` (`bg-[#..]` cru) | **B** | fila nova |
| `ds/no-adhoc-status-text` **Tipo 2** (badge) | **C → D** | C pré-req, D migra |
| `ds/no-handrolled-form-section` | **E** | fila nova |
| `ds/icons` (opcional) | **F** | opcional |

---

## ⚠️ Pré-requisito de tudo (confirme antes de começar)

A Fase A (FieldError) e a Fase E (FormSection) **assumem que os componentes Onda F existem** em `@/Components/ui`: `Segmented`, `FormSection`, `InputGroup`, `FieldState` (`FieldError`/`FieldSuccess`). Eles vêm do **PR-A** (`PROMPT_PARA_CODE_PR-A-onda-f-react.md`).
- **Se PR-A já mergeou:** segue normal.
- **Se NÃO:** rode PR-A primeiro (cria os 4 + regras + stories). Sem isso, não há destino limpo pra `<FieldError>`/`<FormSection>`.

---

## 🗺️ A SEQUÊNCIA (ordem de dependência — não pule)

### FASE A — Controles + FieldError Tipo 1  ▶ **fila ativa**
**Arquivo:** `PROMPT_PARA_CODE_PR-C-WORKLIST.md` (10 módulos, Sells lidera).
Fecha: select/checkbox/radio/rounded + erro-de-form. **Comece aqui.**

### FASE B — Cor crua → token  ▶ independente (pode rodar em paralelo com A)
**Arquivo:** `PROMPT_PARA_CODE_SWEEP-arbitrary-color.md`.
Fecha: `ds/no-arbitrary-color`. Mecânico mas precisa de critério (qual token). Toca folders diferentes da A → não colide ao buildar.

### FASE C — Onda G: Badge +5 variants  ▶ **pré-requisito da D** (component-only)
**Arquivo:** `ONDA_G_BADGE_VARIANTS.md`.
Adiciona `success/warning/danger/info/neutral` ao `badge.tsx`. **Não migra tela** — só cria o destino. **Mergeie antes da D.**

### FASE D — Lote-badge: 410 `STATUS_STYLE` → `<Badge variant>`  ▶ depois da C mergear
**Arquivo:** `PROMPT_PARA_CODE_LOTE-BADGE.md`.
Fecha: `ds/no-adhoc-status-text` **Tipo 2**. É o grosso do drift (64%). Usa o mapa STATUS_STYLE→variant da Onda G.

### FASE E — FormSection hand-rolled → `<FormSection>`  ▶ depois de A (mesmo terreno de form)
**Arquivo:** `PROMPT_PARA_CODE_SWEEP-formsection.md`.
Fecha: `ds/no-handrolled-form-section`. Mais arriscado (mexe em estrutura) → por módulo, com build no meio.

### FASE F — icons lockdown (opcional)  ▶ qualquer hora, último
**Inline neste arquivo (abaixo).** Troca import direto `lucide-react` → `@/Components/ui/icon-registry`.

---

## ✅ Quando tudo mergear
- `npm run ds:report` → todos os `ds/*` = **0** por módulo.
- `% de Pages que importam só de @/Components/ui` → alto.
- `DriftAlertBanner` verde. Daí o gap **não reabre** (o ratchet barra regressão).

---

## Como rodar (token-eficiente)
1. **Onda 1:** dispara **A** (Sells primeiro) **+ B** em paralelo (folders distintos). + **C** (component, mergeia cedo).
2. **Onda 2:** quando C mergear → **D** (lote-badge). Quando A fechar um módulo → **E** no mesmo módulo.
3. **F** por último, opcional.
4. Sempre: merge em série, rebase+`lint:baseline:write` no 2º+ PR, **para no gate visual**.

---

## FASE F inline — icons lockdown (cola, opcional)

````bash
git checkout main && git pull origin main
git checkout -b feat/ds-icons-registry

# achar imports diretos de lucide-react em telas
grep -rn "from 'lucide-react'" resources/js/Pages resources/js/Modules || true

# trocar: import { X } from 'lucide-react'  ->  import { X } from '@/Components/ui/icon-registry'
#   (se algum ícone não existir no registry, ADICIONE no icon-registry e re-exporte — não volte pro lucide direto)

npm run build && npm run lint:baseline:write && npm run lint:baseline:check

git add resources/js config/eslint-baseline.json
git commit -m "refactor(ds): icons via icon-registry (lockdown import lucide-react)

Troca import direto de lucide-react -> @/Components/ui/icon-registry nas telas.
Icones faltantes re-exportados no registry. Baseline cai. Sem mudanca visual."
git push -u origin feat/ds-icons-registry
gh pr create --title "refactor(ds): icons via icon-registry" \
  --body "Lockdown ds/icons: telas importam icones do icon-registry, nao do lucide-react direto. Sem mudanca visual." \
  --base main --head feat/ds-icons-registry
# NÃO --admin. [W2] confere que nenhum icone sumiu.
````
