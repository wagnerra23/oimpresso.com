---
id: requisitos-sells-sells-create-ds-v3-conformance
slug: sells-create-ds-v3-conformance
title: "Sells — Auditoria de conformidade DS v3 ↔ /sells/create"
type: conformance-audit
module: Sells
status: draft
date: 2026-05-28
ds_version: v3.1
ds_source: prototipo-ui/{tokens.css,design-system.css,CODE_DESIGN_CONTRACT.md}
target: resources/js/Pages/Sells/Create.tsx
charter: resources/js/Pages/Sells/Create.charter.md
related_adrs: [0110, 0190]
related_ui_adrs: ["UI-0013"]
gera_para: briefing F1 Cowork (Passo 2 — Vendas)
---

# Auditoria de conformidade — DS v3 ↔ `/sells/create`

> **Read-only.** Não edita código. Cumpre o gate `mwart-comparative` F1.5 (nenhuma
> referência visual aprovada existe pra Sells/Create contra o DS v3) e o
> `CODE_DESIGN_CONTRACT.md` ("o Code para e pede; não inventa CSS").
>
> **Propósito:** mapear componente-a-componente o que a tela atual já tem vs o DS v3,
> listar o que **falta no DS** pra cobrir o Sells (volta pra Cowork desenhar o F1),
> e expor as **duas decisões upstream** que destravam o reskin.

---

## TL;DR

- A tela atual é **madura e disciplinada** (charter `status: live`, Cockpit Pattern V2 · [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md), 39+ testes anti-regressão). Não é greenfield.
- **Incompatibilidade de tecnologia é o eixo central:** DS v3 = classes CSS + tokens (vanilla **ou Radix**); tela = React + Tailwind + shadcn/ui. Convergir é trabalho de **camada de token**, não troca de classe.
- **Boa notícia:** o DS v3 foi escrito pra *casar* com a realidade do repo (`--primary-page` cita [ADR 0190](../../decisions/0190-primary-universal-roxo-295.md); `--sb-*` cita o `cockpit.css` real). Risco de reconciliação **menor** que o esperado.
- **5 gaps** onde o DS não cobre o Sells → voltam pra Cowork antes do F1.
- **2 decisões upstream** bloqueiam o reskin: (1) F1 Cowork do Sells inexistente; (2) ausência de ADR posicionando DS v3 vs Constituição UI v2 / Cockpit V2.

---

## 1. Mapa de conformidade (componente-a-componente)

Estrutura da tela (charter + leitura de `Create.tsx:855-1617`): header sticky + 5 pills
de seção (scroll-spy) → 4 KPIs gigantes → seções `sec-dados / status / sec-produtos /
sec-pagamento / sec-resumo / notes / sec-mais-opcoes` → footer sticky.

| Elemento da tela | Hoje (`Create.tsx`) | Componente DS v3 | Conformidade |
|---|---|---|---|
| Shell + topnav | `AppShellV2` + topnav inline | `.pageheader` + `.moduletopnav` | 🟢 **mapeia** (via token bridge) |
| Busca cliente | `CustomerSearchAutocomplete` | `.combobox` (B7) | 🟢 mapeia |
| Busca produto | `ProductSearchAutocomplete` | `.combobox` (B7) | 🟢 mapeia |
| Status pgto | tom semântico `Pago`/`due` | `.badge` (`ok`/`warn`/`danger`) | 🟢 mapeia |
| Data da venda | `transaction_date` (input date) | `.datepicker` (B8) | 🟢 mapeia |
| Erro de campo | `<FieldError role="alert">` | `.field.has-error` + `.field-error` (A2a) | 🟢 mapeia |
| Inputs / textarea | shadcn `Input`/`Textarea`/`Label` | `.field` + `.input`/`.textarea` | 🟡 **bridge de token** |
| Botões | shadcn `Button` | `.btn` (`primary`/`ghost`/`danger`) | 🟡 bridge de token |
| Footer ações | `div sticky` Cancelar+Salvar (`Create.tsx:1581`) | `.savebar` (`dirty`/`saving`/`saved` · A3a) | 🟡 **upgrade**: savebar tem estados que o footer atual não tem |
| Empty state | `EmptyState` shared | `.empty-state` (matriz A6) | 🟢 mapeia |
| 5 pills de seção | `rounded-full` + counter + scroll-spy (`Create.tsx:869`) | ⚠️ `.filter-chip` (B6) **≠** semântica | 🔴 **gap semântico** (ver §2.3) |
| 4 KPIs gigantes | `text-3xl` 4-col (`Create.tsx:914`) | — (DS não tem stat card) | 🔴 **gap** (ver §2.1) |
| Split de pagamento | `PaymentRow` + saldo/troco | — (DS não tem) | 🔴 **gap** (ver §2.2) |
| "Mais opções" | `<details>` colapsável + localStorage | — (DS não tem disclosure) | 🟡 gap menor (ver §2.4) |
| Numérico pt-BR | `NumericInputPtBR` | `.input` (sem máscara pt-BR) | 🟡 gap menor (ver §2.5) |

Legenda: 🟢 DS cobre · 🟡 cobre com ponte/ajuste menor · 🔴 DS não cobre → Cowork.

---

## 2. Gaps — o que falta no DS pro Sells (→ Cowork, via `COWORK_NOTES.md`)

### 2.1 — Stat card / KPI gigante 🔴
A tela tem 4 KPIs grandes (Itens / Total / Pago / Status, `value text-3xl`, label
`uppercase tracking-widest`). O DS v3 tem **viz primitives** (`.viz-spark`, `.viz-gauge`…)
mas **nenhum "stat card" de número grande**. Cockpit V2 trata isso como pattern canon.
**Proposta:** componente `.stat-card` (value 36px, label eyebrow, tom semântico opcional).

