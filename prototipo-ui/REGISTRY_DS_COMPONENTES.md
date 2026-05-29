# REGISTRY — Componentes canônicos do DS

> Fonte da verdade do que existe pra consumir. **Se está aqui, não hand-rola.**
> Superfície única de import: **`@/Components/ui`** (shadcn + Radix + CVA, bridge visual via `cowork-fields.css` + variantes `cowork-*`).
> Última atualização: 2026-05-29 (Cowork [CC]) · base: leitura de `resources/js/Components/ui/*` @ main.

## Como ler

- **Existe** = componente React já no repo.
- **Cowork** = tem visual canon Cowork aplicado (variante/`.cw-*`) — usar essa, não a shadcn pura.
- **Substitui (anti-pattern)** = o que NÃO se escreve mais à mão. É isto que vira regra `ds/*`.

---

## Form controls (núcleo do drift de cadastro)

| Componente | Import | Existe | Cowork | Substitui (anti-pattern) |
|---|---|---|---|---|
| **Input** | `@/Components/ui/input` | ✅ | ✅ default (`cw-input`, ADR UI-0015) | `<input className="border rounded-md h-9 …">` cru |
| **Textarea** | `@/Components/ui/textarea` | ✅ | ✅ | `<textarea className="border …">` cru |
| **Select** | `@/Components/ui/select` | ✅ | shadcn/Radix | `<select className="h-9 rounded-md border …">` nativo |
| **RadioGroup** / RadioGroupItem | `@/Components/ui/radio-group` | ✅ | Radix | **`<input type="radio">` nativo** ← o drift do Create |
| **Checkbox** | `@/Components/ui/checkbox` | ✅ | Radix | `<input type="checkbox">` nativo |
| **Switch** | `@/Components/ui/switch` | ✅ | Radix | toggle hand-rolled / checkbox pra booleano de setting |
| **Label** | `@/Components/ui/label` | ✅ | Radix | `<label className="text-xs …">` solto |
| **Button** | `@/Components/ui/button` | ✅ | ✅ `variant="cowork-primary\|cowork-ghost"`, `size="cowork"` | `<button className="bg-… rounded-md px-…">` cru |

## Overlays / navegação / feedback

| Componente | Import | Existe | Substitui (anti-pattern) |
|---|---|---|---|
| **Dialog** | `@/Components/ui/dialog` | ✅ | modal hand-rolled com `fixed inset-0` |
| **AlertDialog** | `@/Components/ui/alert-dialog` | ✅ | confirm() / modal de confirmação caseiro |
| **Sheet** (drawer) | `@/Components/ui/sheet` | ✅ | drawer lateral hand-rolled |
| **Popover** | `@/Components/ui/popover` | ✅ | dropdown posicionado na mão |
| **DropdownMenu** | `@/Components/ui/dropdown-menu` | ✅ | menu kebab caseiro |
| **Tooltip** | `@/Components/ui/tooltip` | ✅ | `title=""` / tooltip CSS ad-hoc |
| **Command** (⌘K) | `@/Components/ui/command` | ✅ | palette hand-rolled |
| **Alert** | `@/Components/ui/alert` | ✅ | banner `bg-red-50 border …` cru |
| **Badge** | `@/Components/ui/badge` | ✅ | pílula `<span className="rounded-full px-2 …">` cru |
| **Avatar** | `@/Components/ui/avatar` | ✅ | inicial em `<div>` redondo na mão |
| **Card** | `@/Components/ui/card` | ✅ | `<div className="rounded-lg border bg-card p-…">` |
| **Skeleton** | `@/Components/ui/skeleton` | ✅ | `animate-pulse bg-muted` na mão |
| **Separator** | `@/Components/ui/separator` | ✅ | `<hr>` / `border-t` solto |
| **ScrollArea** · **Resizable** | `@/Components/ui/{scroll-area,resizable}` | ✅ | — |
| **icon-registry** | `@/Components/ui/icon-registry` | ✅ | import solto de `lucide-react` fora do registry |

> Os ✅ "Existe" são fato (lidos do diretório). Detalhe de variante confirmado pra Input/Button/RadioGroup; demais seguem convenção shadcn + bridge cowork. Code valida ao criar as stories.

---

## Onda F — a criar em `@/Components/ui` (hoje só CSS no DS)

Estes 4 ainda **não** têm impl React. Enquanto não existirem, a tela hand-rola — por isso entram primeiro.

| Componente novo | CSS DS (v4.1) | Substitui | Primeiro consumidor |
|---|---|---|---|
| **`Segmented`** | `.segmented` (+`.accent`) | `<input type="radio">` de PF/PJ, Cliente/Fornecedor; e RadioGroup quando é toggle 2–3 opções | `Cliente/Create` (tipo + pessoa) |
| **`FormSection`** | `.form-section` + `.form-grid` | `<section className="rounded-lg border p-4\|p-5">` (hoje duplicado: Create p-4, DadosFiscaisBR p-5) | `Cliente/Create` + `DadosFiscaisBRSection` |
| **`InputGroup`** (+ `ig-btn`/`ig-addon`) | `.input-group` | `<div className="flex"> input + button` do "Buscar CNPJ"/ViaCEP; addon `R$`/`%` | `DadosFiscaisBRSection` (CNPJ) |
| **`FieldState`** (`FieldError`/`FieldSuccess`/`FieldValidating`) | `.field-error` (A2) · `.field-success` · `.field-validating` · `.req` | `<p className="text-rose-600">` / `<p className="text-emerald-600">` soltos | `DadosFiscaisBRSection` (lookup) |

**Pattern (não é componente):** `.form-layout` + `.form-rail` (form + rail de contexto sticky) — o que deu o salto 6,2→9,5 no Contacts. Vira layout reusável de qualquer **Create**.

---

## Regra de ouro

> Precisa de algo que **não está** neste registry? **Não hand-rola na tela.** Atualiza o DS (vira Onda nova com o tripé), aí consome. Toda exceção é dívida que o `ds/*` vai cobrar no PR.
