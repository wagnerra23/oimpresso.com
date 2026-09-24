---
id: requisitos-design-system-protocolo-comparacao-runtime
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

1. **FONTE ERRADA.** O v1 comparou prod contra o **espelho do repo** (`prototipo-ui/cowork/Wagner/<arq>`),
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

## Mecanização — `scripts/design/design-diff.mjs` (a defesa do strike 2, LC-06)

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
- **Papel `chart`** (2026-09-21) — curva, série e barra. Papel novo, **não dimensão nova**: cada
  sinal sai rotulado com a dimensão canônica (forma e altura → D2, `stroke-width` → D4, cor e
  gradiente → D6), como a linha de tabela já fazia. Mede tipo do traço (curva × reta), área
  preenchida, gradientes, cor, espessura e **anisotropia** — o sinal que denuncia traço
  deformado por `preserveAspectRatio="none"` sem `vector-effect`.
  A ausência desse papel tinha sintoma: quando alguém precisava medir gráfico, nascia um gate
  **por tela** (`fiscal-cockpit-sparklines-gate`, `patrimonio-painel-gate`). O terceiro caso — o
  Painel da Jana — foi medido com sonda ad-hoc que morreu com a sessão. Estender aqui é o que
  evita o quarto.
  ⚠️ Não compara **nº de pontos nem valores**, de propósito: dependem do DADO (mock × banco), e
  sinal que não separa "capacidade ausente" de "série mais curta hoje" não pode reprovar ninguém.
- **Dimensão `SAÚDE`** (2026-09-21) — o snapshot carrega `saude` (folhas CSS com href e **zero
  regras** + tokens de cor que não resolvem na raiz), e o comparador emite **`NÃO MEDI`** antes
  de qualquer veredito quando o render está quebrado. Existe porque render sem CSS **não dá
  erro: dá número plausível** — ver §Armadilhas, abaixo.

## Procedimento (passo a passo)

0. **Provar frescor da fonte** — `node scripts/governance/cowork-mirror-freshness.mjs --compare <snap>`
   pro arquivo da tela **E pras DEPS DE RENDER dela** (`--manifest` v3 já enumera: âncoras + o que
   o shell carrega — `app.jsx`, `styles.css`, `ds-v6/tokens.css`, css do módulo); `STALE` ⇒
   re-exportar do Cowork ANTES de comparar. (Sem isso, você compara contra design velho — o erro
   do v1; e SÓ âncora é cego pro drift de infra — o furo LC-07 por onde o PageHeader roxo do [W]
   passou em 2026-07-07: rodada "3 SYNC" verde com `app.jsx` STALE.)
1. **Abrir os DOIS** — prod (Chrome logado) + o Cowork vivo (mesma tela, mesmo tema — o tema é
   escolha do Wagner; comparar no MESMO tema).
2. **Rodar a MESMA sonda D1–D8** nos dois → JSON estruturado por dimensão. Para D2/D4/D6/D8, use
   a sonda de `node scripts/design/design-diff.mjs --probe` (idêntica nos dois lados) → `--compare`
   dá o veredito MEDIDO. **Nunca** conclua "igual" por screenshot — o print não distingue center×left.
3. **D1 sempre**: clicar 1 filtro em prod, `read_network_requests`, classificar (partial vs full).
   Ler `aplicar()`/controller pra confirmar `only:` + `defer`.
4. **Diff por dimensão** → tabela `dimensão | prod | proto | veredito {IGUAL / DÍVIDA A FECHAR /
   PROTÓTIPO ATRASADO}` — vocabulário de 2026-09-18, ver §Regra de veredito. ~~`DIVERGE (decisão)`
   e `PROD-À-FRENTE`~~ saíram: no eixo FORMA não há divergência aceita, há dívida.
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
PROTOCOLO-WAGNER-SEMPRE e com o hook `post-merge-ui-smoke-required`.

