# PageHeader Canon — Matriz de Diferenças Técnicas e Pontos Permitidos

> Referência canônica de **o que DEVE ser igual** e **o que PODE variar** entre telas que adotam o pattern do header `os-page-h` (ADR 0180/0182/**0189**/**0190**). Use esta matriz como checklist de revisão de PR e fonte da skill `pageheader-canon`.

**Origem:** Wagner 2026-05-21 review smoke prod das 12 telas Financeiro → pediu matriz + skill pra padronização escalável.

> ⚠️ **RECONCILIADA 2026-05-25 — 2 supersedes parciais:**
> - [ADR 0189](../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) — canon v3.1 (3 blocos fechados separados, modern saas, font system, density compact 32px, tabs abreviadas)
> - [ADR 0190](../../decisions/0190-primary-button-roxo-universal-295.md) — primary INTERNO universal roxo 295 (não mais hue per grupo). Hue per grupo APENAS pra agrupamento visual sidebar.
>
> Mudanças nas dimensões fixas (vs versão original):
> - **F1** layout flat 3 zonas → **3 BLOCOS fechados separados** (header card + KPI strip + tabela) gap 12px (ADR 0189)
> - **F7** hue per grupo reescrito pra refletir `cockpit/shared.ts SIDEBAR_GROUP_HUE` real (11 hues atuais)
> - **F8** primary cor era "hue do grupo" → **SEMPRE roxo 295 universal** (ADR 0190)
> - **F13** font-family NOVO — sempre forçar inline `ui-sans-serif` (AP16 LEARNINGS)
> - **F14** overflow `⋮` NOVO — sempre ghost puro `border-0` (AP17)
> - **F15** sidebar single-link NOVO — sub-views vão pra tabs do header, NÃO popup do sidebar (AP19)

---

## Dimensões fixas (IDÊNTICAS em todas as telas — drift = bug)

| # | Dimensão | Valor canon | Source of truth |
|---|---|---|---|
| F1 | Estrutura **3 BLOCOS fechados separados** (ADR 0189 v3.1) | **BLOCO 1** Header card (`bg-background border rounded-lg` + Zona L/C/R inline) · **BLOCO 2** KPI strip card · **BLOCO 3** Lista card · gap 12px entre eles via `space-y-3` | [ADR 0189](../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) |
| F2 | Componente ghost tabs inline | `<nav aria-label="Sub-navegação">` com `<a aria-current="page">` + counter pill via `<span>` | inline no Index.tsx (ainda sem wrapper React shared) |
| F3 | ARIA real (nav navegação) | `<nav aria-label="...">` + `aria-current="page"` na tab ativa — NÃO `role="tablist"` (esse exige tabpanel) | Index.tsx |
| F4 | Keyboard nav | ArrowLeft/Right/Home/End wrap-around (Wave 2 — pendente) | futuro `<PageHeader>` componente |
| F5 | Overflow `⋮` (Zona R) | Radix DropdownMenu — 3 seções canon: FILTROS · DADOS · CONFIGURAÇÃO | ADR 0189 §4.6 |
| F6 | Ghost ativo **sempre visível** | Reordenar SLOT_TABS pra tipo mais usado no topo após "Todos" | ADR 0189 §4.3 |
| F7 | Hue OKLCH per-grupo (**APENAS sidebar — ADR 0190**) | Espelha `cockpit/shared.ts SIDEBAR_GROUP_HUE` real (11 hues): `cadastro=202 · vender/comercial=55 · producao=8 · fiscal=175 · financas=145 · pessoas=88 · estoque=315 · sistema=245 · ia=215 · atendimento=30 · equipe=275` | `cockpit/shared.ts` (source of truth) |
| F8 | **Primary cor UNIVERSAL** (ADR 0190) | **`bg: oklch(0.55 0.15 295)` · `border: oklch(0.45 0.15 295)` · `color: oklch(0.99 0 0)`** — roxo médio universal, independente do grupo do módulo | [ADR 0190](../../decisions/0190-primary-button-roxo-universal-295.md) |
| F9 | Labels CURTOS abreviados | Nomes ≥9 chars abreviar com ponto (`Fornec.`, `Repr.`) OU sinônimo completo curto (`Equipe` em vez de `Funcionários`) + `title="{nome completo}"` sempre | ADR 0189 §4.3 |
| F10 | Tipografia título h1 | `text-base font-semibold tracking-tight` (16px / 600 / -0.011em) density compact ADR 0189 — substitui `text-xl md:text-2xl` legacy | [ADR 0189](../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) |
| F11 | Multi-tenant Tier 0 | `shell.menu` filtra por `business_id`; SubNav retorna null se módulo desinstalado | [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) |
| F12 | Botões duplicados-com-ghost | **proibido** — botão que navega pra outra tela que já é ghost = remover | ADR 0182 |
| **F13** | **Font-family forçada inline (AP16)** | SEMPRE `style={{ fontFamily: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif' }}` no `<header>` canon. Nunca herdar IBM Plex de AppShellV2 | LEARNINGS sessão 2026-05-25 AP16 |
| **F14** | **Overflow `⋮` ghost puro (AP17)** | SEMPRE `variant="ghost"` + `className="border-0"` (caso shadcn ghost ainda aplicar border). NUNCA `variant="outline"` | LEARNINGS AP17 |
| **F15** | **Sidebar single-link (AP19)** | Item sidebar = `'href' => '/destino'` direto. Sub-views (filtros por tipo/status) vão pra tabs do PageHeader Zona C — NUNCA popup-menu dropdown do sidebar | LEARNINGS AP19 + [ADR 0180](../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) |
| **F16** | **Counter de tab via backend** (AP18) | `tab_counts` vem do Controller via `Inertia::defer`. NUNCA `rows.filter()` no frontend sobre dados server-side filtered | LEARNINGS AP18 |

