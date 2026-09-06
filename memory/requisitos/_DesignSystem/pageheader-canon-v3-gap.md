---
tela: Foundation / PageHeader
prototipo: TODO
prototipo_nota: "2026-09-06 [C]: o campo era `prototipo-ui/prototipos/pageheader-canon-v3/ (index.html · 3-familias.html · b-v2-roxo-kpis.html · clientes-filtros-amostra.html · SPEC.md · README.md)` — a pasta prototipo-ui/prototipos/ foi expurgada em 2026-06-23 (commit 9da73296d3, consolidação SSOT em prototipo-ui/cowork/; os 7 arquivos desta pasta — os 6 listados + diagram.svg — saíram nele, junto com o visual-source.html da sidebar). Não há sucessor no espelho: fonte viva é o DS em git (prototipo-ui/design-system). Âncora do lado protótipo fica TODO por desenho; o lado vivo é ancorado por grep real."
tela_viva: resources/js/Components/PageHeader/PageHeader.tsx + PageHeaderPrimary.tsx + index.ts (consumo de referência: resources/js/Pages/Cliente/Index.tsx)
paridade_atual: ~85% (código vivo já está ADIANTE do protótipo index.html; protótipo é internamente inconsistente)
gerado_em: 2026-06-23
gerado_por: agente de mapeamento read-only (Fase 1 skill aplicar-prototipo)
governanca:
  - FUNDACAO — PageHeader é DS compartilhado por TODAS as ~80 Index/Show → SERIALIZAÇÃO obrigatória (não paraleliza; PR de fundação sequencial)
  - ADR 0182 (PageHeaderTabs pattern canon · aceito) — header obrigatório com sub-nav
  - ADR 0189 (PageHeader v3.1 · PROPOSTO, não aceito) — 3 blocos fechados + KPI strip + ⋮ overflow + roxo cadastro
  - ADR 0190 (primary roxo universal 295 · SUPERSEDED por ADR 0235) — protótipo referencia ADR já rebaixada
  - ADR 0235 (DS v4 accent roxo universal · ACEITO 2026-05-29) — regime VIGENTE; Claude Design é owner da UI
  - ADR 0180 do enunciado = NÃO é PageHeader (é drift de número ADR). PageHeader v1/3-zonas mora em 0182/0189
status: DRAFT — requer decisão Wagner/Claude Design antes de qualquer aplicação
---

# GAP-SPEC — PageHeader Canon v3 (protótipo) × PageHeader vivo

> **Read-only.** Nada aplicado. Este doc compara a INTENÇÃO do bundle protótipo
> `pageheader-canon-v3/` com o componente `PageHeader` que já roda em produção,
> e cataloga os deltas com esforço/risco/governança por PARTE.

## 0. Achados estruturais (ler antes da tabela)

1. **O código vivo está ADIANTE do protótipo `index.html`.** O `PageHeader.tsx` é
   **v3.8** (flat warm, sem card, border-b warm `oklch(0.93 0.004 90)`, H1 22/bold,
   padding 24/24/14) + `PageHeaderPrimary.tsx` = **roxo universal 295** (ADR 0190/0235).
   O `index.html` do protótipo é uma versão ANTERIOR (Modern SaaS slate, card
   `border rounded-lg`, primary **hue-per-grupo**, font `IBM Plex Sans`). Aplicar o
   `index.html` literalmente seria **REGREDIR**. Risco L-tipo regressão.

2. **O protótipo é internamente INCONSISTENTE** (4 HTMLs, 3 tratamentos de primary):
   - `index.html` → primary `oklch(0.55 0.15 var(--btn-hue))` **hue-per-grupo** (contradiz ADR 0190/0235)
   - `3-familias.html` → primary azul-marinho `#1f3a5f` (família A/C) ou ciano 202 (família B)
   - `b-v2-roxo-kpis.html` → primary **roxo 295 universal** (= canon vigente) + KPI strip separado
   - `clientes-filtros-amostra.html` → primary hue 202 + 5 variantes do botão Filtros
   → A intenção REAL que sobreviveu nas decisões = `b-v2-roxo-kpis.html` (roxo 295 + 3 blocos).
   `index.html` e `3-familias.html` são explorações abandonadas.

