---
slug: protocolo-comparacao-runtime-prod-prototipo
title: "Protocolo de comparação RUNTIME — produção × protótipo (v2)"
type: protocolo
module: _DesignSystem
status: proposto
owner: wagner
date: "2026-07-06"
related_adrs: [0299, 0315, 0324, 0110, 0108]
related: [SCREEN-GRADE-METODO.md, framework-15-dimensoes.md]
---

# Protocolo de comparação RUNTIME — produção × protótipo (v2)

> **Por que existe (Wagner 2026-07-06):** a comparação v1 (extrator de DOM medindo *presença* de
> elementos) declarou "essencialmente igual" e **errou** — perdeu: footer/somatório, ícone de seta,
> filtro em 2 linhas, título, e o **full-reload** (anti-padrão). Wagner: *"acho que a comparação feita
> está errada. peço um método melhor… solicito um protocolo melhor."* Este é o método melhor.

## Os 2 erros de raiz do v1 (que este protocolo conserta)

1. **FONTE ERRADA.** O v1 comparou prod contra o **espelho do repo** (`prototipo-ui/cowork/<arq>`),
   que **pode estar velho** (o mesmo drift que o `cowork-mirror-freshness` existe pra pegar — [ADR 0324](../../decisions/proposals/0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma.md)).
   A fonte da comparação é o **Cowork VIVO** (projeto `019dcfd3`, via `DesignSync.get_file` ou render),
   **nunca** o espelho estático sem antes provar `SYNC`. **Regra dura:** antes de comparar, rodar
   `--compare` do mirror-freshness pra aquele arquivo; se `STALE`, re-exportar ANTES.
2. **DIMENSÃO RASA.** Mediu "elemento existe?" (presença). Não mediu **comportamento**, **layout**,
   **ícone**, **tipografia**, **footer**. Presença ≠ fidelidade (L-24 / "presença ≠ correção").

## As 7 dimensões (medir TODAS, nos DOIS lados, com a MESMA sonda)

| # | Dimensão | O que medir | Técnica (Chrome MCP) | O que o v1 perdeu |
|---|---|---|---|---|
| **D1** | **Comportamento / rede** ⭐ | toda interação de filtro/nav = **partial reload** (Inertia `only:[...]` + `preserveScroll`), **NUNCA** re-fetch de todas as props nem document-load | clicar 1 filtro → `read_network_requests` (initiatorType = xhr/fetch, não document) **E** checar no código `router.get(..., { only:[...] })` + controller `Inertia::defer`. Marker `window.__x` deve sobreviver (senão = document reload). | **o reload D-14** — o pior, não estava em dimensão nenhuma |
| **D2** | **Layout / quebra de linha** | nº de LINHAS visuais de cada zona (ex: barra de filtro = 2 linhas no proto vs 1 na prod), ordem das zonas, wrapping | agrupar `getBoundingClientRect().top` dos controles; contar grupos | filtro 1×2 linhas (eu **dispensei** dizendo "não rearranjei") |
| **D3** | **Ícones** | qual ícone e COMO (SVG lucide vs glyph de texto vs emoji) | `el.querySelector('svg')` vs texto; comparar o nome/《d》do path | seta ‹› (prod texto) vs ChevronLeft (proto SVG) |
| **D4** | **Tipografia** | `font-size`/`weight` dos elementos-chave (título da página, título da lista, valor do KPI, linha da tabela) | `getComputedStyle` de cada âncora tipográfica | "título grande" |
| **D5** | **Footer / somatórios** | conteúdo + FORMATO de cada total (labels, ordem, se há linha de saldo/net, formatação BRL) | `textContent` estruturado do footer, campo a campo | "somatório diferente" |
| **D6** | **Cor / token** | accent (hue), pills (radius/border/saturação), estados | `getComputedStyle` computado (v1 acertou aqui) | — (ok) |
| **D7** | **Densidade** | row-height, paddings, gaps | computed (v1 parcial) | — (parcial) |
| **D8** | **Alinhamento** 🆕 | `text-align`/`justify`/`align-items` do conteúdo do card (label, valor, %) **+ a TAG** (`<button>` herda `text-align:center` do navegador × `<div>` é left) | `getComputedStyle().textAlign` de cada KPI/card nos dois lados **e** comparar `tagName` (a tag explica a causa) | **center×left dos 5 KPI** — v2 (07/07) dispensou dizendo "estruturalmente igual"; o Wagner usou o alinhamento como canário |

