# Jana — inconsistências pós-réplica (ADR 0388)

> **O que é.** A lista que a máquina gera DEPOIS de aplicar o protótipo: cada linha é uma regra de
> conformidade do DS que a cópia fiel viola, com o dono da regra e a contagem. Nada aqui bloqueia a
> aplicação — é o que o Code resolve depois, ou o que [W] aceita como decisão (`status: aceita`).
> **Gerado por máquina** — não edite contagem; mude só `status`/`nota` no JSON em
> `governance/replica-inconsistencias/jana.json` e regenere.
>
> Gerado em 2026-09-03 · comando: `node scripts/governance/replica-inconsistencias.mjs --modulo Jana --prototipo …` · **33 aberta(s)** de 33.
> `origem = aplicado` mede o que está no repo; `origem = prototipo` mede o que VAI entrar quando a onda copiar o JSX.

| status | regra | arquivo | contagem | exemplo | dono da regra | origem |
|---|---|---|---:|---|---|---|
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/Alertas.tsx` | 1 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `resources/js/Pages/Jana/Alertas.tsx` | 1 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `resources/js/Pages/Jana/Chat.tsx` | 1 | #3889 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/Chat.tsx` | 13 | → ⇧ ↑ ↓ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `resources/js/Pages/Jana/Chat.tsx` | 6 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/Index.tsx` | 12 | ⛔ ⚠ → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R4` | `resources/js/Pages/Jana/Index.tsx` | 1 | PageHeader=não · DataTable=não | UiLintCommand.php R4 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `resources/js/Pages/Jana/Index.tsx` | 8 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `resources/js/Pages/Jana/Memoria.tsx` | 1 | #5401 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/Memoria.tsx` | 3 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `resources/js/Pages/Jana/Memoria.tsx` | 3 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `resources/js/Pages/Jana/Plataforma.tsx` | 1 | #6421 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/Plataforma.tsx` | 1 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/Pro.tsx` | 12 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `resources/js/Pages/Jana/Pro.tsx` | 25 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/_components/AssistantUiChat.tsx` | 1 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `resources/js/Pages/Jana/_components/AssistantUiChat.tsx` | 10 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `FLEX-CRU` | `resources/js/Pages/Jana/_components/FabJana.tsx` | 1 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/_components/JanaAcaoModal.tsx` | 3 | ⚠ → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R1` | `resources/js/Pages/Jana/_components/JanaAreaHeader.tsx` | 1 | #1053 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/_components/JanaAreaHeader.tsx` | 15 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `resources/js/Pages/Jana/_components/JanaAreaHeader.tsx` | 2 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R1` | `resources/js/Pages/Jana/_components/JanaCockpit.tsx` | 1 | #6111 | UiLintCommand.php R1 · conformance-gate | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/_components/JanaCockpit.tsx` | 29 | ⚠ → ⛔ ✓ ✗ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `FLEX-CRU` | `resources/js/Pages/Jana/_components/JanaCockpit.tsx` | 32 |  | layout-primitives-guard.mjs | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/_components/JanaConfigDrawer.tsx` | 1 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/_components/JanaDrillDrawer.tsx` | 2 | ⚠ → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/_components/JanaMetaDrawer.tsx` | 2 | ⛔ ⚠ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/_components/JanaPlanoBadge.tsx` | 6 | → ⚠ | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R3` | `resources/js/Pages/Jana/_shared/JanaSubNav.tsx` | 7 | → | UiLintCommand.php R3 | aplicado |
| 🔴 aberta | `R3` | `prototipo-ui/cowork/jana-merge.jsx` | 10 | → • ⇧ | UiLintCommand.php R3 | prototipo |
| 🔴 aberta | `FLEX-CRU` | `prototipo-ui/cowork/jana-merge.jsx` | 4 |  | layout-primitives-guard.mjs | prototipo |
| 🔴 aberta | `ds/no-inline-tablist` | `resources/js/Pages/Jana/Chat.tsx` | 1 | não hand-role `role="tablist"` na tela. Barra de abas de topo (navega por URL) = <PageHeaderTabs> (@/Component | eslint.config.js no-restricted-syntax (ds/*) · ratchet config/eslint-baseline.json · placar scripts/ds-report.mjs | aplicado |

## Soluções por regra (a receita que [W] pediu)

| regra | onde se resolve | automatizável | como |
|---|---|---|---|
| `R1` | fonte + code | parcial | valor com token equivalente no DS → var() (ex.: oklch(0.55 0.15 295)=var(--accent); 0.58 0.21 25=var(--neg); 0.63 0.16 68=var(--warn)); cor DINÂMICA por hue (prio/fase/papel) → classe com custom property (`style={{"--h":hue}}` + `.fj-x{color:oklch(0.6 0.18 var(--h))}`), que tira o literal do JSX sem mudar 1 pixel. O que não tem token: pedir ao Cowork o token na fonte (FORJA-137), não inventar aqui. |
| `R3` | code | sim | codemod glifo→lucide com tamanho igual ao do texto: ✦ Sparkles · ⚠ AlertTriangle · ★/☆ Star · → ArrowRight · ↗ ArrowUpRight · ✓ Check · ✗ X · ⚿ KeyRound · ● Circle(fill). Um componente <Glifo> concentra o mapa; o texto ao redor não muda. |
| `R4` | missão + decisão | não | as telas /project-mgmt/* saem na onda de revogação (item some); nas telas réplica o header É o do protótipo — R4 exige PageHeader+DataTable canon que o protótipo não usa: [W] marca `aceita` OU a regra R4 passa a reconhecer o header do bundle (`.fj-page > header`) como canon. Não reescrever o header pra agradar R4. |
| `FONTRAMP` | fonte | sim (na fonte) | snap ao ramp --fs-1..9 (10.5/11.5/12.5/13.5/15/18/22/28/38) NO PROTÓTIPO, pelo [CC]; fazer aqui muda o pixel (11→11.5) e a sonda D4 acusa. Enquanto a fonte não snapa: `aceita` com nota, contagem fica visível. |
| `IMPORTANT` | fonte | sim (na fonte) | subir a especificidade do seletor em vez de !important; 2 ocorrências, pedir ao [CC]. |
| `HEX-CSS` | fonte | sim (na fonte) | #fff → var(--accent-fg) / var(--surface) conforme o papel; 6 ocorrências, pedir ao [CC]. |
| `FLEX-CRU` | missão | não precisa | as telas antigas usam Tailwind `flex`/`grid` cru; a réplica troca por classes do bundle (`.fj-row`, `.fj-toolbar`…) e o item some. Nas 8 telas /project-mgmt/* some pela revogação. NÃO refatorar pra Stack/Inline antes da onda — seria pagar 2×. |
| `SINTAXE` | fonte | sim (na fonte) | o navegador tolera `)` sobrando, o parser do Tailwind v4 no build do Vite derruba o build inteiro (medido 2026-09-02, forja-page.css:778). Consertar no protótipo; enquanto não desce, desvio de 1 byte DECLARADO no cabeçalho do bundle. |
| `ds/*` | code (a mensagem diz o alvo) | parcial | cada regra ds/* tem alvo canônico na PRÓPRIA mensagem do ESLint — a tabela "Receita ds/*" abaixo lista a mensagem literal de cada regra que apareceu nesta medição (derivada, não escrita à mão). Duas famílias: COMPONENT-SUBSTITUTE (`no-os-btn`→<Button>, `no-inline-tablist`→<PageHeaderTabs>/<SubNav>, `no-handrolled-status-pill`→<Badge>/<StatusBadge>, `no-handrolled-combobox`→<Command> em <Popover>) troca o hand-roll pelo componente do DS; EIXO DE VALOR (`no-raw-palette-color`, `no-inline-raw-color`, `no-arbitrary-color`, `no-rounded-xl`) é o MESMO eixo do R1 acima — se a cor veio copiada do protótipo, o conserto é na fonte (ADR 0374), não aqui. |
| `PALETA` | fonte (DS) | sim | promover --dev/--dev-soft/--dev-line a token do DS (`--origin-DEV*`) no SSOT `resources/css/tokens/semantic.tokens.json` + `npm run tokens:build`; o bundle passa a consumir var() e o ds-guard para de ver família própria. É token novo = decisão [W] (FORJA-137). |

## Receita `ds/*` — mensagem canônica do ESLint (traz o alvo)

> Derivada da medição: cada linha é o texto literal que o `eslint.config.js` emite. O eixo mede
> só `origem = aplicado` — o JSX do espelho (`--prototipo`) está FORA do escopo do dono
> (`Pages/**` das 2 raízes), então não é medido aqui e não vira 0 falso.

| regra | mensagem (= a receita) |
|---|---|
| `ds/no-inline-tablist` | ds/no-inline-tablist — não hand-role `role="tablist"` na tela. Barra de abas de topo (navega por URL) = <PageHeaderTabs> (@/Components/shared, via *SubNav do módulo); switch in-page controlado (value/onChange, sem URL) = <SubNav> (@/Components/shared). Ver REGISTRY_DS_COMPONENTES.md §"barra de abas de topo" / §"Sub-navegação contextual". |

## Como fechar um item

1. **Resolver** (o Code): tokeniza / troca glifo por lucide / adota PageHeader canon **sem mudar o layout** — a próxima medição não encontra o item e ele sai.
2. **Aceitar** (só [W]): a inconsistência é decisão de design, não dívida — `status: aceita` + `nota` no JSON. Fica visível, não alarma.
3. Nunca: apagar a linha à mão, ou relaxar a regra no dono pra o item sumir.