3. **Governança defasada no protótipo.** SPEC.md/README referenciam ADR 0190
   (primary roxo) como "rascunho de ADR a promover". ADR 0190 foi **aceita
   (2026-05-25) e depois SUPERSEDED por ADR 0235 (2026-05-29)**, que tornou o roxo
   295 o accent universal do DS v4 e nomeou o **Claude Design como owner da UI**.
   Qualquer evolução de PageHeader hoje passa pelo Claude Design, não por aplicar
   protótipo cru.

4. **SPEC.md é uma carta de intenções de 30 seções; <50% foi implementado.** Muita
   coisa do SPEC (density modes via Tweaks, dark tokens completos, View Transitions,
   telemetry OTLP, i18n/RTL, keyboard shortcuts, Storybook, visual-regression Pest,
   schema.org) **não existe no código** e nunca foi aceita por ADR. Não é "gap a
   fechar cegamente" — é backlog hipotético sem sinal qualificado (ADR 0105).

---

## 1. Tabela de PARTES

| # | PARTE | Estado: mudou/falta | POR QUÊ | Esforço | Risco / Governança |
|---|---|---|---|---|---|
| P1 | **Geometria 3 zonas (L flex-1 min-w-0 / C subnav / R ml-auto, items-center)** | **PARIDADE ~100%.** Vivo já tem `flex items-center gap-4` + Zona L `flex-1 min-w-0` + Zona R `flex-shrink-0 ml-auto` (via gap). Gap ~0. | Canon já implementado e estável. | — (só doc) | Baixo. É o canon vigente. |
| P2 | **Container / fundação** | **DIVERGE — vivo está adiante.** Protótipo `index.html`: sticky + backdrop-blur + card. Vivo: **flat transparent** (sem bg, sem rounded, sem sticky), `border-b` warm `oklch(0.93 0.004 90)`, herda cream do parent. | Vivo seguiu LEARNINGS v3.8 (espelhar /sells Cowork). Sticky+blur do SPEC §3 NUNCA foi implementado. | M (se quiser sticky) | **Governança:** decidir se sticky+blur entra é decisão Claude Design (ADR 0235). Aplicar protótipo = regressão pro card. NÃO aplicar index.html. |
| P3 | **Título (H1) + suffix + subtitle** | **PARIDADE ~95%.** Vivo: H1 `text-[22px] font-bold tracking-tight leading-snug` + suffix `font-semibold text-muted-foreground` + subtitle `text-xs tabular-nums`. Protótipo `index.html` queria 22/600; vivo é 22/**bold** (700, peso Vendas, LEARNINGS v3.2). | Vivo evoluiu o peso depois do protótipo. Métricas semânticas (rose/amber/emerald) presentes em ambos. | P | Baixo. Pequeno delta de peso (600 vs 700) — vivo é a versão mais recente/decidida. |
| P4 | **SubNav / abas (Zona C)** | **DIVERGE em arquitetura.** Vivo: nav **inline na própria Page** (Cliente/Index hardcoda `<nav>` com tabs), passada via prop `subnav`. NÃO existe `<PageHeaderSubNav>` componentizado. Protótipo SPEC §5 quer overflow `Mais (N)`, scroll-snap, underline framer-motion spring. | Componente `<PageHeaderSubNav>` está listado em `index.ts` como "Wave 3 a fazer". Cada tela reescreve o nav → risco de drift. | **G** | Médio. Componentizar SubNav toca TODAS as telas com sub-nav → **serialização fundação**. Underline framer-motion/spring + overflow dropdown = features não-aceitas (backlog). |
| P5 | **Counter por tab** | **DIVERGE intencionalmente.** Protótipo `b-v2-roxo-kpis.html` mostra `os-tab-count` (badge na tab). Vivo: counter **REMOVIDO da tab** (Wagner 2026-05-25: duplicava KPI strip), mas `tab_counts` continua computado no backend (AP18). | Decisão consciente registrada no código (Cliente/Index L900). Protótipo b-v2 mostra a versão COM badge — não é mais o canon. | P | Baixo, mas **não re-adicionar** sem sinal. AP18 (counter via backend, nunca rows.filter) já respeitado. |
| P6 | **Primary roxo 295 universal (Zona R)** | **PARIDADE ~100% com a INTENÇÃO VIGENTE.** Vivo `PageHeaderPrimary` = `oklch(0.55 0.15 295)` bg + `0.45...295` border + branco + h-8 + ui-sans-serif forçado. Bate com `b-v2-roxo-kpis.html`. **CONTRADIZ `index.html`** (hue-per-grupo) e `3-familias.html` (navy). | Vivo implementa ADR 0190→0235 (roxo universal). Protótipo index.html é pré-0190. | — (só doc) | **Governança alta:** `index.html` viola ADR 0235 (AP20). Se alguém "aplicar o protótipo" pelo index.html, quebra o canon roxo. Vivo está correto. |
| P7 | **Ghosts / overflow ⋮ (Zona R)** | **PARIDADE ~90%.** Vivo: `⋮` = `Button variant="ghost" border-0` (AP17 respeitado) + DropdownMenu com seções (Dados/Configuração). Protótipo b-v2 mostra seções Filtros/Dados/Configuração. NÃO existe `<PageHeaderOverflow>` componentizado (Wave 3). | Vivo segue AP17 (ghost sem border). Seção "Filtros" do b-v2 não está no overflow vivo do Cliente (filtros viraram FilterDropdown na toolbar). | M | Médio. Componentizar overflow = fundação serializada. Diferença Filtros-no-overflow vs Filtros-na-toolbar é decisão de tela, não de fundação. |
| P8 | **KPIs no header / KPI strip (b-v2-roxo-kpis)** | **FALTA componente; existe ad-hoc.** Protótipo `b-v2` propõe **KPI strip = BLOCO 2 separado** (4 cards grid branco frio, gap 12px do header). Vivo: NÃO há `<KpiStripCanon>` (listado em index.ts como Wave 3). Cliente/Index pôs métricas **no subtítulo** do header (não strip). | SPEC/ADR 0189 previam 3 blocos (Header / KPI strip / Conteúdo). Implementação parou no BLOCO 1. KPI strip é gap real de componente. | **G** | Médio-alto. KPI strip canon afeta toda tela que quer KPIs → fundação. Mas é **ADR 0189 (proposto, não aceito)** → precisa decisão antes. |
| P9 | **Modo NAV vs modo FOCO** | **FALTA no protótipo v3; existe como conceito canon.** O bundle `pageheader-canon-v3/` cobre só **modo NAV** (Index com sub-nav). SPEC §0 menciona "Forms página inteira usam PageHeader simplificado sem SubNav" mas não detalha. Modo FOCO (Edit/Create sem sub-nav, pattern Notion/Linear) foi formalizado DEPOIS (skill `pageheader-canon` Fase 4-bis). | Protótipo é só metade da história. Vivo `PageHeader` já suporta modo FOCO trivialmente (subnav opcional). | P | Baixo. Vivo já cobre (subnav é opcional). Só falta documentar que o protótipo não é a fonte do modo FOCO. |
| P10 | **Filtros (clientes-filtros-amostra: 5 variantes A-E)** | **EXPLORAÇÃO abandonada.** O HTML compara 5 tratamentos do botão "Filtros avançados" (ghost/outline/soft/tinted/chip). Vivo Cliente/Index: filtros viraram **6 FilterDropdown na toolbar** (Wave G), não 1 botão no header. | Decisão divergiu do protótipo: filtros saíram do header pra toolbar dedicada. As 5 variantes não são canon de nada. | — | Baixo. É decisão de tela (Cliente), não fundação PageHeader. Ignorar pra fins de fundação. |
| P11 | **As 3 famílias (3-familias.html: C Cowork / A Warm / B Modern SaaS)** | **DECISÃO JÁ TOMADA — protótipo é histórico.** O HTML pede pra Wagner escolher família. Vivo convergiu pra **warm flat (família A/C navy-ish→cream)** no container + **roxo 295** no primary. A recomendação do HTML (família A warm) foi parcialmente seguida; primary virou roxo (ADR 0190/0235), não navy. | Pergunta do protótipo já respondida pelas ADRs 0189/0190/0235. | — (só doc) | Baixo. Não há gap; é decisão fechada. |
| P12 | **SPEC §7-§28 (density Tweaks, dark completo, View Transitions, telemetry, i18n/RTL, shortcuts, Storybook, Pest visual-regression, schema.org)** | **FALTA quase tudo; nunca aceito.** ~20 seções do SPEC são aspiracionais. Código vivo não tem: density switcher, dark tokens completos PageHeader, View Transitions, OTLP telemetry, i18n, keyboard shortcuts, Storybook stories, Pest browser baseline, schema.org breadcrumb. | SPEC.md foi escrito como "spec 10/10" mas é wishlist. Sem ADR aceita + sem sinal qualificado (ADR 0105). | **G+** (cada item) | **Governança:** NÃO implementar em lote. Cada item precisa de ADR/decisão Claude Design + sinal de cliente. Tratar como backlog, não gap. |

---

## 2. Ordem sugerida (SE Wagner/Claude Design decidirem evoluir)

> Tudo aqui é **fundação serializada** — 1 PR por vez, nunca paralelo, porque PageHeader
> é compartilhado por ~80 telas. Cada PR precisa gate visual + smoke + (idealmente)
> Pest browser baseline ANTES de mergear.

1. **PASSO 0 — só doc (custo ~0):** marcar no template `PageHeader-canon-v3-1.md` e no
   bundle protótipo que `index.html`/`3-familias.html` são EXPLORAÇÕES SUPERADAS e que
   `b-v2-roxo-kpis.html` + código vivo são o canon. Atualizar referência ADR 0190→0235.
   Evita que sessão futura "aplique o protótipo" pelo arquivo errado e regrida. **(P)**
2. **PASSO 1 — `<PageHeaderSubNav>` componentizado (P4):** extrair o `<nav>` hardcoded
   das Pages pra um componente canon com contrato `tabs[]`. Reduz drift. Decisão Claude
   Design sobre overflow `Mais (N)` (aceitar ou não a feature do SPEC §5). **(G · fundação)**
3. **PASSO 2 — `<KpiStripCanon>` (P8):** só se ADR 0189 for ACEITA (hoje é proposta).
   BLOCO 2 separado, 4 cards. Afeta toda tela com KPIs. **(G · fundação · gated por ADR)**
4. **PASSO 3 — `<PageHeaderOverflow>` (P7):** componentizar ⋮ com seções canônicas. **(M)**
5. **BACKLOG (não-ordenado, gated por sinal+ADR):** itens do SPEC §7-§28 (P12). Um a um,
   cada um com decisão Claude Design + sinal qualificado.

---

## 3. Veredito

**Paridade global ≈ 85%** entre a INTENÇÃO VIGENTE (roxo 295 + flat warm + 3 zonas,
representada por `b-v2-roxo-kpis.html` + ADRs 0189/0190/0235) e o código vivo. O
componente vivo **já é o canon** nas partes nucleares (P1, P3, P5, P6, P7, P9, P11) e
em vários pontos está **ADIANTE** do protótipo `index.html`.

**Já é canon vigente, NÃO propõe além (no núcleo).** Para as zonas L/C/R, primary roxo e
ghosts, o gap é **~0 = só documentação** (PASSO 0). O protótipo `index.html` e
`3-familias.html` são explorações que, se aplicadas literalmente, **REGRIDEM** o
componente vivo (voltam pro card + hue-per-grupo + IBM Plex) — violando AP16/AP20 e ADR 0235.

**Onde propõe ALÉM do canon (precisa ADR + decisão Claude Design [W]):**
- `<PageHeaderSubNav>` componentizado + overflow `Mais (N)` (P4)
- `<KpiStripCanon>` / 3 blocos (P8) — **gated por ADR 0189 que ainda é PROPOSTA**
- `<PageHeaderOverflow>` componentizado (P7)
- Todo o SPEC §7-§28 (P12) — backlog aspiracional sem sinal

**Flags de governança (Tier 0 / fundação):**
- 🔴 **SERIALIZAÇÃO:** qualquer mudança no componente = PR de fundação sequencial, gate visual + smoke. Nunca paralelizar.
- 🔴 **ADR defasada:** protótipo aponta ADR 0190; vigente é **ADR 0235** (DS v4, Claude Design owner). Atualizar antes de citar.
- 🟡 **ADR 0189 é PROPOSTA, não aceita** — KPI strip / 3 blocos não podem ser "aplicados" como canon ainda.
- 🟡 **Owner da UI = Claude Design** (ADR 0235 §3) — evolução de PageHeader passa por ele, não por aplicar HTML cru.
- 🟡 **Não aplicar `index.html`/`3-familias.html`** — são pré-0190, aplicá-los regride (AP16 IBM Plex, AP20 hue-per-grupo).

**Recomendação:** executar só o **PASSO 0 (doc)** sem decisão adicional. Tudo além
(P4/P7/P8 + SPEC §7-§28) requer decisão explícita de Wagner/Claude Design — não é
trabalho de "aplicar protótipo", é evolução de fundação governada.

---

## Tabela de partes (derivada 2026-09-06 — veredito da prosa × código medido)

> Escrita por [C] em 2026-09-06. Regra: cada Ação cita o veredito da prosa acima (§1 Risco/Governança · §2 Ordem · §3) **e** o estado do código medido hoje com `arquivo:linha`. "**Decidir.**" só onde a prosa registra gap aberto que exige decisão; "Nada — <veredito>" onde a prosa fecha (já é canon · não aplicar · backlog) ou onde o código fechou o gap DEPOIS da prosa (2026-06-23). Em conflito prosa × código, o código medido vence e a linha diz isso. Lado protótipo = `TODO` (expurgado — ver `prototipo_nota`). Consumida por `prototipo-ui/gerar-map.mjs` → `pageheader-canon-v3.map.json`.

| Parte | Estado no vivo (medido 2026-09-06) | Ação |
|---|---|---|
| Geometria 3 zonas | `resources/js/Components/PageHeader/PageHeader.tsx:107` `flex items-center gap-4 pt-6 px-6 pb-3.5 min-h-[60px]` · `:109` Zona L `flex-1 min-w-0` · `:158` Zona R `flex-shrink-0`. | Nada — paridade (§3 "já é canon"; P1 esforço `— (só doc)`). |
| Container / fundação | `PageHeader.tsx:102-106` flat: `border-b overflow-visible` + `borderBottomColor: var(--border)`; sem sticky, sem blur, sem card. | **Decidir.** Sticky+blur do SPEC §3 nunca foi implementado — P2 Governança: "decidir se sticky+blur entra é decisão Claude Design (ADR 0235)". NÃO aplicar `index.html` (regressão pro card · AP16/AP20). |
| Título + suffix + subtitle | `PageHeader.tsx:110-146` H1 `text-[22px] font-bold tracking-tight leading-snug` + suffix `font-semibold text-muted-foreground` (`:139`) + subtitle `text-xs tabular-nums` (`:143`). | Nada — paridade ~95% (P3: vivo é a versão decidida, 22/700). |
| SubNav / abas (Zona C) | A prosa (2026-06-23) media "NÃO existe componentizado". Hoje existe: `resources/js/Components/shared/PageHeaderTabs.tsx` (352 ln · ADR 0180 · ghosts ARIA tablist + overflow `⋯ Mais` quando > `maxVisible`, docblock `:16-26`) + `resources/js/Components/shared/SubNav.tsx` (217 ln) + slot `below` em `PageHeader.tsx:82` (2026-09-03); `resources/js/Pages/Cliente/Index.tsx:961` consome `<PageHeaderTabs>` abaixo do header. | Nada — fechado no código depois da prosa: o §3 "propõe além: `<PageHeaderSubNav>` + overflow `Mais (N)`" já existe (PageHeaderTabs). Sobra do SPEC §5 (scroll-snap, underline framer-motion spring) = "features não-aceitas (backlog)" (P4), sem sinal (ADR 0105). `index.ts:8` ainda lista Wave 3 — só doc. |
| Counter por tab | A prosa (2026-06-23) media "counter REMOVIDO da tab". Hoje: `Cliente/Index.tsx:807-811` `tab_counts` do backend (AP18) → `:827` `badge: tabCounts[t.key]` → renderizado por `resources/js/Components/shared/PageHeaderTabs.tsx:279-300` (badge de contagem na aba; comentário `Cliente/Index.tsx:956-959`: [W] 2026-07-14 "mesma posição do Clientes/protótipo em todas"). | Nada — decisão [W] 2026-07-14 POSTERIOR à prosa re-adicionou o badge (via backend, AP18 respeitado — nunca `rows.filter`); a P5 ("não re-adicionar sem sinal") foi superada por decisão datada. Não reabrir. |
| Primary roxo 295 universal (Zona R) | `resources/js/Components/PageHeader/PageHeaderPrimary.tsx:70-72` `oklch(0.55 0.15 295)` bg + `oklch(0.45 0.15 295)` border · `:63` `h-8 rounded-md`. | Nada — paridade com a intenção vigente (ADR 0190→0235; P6). `index.html`/`3-familias.html` violam AP20 — não aplicar. |
| Ghosts / overflow ⋮ (Zona R) | 0 componente `<PageHeaderOverflow>` no repo (`PageHeader/index.ts:9` ainda lista Wave 3). `PageHeaderTabs.tsx:87-109` tem só o `⋯ Mais` de ABAS (`PageHeaderOverflowItem`/`extraOverflowItems`), não o ⋮ de ações. `Cliente/Index.tsx:888-942` hand-rola o `DropdownMenu` ⋮ (Dados/Configuração). | **Decidir.** Componentizar ⋮ com seções canônicas — §2 PASSO 3 (M) e §3 "propõe além (precisa ADR + decisão Claude Design [W])". Filtros-no-overflow × toolbar é decisão de tela, não de fundação (P7). |
| KPIs no header / KPI strip | 0 `<KpiStripCanon>` (`index.ts:10`). Existem `resources/js/Components/shared/KpiGrid.tsx` (66 ln) + `KpiCard.tsx` (257 ln) genéricos de dashboard, e `KpiStripClickable` local do Cliente (`Cliente/Index.tsx:976`). | **Decidir.** BLOCO 2 canon — §2 PASSO 2 (G · fundação serializada). O gate da prosa ("só se ADR 0189 for ACEITA") caducou: `memory/decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md` está `status: aceito`. Resta a decisão Claude Design/[W] do §3. |
| Modo NAV vs modo FOCO | `PageHeader.tsx:66,155` `subnav` é opcional — modo FOCO = não passar. | Nada — já é canon (P9: só documentar que o protótipo não é a fonte do modo FOCO). |
| Filtros (clientes-filtros-amostra) | Decisão de tela: o Cliente moveu filtros pra toolbar (Wave G, P10). Não medido aqui — não é fundação. | Nada — exploração abandonada (P10 "ignorar pra fins de fundação"). |
| As 3 famílias | Container warm flat (`PageHeader.tsx:7-9`, `:102-106`) + primary roxo (`PageHeaderPrimary.tsx:70-72`). | Nada — decisão fechada pelas ADRs 0189/0190/0235 (P11). |
| SPEC §7-§28 | Sem density switcher, View Transitions, telemetry, i18n, Storybook no componente (`PageHeader.tsx` inteiro, 175 ln). | Nada — não é gap, é backlog (P12 Governança: "tratar como backlog, não gap"; cada item só com ADR + sinal ADR 0105). |