> **Errata 2026-08-28 — a frase que estava aqui caducou.** Até esta data o texto dizia que este
> protocolo *"ADICIONA o que o hook não cobre: a comparação com o protótipo"*. Era verdade quando
> foi escrito (`grep -nE "prototipo|design-diff|ancora"` no corpo do hook devolvia ZERO), e o custo
> apareceu no [#6385](https://github.com/wagnerra23/oimpresso.com/pull/6385): 1 screenshot liberou
> o merge e 6 regressões de fidelidade passaram. **Deixou de ser verdade** — o hook passou a cobrar
> a comparação MEDIDA quando o PR toca tela cujo charter declara `related_prototype` resolvível e o
> diff mexeu em marcação/estilo (medido: 140 PRs/90d, FP 1,4%). O que o hook **continua não
> cobrindo**, e por isso segue sendo trabalho deste protocolo: as telas que declaram `n/a` (119 de
> 217 — nascem do DS), os passos D1/D3/D5/D7 que o `design-diff` não mecaniza, e o veredito por
> item. Enforcement em tempo presente não se restateia aqui: o dono é o próprio hook + o teste dele
> (`post-merge-ui-smoke-required.test.mjs`).

## Regra de veredito — **o protótipo manda; paridade é o objetivo** ([W] 2026-09-18)

> ⛔ **REVOGADA a regra anterior.** [W] 2026-09-18, textual: *"eu revogo tudo, de todos. a regra
> mudou agora é o Protótipo quem manda, e a paridade deve ser o objetivo"* + *"pode revogar regras
> conflitantes"*.
>
> O texto revogado dizia: ~~*"Divergência não é automaticamente bug: pode ser prod-à-frente
> (evolução aprovada — ex: pills #3391) ou decisão. (…) a prod é o mais novo → re-exportar o
> protótipo, não arrastar a prod pra trás."*~~ Fica como registro do que valeu entre 2026-07-06 e
> 2026-09-18 — **não instrui mais nada**.
>
> **Ele já estava em conflito com a [UI-0029](adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)**
> (accepted 2026-08-28, ratificada 08-31), que decidiu *"Divergência é DEFEITO, não pauta"* e pôs o
> protótipo **acima do teste** na cadeia de FORMA. As duas conviveram 3 semanas sem reconciliação, e
> o custo é medido: **15 dos 17** arquivos com divergência declarada no repo foram escritos **depois**
> da ratificação da 0029.

**No eixo FORMA, divergência do protótipo é DÍVIDA — nunca estado final.**

| veredito | quando | o que fazer |
|---|---|---|
| **IGUAL** | os dois lados medem o mesmo | nada |
| **DÍVIDA A FECHAR** | prod diverge da âncora | **prod converge.** Registrar no `<tela>-visual-comparison.md` com o valor-alvo |
| **PROTÓTIPO ATRASADO** | prod tem capacidade que a âncora não desenha | **e só então** re-exportar do Cowork — com o recibo de que a capacidade é nova, não de que "prod é mais novo" |

**O que NÃO muda** (a 0029 já separava, e a separação continua): o protótipo manda na **forma**;
**visibilidade** (permissão · pacote por business · módulo) e **dado** seguem do código — protótipo
não revoga permissão nem tenancy. `D1` (full-reload) segue sendo bug por outra via: é anti-padrão
de comportamento, não de forma.

### Componente compartilhado não impõe a forma de uma tela às outras

Conflito medido em 2026-09-18 na Jana: `tests/pageHeaderTabsFidelity.spec.tsx` trava o
`PageHeaderTabs` (**38 telas**) contra `clientes-page.css` — o protótipo do **Clientes** —, e
`Components/PageHeader/PageHeader.tsx:111` carrega `font-bold` com o docblock *"peso Vendas"*. A
Jana consome os dois e herdava as abas do Clientes e o título de Vendas, **nenhum dos dois sendo a
âncora dela**.

O caminho é **réplica local** ([ADR 0388](../../decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md)
§D-1, *"réplica primeiro"*) — que a Jana já aplicou no `JanaKpiCard` em vez de mudar o `KpiCard`
shared de 37 telas. Mudar o compartilhado para servir uma tela é o **mesmo erro invertido**.

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
a poda [ADR 0314](../../decisions/0314-poda-gates-onda-2-lei-fusoes.md) o manteve como LEI)
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

---

## Armadilhas de medição — instrumentos que devolvem número plausível e errado

> Catalogadas medindo, não em revisão. Cada uma custou pelo menos uma rodada, e **nenhuma
> falhou como erro**: falharam como número que parecia resposta. É por isso que estão aqui e
> não num comentário de código.

### `getBoundingClientRect()` de `<polyline>` e `<path>` é CEGO ao stroke

Ele devolve a caixa **geométrica escalada, sem a espessura do traço**. Medir espessura por ali
dá **idêntico** com e sem `vector-effect` — o canário passa e você conclui "não há deformação"
quando há.

Medido em 2026-09-21 (Painel da Jana): a primeira tentativa deu `PEGOU: false`, e a deformação
real era de **8,97×**. A API que responde é **`isPointInStroke()`**, no espaço do usuário,
convertida pela escala que você mediu (`bbox ÷ viewBox`).

### `preserveAspectRatio="none"` sem `vector-effect` deforma o traço — e some quando o dado é plano

Com `none`, o SVG estica para preencher a caixa. Sem `vector-effect="non-scaling-stroke"`, o
traço estica junto: medido, escala `x=8.971 · y=1` → **13,46px** de espessura na horizontal
contra **1,5px** na vertical.

⚠️ **O defeito não aparece enquanto a série for plana** — uma linha horizontal não exibe
deformação nenhuma. No caso medido, os 30 pontos tinham um único valor de `y`, então print,
olho e screenshot mostravam tudo certo. Só apareceria no primeiro tenant com dado variável.
É a razão de o papel `chart` da sonda medir `anisotropia` e não a aparência.

### Render com CSS faltando devolve medida, não erro

O shell do espelho resolve a base do Design System por `location.pathname`. Servindo a raiz
**de dentro** de `prototipo-ui/cowork`, o pathname não casa, o CSS do DS volta **404**, e
**toda cor cai em preto** — num tema dark, sem aviso nenhum.

Duas sessões caíram nisso no mesmo dia: uma descartou a rodada inteira, a outra quase registrou
*"o design diverge em cor"* quando era o próprio servidor.

**Agora a sonda recusa:** o snapshot carrega `saude` (folhas CSS com href e zero regras +
tokens de cor que não resolvem na raiz), e o comparador emite `NÃO MEDI` na dimensão `SAÚDE`
antes de qualquer veredito. Render quebrado não vira opinião sobre o design.

**A regra positiva, se você medir fora da sonda:** sirva a **raiz do repositório** e abra
`/prototipo-ui/cowork/Wagner/oimpresso.com.html`. Confira que `colors_and_type.css` carregou
com mais de zero regras antes de confiar em qualquer cor.

---

## Merge ≠ deploy — todo número medido nessa janela nasce datado

Um PR mergeado em `main` **não** está em produção. Entre o merge e o deploy existe uma janela
em que a produção serve o código antigo, e medir ali produz número correto sobre um estado que
vai mudar.

Medido em 2026-09-21: o PR da grade de Análises mergeou, e a produção continuou servindo
**2 colunas** (`1110.5px × 2`) enquanto a âncora já pedia 3. Quem lesse aquele número depois do
deploy leria como regressão.

**A regra:** ao registrar medição de produção, diga contra qual dos dois estados foi medida —
*"antes do deploy do #NNNN"* é uma frase de uma linha que impede um diagnóstico errado inteiro.

⚠️ **E o inverso, que é pior:** o OPcache pode servir bytecode antigo **depois** do deploy. Em
2026-09-18 dois smokes passaram contra bytecode de **10 dias**. Cerco de hash prova o que está
no disco, nunca o que o runtime carregou — a prova é um **caso discriminante**, um cujo
desfecho muda com o fix aplicado.

---

## Promoção de protótipo — inventário e aceite POR ITEM (vale para toda tela)

> **Por que existe ([W] 2026-09-22):** o [#7701](https://github.com/wagnerra23/oimpresso.com/pull/7701)
> (SupportWR) escreveu este procedimento como RUNBOOK de **um módulo**
> (`memory/requisitos/Manufacturing/RUNBOOK-promocao-prototipo.md`). [W], textual:
> *"Isso deve ser global"*. A peça vem para cá porque aqui é o dono do tema — e porque a
> **errata de 2026-08-28** deste mesmo arquivo já declarava, com todas as letras, que o que
> seguia descoberto era *"os passos D1/D3/D5/D7 que o `design-diff` não mecaniza, e **o veredito
> por item**"*. Isto é o veredito por item.
>
> Medido antes de escrever, com o comando ao lado (`git grep -lI "<termo>" origin/main | wc -l`):
> `matriz de aceite`, `FORA DE ESCOPO APROVADO` e `PARCIALMENTE PUBLICADA` existiam em **1**
> arquivo cada — o próprio #7701. O conteúdo não duplica régua nenhuma; o que estava estreito
> era só o escopo.

### O caso que obriga esta seção a existir (medido 2026-09-22)

`governance/design/targets/medidas/Manufacturing--Recipes/resultado.json` (`medidoEm`
2026-09-18) carrega `compare.veredito = "IGUAL"`. Abrindo o mesmo arquivo, o rótulo sobreviveu a
**duas** condições que deveriam tê-lo barrado:

| campo | valor | por que deveria ter barrado |
|---|---|---|
| `compare.rows[*]` | `IGUAL` 4 · **`SEM-DADO` 9** (de 13) | as regiões principais — cabeçalho, filtros, KPIs, lista — saem com *"região só existe de um lado"* |
| `compare.sameTheme` | **`false`** | este protocolo exige **mesmo tema nos dois lados**; sem isso o veredito não vale |

O rótulo agregado do topo **não distingue "bateu" de "não foi medido"** — e não se auto-invalida
quando a pré-condição falha. Quem lesse só ele declararia fidelidade com 9 células cegas e temas
diferentes: é a [LC-13](../../LICOES_CODE.md) (verde por não-execução) no eixo design. Nenhum
comparador automático resolve isso sozinho; separar *medido e igual* de *não medido* é o trabalho
da matriz abaixo.
_(Medição levantada pela sessão da Fabricação; recibo em `Manufacturing/Recipes-visual-comparison.md`.)_

### Linha zero da matriz — IDENTIDADE DA VIEW, antes de qualquer dimensão

Antes de comparar o que quer que seja, a matriz responde: **os dois lados são a MESMA tela?** Não é
uma dimensão entre outras — é **pré-condição**, e o `design-diff` já a trata assim
(`scripts/design/design-diff.mjs`, *"D0 — IDENTIDADE DA VIEW (pré-condição, não dimensão)"*,
com `⛔ NÃO MEDI — identidade da view não provada`; o modo lote emite
`avisoD0: "sem contrato — identidade da view NÃO provada (âncora pode servir outra tela)"`).

**O sinal existe e é ignorado** — e é isso que esta linha conserta. Caso medido em 2026-09-22: uma
rodada do `Manufacturing/Index` saiu com `token: manufacturing`, que no shell monta a aba
**Receitas**, enquanto a produção mostrava **Ordens de produção** — o driver derivava a rota do
`source` do hub do módulo, não da âncora resolvida. O veredito tinha **aparência inteiramente
válida**; só foi pego porque o token fica gravado no `resultado.json`.

Medir a tela errada não devolve erro: devolve **número plausível sobre outra coisa**. Por isso o
`D0` é a linha `T00` da matriz, e enquanto ela não estiver `ACEITO` **nenhuma outra linha conta** —
um `IGUAL` abaixo de um `D0` não provado é medição de outra tela.

#### As duas pré-condições falham em SILÊNCIO — e é por isso que a amarração é dura

Identidade da view e mesmo tema têm o mesmo defeito, em eixos diferentes: **nenhuma das duas
rebaixa o veredito agregado**. Medido em `origin/main`:

| pré-condição | como o código a trata | consequência |
|---|---|---|
| identidade (`D0`) | `design-diff-lote.mjs:481` grava `avisoD0` num campo do JSON | fica num campo que ninguém lê |
| mesmo tema | `design-diff.mjs:1120` deriva `sameTheme: prodSnap.theme === designSnap.theme`; em `:1386` imprime `⚠ TEMAS DIFERENTES` | é **aviso de console**; `res.sameTheme` não entra no cálculo do veredito |

Ou seja: a falha das duas é **registrada e não punida**. É o mesmo defeito do rótulo agregado, um
nível acima — a pré-condição não se auto-invalida, só deixa rastro.

**E a forma certa já existe no mesmo arquivo — aplicada a UM eixo só.** O `--check-shell`
(`design-diff.mjs:1409-1419`, auditoria adversarial de 2026-08-28) sai **`2`** quando há `SEM-DADO`
de shell, com o vocabulário escrito no próprio comentário — *"1 = medi e divergiu · 2 = não
consegui medir"* — e a justificativa: *"zero medido lido como zero divergente é o mesmo defeito de
'0 failed' numa suíte que não rodou"*. Já o `--check` (`:1407`) sai `1` olhando **só** `res.bugs`.

Medido, o instrumento pune **1 de 3** pré-condições:

| eixo | tratamento hoje | pune? |
|---|---|---|
| shell sem dado | `--check-shell` → **exit 2** | ✅ |
| identidade (`D0`) | campo `avisoD0` no JSON | ❌ |
| mesmo tema | `console.log` de aviso | ❌ |

Então esta seção **não pede mecanismo novo**: pede **estender ao `D0` e ao tema o tratamento que o
shell já tem** — mesmo arquivo, mesmo vocabulário de exit code, precedente com autoria e data.
Enquanto isso não for feito, `T00` é a trava no processo, e é por isso que ela é **bloqueante** em
vez de mais um campo informativo: um campo a mais seria a terceira coisa verdadeira que ninguém lê.

⚠️ **E esta matriz diz de si mesma o que é:** em 2026-09-22 **nenhuma máquina a força** — o
`Gate:` declarado da [LC-13](../../LICOES_CODE.md) (`junit-summary --check-assertions`) roda dentro
das lanes Pest e **não alcança instrumento de design**. A matriz é disciplina de processo com
âncora medível, não gate; quem a tratar como defesa automática está lendo errado.
_(Eixo do tema e medição do `--check-shell` levantados pela sessão da Fabricação; verifiquei cada
linha citada em `origin/main` antes de escrever. O `--tema` existe no `design-diff-lote.mjs:820`,
não no `design-diff.mjs` — onde tem 0 ocorrências.)_

### A quem se aplica — o denominador, para não cobrar o impossível

Só a tela cuja **âncora de design resolve**: `node scripts/design/ancora.mjs <Mod/Tela>` devolve
fonte. Tela que declara `n/a (herda PT-0X)` **nasce no Design System**, não no Cowork — não tem
protótipo a promover e **não entra neste denominador**. Cobrá-la seria falso-positivo por
construção (§5 2026-08-28).

Quem responde *"que telas posso aplicar sem quebrar comportamento"* é a porta viva
`node scripts/qa/prototipo-readiness.mjs` (✅ PRONTA / 🟡 1-CICLO / ⛔ SEM-ÂNCORA). Este protocolo
**não republica** essa lista nem a contagem dela — número que outro sistema sabe melhor não se
restateia em prosa (§5 2026-07-17).

### Regra de conclusão

Uma tela está **CONCLUÍDA** quando todo item do inventário está `ACEITO` ou
`FORA DE ESCOPO APROVADO`, cada um com evidência. Com qualquer item pendente, bloqueado ou sem
evidência, ela está **PARCIALMENTE PUBLICADA** — e é assim que se reporta.

**Não usar "igual ao protótipo", "pronta", "completa" ou "fiel" sem anexar a matriz.** Um medidor
parcial autoriza afirmar **quais dimensões ele comparou**, nunca fidelidade integral: o
`design-diff` mecaniza D2/D4/D6/D8 e não decide fluxo, drawer, permissão, ícone nem regra de
negócio. É a **Regra 0** (acima) aplicada item a item — ela já proíbe declarar "aplicado/pronto"
sem prova; aqui a prova ganha unidade.

### Onde a matriz mora — no artefato que já existe

A matriz é uma seção do **`<Tela>-visual-comparison.md`** daquela tela, ao lado dos demais itens.
Não criar artefato novo, não abrir planilha, não deixar em comentário de PR: o inventário tem de
sobreviver à sessão que o escreveu, porque o agente seguinte retoma **da matriz versionada, nunca
da memória de conversa** — contexto longo omite item.

### Inventário — ler o protótipo de cima a baixo

Cada elemento observável vira **uma linha**. Não agrupar em termo vago ("ajustes de tabela",
"formulário revisado"): item agrupado é item que ninguém confere. Registrar, quando existirem:

1. Cabeçalho, breadcrumb, título, descrição e ações principais.
2. Busca, filtros, chips, ordenação e filtros ativos.
3. Indicadores, cartões, totais e métricas.
4. Abas, navegação interna e estado selecionado.
5. Lista/tabela: **cada** coluna, badge, menu, paginação, ordenação, e os estados vazio,
   carregando e erro.
6. Modais, drawers e formulários: campos, máscaras, obrigatoriedade, validação, erro, sucesso e
   cancelamento.
7. Fluxos: criar, editar, excluir/cancelar, aprovar, imprimir/exportar, abrir detalhe.
8. Tooltip, ícone, rodapé, atalho e a responsividade que o protótipo mostrar.
9. Permissões: quem vê e quem executa cada ação.

Elemento desenhado sem comportamento definido → `NÃO DEFINIDO NO PROTÓTIPO`. **Não inventar
regra:** anti-padrão inventado é pior que ausente, porque parece canon (§5 2026-07-16).

### Classificar ANTES de implementar

| Classe | Tratamento |
|---|---|
| Visual | Implementa e compara pelas dimensões D2/D4/D6/D7/D8. |
| Comportamento existente | Reusa o comportamento confirmado **e** testa. |
| Dado existente | Confirma endpoint, query e permissão de origem. |
| Dado novo | Backend primeiro. O componente não aparece antes do dado. |
| Regra de valor/estoque | **Regra Mestre Tier 0** ([proibicoes.md](../../proibicoes.md)): dupla prova + antes→depois + aprovação [W]. Não se restateia aqui. |
| Permissão | Confirma pacote, perfil e o comportamento sem autorização. |
| Fluxo novo | Especifica e testa antes de chamar de funcional. |
| Fora de escopo | Só com aprovação explícita, com quem aprovou e quando. |

**Dado fictício para a tela "parecer certa" é proibido.** Sem backend, o item fica `BLOQUEADO` — um
mock que engana a comparação transforma o medidor em carimbo.

### Os dois eixos da matriz — e como se amarram

A matriz carrega **duas colunas que não são a mesma pergunta**, e confundi-las é o que faz uma tela
parecer pronta:

- **Estado de aceite** — onde o item está no ciclo:
  `NÃO INICIADO` · `EM IMPLEMENTAÇÃO` · `PRONTO PARA VALIDAR` · `ACEITO` · `BLOQUEADO` ·
  `FORA DE ESCOPO APROVADO`. Nenhum outro estado encerra um item.
- **Veredito de medição** — o que a comparação devolveu, no vocabulário que a **Regra de veredito**
  (acima, [W] 2026-09-18) já fixou: `IGUAL` · `DÍVIDA A FECHAR` · `PROTÓTIPO ATRASADO`. Acrescente
  `NÃO MEDIDO` quando a sonda não cobriu aquele item — pelo caso do `Recipes`, é o estado que mais
  se disfarça de aprovação.

**A amarração, que é o que impede os dois de brigarem:**

| Para marcar | Exige |
|---|---|
| qualquer linha | a linha `T00` (identidade da view) `ACEITO` — senão o veredito é de outra tela |
| `ACEITO` | veredito `IGUAL`, **ou** uma diferença aprovada com quem aprovou e quando |
| `ACEITO` | evidência anexada — sem ela o estado não vale |
| `ACEITO` | a medição que o sustenta tem `sameTheme: true` — tema diferente invalida o veredito |
| `FORA DE ESCOPO APROVADO` | quem aprovou, data e motivo |
| `BLOQUEADO` | o que falta e de quem é a próxima decisão |

`DÍVIDA A FECHAR` **nunca** convive com `ACEITO`: no eixo FORMA divergência é dívida, não estado
final — quem converge é a produção. `PROTÓTIPO ATRASADO` exige o recibo de que a capacidade é nova,
e aí o caminho é re-exportar do Cowork, não arrastar a prod para trás. **`NÃO MEDIDO` nunca vira
`ACEITO` por omissão** — ou se mede, ou se declara fora de escopo com aprovação.

### Ordem de execução

1. Fixar a fonte oficial **e a revisão dela**. Havendo mais de um protótipo candidato, o trabalho
   fica `BLOQUEADO` até decisão humana — não assumir que o mais novo, o mais bonito ou o mais
   parecido com o código atual é o certo.
2. Criar a matriz e levantar bloqueio de dado, regra, permissão e fluxo novo.
3. Dados e regras confirmadas.
4. Estrutura da tela + estados carregando/vazio/erro.
5. Fluxos e validações.
6. Tabela, filtros, indicadores, ações, modais, drawers.
7. **Só então** o acabamento visual contra a âncora.
8. Evidência anexada, matriz atualizada.
9. Só então declarar.

Começar pelo passo 7 numa tela que depende de dado ausente produz exatamente a tela que parece
pronta e não é.

### Relatório final

```text
Tela: <nome e rota>
Fonte oficial: <caminho/URL e revisão>
Identidade da view (T00): PROVADA | NÃO PROVADA     Mesmo tema: sim | não
Itens inventariados: <n>   Aceitos: <n>   Fora de escopo aprovados: <n>
Pendentes: <lista ou nenhum>      Bloqueados: <lista ou nenhum>
Não medidos: <lista ou nenhum>
Evidência visual: <links>   de fluxo: <testes>   de dado/valor/permissão: <provas>
Diferenças aprovadas: <lista ou nenhuma>
Declaração: CONCLUÍDA | PARCIALMENTE PUBLICADA | BLOQUEADA
```

### Módulo é a soma das telas

**Não existe aceite único de módulo.** Uma matriz por tela, mais uma visão consolidada; trabalhar
por tela ou onda pequena sem dependência, registrando antes da próxima. Aprovação visual de uma
tela não aprova as vizinhas — e componente compartilhado não transporta aceite (ver
*Componente compartilhado não impõe a forma de uma tela às outras*, acima).

---

## Fonte provada — DS único, lock de design e resolução sem ambiguidade

> **Por que existe ([W] 2026-09-22, complemento ao [#7703](https://github.com/wagnerra23/oimpresso.com/pull/7703)):**
> a seção anterior fechou o **aceite por item** (matriz, `T00`, `sameTheme`). Faltava o outro
> eixo do mesmo problema — **garantir que a comparação usa o protótipo certo e o Design System
> certo**. [W], textual: *"o que não pode existir é […] a ferramenta escolher a fonte por
> basename […] usar 'o primeiro arquivo encontrado'"*.
>
> Pastas por autor (`cowork/Felipe/`, `cowork/Wagner/`) são **legítimas** — os dois trabalham no
> mesmo projeto e sincronizam. O que não pode existir é **duas cópias do DS**, **dois globals
> carregáveis**, ou **fonte escolhida por sorte de varredura**.

### Autoridade — [W] decide a fonte; a máquina prova qual foi usada

[W] é o dono do oimpresso e do Design System, e é a autoridade para: aprovar a versão canônica
do DS · decidir qual protótipo rege uma tela · resolver conflito entre protótipos de autores ·
aprovar diferença intencional · aceitar exceção de escopo · aceitar a promoção final.

**A decisão fica registrada e auditável, e não substitui a validação técnica.** Qualquer autor —
[W], [F] ou outro — passa pela mesma prova depois que a fonte estiver aceita. A separação é:

| quem | responde |
|---|---|
| **[W]** | *qual* fonte vale |
| **a máquina** | *qual* fonte foi de fato usada, e o que entrou na aplicação |

### O defeito é ATIVO — medido em 2026-09-22, antes de escrever a regra

`node scripts/design/design-lock.mjs --check` sobre a árvore:

| medida | valor |
|---|---|
| charters que declaram `-page.jsx` em `bundle_source`/`visual_source` | **46** |
| declaram com **caminho completo** (a forma correta) | **6** → resolvem **NADA**: `ancora.mjs:512` compara `basename(f) === valor cru`, e o caminho nunca casa |
| **ambíguos** (2+ arquivos com aquele basename) | **40** |
| destes, com **conteúdo divergente** | **38** → `.find()` escolhe o primeiro da varredura (`Felipe/`, por ordem alfabética) e nada declara a escolha |
| resolvem exatamente 1 candidato | **0** |

Ou seja: a perna do bundle **nunca resolve corretamente hoje** — ou escolhe entre divergentes,
ou ignora quem declarou o caminho certo. E quem faz o **certo** (declarar o caminho) é o mais
penalizado. Isto não é risco futuro; é o estado medido.

### Regra do DS único

O Design System tem **uma** fonte canônica: `prototipo-ui/design-system/`.

Protótipos de qualquer autor, handoffs e páginas-guia podem **referenciar** o DS canônico e
**não podem** carregar, embutir ou manter cópia independente dele. A validação reprova:

1. cópia de arquivo de DS **fora** da fonte canônica;
2. mesmo nome de arquivo de DS com **conteúdo divergente**;
3. mais de um global/runtime de DS disponível;
4. versão de DS em runtime diferente da declarada para a comparação;
5. handoff ou bundle legado capaz de **vencer** a resolução de fonte.

⚠️ **Sincronizar DS não é promover tela.** Renomear global, mudar comentário, ajustar caminho de
import ou subir versão de dependência é **sincronização** — nunca prova de que uma tela foi
promovida para produção.

### Lock de design — caminho completo, revisão e hash

Antes de implementar ou comparar, a fonte fica travada em
`governance/design/design-lock.json` — que **ainda não existe**, de propósito: ele exige
`approved_by` de [W], e criá-lo aqui seria forjar a aprovação que o mecanismo existe para provar.
O formato é este:

```json
{
  "design_system": {
    "path": "prototipo-ui/design-system/colors_and_type.css",
    "git_revision": "<commit>",
    "content_hash": "<sha256>",
    "runtime_global": "window.<nome-canonico>",
    "approved_by": "Wagner",
    "approved_at": "YYYY-MM-DD"
  },
  "screens": [
    {
      "id": "<Modulo>/<Tela>",
      "prototype_path": "prototipo-ui/cowork/<Autor>/<arquivo>-page.jsx",
      "git_revision": "<commit>",
      "content_hash": "<sha256>",
      "anchor_status": "VERIFIED",
      "accepted_by": "Wagner",
      "accepted_at": "YYYY-MM-DD"
    }
  ]
}
```

**Basename não identifica fonte.** `manufacturing-page.jsx` não diz nada quando existem três
arquivos com esse nome — e existem. A referência válida é **caminho completo + revisão + hash**.
O hash de um arquivo sai de `node scripts/design/design-lock.mjs --hash <arquivo>`.

_(JSON e não YAML por medição: `js-yaml` não está em `dependencies` nem `devDependencies`, e um
lock YAML faria um gate depender de pacote ausente — o defeito que o gate existe para impedir.
`governance/` já é JSON.)_

### Resolução sem ambiguidade

Havendo **mais de um candidato** para uma fonte visual ou de DS, a execução **falha**, lista os
candidatos e exige caminho explícito no lock. É **proibido** resolver fonte por: primeiro
resultado de `find()` · basename · ordem de varredura · prioridade implícita de pasta · estado da
máquina local.

Renomear diretório ou rodar noutra máquina **não pode** mudar em silêncio o protótipo comparado.

### Prova de runtime

Git correto **não** é prova suficiente. A comparação prova, no ambiente real, que a aplicação
carregou: **uma** versão do DS · o global declarado no lock · a revisão/fingerprint esperada ·
**nenhuma** cópia legada de handoff, bundle alternativo ou import antigo.

A prova é registrada junto ao `<Tela>-visual-comparison.md`, ao lado da matriz.

### Ordem das pré-condições — o lock vem ANTES do `T00`

Estas regras **complementam** a seção anterior; não substituem `T00` nem `sameTheme`. A ordem é:

| # | pré-condição | se falhar |
|---|---|---|
| 1 | lock resolve caminho, revisão e hash | `BLOQUEADO` |
| 2 | sem ambiguidade de âncora e sem duplicata de DS | `BLOQUEADO` |
| 3 | runtime provou o DS esperado | `NÃO MEDIDO` |
| 4 | `T00` — os dois lados são a mesma tela | `NÃO MEDIDO` |
| 5 | `sameTheme: true` | `NÃO MEDIDO` |
| 6 | veredito visual | só aqui `ACEITO` é possível |

**Falhou qualquer etapa, o resultado é `NÃO MEDIDO` ou `BLOQUEADO` — nunca `IGUAL`.**

### Reprodutibilidade entre máquinas

O mesmo commit, lock, dado, viewport, tema e versão de ferramenta devem produzir o mesmo
resultado para qualquer autor. O recibo da comparação registra: commit do repositório · hash do
protótipo · hash do DS · viewport · tema · fixture/dado · versão da ferramenta · ambiente.

Máquinas diferentes achando fontes ou resultados diferentes ⇒ **`NÃO REPRODUTÍVEL`**, que é um
veredito próprio e não se confunde com divergência de design.

### O que a máquina já faz, e o que ainda não

Honestidade sobre o próprio alcance, datada — 2026-09-22:

| regra | estado |
|---|---|
| lock válido (campos, caminho completo, hash confere) | ✅ `design-lock.mjs --check` |
| DS único — **duplicata por conteúdo** (mesmo nome, hash divergente) | ✅ `--ds` |
| DS único — "nome de DS fora da canônica" | ⚠️ **informativo, não achado** — ver abaixo |
| âncora ambígua falha e lista candidatos | ✅ `--ambiguidade` (e `--strict` para exit 1) |
| lock desambigua a tela travada | ✅ testado nos dois sentidos |
| **prova de runtime (global/fingerprint do DS carregado)** | ❌ **não implementado** — depende de sonda no browser, como o resto do D1–D8 |
| **recibo de reprodutibilidade no artefato da tela** | ❌ **não implementado** |

As duas linhas ❌ ficam **declaradas como dívida**, não descritas como se existissem — mecanismo
que anuncia o que não faz é [LC-15](../../LICOES_CODE.md). E este complemento **não afirma o
próprio enforcement**: quem é required vive em
[`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json).

#### Por que "nome de DS fora da canônica" é informativo, e não achado

Porque classificar por **nome de arquivo** é o guard sintático que o §5 de
[`proibicoes.md`](../../proibicoes.md) enterra 8× — e aqui ele já produziu **falso-positivo com
consequência**, medido em 2026-09-22, no mesmo dia em que nasceu:

`prototipo-ui/cowork/Wagner/venda-v3/01-fundacoes/css/tokens-tema-escuro.css` foi acusado e
**não é cópia de DS**. São **34 linhas** em escopo `.cockpit[data-theme="dark"]`, com o cabeçalho
declarando *"NÃO cria token novo (ADR-0050): apenas dá valor escuro a tokens que o bundle do DS
declara só no tema claro"*. Existe porque **8 de 29 pares reprovavam WCAG AA no escuro**, e é
carregado por `prototipo-ui/cowork/Wagner/venda-v3/index.html:47`. Apagá-lo reintroduziria a falha
de contraste e deixaria aquele `<link>` em 404.

E a regra que teria evitado isto **já estava escrita e é always-on**:
[`proibicoes.md`](../../proibicoes.md) §"LIGUE A MÁQUINA" item 4 — *máquina nova exige **FP medido
ANTES** de instalar*. Some-se a lápide §5 de **2026-06-30**, que mata este mesmo predicado **neste
mesmo domínio** e nomeia o dono (`ancora.mjs::resolveAncora`). Não foi lacuna de conhecimento: foi
falha de execução.

> ⚠️ **ERRATA 2026-09-22 — duas frases desta seção nasceram FALSAS e ficam registradas, não
> apagadas.** Foram pegas pelo `ciclo-adversary`, rodado antes de a lição virar ledger.
>
> **(1)** Dizia-se *"o `venda-v3` é a **âncora viva** de `Sells/CreateV3`"*. **Falso.**
> `node scripts/design/ancora.mjs Sells/CreateV3` resolve
> **`prototipo-ui/cowork/Felipe/venda-v3.jsx`** — outra árvore, outro dono; nenhum charter declara
> `cowork/Wagner/venda-v3`. A recusa em apagar estava certa, mas a razão que eu publiquei, não. A
> razão que se sustenta sozinha é a do parágrafo acima: é override de contraste em escopo, com um
> `<link>` apontando para ele.
>
> **(2)** Dizia-se que o critério *"declara token em `:root` = fundação"* havia **falhado no
> controle positivo**, com `colors_and_type.css` medindo `tokensRoot = 0`. **Era a sonda que
> estava cega**, não o critério: ela fazia `indexOf(':root')` e casou uma **menção a `:root` dentro
> de um comentário** (linha 12), parando antes do seletor real (linha 44). Removendo os comentários
> antes de casar, o critério **discrimina**:
>
> | arquivo | tokens | em `:root` |
> |---|---:|---:|
> | canônico `colors_and_type.css` | 245 | **128** |
> | o FP `tokens-tema-escuro.css` | 13 | **0** |
> | Felipe `ds-galerias/tokens.css` | 139 | **102** (cópia real) |
> | Felipe `ds-galerias/styles.css` | 80 | **42** (cópia real) |
>
> Publicar *"o critério falhou"* a partir de sonda cega é **instrução de desistência sobre algo que
> funciona** (§5 2026-09-01) — por isso a errata fica, em vez de a frase sumir.

Então o eixo **reporta e não reprova** — mas pela razão **verdadeira**, que é outra: o critério de
`:root` tem falso-**negativo** (`ds-galerias/design-system.css` mede 26 tokens · **0** em `:root` e
ainda assim pode ser cópia). O que reprova é a **duplicata por conteúdo** — mesmo nome, hash
divergente — que não depende do nome parecer "de DS", e foi o eixo que de fato pegou o
`ds-v6/tokens.css`.

## Migração dos charters existentes — estado da âncora, lotes e testes

> **Por que existe ([W] 2026-09-22, 2º complemento no mesmo dia):** a seção anterior diz **como**
> uma fonte fica provada. Faltava dizer o que acontece com os **charters que já existem** e cuja
> âncora nasceu antes dessa prova — caminho incorreto, cópia antiga, espelho do código de
> produção, basename duplicado, formato que a ferramenta não resolve, ou protótipo que não existe
> mais. Esses charters **não podem continuar gerando verde por compatibilidade**.
>
> Ela fecha quatro formas de falso "validado": comparar contra o **protótipo errado** · contra
> **cópia duplicada do DS** · medir a **tela errada** · manter **teste verde** que só prova uma
> âncora antiga, incompleta ou ambígua.

### Âncora errada não rebaixa o charter — é um eixo separado

Uma âncora visual incorreta **não** significa que o charter inteiro está errado: o contrato
funcional, os casos de uso e os testes de comportamento podem continuar válidos. Por isso:

- o **status funcional** do charter (`draft`/`live`) **não muda** por causa da âncora;
- a confiabilidade da fonte visual vive num **eixo próprio**, o `anchor_status`, registrado no
  lock (campo em `screens[]`, ver o formato acima).

### Os 6 estados da âncora visual

| estado | significado | gera veredito visual? |
|---|---|---|
| `VERIFIED` | caminho completo, revisão, hash e aceite de [W] confirmados | **sim** |
| `AMBIGUOUS` | dois ou mais candidatos possíveis | não |
| `WRONG_SOURCE` | aponta para cópia ou espelho não aprovado | não |
| `MISSING` | a fonte declarada não existe | não |
| `UNSUPPORTED` | a ferramenta ainda não resolve o formato declarado | não |
| `N_A` | a tela herda o Padrão de Tela e não tem protótipo próprio | **fora do denominador** |

Qualquer estado diferente de `VERIFIED` ou `N_A` produz, e só produz:

```text
NÃO MEDIDO — <motivo explícito>
```

Nunca `IGUAL`, `VALIDADO`, `ACEITO` ou "fiel ao protótipo".

⚠️ `N_A` é a declaração consciente que a cadeia já reconhece (`n/a (herda PT-0X…)`,
`ehDeclaracaoNa`) — não é ausência de fonte e não é dívida. Cobrá-la seria falso-positivo por
construção (§5 2026-09-09 de [`proibicoes.md`](../../proibicoes.md)).

### A ferramenta aceita caminho completo — e não reescreve caminho para basename

Caminho completo declarado em charter **é suportado**. Reescrever um caminho completo para
basename só para caber numa limitação da ferramenta é proibido: é trocar a forma certa pela
errada para calar um sintoma. Se a ferramenta não resolve o caminho, a âncora fica
`UNSUPPORTED` e o conserto é **na ferramenta**.

### As 5 etapas da migração

**Etapa 1 — corrigir a ferramenta, antes de tocar charter em massa.**
1. suportar caminho completo;
2. detectar ambiguidade;
3. falhar de forma observável com dois ou mais candidatos;
4. validar lock, revisão e hash;
5. impedir que âncora não-`VERIFIED` gere `IGUAL`.

Falha obrigatória global **não** se promove sem medir o impacto e preparar a migração dos casos
existentes.

**Etapa 2 — inventário.** Um relatório **derivado** (gerado pela máquina, nunca escrito à mão)
com uma linha por charter:

```text
Tela · Charter · Fonte declarada · Candidatos encontrados · Hash de cada candidato
     · Estado da âncora · Fonte aprovada · Próxima ação
```

**Etapa 3 — decisão de fonte.** Havendo mais de uma fonte possível, **[W] escolhe**. Não se
assume que a mais nova, a mais bonita, a mais próxima do código ou a que está na pasta de um
autor é a correta.

**Etapa 4 — lock e atualização.** Depois da decisão: registrar caminho, commit e hash no lock ·
apontar o charter para a fonte aceita · provar que a ferramenta resolve **uma** fonte · rodar a
comparação de novo · gerar recibo novo.

**Etapa 5 — evidência histórica.** Recibo feito contra fonte errada, ambígua ou não verificável
**não é apagado** (append-only). Ele é marcado:

```text
SUPERSEDED — comparação anterior usou fonte não verificável
```

e deixa de contar como evidência atual de fidelidade.

### Remodelagem dos testes existentes

Teste verde hoje pode provar apenas que a ferramenta **achou algum arquivo** — não que achou o
certo. Depois da correção, um teste antigo pode ficar vermelho **legitimamente**.

**Expectativa não se atualiza para "fazer passar".** Cada falha nova é classificada: ou o teste
provava a âncora errada (reescreve-se o teste contra a fonte aprovada), ou revelou divergência
real (a divergência entra na matriz de promoção). Mesma regra do eixo FORMA (UI-0029): o perdedor
é **reescrito, nunca desabilitado**.

| teste de hoje | contrato novo |
|---|---|
| "a âncora existe" | resolve caminho completo **único**, revisão e hash esperados |
| busca por basename | **falha** se houver dois candidatos |
| snapshot de fonte antiga | só se regenera contra fonte **aprovada** |
| charter apontando para espelho | migra para lock aprovado |
| smoke que abre qualquer página | prova rota, `T00`, tema e a tela esperada |
| "o DS carregou" | prova global/fingerprint **único** em runtime |
| verde sem recibo | vira `NÃO MEDIDO` |

**Casos negativos obrigatórios** — cada um com fixture própria, provando **falha real** (exit ≠ 0
ou veredito `NÃO MEDIDO`/`BLOQUEADO`), nunca só que uma mensagem foi impressa:

1. dois protótipos com mesmo basename e conteúdo diferente;
2. charter com caminho completo válido (controle positivo — tem de **resolver**);
3. charter com caminho completo inexistente;
4. charter apontando para espelho/cópia não aprovada;
5. lock com hash divergente;
6. duas cópias funcionais de DS;
7. dois globals de DS carregáveis;
8. runtime com DS diferente do lock;
9. `T00` não provado;
10. `sameTheme: false`;
11. recibo antigo contra fonte errada;
12. máquinas ou fixtures que produzem resultado não reprodutível.

O caso 2 é o controle positivo do conjunto: sem ele, uma ferramenta que reprova **tudo** passaria
nos outros onze.

### Rollout — em lotes, nunca num PR só

1. corrigir resolvedor, lock e casos negativos;
2. gerar o inventário;
3. marcar as fontes ambíguas como `NÃO MEDIDO`;
4. migrar telas **por lotes aprovados por [W]**;
5. regenerar comparações e recibos contra a fonte correta;
6. medir falso-positivo e impacto;
7. só então promover enforcement para todos os charters elegíveis.

**Durante a migração não existe fallback para "o primeiro arquivo encontrado".** Compatibilidade
que escolhe fonte em silêncio perpetua o defeito — é exatamente o `.find()` que a medição acima
flagrou.

### Critério de conclusão

O trabalho termina quando:

- todo charter tem `anchor_status` explícito;
- toda fonte `VERIFIED` tem caminho, commit, hash e aceite de [W];
- âncora ambígua falha;
- DS duplicado ou runtime divergente falha;
- recibos antigos contra fonte errada estão `SUPERSEDED`;
- os testes existentes foram remodelados para provar a fonte correta;
- tela nova não consegue nascer com âncora por basename;
- tela sem prova é reportada `NÃO MEDIDO`, nunca verde;
- a mesma entrada gera o mesmo resultado técnico em qualquer máquina.

### Onde isto está hoje — datado, 2026-09-22

| etapa / regra | estado |
|---|---|
| resolvedor aceita caminho completo | ❌ **não** — `scripts/design/ancora.mjs` ainda casa `basename(f) === valor declarado` com `.find()` |
| ambiguidade reportada e listada | ✅ `design-lock.mjs --ambiguidade` (`--strict` sai 1) |
| `anchor_status` no lock | ❌ **não implementado** — o campo existe só no formato acima |
| inventário da Etapa 2 | ❌ **não existe** |
| marcação `SUPERSEDED` de recibos | ❌ **não existe** |
| os 12 casos negativos | ❌ **não existem** como conjunto; os já cobertos vivem no `--selftest` do `design-lock.mjs` |

As linhas ❌ são **dívida declarada**, não capacidade — descrever como feito o que não está feito
é [LC-15](../../LICOES_CODE.md). O próximo passo é a **Etapa 1**, e ela é código, em PR próprio.