⭐ **D1 é a mais importante e a mais barata de esquecer.** Um "print igual" pode esconder um
full-reload. Comportamento **antes** de pixel.
🆕 **D8 é o buraco que o strike 2 (07/07) expôs** — alinhamento não era dimensão nenhuma. `<button>` centraliza por default do UA; se o CSS não reseta, o KPI centraliza sem ninguém pedir.

## Mecanização — `prototipo-ui/design-diff.mjs` (a defesa do strike 2, LC-06)

> **Por que (Wagner 2026-07-07):** este protocolo em prosa não impediu o agente de comparar
> no olho — repetiu a classe de erro. Pela regra two-strikes (`LICOES_CODE` LC-06), strike 2
> vira **defesa mecânica**. `design-diff.mjs` é o `/design-diff` previsto na [ADR 0299](../../decisions/0299-figma-nao-e-fonte-de-design.md):
> o veredito das dimensões medíveis (D2/D4/D6/D8) vem de um **diff computado**, não do olho.

- **Split** (igual ao `cowork-mirror-freshness`): `--probe` imprime a sonda JS CANÔNICA → o agente
  injeta ela **igual nos dois lados** via Chrome MCP (`window.__DD_ROLES` mapeia os seletores do
  papel — as classes diferem, `.fin-stat` prod × `.os-stat` design, o PAPEL é o mesmo) → dois
  snapshots medidos → `--compare prod.json design.json --check` (exit 1 se DIVERGE(bug)).
- **`--selftest`** (no CI, design-memory-gate) trava um fixture hermético que **reproduz o
  incidente 07/07** (center×left + button×div + overflow + roxo escuro×roxinho + texto dark
  invisível) — se o comparador parar de pegar isso, o CI quebra.
- **Honestidade:** cobre só as dimensões de **computed-style puro** (D2/D4/D6/D8). D1 (rede),
  D3 (ícones), D5 (footer) seguem passos do agente abaixo — o tool **mecaniza** a parte medível,
  **não substitui** o protocolo. A comparação é dispatch do agente (browser + design vivo), não
  gate de PR (CI não renderiza — mesma limitação de plataforma do mirror-freshness).

## Procedimento (passo a passo)

0. **Provar frescor da fonte** — `node scripts/governance/cowork-mirror-freshness.mjs --compare <snap>`
   pro arquivo da tela; `STALE` ⇒ re-exportar do Cowork ANTES de comparar. (Sem isso, você compara
   contra design velho — o erro do v1.)
1. **Abrir os DOIS** — prod (Chrome logado) + o Cowork vivo (mesma tela, mesmo tema — o tema é
   escolha do Wagner; comparar no MESMO tema).
2. **Rodar a MESMA sonda D1–D8** nos dois → JSON estruturado por dimensão. Para D2/D4/D6/D8, use
   a sonda de `node prototipo-ui/design-diff.mjs --probe` (idêntica nos dois lados) → `--compare`
   dá o veredito MEDIDO. **Nunca** conclua "igual" por screenshot — o print não distingue center×left.
3. **D1 sempre**: clicar 1 filtro em prod, `read_network_requests`, classificar (partial vs full).
   Ler `aplicar()`/controller pra confirmar `only:` + `defer`.
4. **Diff por dimensão** → tabela `dimensão | prod | proto | veredito {IGUAL / DIVERGE (bug) /
   DIVERGE (decisão) / PROD-À-FRENTE}`.
5. **Screenshots de REGIÃO** (footer, barra de filtro, título) — não só a tela inteira; o v1
   olhou o todo e perdeu as partes.
6. **Registrar** no `<tela>-visual-comparison.md` (append; 1 tema = 1 doc).

