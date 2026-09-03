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

---

## Rodapé 2026-09-02 (tarde) — o que o Code fez direto na fonte, e o que devolve pra você

> Autorização [W] 2026-09-02, textual: **"apenas faça"** — opt-in de escrita do design-sync
> (ADR 0315). O Code escreveu no projeto Cowork ERP (`019dcfd3-…`) e desceu pro espelho por
> `get_file` → `--export-from` (ADR 0374). Nada foi transcrito: o conteúdo saiu do dado, por script.

**Lista: −2 linhas (`SINTAXE`, `IMPORTANT`) e `FONTRAMP` 291 → 192** (`replica-inconsistencias.mjs`,
mesmo comando do cabeçalho + o `.css`).

> ⚠️ **Esqueça o total absoluto — inclusive o 101 do cabeçalho deste pedido.** Ele é o denominador da
> Forja inteira e se moveu **quatro vezes em 24h**: 101 (aqui) → 107 (com o `.css` no comando) → 123
> (o [#6569](https://github.com/wagnerra23/oimpresso.com/pull/6569) passou a cobrir os avisos ESLint
> `ds/*`) → 126 (quatro PRs da Forja entraram na madrugada de 02→03/09). Citar o par antes→depois
> apodrece em horas. O que a correção causa é o delta acima, e ele não se mexe. Pro número do dia,
> **rode o comando** — é a porta viva.

| regra | antes | depois | quem fez |
|---|---:|---:|---|
| `SINTAXE` | 1 | **0** | Code (mecânico) |
| `IMPORTANT` | 2 | **0** | Code (mecânico) |
| `FONTRAMP` | 291 | **192** | Code fez os 99 que caíam em degrau |
| `HEX-CSS` | 6 | 6 | **devolvido — ver abaixo** |
| `R1` (css) | 326 | 326 | **devolvido — ver abaixo** |
| `PALETA` | 1 | 1 | token novo = decisão [W] |

### O que o Code fez (3 + 99 trocas, todas medidas no render)

- **SINTAXE** `forja-page.css:764`: o `)` sobrando **não era cosmético**. O CSSOM do browser mostra
  `[data-theme="dark"] .fj-ho-flow` com `background: "(vazio)"` — a declaração era **descartada**.
  É a única das nossas mudanças que move pixel: no dark o chip herdava `--accent-soft`
  (`0.32/0.06`) e agora vale o que você escreveu (`0.275/0.050`). No claro nada muda.
- **IMPORTANT** ×2: saíram com render **idêntico** nos dois temas, provado com controle negativo.
  O `.fj-int-tab` precisou subir especificidade pra `.fj-int-rota small.fj-int-tab` — sem isso o
  `.fj-int-rota small` (L682, 0‑1‑1) vencia e a cor caía de `--text-dim` pra `--text-mute`.
  O `.fj-anexo-hint` não precisou: já vencia `.fj-dr-desc` por ordem de origem.
- **FONTRAMP** 99/291: só os que caíam **exatamente** num degrau. `--fs-1..9` têm 18 definições,
  todas em `:root`, valores idênticos, zero override por media/tema — a troca é neutra por
  construção, e confirmada no render (9/9).

### O que volta pra você — e por que o Code parou

**FONTRAMP, os 192 restantes.** Caem fora de degrau; escolher o degrau **move pixel** (0,5 a 2,0px).
É a decisão que o próprio pedido te atribui. Histograma medido, pra você decidir de uma vez:

| valor | ocorrências | degrau mais perto | delta |
|---:|---:|---|---:|
| 11px | 67 | `--fs-1` (10.5) | −0,5 |
| 10px | 38 | `--fs-1` (10.5) | +0,5 |
| 12px | 37 | `--fs-2` (11.5) | −0,5 |
| 13px | 17 | `--fs-3` (12.5) | −0,5 |
| 9.5px | 13 | `--fs-1` (10.5) | +1,0 |
| 9px | 11 | `--fs-1` (10.5) | +1,5 |
| 14px | 4 | `--fs-4` (13.5) | −0,5 |
| 17px | 2 | `--fs-6` (18) | +1,0 |
| 16px · 30px · 8.5px | 1 cada | `--fs-5` · `--fs-8` · `--fs-1` | −1,0 · −2,0 · +2,0 |

> Os 67 de `11px` e os 38 de `10px` sozinhos são 105 dos 192. Se você decidir **só esses dois**
> (ambos a 0,5px do `--fs-1`), o `FONTRAMP` cai de 192 pra 87 numa tacada.

**HEX-CSS, os 6 `#fff`: NÃO trocamos, e o motivo é medido.** No shell, `--accent-fg` **não** vem do
`.cockpit` do DS — vem do `styles.css` (`:root` = `oklch(1 0 0)` · `[data-theme="dark"]` =
`oklch(0.14 0.02 295)`). Trocar `#fff` por `var(--accent-fg)` dá a **mesma cor no claro** e
**inverte no escuro** (branco → quase-preto). Medido em `.fj-ho-tab.on`. Os 6 se separam assim:

- **2 sobre `var(--accent)`** (`.fj-ho-tab.on` L360, `.fj-onda-encerrar:hover` L1000): aqui o
  `#fff` é **defeito de contraste real no dark** — `--accent` é `oklch(0.70 0.15 295)` (roxo
  claro) e texto branco em cima disso é ilegível. O token certo É o `--accent-fg`; só que aplicá-lo
  **muda o render**, então é decisão sua, não conserto mecânico nosso.
- **4 sobre `--ink` / `--neg`** (`.fj-ho-toast` L419, `.fj-bell-badge` L547, `.fj-tab-badge` L548,
  `.fj-phstep-dot` L159): fundo escuro nos dois temas, branco está **certo**. Falta um token de
  foreground pra essas famílias. Como você mesmo escreveu no pedido: **pedir o token, não inventar**.
  Fica o pedido: `--ink-fg` e `--neg-fg` (ou o nome que você preferir).

**R1 no CSS, os 326: o Code mediu e concluiu que NÃO é trabalho mecânico.** Triagem completa:

| | |
|---|---:|
| total `oklch()` literais | 326 |
| dinâmicos (`oklch(... var(--ph))`) ou com alpha (`/ .18`) | 88 |
| simples | 238 |
| ↳ dentro de regra `[data-theme="dark"]` | 74 |
| ↳ em regra neutra de tema | 164 |
| **com token que resolve no MESMO valor em todo tema onde a regra vale** | **5** |
| sem token render-neutro | 233 |

E os **5 batem por VALOR, não por SENTIDO**: `--kind-employee-soft` num `:hover` de alavanca,
`--vip-soft` num banner de proposta, `--color-sla-expired-soft` num gate vermelho. Amarrar a regra
a um token de outro domínio só porque o número coincide é pior que o literal — no dia em que o DS
mexer naquele token, a Forja muda sozinha e sem motivo. **Por isso trocamos zero.**

A causa raiz é estrutural e vale a pena você ver: **164 dos literais estão em regras neutras de
tema**, e quase todo token de cor do DS **varia** entre claro e escuro (medi: dos 258 tokens de cor,
os invariantes são só `--av-c*`, `--sb-*`, `--vd-ai*`, `--bubble-me*`, `--kpi-feature-fg`). Então
tokenizar um literal neutro **sempre** muda um dos dois temas. Não é "falta token" — é que a regra
precisa ganhar par claro/escuro antes de poder ser tokenizada. Isso é desenho, e é seu.

### O recibo que continua faltando (não é desta rodada)

`bundle regenerado` no `github.md`. Ver o rodapé de 2026-09-02 em
[`CODE_NOTES.prompt-cowork-payload-gerador-2026-08-22.md`](CODE_NOTES.prompt-cowork-payload-gerador-2026-08-22.md)
e o pedido de [2026-09-01](CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md):
o `sync/` do projeto ERP ainda é o lote de **24/08**, e o `Segmented` segue sem descer.
