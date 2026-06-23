---
tipo: gap-spec
tela: Shell / Sidebar (AppShellV2)
prototipo: prototipo-ui/prototipos/sidebar-v3-unificado/visual-source.html
tela_viva:
  - resources/js/Layouts/AppShellV2.tsx
  - resources/js/Components/cockpit/Sidebar.tsx
  - resources/js/Components/cockpit/shared.ts
  - resources/css/cockpit.css
paridade_atual: 62%
gerado_em: 2026-06-23
gerado_por: agente de mapeamento read-only (Fase 1 — aplicar-prototipo)
caracter: FUNDAÇÃO / ZONA DE SERIALIZAÇÃO (NÃO paraleliza com telas)
governanca:
  - "FUNDAÇÃO: sidebar = DS compartilhado, shell de TODO o app. PR de fundação SEQUENCIAL, isolado, ANTES de qualquer batch de telas (incidente #2495)."
  - "ADR UI-0009 (sidebar light, segue data-theme) — CANON aceito 2026-05-04"
  - "ADR UI-0014 (sidebar light MANTIDA, dark-sempre rejeitado) — CANON aceito 2026-05-24, Wagner explícito 'gosto como está hoje, não gostaria de mudar'"
  - "ADR 0180 (sidebar v3 — 5 grupos + ghosts header + Cmd+K + Pinned) — CANON aceito 2026-05-21; ESTE protótipo É o artefato canônico referenciado (linha 338 da ADR)"
  - "ADR 0190 (primary roxo universal hue 295) — afeta cor do botão primary do PageHeader, NÃO o protótipo (que usa hue por grupo)"
---

# GAP-SPEC — Sidebar v3 unificado (protótipo Cowork) × Shell vivo

> **Read-only.** Nenhum código foi tocado. Este documento mapeia diferenças e
> registra governança. Aplicação é Fase 5 da skill `aplicar-prototipo`, em
> **sessão limpa, PR de fundação sequencial** (ver aviso de serialização).

## ⚠️ Aviso de serialização (Tier 0 de processo)