## Regra 0 — PÓS-DEPLOY OBRIGATÓRIO, SEM O WAGNER PEDIR (Tier 0 deste protocolo)

> Origem (Wagner 2026-07-06, verbatim): *"preciso pedir para olhar se deu certo no chrome? e
> comparar com o prototipo? por que já sei que vai falar que o que tu fez está tudo aplicado,
> mas sempre deixa de analisar e comparar e eu tenho que falar de novo. isso deve ficar no método."*

Depois de **TODO merge que toca tela** (`resources/js/Pages/**` · `resources/css/**` · controller
que alimenta a tela), o agente **NÃO declara "aplicado/pronto/funcionando"** até completar, por
conta própria:

1. **Esperar o DEPLOY ficar `success`** (não o merge — o deploy).
2. **Abrir a tela em PRODUÇÃO no Chrome** (aba nova, sem injeção prévia) e **verificar item a
   item o que o PR prometeu** (computed style/DOM pra cada mudança — não só "a página abre").
3. **Comparar com o protótipo** (render do espelho SYNC-provado ou Cowork vivo, MESMO tema) —
   as dimensões D1–D7 tocadas pelo PR, com a MESMA sonda nos dois lados.
4. **D1 sempre que o PR tocou navegação/filtro**: 1 interação em prod + trace de rede
   (partial vs full-reload).
5. **Entregar PROVA no chat**: screenshot da prod + (quando visual) do protótipo, com veredito
   por item. "CI verde" e "merge feito" NÃO são prova de aplicado — prova é pixel/DOM/rede em prod.

Divergiu do prometido → é regressão AGORA (não "quase deu"). Pareia com R1 (smoke real) do
PROTOCOLO-WAGNER-SEMPRE e com o hook `post-merge-ui-smoke-required` — este protocolo ADICIONA o
que o hook não cobre: **a comparação com o protótipo**, sem o Wagner mandar.

## Regra de veredito (não reverter decisão aprovada)