---

## Dimensões variáveis (PODE diferir entre telas — caso-a-caso)

| # | Dimensão | Permitido | Padrão recomendado |
|---|---|---|---|
| V1 | Label do primary | Contextual à tela (verbo ação): "Novo título" / "Nova categoria" / "Importar OFX" / "Receber" / "Pagar" | Sempre verbo de ação canônico do domínio |
| V2 | Existência de primary | Opcional — telas read-only (Fluxo / Relatórios) podem omitir | Telas CRUD/workflow têm; visualização pura omite |
| V3 | Tipo do elemento primary | `<button>` (default) OU `<a href>` (caso de link externo) OU `<button type="submit">` (form upload) | `<button>` via `FinanceiroPrimaryButton` componente |
| V4 | `extraOverflowItems` | 0 a N ações features-específicas (Resumir mês / Apresentar / Exportar / OCR / etc) | Telas com ações features ricas (Unificado/DRE/Cobrança); outras 0 |
| V5 | Ícone do primary | `<Plus/>` (default componente) OU outro (`<Upload/>` em Conciliação) | Default `<Plus/>`; override pra workflows não-create |
| V6 | maxVisible (ghosts inline) | 4-6 (default 5) | 5 — cabe Larissa 1280px |
| V7 | Subtítulo do título | Texto curto contextualizando (período / business / total) | Padrão `os-page-h-l p` |
| V8 | Modal/Sheet abertas via `extraOverflowItems` | Tela define handlers próprios (setNovaOpen / setResumoOpen / etc) | onClick stateful no Index.tsx |
| V9 | Filtros/KPIs abaixo do header | Layout livre pós-header (cards / filtros / tabela) | Cada tela define conforme persona |

---

## Matriz por tela (Financeiro — estado atual)

| Tela | Label canon | Active visível? | Primary | Cor primary | extraOverflowItems |
|---|---|---|---|---|---|
| Unificado | Financeiro | ✅ | ✅ "Novo título" | hue 145 ✅ | 7 (Buscar/Resumir/Fechamento/Apresentar/Imprimir/Exportar/OCR) |
| Contas a Receber | Receber | ✅ | ✅ "Novo recebimento" | hue 145 ✅ | 0 |
| Contas a Pagar | Pagar | ✅ | ✅ "Novo pagamento" | hue 145 ✅ | 0 |
| Fluxo de Caixa | Fluxo | ✅ | ⚠️ omitido (read-only) | — | 0 |
| Cobrança | Cobrança | ✅ | ✅ "Nova cobrança" | hue 145 ✅ | 3 (Resumir/Gateways/Remessa) |
| Caixa do turno | Caixa | ⚠️ 500 prod | — | — | — |
| Conciliação | Conciliação | ✅ (auto-promove F6) | ✅ "Importar OFX" (submit form) | hue 145 ✅ | 0 |
| DRE | DRE | ✅ (auto-promove F6) | ✅ "Novo lançamento" | hue 145 ✅ | 5 (Buscar/Resumir/Fechamento/Apresentar/Exportar) |
| Relatórios | Relatórios | ✅ (auto-promove F6) | ⚠️ omitido (read-only) | — | 1 (Exportar CSV) |
| Contas Bancárias | Bancos | ✅ (auto-promove F6) | ✅ "Nova conta" (`<a href>`) | hue 145 ✅ | 1 (Gateways) |
| Plano de Contas | Plano | ✅ (auto-promove F6) | ✅ "Nova conta" | hue 145 ✅ | 0 |
| Categorias | Categorias | ✅ (auto-promove F6) | ✅ "Nova categoria" | hue 145 ✅ | 0 |
| Contador | Contador | ✅ (auto-promove F6) | (tela config, no header próprio) | — | — |

**Score consistência:** 11/13 telas com pattern 100% canon · 1 tela 500 prod (Caixa) · 1 tela config (Contador)

---

## Pontos de revisão de PR (checklist canon)

Pra cada PR que tocar tela com sub-navegação, reviewer DEVE verificar:

- [ ] **F1**: header tem 3 zonas (`os-page-h-l` + `os-page-h-r` com ghosts/overflow + primary direita)?
- [ ] **F2**: usa `<{Modulo}SubNav active="X"/>` (não inline JSX)?
- [ ] **F6**: ghost ativo aparece inline (não no overflow)?
- [ ] **F8**: primary cor harmônica com hue do grupo (não magenta canon UPOS default)?
- [ ] **F9**: labels dos ghosts são curtos (≤2 palavras)?
- [ ] **F12**: botões inline NÃO duplicam com ghosts (Conciliar/Plano de contas removidos quando ghost cobre)?
- [ ] **V1-V5**: variações documentadas (primary opcional pra read-only OK; tipo do elemento OK)?
- [ ] **F11**: SubNav retorna null se tenant sem módulo (Multi-tenant Tier 0)?

---

## Refs

- [ADR 0180](../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) — Sidebar v3 5 grupos canon
- [ADR 0182](../../decisions/0182-pageheadertabs-canon-pattern-telas.md) — PageHeaderTabs canon pattern 3 zonas
- [ADR 0110](../../decisions/0110-tipografia-canon-h1-subtitle.md) — Tipografia h1 + subtitle
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant
- [Skill `pageheader-canon`](../../../.claude/skills/pageheader-canon/SKILL.md) — aplicação automatizada
- PRs Fase 5 Financeiro: #1363/#1364/#1365/#1366/#1367/#1368/#1369
- Wagner reviews 2026-05-21 (revisão visual prod 12 telas)
