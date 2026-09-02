# Pedido ao [CC] — resolver NA FONTE as inconsistências de DS do protótipo (Forja primeiro, depois todos)

> Decisão [W] 2026-09-02 (ADR 0388): o protótipo é o contrato de layout; a conformidade do DS vira
> **lista de inconsistências pós-aplicação**, gerada por máquina, com a receita por regra. Parte da
> lista só fecha **no Cowork**, porque o espelho é read-only (ADR 0374) e qualquer conserto feito
> no repo some no próximo `--export-from`. Este é o pedido dessa parte.

## A lista (gerada, não escrita à mão)

`memory/requisitos/Forja/INCONSISTENCIAS-replica.md` — regenerável com
`node scripts/governance/replica-inconsistencias.mjs --modulo Forja --prototipo prototipo-ui/cowork/forja-*.jsx prototipo-ui/cowork/forja-page.css`.

## O que pedimos que seja feito NO PROTÓTIPO (`forja-page.css` + `forja-*.jsx`), sem mudar 1 pixel de intenção

| regra | contagem hoje | o que fazer na fonte |
|---|---:|---|
| `FONTRAMP` | 291 `font-size: Npx` | snap ao ramp `--fs-1..9` (10.5 / 11.5 / 12.5 / 13.5 / 15 / 18 / 22 / 28 / 38). Onde o valor cair fora do degrau, escolher o degrau e **registrar no github.md** — é decisão de design, e é sua |
| `R1` cor crua no CSS | 326 `oklch()` literais | valor com token do DS → `var()`. Os 12 mais frequentes: `oklch(0.95 0.003 95)` ×13 (=`--sunken`?), `oklch(0.50 0.18 25)` ×9 / `0.58 0.21 25` / `0.55 0.18 25` (=`--neg` family), `0.94 0.06 150` / `0.275 0.060 150` (=`--pos-soft` light/dark), `0.84 0.150 68` / `0.46 0.15 68` (=`--warn`), `0.95 0.08 80` (=`--warn-soft`). O que NÃO tem token: pedir o token, não inventar |
| `R1` cor crua no JSX | 19 `oklch()` inline | cor dinâmica por hue (prio/fase/papel) → `style={{"--h": hue}}` + classe `.fj-x{ color: oklch(0.6 0.18 var(--h)) }`. O JSX fica sem literal e o render é idêntico |
| `HEX-CSS` | 6 `#fff` | `var(--accent-fg)` / `var(--surface)` conforme o papel |
| `IMPORTANT` | 2 | subir especificidade do seletor |
| `SINTAXE` | 1 | `forja-page.css:778` — `.fj-ho-flow{ background: oklch(0.275 0.050 295)); }` tem um `)` sobrando. O navegador tolera; o build do Vite (Tailwind v4) **derruba o build inteiro** com "Missing opening (". Consertar na fonte; no repo entrou com desvio de 1 byte declarado |
| `PALETA` | `--dev`, `--dev-soft`, `--dev-line` | promover a token do DS (`--origin-DEV*`) no DS vivo, como o próprio comentário do `forja-page.css` já prevê (FORJA-137). Token novo = decisão [W]; o pedido aqui é você propor o valor e o nome |

`R3` (glifos `✦ ⚠ ★ →`) e `R4` (header canon) ficam do lado do Code — não são pedido a você.

## O recibo esperado

Linha no `github.md` ("inconsistências Forja: N → M") + **bundle regenerado** (é o pedido de
2026-09-01 que continua em aberto: sem o pacote v2 novo, o DS do espelho fica em 24/08 e o
`Segmented` que a Lista|Quadro|Gantt usa não renderiza aqui).