Divergência **não é automaticamente bug**: pode ser **prod-à-frente** (evolução aprovada — ex: pills
#3391) ou **decisão**. Cruzar com o **charter** + trilha antes de "consertar". Só é **bug** o que
(a) é anti-padrão do sistema (D1 full-reload), (b) contradiz o token/ADR canon, ou (c) o Wagner
aponta como não-intencional. Caso contrário: **a prod é o mais novo → re-exportar o protótipo**, não
arrastar a prod pra trás.

## Camada BUILDADA (pixel) — regressão vs baseline própria (complementar, não substituta)

Existe uma terceira camada, **já viva e REQUIRED no CI**, que este protocolo não substitui — e que
não substitui este protocolo: a **comparação pixel-a-pixel oficial do app BUILDADO**.

**Onde vive:** [`tests/Browser/CoreScreens/PixelBaselineTest.php`](../../../tests/Browser/CoreScreens/PixelBaselineTest.php)
rodando em [`.github/workflows/visual-regression.yml`](../../../.github/workflows/visual-regression.yml)
(Pest 4 Browser + Playwright chromium): builda o Vite (React/CSS reais), navega logado
(auth-bridge `/_visreg-login`), tira screenshot de viewport das **núcleo-6** (Financeiro/Unificado ·
Compras · Clientes · Oficina/OS · Sells/Index · Sells/Create) e roda pixelmatch (GD, mesma semântica
do plugin) contra baseline `.snap` **commitada** (`tests/.pest/snapshots/Browser/CoreScreens/`).
**Double-threshold L7:** diff < τ_baixo (0.1%) auto-aprova · > τ_alto (2%) **FALHA o merge** · o meio
é ZONA CINZA (não falha; diff-view no artifact `pixel-diff-views` + step summary pro [W] revisar).
Estados não-default (ex: `financeiro-unificado` **dark**) são o gate L2 irmão
(`IsolatedStatesBaselineTest` + manifesto [`tests/Browser/visreg-states.json`](../../../tests/Browser/visreg-states.json)),
hoje ainda ADVISORY.

**Enforcement (estado 2026-07-06):** o check `visual-regression` é **required** no main
([`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) GT-G4;
a poda [ADR 0314](../../decisions/proposals/0314-poda-gates-onda-2-lei-fusoes.md) o manteve como LEI)
e o step do pixel-diff é **ENFORCING** desde o [#3277](https://github.com/wagnerra23/oimpresso.com/pull/3277)
(2026-06-23, padrão [ADR 0271](../../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md)
"promover = remover o continue-on-error"). Update de baseline: `npm run visreg:update` + aprovação [W]
(gate F1.5), no MESMO PR da mudança intencional.

**O que cada camada prova (a pegadinha é achar que uma cobre a outra):**

| Camada | Pergunta que responde | Referência de comparação | Exemplo que SÓ ela pega |
|---|---|---|---|
| **D1 (comportamento)** | filtro/nav é partial reload? | contrato Inertia (`only:` + defer) | full-reload D-14 (invisível em qualquer print) |
| **D2–D7 (fidelidade · runtime)** | a prod bate com o DESIGN? | protótipo Cowork VIVO | primary ghost [#3885](https://github.com/wagnerra23/oimpresso.com/pull/3885) (nasceu errado — ver abaixo) |
| **Pixel CI (regressão · buildado)** | o PR mudou a tela SEM querer? | baseline própria commitada | refactor de CSS que desloca o footer de uma núcleo-6 |

**Prova de que não são substitutas — o incidente 2026-07-06 (#3885):** o primary "Novo título" da
Unificado renderizava GHOST porque a regra `.os-btn.primary` escopada `.fin-cowork` nunca casou com o
botão do PageHeader — o bug **nasceu com a tela**, e a baseline de pixel foi capturada JÁ com o bug
(baseline própria congela o estado atual, certo ou errado). O acento magenta 330 vinha de
`localStorage` legado do browser — que nem existe no chromium limpo do CI. O gate de pixel estava
verde e SEMPRE estaria: regressão zero, fidelidade zero. Só a comparação prod × protótipo (D6) pegou.
O inverso também vale: uma regressão de CSS num PR qualquer aparece no pixel-diff em ~10min de CI sem
ninguém abrir o Chrome — patrulha PR-a-PR que D1–D7 (manual, por sessão) não faz.

**Regra prática:** achado D2–D7 que vira fix de tela núcleo-6 provavelmente mexe o pixel → atualizar
a baseline (`npm run visreg:update`) no MESMO PR do fix, citando o print do protótipo como
justificativa (F1.5). Baseline que "trava" um fix de fidelidade aprovado não é gate chato — é o gate
funcionando; atualize-a, não o contorne.

## Achados da 1ª aplicação (Financeiro/Unificado, 2026-07-06)

- **D1 🔴 BUG (prioridade):** `aplicar()` = `router.get(url, params, {preserveState,preserveScroll,replace})`
  **sem `only:`** ([Index.tsx:1240](../../../resources/js/Pages/Financeiro/Unificado/Index.tsx)) + controller
  **sem `Inertia::defer`** ([UnificadoController.php:295](../../../Modules/Financeiro/Http/Controllers/UnificadoController.php)) →
  cada filtro re-roda TODAS as queries (kpis/lancamentos/contas/categorias) + re-render da página inteira.
  XHR, mas "carrega tudo" = o D-14 que a proibição "Inertia::defer DEFAULT" combate. **"Não pode em tela nenhuma"** (Wagner).
- **D3 🟡 DIVERGE:** seta do navegador de mês — prod = glyph texto `‹ ›`; proto = SVG `ChevronLeft/Right` lucide.
- **D2 🟡 DIVERGE:** barra de filtro — proto em 2 linhas (Filtrar-por+período / chips); prod comprime em 1.
- **D4/D5 ⚠️ INCONCLUSIVO:** no ESPELHO local, título (22px/700) e footer (fmt "N lançamentos · Total
  entrada · Total saída") são IGUAIS à prod — mas o espelho pode estar velho. **Refazer contra o
  Cowork vivo** (passo 0) antes de cravar "título grande"/"somatório diferente".
