---
id: resources-js-layouts-appshellv2-charter
page: n/a — Layout, é a moldura de TODAS as rotas (não tem rota própria)
component: resources/js/Layouts/AppShellV2.tsx
related_prototype: prototipo-ui/cowork/app.jsx
module: _Shell
status: draft
created: 2026-09-02
owner: wagner
related_adrs: [0094-constituicao-v2-7-camadas-8-principios, 0114-prototipo-ui-cowork-loop-formalizado]
related_ui_adrs: [0013-constituicao-ui-v2-camadas, 0023-sidebar-dark-fixo-preto-definitivo-supersede-0019, 0029-prototipo-soberano-sobre-adr-ui, 0030-sidebar-auto-rail-responsivo]
prototypes: [prototipo-ui/cowork/app.jsx]
---

# Charter — `AppShellV2` (camada Shell da Constituição UI v2)

> ⚠️ **Este charter NÃO é coberto por gate.** Medido em 2026-09-02: todo glob de
> charter/casos do projeto aponta pra `resources/js/Pages/**` — `casos-coverage-guard.mjs`
> lista telas por `raizesDePages()`, e os schemas de `scripts/memory-schemas/` idem.
> O shell mora em `Layouts/` e é invisível pra eles. A defesa deste contrato é o
> **teste**, não o gate: `tests/Browser/Shell/SidebarAutoRailTest.php`.

## Mission

Ser a moldura única do ERP (sidebar + topbar + painel Linked + área de conteúdo) —
uma vez pro app inteiro, herdando das Fundações e **nunca** contradizendo-as
([ADR UI-0013](../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)).

## Contrato visual — largura do sidebar

O sidebar tem dois modos: **`expanded` (260px)** e **`rail` (56px)**. Quem decide:

| Situação | Modo | Coluna |
|---|---|---|
| Sem escolha manual · viewport **≤ 1280px** | `rail` | `56px` |
| Sem escolha manual · viewport **> 1280px** | `expanded` | `260px` |
| **Com** escolha manual persistida (`oimpresso.sb.mode`) | o que o usuário escolheu | — |
| Mobile (≤768px), drawer aberto | render forçado `expanded` | fora do grid |

A escolha manual (alça na borda direita, atalho `⌘\`) **vence em qualquer largura** e
é a **única** coisa gravada em `oimpresso.sb.mode`. Sem escolha, o modo acompanha a
largura **ao vivo** (`matchMedia`), não só no mount.

Sidebar é **PRETA (dark-fixo)** nos dois temas — [UI-0023](../../../memory/requisitos/_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md), intocado aqui.

## Non-Goals

- ❌ Um terceiro modo `hidden` (o protótipo tem `⌘⇧\` → `hidden`; produção **não** porta isso agora)
- ❌ Persistir o modo **automático** — só a escolha manual é gravada (ver Anti-hooks)
- ❌ Mudar cor, tipografia ou densidade do sidebar (Fundações — só por ADR)
- ❌ Breakpoint por módulo ou por tela: o limiar é do shell, um só

## Anti-hooks

- 🚫 **Não** voltar a gravar `oimpresso.sb.mode` em `useEffect` a cada mudança de estado.
  Isso persiste também o valor **automático**, e aí a regra dispara **uma vez por navegador**
  e nunca mais solta. Não é hipótese: é o comportamento do protótipo, e foi medido no espelho
  em 2026-09-02 — uma chave `rail` sobrevivente de um run a 1279 mantinha o protótipo em rail
  a 1728, o que quase virou "o protótipo é rail a 1728" na comparação daquele dia.
- 🚫 **Não** decidir o modo só no mount. Sem o listener de `matchMedia`, plugar/desplugar
  monitor externo deixa o shell no modo errado até dar F5.
- 🚫 **Não** criar um segundo limiar de "estreito" no shell. A `cockpit.css` já usa
  `@media (max-width: 1280px)` pra colapsar o painel Linked; o auto-rail usa **o mesmo 1280**.
  Fonte única: `AUTO_RAIL_MAX_W` em `resources/js/Components/cockpit/shared.ts`.
- 🚫 **Não** afirmar o modo lendo `data-sidebar` num teste. Esse atributo é o que o React
  **manda**; o que o usuário vê é `getComputedStyle(.cockpit).gridTemplateColumns`, depois da
  cascata. Medir só o atributo é medir a própria intenção (`memory/proibicoes.md` §5 2026-07-16).

## UX Target

- Zero erro de console no shell
- A troca `expanded ↔ rail` é animada pelo `transition` do grid (não pisca)
