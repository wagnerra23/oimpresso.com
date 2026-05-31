# GOLDEN-REFERENCE — as 10 regras da auditoria de tela (+ `ds/*`)

> **status:** draft (Code [CL] 2026-05-31) · **reconciliar** com as "10 regras" da cópia do Cowork antes de virar canon.
> **NÃO é invenção** — é consolidação de canon já existente em git:
> - **AP1–AP10** de [`PRE-MERGE-UI.md`](../../memory/requisitos/_DesignSystem/PRE-MERGE-UI.md) (Camada 4 · KB-9.75)
> - **`ds/*`** (6 regras ESLint) de [`REGRAS_DS_LINT.md`](../REGRAS_DS_LINT.md) / `config/eslint-baseline.json` ([ADR 0209](../../memory/decisions/0209-eslint-9-flat-config.md))
> - hierarquia [Constituição UI v2 · UI-0013](../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
>
> **Princípio:** regra **mecanizada** (`mechanized: true`) = evidência reproduzível por terceiro (regex/AST/ESLint). Regra **julgada** (`mechanized: false`) = opinião do agente, vale menos e exige citação. Tela fecha só pela mecanizada (anti-"Gaming the Judge", dossier 2026-05-30).

## As 10 regras

| id | regra | mecanizado? | detecção (read-only) | canon | peso |
|---|---|:--:|---|---|:--:|
| **R1** (AP1) | **Sem cor crua.** Zero `#hex`, `oklch()`/`rgb()`/`hsl()` literal, `bg-*-100/500/700` cru. Só tokens DS (primary roxo 295). | ✅ | regex `#[0-9a-fA-F]{3,8}` / `oklch\(` / `rgb\(` / `hsl\(` no `.tsx` (exceto `#fff`/`#000` e comentários). Cruza com `ds/no-arbitrary-color`. | PRE-MERGE AP1 · UI-0013 | 3 |
| **R2** (AP2) | **Componentes do shared.** Não reinventar `Table`/`Drawer`/`PageHeader`/`DataTable`/`BulkActionBar`/`EmptyState`/`StatusBadge`/`Select`/`Input`. | ⚠️ | `<table`/`<select`/`<input`/`<textarea` nativos = sinal forte (mecanizado); "reinventou Drawer" = julgado. Cruza `ds/no-native-*`. | PRE-MERGE AP2 | 3 |
| **R3** (AP3) | **`localStorage` prefixado** `oimpresso.<modulo>.*`. | ✅ | regex `localStorage` sem `oimpresso\.` na mesma linha/bloco. | PRE-MERGE AP3 · ADR 0093 | 1 |
| **R4** (AP4) | **Ícones só `lucide-react`.** Sem SVG inline decorativo, sem outra lib de ícone. | ✅ | imports de ícone fora de `lucide-react`; `<svg` inline decorativo. | PRE-MERGE AP4 · UI-0003 | 1 |
| **R5** (AP5) | **Sem gradient decorativo** 135deg bluish-purple (anti-trope). | ⚠️ | regex `gradient` + hue azul/roxo cru; intenção decorativa = julgado. | PRE-MERGE AP5 | 1 |
| **R6** (AP6) | **Sem emoji** em UI de produto (só ícone lucide). | ✅ | regex de range emoji no JSX (fora de comentário/teste). | PRE-MERGE AP6 | 1 |
| **R7** (AP7) | **Status = dot + texto**, não `bg-fill` (Stripe-style). | ⚠️ | `bg-(red\|green\|amber\|emerald)-100` em badge; cruza `ds/no-adhoc-status-text`. | PRE-MERGE AP7 | 2 |
| **R8** (AP8) | **PT-BR** em todo label/copy/erro/empty. | ⚠️ | strings em inglês visíveis ao usuário = julgado (citação obrigatória). | PRE-MERGE AP8 | 1 |
| **R9** (AP9) | **Um só `<main>`.** Sem `<main>` aninhado dentro do `<main>` do AppShellV2. | ✅ | `<main` count no `.tsx` (>0 dentro de Page já é suspeito; runtime `querySelectorAll('main').length ≤ 1`). | PRE-MERGE AP9 | 2 |
| **R10** (AP10) | **Chain de overflow** respeitada — nó `flex-1` em coluna tem `h-full` ou pai `flex flex-col min-h-0`. | ⚠️ | estático: heurística da chain; definitivo = runtime (`scrollHeight>clientHeight` + ancestor `overflow:hidden`). | PRE-MERGE AP10 | 2 |

**Total de peso:** 17. Regras mecanizadas (✅ R1,R3,R4,R6,R9) = evidência dura; ⚠️ = parte mecanizada + parte julgada.

## `ds/*` (camada mecanizada, ESLint — espelha `DS_ADOCAO_INDICE`)

Contagem por módulo de `config/eslint-baseline.json` (ratchet ADR 0209). Cada `design-report.json` carrega `ds_violations.by_rule`:

| `ds/*` | substitui (anti-pattern) |
|---|---|
| `no-adhoc-status-text` | `<FieldError>`/`<Alert>`/`<Badge variant>` |
| `no-native-select` | `<Select>` @/ui |
| `no-rounded-xl` | `rounded-lg`/`<Card>` |
| `no-native-checkbox` | `<Checkbox>` |
| `no-native-radio` | `<Segmented>`/`<RadioGroup>` |
| `no-arbitrary-color` | tokens (overlap R1) |

> `ds/*` é a evidência mais dura (a máquina varre 100% dos arquivos). As 10 regras acima cobrem o que o ESLint ainda não pega (estrutura, `<main>`, overflow, emoji, PT-BR).

## Implementação mecanizada ([`score-mechanized.mjs`](score-mechanized.mjs))

Roda **7 das 10 regras** por regex, zero LLM (R1·R2-nativo·R3·R4·R6·R7·R9) + `ds/*` do baseline. As 3 julgadas (R5·R8·R10) ficam pro agente Fase 2.

Calibração v1 (2026-05-31, após validar contra o board):
- **R6** = só emoji real (`\u{1F000}-\u{1FAFF}`). Dingbats BMP (`✓ ✕ ★ ✦ ⚙ ⬇`) **excluídos** — eram falso-positivo nos goldens (são glyph de UI, não emoji; o smell correto é R4 "usar lucide").
- **R7** = heurística **ampla** (qualquer `bg-*-(50|100|200)`, não só badge). Mecanizado mas **baixa-precisão** → o agente Fase 2 confirma AP7 real. 80/239 telas batem; tratar como sinal.
- **R1** exclui `#fff`/`#000`; pode ter FP raro em comentário (aceitável v1).

## Nota e nível (derivados — secundário ao pass/fail)

A **nota 0-100** mantém compatibilidade com o board (`SCREEN-GRADE-9.75`): é o sinal de julgamento. O sinal **primário** desta worklist é o **pass/fail mecanizado** (quantas das ✅ falharam + `ds/*` total) — esse é o que fecha tela por evidência. Níveis: `Champion 95-100 · Leader 85-94 · Advanced 70-84 · Developing 50-69 · Beginner 0-49`.

Fórmula de referência (auditável, não autoritativa): `nota = 100 − Σ(peso da regra falhada × 4) − min(ds_total, 20)`, piso 0. O agente PODE ajustar por julgamento, mas **deve** registrar as regras mecanizadas pelo fato, não pela nota.