### 2.2 — Linha de pagamento dividido 🔴
`PaymentRow` (split de pagamento + indicador falta/troco/exato) é específico de venda.
DS não tem. **Proposta:** ou aceitar como componente de módulo Sells (override
justificado), ou DS ganha `.payment-split-row`. Decisão da Cowork no F1.

### 2.3 — Pills de seção com scroll-spy 🔴 (semântico)
As "filter pills" da tela **não são** filtros removíveis — são **âncoras de navegação de
seção** com scroll-spy (`IntersectionObserver` marca a ativa). O `.filter-chip` (B6) do DS
é `label/value/remove` (filtro tipo Linear), semântica diferente. O mais próximo é
`.tabs` (B3) ou `.moduletopnav-tab`. **Decisão necessária:** qual padrão o DS adota pra
"section anchor nav em form longo"? (candidato a PT novo: *PT — Form longo com section-nav*).

### 2.4 — Disclosure "Mais opções" 🟡
`<details>` nativo com persist localStorage. DS não lista disclosure/accordion.
Aceitável manter `<details>` nativo estilizado por tokens; opcional formalizar `.disclosure`.

### 2.5 — Input numérico pt-BR 🟡
`NumericInputPtBR` (vírgula decimal, máscara R$). `.input` do DS é genérico.
Aceitável como variante de módulo; opcional `.input.num-ptbr` no DS.

---

## 3. Ponte de tecnologia (o trabalho real do reskin)

A tela consome o tema **shadcn/Tailwind** (`bg-background`, `border-border`, `Button`,
`Card`…). O DS consome **CSS custom props** (`--accent`, `--surface`, `--s-N`, `--radius`).
Convergir tem dois caminhos:

| Caminho | O que é | Custo | Risco |
|---|---|---|---|
| **A — Token bridge (recomendado)** | Mapear o tema Tailwind/shadcn pros tokens DS no `:root` (ex: `--background`→`--bg`, `--primary`→`--primary-page`, `--radius`→`--radius`). Componentes shadcn passam a *herdar* o DS. | Baixo, **app-wide** (1×) | Baixo — não mexe em markup, testes seguem verdes |
| **B — Substituir por classes DS** | Trocar `<Button>` por `.btn`, etc., tela a tela. | Alto, por tela | Alto — quebra os 39+ testes estruturais + `CockpitPatternConformanceTest` |

→ **Caminho A primeiro** (uma vez, global) resolve ~80% da aparência sem tocar markup.
Isso reforça que a decisão é **global, não Sells-específica** (ver §4.2).

---

## 4. As duas decisões upstream (bloqueiam o reskin)

### 4.1 — F1 Cowork do Sells inexistente 🔴
`prototipo-ui/prototipos/` tem `clientes` mas **não tem `vendas`/`sells`**. O
`mwart-comparative` F1.5 e o contrato exigem referência visual aprovada antes de tocar a
tela. **Caminho:** pedir o F1 à Cowork no formato do contrato (§"Como Wagner deve pedir"):
```
TELA: Vendas (criar)
PADRÃO BASE: PT — Form longo com section-nav (novo? ver §2.3)
COMPONENTES: pageheader, moduletopnav, combobox, datepicker, badge, field, savebar, empty-state
NOVOS: stat-card (§2.1), payment-split-row (§2.2) — adicionar no DS primeiro
ALVO DE NOTA: ≥ 9.0
```
Esta auditoria **é o anexo técnico** desse pedido.

### 4.2 — Sem ADR posicionando DS v3 vs canon existente 🔴
A tela é governada por Cockpit Pattern V2 ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)) e Constituição UI v2
([ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)). O DS v3 entrou (PR #1893) **sem ADR**.
O próprio `CODE_DESIGN_CONTRACT.md` exige: *"Cada mudança no DS: 3. ADR registrada em
memory/decisions/"*. **Pergunta a resolver:** DS v3 é a nova camada Fundações/Shell que
**supersede** os tokens V2, ou **coexiste**? Sem isso, o token bridge (§3-A) não tem mandato.
Decisão de app inteiro — destrava Clientes, OS, Vendas, Compras, Financeiro de uma vez.

---

## 5. Achado independente do DS (corrigível já)

`Create.tsx` tem ~9 ocorrências de cor crua `text-blue-*`/`bg-blue-*` (`text-blue-700/300`,
`bg-blue-500/50/950`, `border-blue-500`). Viola o charter (*"❌ Cor crua bg-(gray|red|…)-N"*)
e o DS (*"❌ Inventar cor fora dos tokens"*). **Independe** do reskin DS v3 — pode virar PR
pequeno isolado trocando por tom semântico (`info`/`accent`) ou token.

---

## 6. Recomendação de sequenciamento

1. **ADR de reconciliação** DS v3 ↔ UI v2 / Cockpit V2 (§4.2) — destrava todo o resto.
2. **Token bridge global** (§3-A) — 1 PR app-wide, baixo risco, ~80% do ganho visual.
3. **Piloto Clientes** (F1 já existe) valida o loop ponta-a-ponta.
4. **Cowork desenha F1 do Sells** (§4.1) com os 2 componentes novos (§2.1, §2.2).
5. **Reskin Sells/Create** no loop MWART completo, testes verdes.
6. (paralelo, a qualquer momento) **fix cores azuis** (§5).

---

_Gerado por Claude Code · skill `mwart-comparative` F1.5 (modo conformance, read-only) ·
2026-05-28. Não substitui o `<tela>-visual-comparison.md` aprovado — é insumo pra ele._
