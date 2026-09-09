---
id: requisitos-asset-management-index-visual-comparison
title: "Comparacao design x producao — Patrimonio/Index (Painel)"
module: AssetManagement
tela: Patrimonio/Index
owner: W
status: rascunho
# `inertia_target` + `last_updated` NAO sao enfeite: sao o que poe este doc SOB VIGILANCIA.
# O `visual-comparison-staleness.mjs` compara a data declarada aqui com a data-git da tela
# apontada — doc sem esses campos cai no balde "sem inertia_target" e nunca e avaliado
# (hoje: 46 dos 79). Sem eles, este arquivo envelheceria calado, que e o defeito que ele
# proprio existe pra evitar. O gate le `last_updated`/`date`/`updated_at`, NAO `last_validated`.
inertia_target: resources/js/Pages/Patrimonio/Index.tsx
last_updated: "2026-09-09"
last_validated: "2026-09-08"
---

# Comparacao design x producao — `Patrimonio/Index` (Painel)

> **Ancora computada, nao escolhida no olho** (`node prototipo-ui/ancora.mjs Patrimonio/Index --staging prototipo-ui/cowork`):
> `prototipo-ui/cowork/patrimonio-page.jsx`, aba `painel` (`:149` `painelData` · `:199` `PatPainel`),
> declarada em `related_prototype` do [`Index.charter.md`](../../../resources/js/Pages/Patrimonio/Index.charter.md).
>
> **Frescor da fonte, medido — nao suposto:** `✓ verificado contra o Cowork vivo em
> 2026-09-08T12:57:04.576Z`. Isso importa porque o `--sla` do espelho devolve **INCONCLUSIVO**
> no geral (98 arquivos existem no vivo e nunca desceram); esta tela **nao** esta entre eles —
> ela e um dos 273 que o `--compare` de hoje mediu com **0 stale**.
>
> Primeiro registro desta tela, e o primeiro do modulo: ate 2026-09-08 o
> `memory/requisitos/AssetManagement/` tinha **0** arquivos `*visual-comparison*`, contra **87**
> no resto do repo. As tres telas migradas ([#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035)
> Bens, [#7040](https://github.com/wagnerra23/oimpresso.com/pull/7040) Painel,
> [#7046](https://github.com/wagnerra23/oimpresso.com/pull/7046) Alocacoes) nasceram sem ele.

## O que este documento NAO e

**Nao e veredito de pixel, e a razao e concreta.** A dimensao **D6 (CSS computado / render
pareado)** do [PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
**nao foi medida**: `staging.oimpresso.com/asset/dashboard` redireciona para `/login`, e o agente
**nao insere credenciais** (regra de seguranca, nao limitacao de ferramenta). Sem render de
producao nao ha onde injetar a sonda `design-diff.mjs --probe`, e **medir um lado so nao e
comparar**.

**O que FOI feito, e e mais forte que ler os dois fontes:** o lado do design foi **renderizado de
verdade** — servidor local sobre `prototipo-ui/cowork/`, `oimpresso.com.html`, aba Patrimonio →
Painel, com o portao `cowork-mirror-freshness --preview-ds` passando (`PREVIEW COMPLETO`, rc=0)
antes. Entao o que segue e **design renderizado x codigo de producao**, com os rotulos extraidos
de cada lado — nao impressao, e nao dois greps.

**Destravar D6** depende de um render de producao autenticado (sessao aberta por [W], ou um
ambiente sem login). Ate la os vereditos abaixo valem para **presenca, rotulo e ordem**, nunca
para cor, espacamento ou tipografia.

---

## D7 — KPIs do topo: **IGUAL**

Os quatro, na mesma ordem e com o rotulo literal identico:

| # | Prototipo (`painelData:168-176`) | Producao (`Index.tsx:174-198`) |
|---|---|---|
| 1 | Patrimonio bruto | Patrimonio bruto |
| 2 | Valor residual | Valor residual |
| 3 | Alocados | Alocados |
| 4 | Garantia vencida ou vencendo | Garantia vencida ou vencendo |

**`Valor residual` renderiza `—` em producao, e isso e paridade, nao gap.** A depreciacao nunca e
calculada (`assets.depreciation` e gravada como texto e nao lida por conta nenhuma) e a regra
— linear ou SAC, com que fonte contabil — e decisao [W] em aberto (`SPEC.md` `US-ASSET-W01`,
RESIDUO 6 do playbook). O charter declara o item nos Non-Goals com o dono. **Numero sem fonte nao
renderiza** — mostrar zero seria pior que o traco.

## D7 — Blocos de analise: **IGUAL**

Os tres, na mesma ordem e com o titulo literal identico:

| # | Prototipo (`painelData:177-193`) | Producao (`Index.tsx`, `titulo=`) |
|---|---|---|
| 1 | Patrimonio por categoria | Patrimonio por categoria |
| 2 | Situacao da garantia | Situacao da garantia |
| 3 | Manutencao em aberto | Manutencao em aberto |

O **custo de manutencao no ano** (rodape do 3o bloco no prototipo) renderiza `—`: a tabela
`asset_maintenances` nao tem coluna de valor (RESIDUO 3 do playbook). Tambem declarado no charter.

## D7 — "Resumo de hoje": **IGUAL na 1a frase · a 2a e RECUSA DECLARADA**

O prototipo escreve duas linhas mais um destaque. A producao porta a primeira e **omite a segunda
com o motivo escrito no proprio `.tsx`** (`:136-137`): ela nomeia equipamentos especificos e um
custo por peca que **sao o cenario do mock**, nao dados do banco. Omitida, nao imitada. O destaque
(que manda comecar pela garantia de um equipamento nominal) cai pelo mesmo motivo.

Isto e o comportamento certo, e vale registrar como precedente: **prosa de prototipo que cita
numero especifico e dado de mock ate prova em contrario.**

## D7 — Chips do "Resumo de hoje": ~~AUSENTE~~ → **PORTADO em 2026-09-09** ✅

> **Fato datado, e o retrato abaixo e de 2026-09-08.** Os tres chips que apontam para rota
> existente entraram no [#7133](https://github.com/wagnerra23/oimpresso.com/pull/7133)
> (`0e6012b46f`); o quarto (`Auditoria`) segue de fora, agora com o motivo **escrito na propria
> tela** — o comentario acima do bloco cita o Non-Goal `D-AUDITORIA`. Ou seja: a ausencia que
> este paragrafo cobrava — a **nao declarada** — deixou de existir. Recontado em 2026-09-09
> direto no `.tsx`: `Garantia critica` 2 · `Em manutencao` 1 · `Alocados` 2 ocorrencias.
>
> O texto original fica porque era verdade na data. O que **nao** pode ficar e a frase em
> presente: doc canon que afirma ausencia do que existe vira instrucao de trabalho duplicado
> pra proxima sessao (§5 2026-09-01).

O prototipo desenha quatro chips de navegacao logo abaixo do resumo
(`PatPainel:216-222`), cada um com `aba` e `filtro` de destino:

| Chip | Destino no prototipo | Rota existe hoje? |
|---|---|---|
| Garantia critica | aba `bens`, filtro `garantia` | **sim** (`/asset/assets`) — o filtro e que nao existe |
| Em manutencao | aba `manutencoes` | **sim** (`/asset/asset-maintenance`) |
| Alocados | aba `alocacoes` | **sim** (`/asset/allocation`) |
| Auditoria | aba `auditoria` | **nao** — decisao [W] aberta (item 5 do `00-INDICE.md §6`) |

Medido em `Index.tsx`: zero ocorrencia de chip/Badge/`Garantia critica`. **Tres dos quatro
apontam para rota que existe**, entao o bloqueio declarado (`D-AUDITORIA`) explica **um** chip, nao
os quatro.

**Por que isto e diferente dos `—` acima:** os tracos sao recusa COM motivo, escrita no charter e
visivel na tela. Estes chips simplesmente nao aparecem, e nao ha Non-Goal, `[BACKLOG]` ou
comentario que os mencione. **Ausencia sem declaracao e indistinguivel de esquecimento** — e a
proxima sessao nao tem como saber qual dos dois foi.

## D7 — Bloco "O QUE FAZER PRIMEIRO": ~~AUSENTE~~ → **PORTADO em 2026-09-09** ✅

> **Fato datado, e o retrato abaixo e de 2026-09-08.** O bloco entrou no mesmo
> [#7133](https://github.com/wagnerra23/oimpresso.com/pull/7133), com `data-contract="acoes"`.
> A ressalva que este documento fez foi **respeitada e esta escrita no `.tsx`**: desceu a
> AFORDANCIA (as tres linhas com CTA pras rotas que existem), nao a COPY — o texto do prototipo
> nomeia equipamento e crava o custo da peca em reais, que e cenario de mock (e citar valor em
> real reprovaria no `brl-scan`, com razao). Cada linha e derivada dos mesmos agregados que os
> KPIs usam. Recontado em 2026-09-09: `O que fazer primeiro` 2 · `Ver bens` 1 ·
> `Ver manutencoes` 1 · `Ver alocacoes` 1 · `data-contract="acoes"` 1.

O prototipo fecha o painel com tres acoes acionaveis (`PatPainel:204-214`), cada uma com CTA:

| Acao | CTA | Destino | Rota existe? |
|---|---|---|---|
| Equipamento sem cobertura de garantia | Ver bens criticos | aba `bens` + filtro | **sim** |
| N manutencoes em aberto | Ver manutencoes | aba `manutencoes` | **sim** |
| N unidades alocaveis paradas | Ver alocacoes | aba `alocacoes` | **sim** |

Medido em `Index.tsx`: zero ocorrencia de "fazer primeiro", "Ver bens", "Ver manutencoes",
"Ver alocacoes". **As tres rotas existem.** Mesma observacao do bloco anterior: nao ha declaracao.

⚠️ **Ressalva honesta sobre as duas ausencias:** os textos das acoes no prototipo sao prosa de
mock (nomeiam equipamento e custo de peca), entao portar **o texto** seria o erro que a 2a frase
do Resumo evitou. O que esta ausente e a **afordancia** — o caminho de 1 clique do painel para a
tela que resolve. Se ela deve existir e decisao de produto do [W]; o que este documento afirma e
apenas que **hoje ela nao existe e ninguem disse que nao deveria**.

## D7 — "Bens alocados a voce": **PROD-A-FRENTE por heranca do legado**

A producao tem um bloco que o prototipo **nao** tem (`data-contract="meus-bens"`): dois KPIs
(*Bens alocados a voce*, *Categorias*) mais a lista por categoria.

Nao e invencao: e o bloco pessoal que a `dashboard.blade.php` legada ja exibia
(`total_assets_allocated` + `asset_allocation_by_category`), preservado na migracao. O prototipo
desenhou o painel so na visao de quem administra; o backend sempre teve as duas visoes.
**Prod a frente, e corretamente.**

## D7 — Icone do "Resumo de hoje" e selo da analise: **PORTADOS em 2026-09-09** ✅

Os dois ultimos detalhes de forma do painel, medidos com a mesma sonda nos dois lados.

| Item | Prototipo | Producao (antes) | Producao (agora) |
|---|---|---|---|
| Icone abrindo o "Resumo de hoje" | `MP.Resumo` → `<JcIcon name="calendar"/>` antes do titulo (`modulo-padrao.jsx:52`) | nenhum icone | `<Icon name="calendar">`, `aria-hidden`, mesmo idioma das duas secoes abaixo |
| Selo no card "Patrimonio por categoria" | `a.pill` do `AnaliseCard` (`chat-jana.jsx:329`), alimentado por `pill:` em `patrimonio-page.jsx:176` | nenhum selo | `<Badge variant="warning">` com o percentual da categoria dominante |

**O selo nao precisou de backend novo:** `porCategoria` (categoria/unidades/valor) ja chegava na
tela; o percentual e derivado dela sobre `kpis.bruto` — o **mesmo** denominador que as barras
deste card ja usavam. Derivar um segundo total criaria dois numeros discordando na mesma tela.

**Uma precisao sobre a contagem que motivou o pedido.** A medicao falava em *"3 selos nos cards
de analise"* no prototipo. Recontado por varredura do codigo-fonte da ancora, o numero e **1**:
`jc-pill` tem tres sites no bundle (`modulo-padrao.jsx:53` o selo `IA` do resumo, que so
renderiza com `ia` verdadeiro e o Patrimonio nao passa; `chat-jana.jsx:236` idem no Jana;
`chat-jana.jsx:329` o `a.pill` do card de analise), e das tres analises do Patrimonio **so a de
categoria define `pill`** — `gar` e `man` nao tem. O `AcaoRow`, que renderiza as tres linhas do
"O que fazer primeiro", tambem nao tem pill nenhum. O alvo **nomeado** no pedido (*"um selo no
card 'Patrimonio por categoria' com o percentual da categoria dominante"*) bate exatamente com o
codigo — foi esse que desceu. Se a sonda contou 3, contou outro seletor.

**Nao desceu, e segue sem descer:** o subtitulo da secao ANALISES (*"clique num card pra ver de
onde vem o numero"*), porque promete o `MP.Drill` que a producao nao tem; e o destaque com
lampada do resumo, porque o texto do prototipo nomeia equipamento e custo de peca — cenario de
mock, o mesmo motivo que ja tinha derrubado a 2a frase do Resumo.

**O nome do icone foi medido, nao suposto.** `Components/Icon` resolve por string e cai em
`Circle` quando o nome nao existe no lucide; o TS nao valida (o componente faz cast). Render
comparado em 2026-09-09: `calendar` sai `class="lucide lucide-calendar"`, um nome invalido sai
`lucide-circle`. O **UC-PAT-08** trava isso — sem ele, o defeito seria tsc verde, eslint verde,
build verde e uma bola vazia na tela (foi o bug de 2026-05-07,
[#184](https://github.com/wagnerra23/oimpresso.com/pull/184)).

## D1 — Rede / partial reload: **CONFORME**

Todas as cinco props pesadas sao `Inertia::defer` (`kpis`, `porCategoria`, `garantia`,
`manutencoes`, `meusBens`); so `is_admin`, `pode` e `apurado_em` vem eager. Cada bloco tem
`<Deferred>` com esqueleto proprio. E o default do projeto para prop cara
([RUNBOOK-inertia-defer-pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)).

## D3 — Sub-navegacao: **DIVERGE por decisao declarada, e a decisao esta certa**

O prototipo desenha **7** abas (`:835`); o menu vivo tem **6** ghosts — tem *Devolucoes*, nao tem
*Garantias* nem *Auditoria*. O `PatrimonioSubNav` **deriva** de `shell.menu`
(`DataController::modifyAdminMenu`) em vez de declarar array, entao a divergencia se resolve
sozinha quando o [W] decidir as duas abas em aberto. Registrado no charter de Bens, que fundou o
componente: *"renderizar aba que nao navega e afordancia falsa"*.

---

## O que falta pra fechar

| # | Item | Dono |
|---|---|---|
| 1 | **D6** — render pareado com `design-diff --probe` nos dois lados (cor, espacamento, tipografia) | precisa de render de producao autenticado |
| 2 | ~~Decidir os **chips** e o **bloco de acoes**~~ — **FECHADO em 2026-09-09**: os dois desceram no [#7133](https://github.com/wagnerra23/oimpresso.com/pull/7133) portando a afordancia sem o texto de mock, que era exatamente a ressalva deste doc | — |
| 3 | Screenshot aprovado por [W] (gate visual F1.5 · [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)) — o charter esta `draft` ate la | **[W]** |
| 4 | `Bens-visual-comparison.md` e `Alocacoes-visual-comparison.md` — as duas telas irmas seguem sem registro | proxima onda |
| 5 | Depreciacao (`US-ASSET-W01`) e rota de Garantias/Auditoria — destravam KPI e abas | **[W]** |

## Refs

- Charter: [`Index.charter.md`](../../../resources/js/Pages/Patrimonio/Index.charter.md) ·
  Casos: [`Index.casos.md`](../../../resources/js/Pages/Patrimonio/Index.casos.md)
- RUNBOOK: [`RUNBOOK-patrimonio-index.md`](RUNBOOK-patrimonio-index.md)
- Fonte visual: `prototipo-ui/cowork/patrimonio-page.jsx` (aba `painel`) — **alvo**, nao decisao
  de produto
- Playbook: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/07-painel.md` ·
  saidas [`_saida-07.md`](../../../prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/_saida-07.md)
  e [`_saida-06-painel.md`](../../../prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/_saida-06-painel.md)
- [PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md) ·
  [PT-04 Dashboard](../_DesignSystem/padroes-tela/PT-04-Dashboard.md)