Este NÃO é um gap de tela. A sidebar é a camada **Shell** da Constituição UI v2
(ADR UI-0013) — muda o chrome de TODO o ERP. **Não paraleliza** com aplicação de
protótipos de tela. Tem que virar **um PR de fundação isolado, sequencial,
mergeado e validado ANTES** de qualquer batch de telas que dependa do shell
(incidente #2495). Tocar sidebar + telas no mesmo lote = colisão garantida.

## ⚠️ Conflito de canon DETECTADO (precisa decisão Wagner antes de aplicar tema)

O protótipo é **light** (`--bg-sb: oklch(0.985 0.003 250)`), coerente com **ADR
UI-0009 + UI-0014** (sidebar light, segue tema). MAS o código vivo tem uma
**contradição interna**:

- `cockpit.css` linhas 6-19 (`.cockpit{}`): declara `--sb-*` **light** "espelha
  protótipo Claude Design" — bate com o canon.
- `cockpit.css` linhas 286-309 (`.cockpit .sb`): **sobrescreve `--sb-*` pra DARK
  FIXO** `oklch(0.18 0.006 240)`, com comentário literal _"Reverte UI-0009 …
  Wagner: 'Menu Fundo Black como no cokpit'"_ (2026-05-05).

Resultado: a sidebar viva **renderiza dark fixo**, contradizendo UI-0009/UI-0014
(que são CANON aceitos, e UI-0014 é POSTERIOR — 2026-05-24 — ao override dark de
2026-05-05). Há `not_adopted`/drift na própria base.

→ **Aplicar o tema light do protótipo é tecnicamente "voltar pro canon", mas
exige Wagner desempatar explicitamente** (matriz governance UI-0013: Wagner é
único aprovador). NÃO aplicar tema sem essa decisão. `_pendente_` decisão.

---

## Tabela de gaps por PARTE

| # | PARTE | Protótipo | Código vivo | Mudou/Falta | POR QUÊ | Esforço | Risco |
|---|---|---|---|---|---|---|---|
| 1 | **Tema (light/dark)** | Sidebar **light** creme (`--bg-sb` 0.985) | **Dark fixo** override em `.cockpit .sb` (linhas 286-309) reverte UI-0009 | DIVERGE | Override dark de 2026-05-05 contradiz UI-0009 + UI-0014 (canon posteriores) | M | **Tier 0 governança** — conflito de canon, precisa Wagner desempatar |
| 2 | **Largura sidebar** | `--sb-w: 248px` | grid `260px` (cockpit.css `grid-template-columns`) | DIVERGE leve | 12px de diferença, cosmético | P | Visual baixo |
| 3 | **Logo/topo (CompanyPicker)** | avatar gradient + nome + chevron `▾` | `CompanyPicker` (avatar gradient + nome + ChevronDown) | PARIDADE ~alta | Já implementado, igual conceito | — | Nenhum |
| 4 | **Busca / Cmd+K na sidebar** | Caixa "Buscar tudo… ⌘K" **dentro** da `sb-top` | Cmd+K existe global (`CommandPalette`, atalho no AppShellV2) mas **NÃO há caixa de busca na sidebar** | FALTA (entry visual na sidebar) | Discoverability: protótipo coloca trigger visível no topo; vivo só tem atalho de teclado | P-M | Visual + a11y |
| 5 | **Seção FIXADOS / Pinned** | Grupo `★ Fixados` no topo do scroll (Financeiro·Receber, Vendas, Compras) com estrela | **NÃO existe** seção pinned na sidebar (favs vivos são por-página em kb/Financeiro/Atendimento, não no shell) | **FALTA** | ADR 0180 §4 prevê Pinned/Favoritos LocalStorage `oimpresso.cockpit.b<bizId>.pinned[]` (Fase 7, não entregue) | G | Visual + Tier 0 (LocalStorage scopado por business_id) |
| 6 | **Grupos de navegação** | 5 grupos + 3 shortcuts topo (IA/Atendimento/Equipe). Headers uppercase | `SIDEBAR_GROUPS` = **7 keys** (CADASTRO/COMERCIAL/FINANÇAS/FISCAL/PRODUÇÃO/ESTOQUE/RH/SISTEMA = na verdade 8) + `SidebarShortcuts` (IA/Forja/Atendimento) | DIVERGE (taxonomia) | Vivo evoluiu p/ 7-8 grupos (Wagner 2026-05-22 split CADASTRO/COMERCIAL/FISCAL); protótipo congelou nos 5 originais da ADR 0180 §2 | M | **Tier 0 governança** — protótipo está DESATUALIZADO vs direção Wagner 2026-05-22; NÃO regredir 8→5 |
| 7 | **Labels dos grupos** | TOPO/VENDER/OPERAR/FINANÇAS/PESSOAS/SISTEMA | CADASTRO/COMERCIAL/FINANÇAS/FISCAL/PRODUÇÃO/ESTOQUE/RH/SISTEMA | DIVERGE | idem #6 — vivo é mais recente | — | governança (vivo vence) |
| 8 | **Atalhos kbd (`G I`, `G V`…)** | Hint `G X` à direita de cada item, aparece on-hover/active | **NÃO renderizado** (atalho `G X X` é Fase 8 da ADR 0180, não entregue) | FALTA | ADR 0180 §4 + Fase 8 — overlay de sequência de teclas | M | Visual + a11y |
| 9 | **Ícones dos itens** | glyphs unicode (`✦ ☎ $ ⚙ ₿`…) | **Lucide React** (`Bot`, `MessageCircle`, `Wallet`, `Factory`…) via `MENU_ICON_MAP`/`GROUP_ICON_MAP` | DIVERGE (vivo melhor) | Vivo usa Lucide (canon, tree-shaken); protótipo usa glyph só pra mock standalone | — | Nenhum — **NÃO trocar Lucide por glyph** (regressão) |
| 10 | **Item ativo / hover** | `.active` bg `oklch(0.94 0.02 var(--gh))` + texto colorido por hue; hover bg neutro | `.sb-item.active` com `::before` rail + hue por grupo via `--gh` (SIDEBAR_GROUP_HUE); hover `--sb-hover` | PARIDADE conceitual | Ambos usam hue por grupo. Diferença é só light(proto) vs dark(vivo) — ligado ao gap #1 | P | depende de #1 |
| 11 | **Hue por grupo** | `--gh` por `data-group` (ia 220, vender 60, financas 145…) | `SIDEBAR_GROUP_HUE` (comercial 55, financas 145, fiscal 175…) — escala canon Wagner 2026-05-22, ≥25° entre grupos | DIVERGE (vivo mais elaborado) | Vivo tem escala cromática espaçada p/ 8 grupos; protótipo tem 5 hues antigos | — | governança (vivo vence) |
| 12 | **Colapso (rail/expanded)** | **Não tem** (protótipo é só expanded) | `sb--rail` 56px + `SidebarMenuRail` + alça `sb-collapse-handle` + atalho `⌘\` | **VIVO TEM A MAIS** | Wagner 2026-05-16 — feature além do protótipo | — | Nenhum — **preservar rail** (protótipo não cobre, não remover) |
| 13 | **Mobile / off-canvas** | **Não tem** | drawer `≤768px` + hambúrguer + backdrop (Wagner 2026-06-17) | **VIVO TEM A MAIS** | handoff Cowork posterior | — | Nenhum — preservar |
| 14 | **Rodapé / usuário** | avatar `WR` + nome + `⚙` + `▾` | `SidebarFooter` (avatar + nomeCurto + cargo + ChevronUp) → `SidebarUserMenu` cascata completa (perfil/status/aparência/vibes/superadmin/logout) | PARIDADE+ (vivo bem mais rico) | Vivo entrega user menu completo; protótipo só mostra a casca | — | Nenhum — preservar |
| 15 | **Densidade / tema tweaks** | n/a | `TweaksPanel` (vibe/densidade/accentHue) flutuante | VIVO TEM A MAIS | — | — | Nenhum |
| 16 | **PageHeader + ghosts ARIA** | Header da tela com `[+ Novo]` primary + ghosts `role=tablist` + overflow "Mais" + chip Exportar | NÃO está no shell da sidebar — é responsabilidade da tela (PageHeader canon ADR 0180/0182/0189) | FORA DE ESCOPO (sidebar) | Protótipo desenha a tela inteira p/ contexto; o gap de ghosts/header é por-tela, não da fundação sidebar | — | n/a — separar do PR de fundação |
| 17 | **Topbar / breadcrumb** | `page-topbar` com crumbs + `⌘K ? 🔔` | `topbar` existe mas `hideTopbar=true` default (Wagner 2026-05-17 removeu topbar global) | DIVERGE (vivo decidiu remover) | Wagner removeu topbar global; protótipo ainda desenha | — | governança (vivo vence — NÃO ressuscitar topbar) |

---

## Ordem sugerida (se Wagner aprovar aplicação)

Tudo num **único PR de fundação sequencial** (ou poucos PRs atômicos ≤300 LOC,
um por sub-gap), **isolado de telas**:

1. **DECISÃO Wagner primeiro (bloqueante):** gap #1 (tema). Sem isso, não mexer
   em `--sb-*`. Opções: (a) aplicar light do protótipo = voltar pro canon
   UI-0009/0014 e remover override dark 286-309; (b) manter dark fixo e abrir
   ADR `supersedes UI-0014` formalizando; (c) sidebar segue `data-theme` de
   verdade (remover override, deixar tokens dos dois temas funcionarem).
2. **#4 Caixa de busca/Cmd+K na sidebar** (P-M) — entry visual no topo, plugando
   no `CommandPalette` já existente. Ganho de discoverability barato.
3. **#5 Seção FIXADOS/Pinned** (G) — Fase 7 da ADR 0180. LocalStorage scopado
   `b<bizId>` (Tier 0). Maior esforço; pode ser PR próprio.
4. **#8 Hints de atalho kbd** (M) — Fase 8 da ADR 0180. Cosmético + a11y.
5. **#2 Largura 260→248** (P) — trivial, só se Wagner quiser paridade exata.

**NÃO fazer (seriam regressões):** #6/#7/#11/#17 — o código vivo está MAIS
recente que o protótipo (direção Wagner 2026-05-22 / 2026-05-17). #9/#12/#13/#14
— vivo tem features além do protótipo; preservar.

---

## Veredito

**Paridade ~62%.** O protótipo `sidebar-v3-unificado` foi o **artefato de origem
da ADR 0180** (2026-05-21) e o código vivo **já implementou o grosso** dele e
**evoluiu além** em várias frentes (rail, mobile, user menu, escala de 8 grupos,
Lucide). Em vários eixos o **código vivo é mais novo que o protótipo** — aplicar
o protótipo cegamente seria REGREDIR (grupos 8→5, Lucide→glyph, ressuscitar
topbar). 

Os gaps reais e legítimos são **3, todos internos à sidebar**:
1. **Tema** (#1) — bloqueado por conflito de canon, precisa Wagner desempatar.
2. **Caixa de busca/Cmd+K visível na sidebar** (#4) — falta genuína, barata.
3. **Seção FIXADOS/Pinned** (#5) — Fase 7 da própria ADR 0180, nunca entregue.
   (+ #8 hints de atalho, menor.)

**Recomendação:** NÃO tratar como "aplicar protótipo". Tratar como **3 itens de
backlog de fundação**, cada um PR sequencial isolado, com gap #1 **bloqueado em
decisão Wagner** (é mexer em canon UI-0009/0014). Caráter de serialização
mandatório — nada disso entra num lote de telas.
