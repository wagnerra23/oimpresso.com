---
title: "O resíduo dark da Oficina não era do Board — o shell renderiza light, e 95% da base está no caminho quebrado"
date: "2026-07-16"
topic: "Passe adversarial sobre o resíduo dark do Board: a hipótese do --atmo cai, a causa é o .cockpit renderizando com tokens light (híbrido). ADR 0340 aberta+mergeada e smoke de produção EXECUTADO: 117/123 usuários (95%) no cenário do bug."
type: session
authority: informativa
module: design-system
authors: [C]
prs: [4380]
us: []
related_adrs:
  - 0340-tema-colapso-oposto-auto-blade-dark-react-light
  - 0281-dark-mode-bridge-data-theme-tokens
  - 0114-prototipo-ui-cowork-loop-formalizado
outcomes:
  - "ADR 0340 (proposto) mergeada — PR #4380, 82 checks verdes"
  - "Smoke de produção EXECUTADO: fecha o §Verificação item 1 e 2 da 0340"
  - "D-2 confirmado em prod: 117/123 usuários (95%) no caminho do híbrido"
---

# O resíduo dark da Oficina não era do Board

## TL;DR

O resíduo das 2 colunas de espera do Quadro de OS (AA-large, não AA) **não é defeito do
Board** — o código dele está certo e os pares `dark:` funcionam. O teto é o `.cockpit`
renderizando com tokens **light** enquanto o Tailwind renderiza **dark**: um **híbrido**.
Consertar o shell leva as colunas de 4,06:1 e 3,80:1 a **~15:1 sem tocar uma linha de `.tsx`**.

A hipótese herdada (`--atmo` sem par dark) é **falsa**: o par existe
([`_generated-foundations-dark.css:9`](../../resources/css/tokens/_generated-foundations-dark.css)).
A leitura *"`bg-muted/40` é translúcido ⇒ vaza"* também: a translucidez é o **revelador**,
não a causa.

Registrado em [ADR 0340](../decisions/0340-tema-colapso-oposto-auto-blade-dark-react-light.md)
(`status: proposto` — Fundação/Shell exige mandato [W]). Este log fecha o **§Verificação**
dela, que listava o smoke como pendente.

## A cadeia (código + pixel, sem hipótese)

| # | Elo | Prova |
|---|---|---|
| 1 | `HandleInertiaRequests` roda **antes** do `VisregStateMiddleware` | `Kernel.php:55` × `:59` — mesmo grupo `web` |
| 2 | `share()` do Inertia é **eager** | `vendor/inertiajs/inertia-laravel/src/Middleware.php:116` (antes do `$next`) |
| 3 | `ui_theme` é escalar eager, não closure | `HandleInertiaRequests::share()` |
| 4 | override do VRT chega tarde | a prop congela **NULL** |
| 5 | React colapsa o ausente em **light** | `AppShellV2:242` `?? 'light'` |
| 6 | Blade colapsa o mesmo ausente em **dark** | anti-flash faz `classList.add('dark')` |

O comentário do `VisregStateMiddleware` — *"blade + share leem a mesma instancia"* — é
**falso**: mesma instância, **tempos diferentes**.

## O smoke — EXECUTADO (o que este log acrescenta à 0340)

### Metade 1 — população (prod, `SELECT` de contagem, sem PII)

| `ui_theme` | usuários |
|---|---|
| **NULL (modo `auto` → cenário do bug)** | **117** |
| dark explícito | 2 |
| light explícito | 4 |
| **TOTAL** | **123** |

**95% da base está no caminho quebrado.** A 0340 previa por leitura de código que "NULL é o
padrão de quem nunca abriu *Aparência*" — o banco confirma. Os 2 de dark explícito (o único
grupo que o "produção está OK" cobria) são a exceção estatística.

### Metade 2 — render (prod, computed style, sonda por injeção + restauração)

Técnica: a mesma "probe de injeção em produção" que a própria [ADR 0281](../decisions/0281-dark-mode-bridge-data-theme-tokens.md)
usou. Estado restaurado ao fim (`restaurado_ok: true`).

| Camada | Controle (`dark` explícito) | **Cenário `auto` (os 117)** |
|---|---|---|
| `.cockpit` `--bg` | `oklch(.26 .006 240)` **dark** ✓ | **`oklch(.985 .003 90)` LIGHT** ✗ |
| `--text` (Cowork) | claro ✓ | **escuro** (`oklch(.22 .01 80)`) |
| `--color-card` (Tailwind) | **dark** ✓ | **dark** |
| `--color-foreground` (Tailwind) | claro ✓ | claro |
| Pintado de fato | `oklch(0.26…)` escuro | **`oklch(0.985…)` claro** |

**Híbrido confirmado no build de CSS real de produção.** O controle (dark explícito) fecha a
causa: com `ui_theme='dark'` as duas camadas escurecem juntas — o defeito é **só** o colapso
do `auto`.

