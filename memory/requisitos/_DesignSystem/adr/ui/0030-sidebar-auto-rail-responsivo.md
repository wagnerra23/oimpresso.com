---
id: requisitos-design-system-adr-ui-0030-sidebar-auto-rail-responsivo
---

# ADR UI-0030 · Sidebar vira rail automaticamente em viewport estreita (≤1280), com a escolha manual vencendo

- **Status**: accepted
- **Data**: 2026-09-02
- **Decisores**: Wagner (decisão — *"apenas faça"*, 2026-09-02), Claude Code (medição, execução, registro)
- **Categoria**: ui · shell · fundações
- **Camada**: **Shell** ([UI-0013](0013-constituicao-ui-v2-camadas.md)) — vale 1× pro app inteiro; nenhum Padrão de Tela ou módulo redefine.
- **Refs**: [UI-0029](0029-prototipo-soberano-sobre-adr-ui.md) (protótipo soberano na forma) · [UI-0023](0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md) (sidebar preta — **intocada** aqui) · [ADR 0114](../../../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- **Não supersede nada.** Acrescenta comportamento onde não havia: até aqui produção só tinha rail **manual**.

## Contexto

A 1280px — a largura do monitor do [W] — o header da Forja quebrava os controles pra uma
2ª linha. O sidebar de produção só virava rail se o usuário clicasse na alça: `AppShellV2.tsx`
lia `oimpresso.sb.mode` do `localStorage` e, na ausência de chave, devolvia `expanded` sempre,
em qualquer largura. O shell do protótipo Cowork não faz assim.

## Medição (2026-09-02) — a fonte, com o localStorage limpo a cada largura

Espelho `prototipo-ui/cowork/` servido em `http://localhost:5623`, sonda lendo
`getComputedStyle(.app).gridTemplateColumns` e `.os-page-h → getBoundingClientRect().left`,
esperando `__oiLazyDone` + 2 leituras iguais de `querySelectorAll('*').length`:

| `innerWidth` | localStorage | modo | `grid-template-columns` | header `left` |
|---|---|---|---|---|
| 1279 | limpo | **rail** | `56px 1223px` | 56 |
| **1280** | limpo | **expanded** | `260px 1020px` | **260** |
| 1440 | limpo | expanded | `260px 1180px` | — |
| 1728 | limpo | **expanded** | `260px 1468px` | **260** |
| 1920 | limpo | expanded | `260px 1660px` | 260 |
| 1728 | herdado `"rail"` | rail | `56px 1672px` | — |
| 1279 → 1728 **ao vivo** | — | **continua rail** | `56px 1672px` | — |

Regra do protótipo, lida na fonte (`prototipo-ui/cowork/app.jsx:624-634`): **JS no mount**,
`window.innerWidth < 1280 ? "rail" : "expanded"`, com o `localStorage` vencendo — e um
`useEffect` que grava **todo** valor, inclusive o automático. Não há `matchMedia` de resize
(o CSS diz isso explicitamente em `styles.css:5540`).

### Duas coisas que a medição corrigiu

1. **O breakpoint é `< 1280`, não `≤ 1280`.** A 1280 exatos o protótipo é **expanded**.
2. **O "protótipo é rail a 1728", registrado em `forja-cockpit-visual-comparison.md`
   §2026-09-02, era a chave `oimpresso.sidebar.mode="rail"` persistida naquele navegador** —
   não a regra automática. Com o localStorage limpo, 1728 dá `260px 1468px`: **exatamente o
   que produção já dava**. Logo **não havia divergência a 1728**, e a premissa de que as
   baselines de 1728 mudariam era falsa. É a classe LC-08 (medir a partir da fonte errada);
   o vetor aqui é o `useEffect` que persiste o valor automático e transforma um run antigo
   em "estado do protótipo".

## Decisão

Produção adota o **mecanismo** do shell do protótipo — modo derivado da largura quando não há
escolha do usuário — com dois deltas deliberados:

| # | Produção | Protótipo | Por quê |
|---|---|---|---|
| D1 | limiar **≤1280** (`AUTO_RAIL_MAX_W`) | `<1280` | 1280 é o monitor do [W] e o caso que motivou o pedido; e a `cockpit.css` **já** trata `@media (max-width:1280px)` como a banda estreita do shell (é onde o painel Linked colapsa). Dois limiares de "estreito" no mesmo shell seria drift. |
| D2 | segue a largura **ao vivo** (`matchMedia`) | decide só no mount | plugar/desplugar monitor externo não pode exigir F5. |
| D3 | persiste **só a escolha manual** | persiste todo valor | persistir o automático faz a regra disparar **uma vez por navegador** e nunca mais soltar — é literalmente o defeito que produziu a leitura errada de 1728 acima. |

D1 é divergência de **forma** e, por [UI-0029](0029-prototipo-soberano-sobre-adr-ui.md), o
protótipo é soberano nesse eixo: registrada aqui como **exceção consciente de 1px**, reversível
numa constante. Se [W] preferir paridade exata, `AUTO_RAIL_MAX_W = 1279` fecha.

A sidebar continua **PRETA** ([UI-0023](0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md)) — esta ADR não toca cor.

## Consequências

- Fonte única do limiar: `AUTO_RAIL_MAX_W` / `AUTO_RAIL_MQ` em `resources/js/Components/cockpit/shared.ts`.
- Contrato em `resources/js/Layouts/AppShellV2.charter.md` + 4 UCs em `AppShellV2.casos.md`,
  defendidos por `tests/Browser/Shell/SidebarAutoRailTest.php`. ⚠️ O trio mora em `Layouts/`,
  que **nenhum** glob de `casos-gate`/schema alcança (medido) — a defesa é o teste, não o gate.
- **Baselines visuais** (medidas, não estimadas — dimensões lidas do IHDR dos `.snap`):

  | Suite | bandas | auto-rail ≤1280 toca? |
  |---|---|---|
  | `PixelBaselineTest` (39) · `IsolatedStatesBaselineTest` (19) | 1728×1117 | **não** |
  | `Compras` / `Financeiro` / `SellsCreate` Flow | 1024 (11) · 1280 (11) · 1440 (11) | **sim — 22** (1024 e 1280) |

  A banda 1024 mudaria mesmo com `<1280`; o `≤` acrescenta as 11 de 1280. `visual-regression`
  é **advisory** desde 2026-08-26 (decisão [W]) — não bloqueia merge. O rebake é o
  `workflow_dispatch` do `visual-regression.yml` **sem** `screens` (o modo com escopo pula
  estados/flows); ele regenera também as 58 baselines de 1728 que esta mudança **não** afeta,
  então fica como decisão [W] e não foi disparado aqui.

## Alternativas descartadas

- **Media query pura em CSS** (`@media (max-width:1280px){ .cockpit{grid-template-columns:56px …} }`):
  o CSS não sabe se o usuário escolheu — sobrescreveria a preferência, e o próprio protótipo
  registra isso em `styles.css:5540` (*"não sobrescreve a escolha do usuário via CSS"*).
- **Copiar o protótipo literalmente** (mount-only + persistir tudo): reimportaria D2 e D3, e o
  D3 é a causa provada da leitura errada de 1728.