Agravante que só apareceu na medição: no `auto` os **dois** tokens de texto convivem —
`--text` fica **escuro** (tema light) e `--color-foreground` fica **claro** (tema dark), ambos
sobre fundo **claro**. Onde um texto Tailwind cair direto no fundo do shell é
claro-sobre-claro (~1,04:1, invisível); no Board o `bg-muted/40` amortece e vira o resíduo de
4,06:1.

## O que a medição do gate revelou (baseline canon `20e947c0`)

Cadeia de composição de 3 camadas, prevista × medida (**±2**):

| Camada | Previsto | Medido |
|---|---|---|
| + `bg-muted/40` do board | (160,162,162) | **(160,162,163)** — 48,4% da tela |
| + `dark:bg-amber-950/30` | (132,121,115) | **(132,121,113)** |
| + `dark:bg-violet-950/25` | (131,125,147) | **(132,124,148)** |

- cockpit `--bg` **dark** = **0 px** → tokens dark do cockpit nunca aplicados;
- `sells_index · dark`: o `--bg` light ocupa **62% da tela** coexistindo com `bg-card` dark.

**As 6 baselines dark do gate estão erradas por construção** — `visreg` verde no escuro não é
evidência de dark correto. Corolário operacional: regenerar baseline **só depois** do fix
(antes, trava o estado errado); ⛔ não afrouxar τ_alto nem usar `visreg-gray-approved` pra
mascarar.

## Erros meus — o passe adversarial pegou 3

1. **Medi a baseline errada.** O worktree estava **3 commits atrás** e o #4367 alterou
   exatamente aquele `.snap`. Refiz no canon: a conclusão sobreviveu e a prova melhorou.
   O guard `git-base-freshness-guard` avisou e eu quase ignorei.
2. Afirmei que (251,250,248) *"só pode ser o `--bg` light"* — **errado**: `--color-page-cream`
   light é idêntico. Salvou **medir coexistência** (`bg-card` dark + o claro no mesmo PNG),
   não retórica.
3. Um adversário voltou *"o visreg força dark → tudo casa"* — **refutado** pelos pixels + a
   ordem do `Kernel`. Ele acertou o veredito do bug de produção e errou a explicação do CI.

Bônus de higiene: o `related: 0013-constituicao-ui-v2-camadas` da ADR era **cross-link falso**
(a UI-0013 não vive em `decisions/`; lá o 0013 é outra ADR). O schema **passaria** — peguei
conferindo existência, não formato.

## Lições

**Strike 3 do [LC-06](../../.claude/skills/comparar-design-prod/SKILL.md).** A régua já dizia:
*"o strike 1 é olhar; o strike 2 é medir a coisa errada — e esse passa despercebido porque vem
com número"*. Este caso é o seguinte: **medir o pixel certo numa CENA que não existe em
produção**. Os números herdados (1,56:1 · 3,84:1 · rgb 159,162,160) estavam **certos**; errada
estava a cena — um rig cujo estado `dark` nenhum usuário de dark explícito vê.

Antídotos que funcionaram, os dois baratos:
- **controle que isola a variável** — a coluna opaca (`bg-card`, 13,12:1) provou que o culpado
  é o que está **por baixo**, não o Board;
- **conferir a baseline contra o canon** antes de acreditar no número.

**Translucidez não é defeito.** `bg-muted/40` sobre um `--bg` dark daria (30,34,37) e ~15:1.
Quem aceitar *"translúcido ⇒ compensar"* escurece o componente e **trava o bug do shell** —
e o componente fica escuro demais quando a causa for corrigida.

## Coordenação (sessões paralelas · ADR 0119)

`claude/oficina-kpi-dark-tons` está empilhada no #4367 e edita o **mesmo `boardTone.ts`**.
O fix de KPI dela é **legítimo e imune** (fundo `bg-card` **opaco** não vaza) e **deve seguir**.
Ela já deixara as colunas como *"task própria"*. O que este log corrige é o **enquadramento**
dela (*"translúcido ⇒ compensar"*), não o fix.

## Honestidade (ADR 0108)

- **Provado e executado:** a cadeia (leitura literal, incl. `vendor`), os pixels do canon, a
  população (`SELECT` em prod) e o render (computed style em prod, com controle e restauração).
- **Não medido:** a **magnitude visual por tela** no `auto`. Varia — no Board é 4,06:1; onde
  texto Tailwind cai direto no shell seria ~1,04:1. **Não enumerei onde é pior.**
- **Não medido:** quantos dos 117 rodam o SO em dark (é client-side; o banco não sabe). A
  máquina desta sessão está em dark (`prefers-color-scheme: dark` = true), então o gatilho não
  é hipotético — mas a fração real da base é desconhecida.
- **Sem screenshot:** a página autenticada de prod tinha nome de cliente à vista; PII em
  artefato é Tier 0. A prova por computed style é mais forte que print (LC-06) e não vaza dado.
- **Nada de código foi tocado.** Board intocado, `boardTone.ts` livre pra sessão paralela.
- **Teste local:** não rodei (proibição — CT 100 apenas). A sonda de prod é inspeção read-only
  de DOM, revertida na hora.
