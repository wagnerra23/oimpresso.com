# Painel da Jana (`/ia`) — protótipo × tela viva, por região e componente

> ## ⚠️ ERRATA 2026-09-18 — **todo `19px` deste documento caducou em 2026-09-11**
>
> Este doc afirma, em 7 lugares, que o `h1` da âncora mede **19px** (medições de 08-17, 08-25 e
> 09-03, todas corretas na data). **Deixaram de valer** com o [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224)
> (2026-09-11, *"separar fontes por dono e remover paralelos"*), que fez o `JanaHeader` da âncora
> delegar ao `CliPageHead`. As linhas antigas **ficam** — são fato datado, e é a data que lhes dá
> sentido. O que muda é o estado de hoje:
>
> | | prod `/ia` | âncora (espelho servido) | veredito |
> |---|---|---|---|
> | `h1` font-size | **22px** | **22px** | **IGUAL** — a divergência Δ3px **FECHOU** |
> | `h1` font-weight | **700** | **600** | ❌ **DÍVIDA A FECHAR** — prod converge pro protótipo |
>
> **Medido** no DOM renderizado, dark × dark, viewport **2560** (a mesma das rodadas antigas) **e**
> 1440 — mesmo resultado nas duas, o título **não** é responsivo.
>
> **Causa:** a regra que produzia 19px é [`chat-jana.css:40`](../../../prototipo-ui/cowork/Wagner/chat-jana.css)
> `.jc-id h1 { font: 700 19px/1.2 }`, e **`.jc-id` tem 0 nós no DOM** — CSS órfão. O
> `JanaHeader` ([`chat-jana.jsx:214`](../../../prototipo-ui/cowork/Wagner/chat-jana.jsx)) renderiza
> `<window.CliPageHead>`, cujo docblock (`cli-pagehead.jsx:11`) declara *"o desenho é do DS, aqui só
> resta tradução de vocabulário"* e cita essas mesmas regras `.jc-id` legadas como o problema que
> veio resolver. O `h1` passou a herdar o token do DS
> ([`colors_and_type.css:373`](../../../prototipo-ui/design-system/colors_and_type.css)):
> `h1 { font-size: var(--fs-7); font-weight: 600 }`, `--fs-7: 22px` (`:148`). O seletor sobreviveu
> no arquivo; o nó que o recebia, não.
>
> **Por que sobreviveu 7 dias sem ninguém ver:** nenhuma máquina mede a tipografia deste `h1`. O
> [`jana--index.alvo.json`](../../../governance/design/targets/jana--index.alvo.json) foi re-medido
> **no mesmo #7224** (não está atrasado), mas os 9 seletores dele param no container — a seção
> `header` mede `13px/400` e não desce até o título. E o `secao-check` que o consome roda
> `--servir-espelho` (espelho × espelho) e é **advisory**. O
> [`PARIDADE-area-jana-diagnostico-e-ondas.md`](PARIDADE-area-jana-diagnostico-e-ondas.md) carrega o
> mesmo 19px e fica intacto pelo mesmo motivo: é fóssil datado, e esta errata é o ponteiro.
>
> **FECHADO em 2026-09-21 — o peso convergiu, por réplica local.** A linha `font-weight` da
> tabela acima vira **600 = 600**; ela fica como está porque é o retrato de 09-18. O que a rodada
> de hoje acrescenta é o fundamento que faltava: **as duas âncoras discordam entre si.** A de
> **Vendas** declara **700** explicitamente (`styles.css:4772`, 0-1-1, e `financeiro.css:1727`,
> 0-3-1 — esta vence), com o comentário de `styles.css:4765` dizendo textual *"`.os-head` — mesmo
> CANON do PageHeader"*; a da **Jana** não declara peso e herda o DS (600). O 700 do componente é
> decisão [W] datada e **ainda válida** (PR #1477, 2026-05-25, *"prefiro o mesmo peso do sells"* —
> referência re-medida hoje, segue 700), então mudá-la alinharia **42** telas ao peso da Jana.
> Conserto: prop opt-in `titleWeight` no `PageHeader` (default `'bold'`, mesmo contrato de
> `leading`/`below`); só o `JanaAreaHeader` declara `'semibold'` — as outras **41** não mudam um
> pixel. UC-JPAIN-30, mordida provada por 2 mutações. **Computed style MEDIDO** (browser real + CSS do
> projeto gerado pelo entry de verdade, Tailwind v4.3.3): **600** com a prop, **700** sem ela, e
> `font-size` **22px nos dois** — a paridade de tamanho **não foi tocada**. Necessária porque no v4
> a regra é indireta (`font-weight: var(--font-weight-semibold)`) e só o browser resolve; controle
> positivo `folhaCarregou: true` no mesmo turno. ⚠️ Não é a tela `/ia` **logada inteira** — smoke
> autenticado não foi feito (302 sem sessão). O `<h1>` **continua fora** dos 9
> seletores do `jana--index.alvo.json` — o buraco de medição que esta seção denuncia **segue
> aberto**, e fechá-lo exige re-medir por sonda contra render servido, não editar o alvo à mão.
> Trilha: `Index.charter.md` **v25**.
>
> Trilha completa: `Index.charter.md` **v17**.

- **Data da medição:** 2026-08-17 (**re-medido** — ver §Correções abaixo) · **âncora:** `prototipo-ui/cowork/Wagner/jana-merge.jsx` (resolvida por `node scripts/design/ancora.mjs Jana/Index`)
- **Tela viva:** `resources/js/Pages/Jana/Index.tsx` + `_components/JanaCockpit.tsx` + `_components/JanaDrillDrawer.tsx` + `_components/JanaMetaDrawer.tsx` + `_components/JanaConfigDrawer.tsx`
- **Charter:** `resources/js/Pages/Jana/Index.charter.md` **v10**
- **Gate F1.5:** esta tela está no manifesto `tests/Browser/visreg-screens.json` como `Jana`; toda mudança aqui gera diff de pixel e precisa de aprovação [W]

> 🔬 **Re-medido no DOM em 2026-08-25 (protótipo servido em localhost, sonda de `design-diff`).**
> [W] pediu **paridade máxima** — "tudo deve entrar no denominador". Duas coisas saíram da medição:
>
> **1. O protótipo NÃO mudou.** `h1` = **19px**, o mesmo valor da medição de 08-17. As 3 divergências
> de D4/D8 registradas abaixo seguem válidas do lado do protótipo. (Contraste medido no mesmo turno:
> o `h1` do protótipo de **Arquivos** mede **22px** — ou seja, os 19px são particulares da Jana,
> não do shell do protótipo. Isso reposiciona a nota "divergência sistemática do shell": o shell de
> PRODUÇÃO é 22 nas 4 telas, e é o protótipo da Jana que destoa do próprio espelho.)
>
> **2. A projeção da meta fechou no DRAWER, não no CARD — a divergência R4 §card continua ABERTA.**
> Medido nos dois lados: o protótipo renderiza `jm-meta-proj` **5×** dentro do `JmMetaCard`
> (`jana-merge.jsx:215`); em produção `projecao` só existe em `_components/JanaMetaDrawer.tsx` e
> `metaFormat.ts` — `JanaCockpit.tsx`, que desenha os cards, **não tem nenhuma ocorrência**. A onda 5
> (#5923) entregou a projeção, e entregou no lugar certo pelo argumento dela (projeção é veredito do
> servidor); mas quem lê a linha 94 desta tabela como "resolvido" erra: **no card, segue ausente**.
>
> ⚠️ **O lado PRODUÇÃO não foi re-medido neste turno** — exige sessão autenticada em `oimpresso.com`,
> e o navegador usado aqui não a tem. Os números de produção abaixo continuam sendo os de **08-17**.
> Onde este bloco fala de produção, a fonte é `grep` no código do `main` de hoje, não DOM renderizado.

> ## 🔬 Paridade MEDIDA nos DOIS lados — 2026-08-25 (prod logada × protótipo em localhost)
>
> [W]: *"QUERO PARIDADE MÁXIMA"*. Desta vez os dois lados foram medidos no **DOM renderizado**,
> no mesmo turno e **no mesmo tema (dark nos dois)** — não por leitura de arquivo, não por olho.
> Produção: `https://oimpresso.com/ia`, sessão WR2 Sistemas (biz=1), componente `Jana/Index`,
> 1014 nós, DOM estabilizado por releitura até parar de crescer. Protótipo: `jana-merge.jsx`
> servido em localhost, aba Painel, 1056 nós, idem.
>
> | dimensão | produção | protótipo | veredito |
> |---|---|---|---|
> | tema | `dark` | `dark` | ✅ comparável |
> | `h1` da tela | **22px** (classe `text-[22px]` literal) | **19px** | ❌ **DIVERGE Δ3px** |
> | projeção no **card** de meta | **0** — `/proje[çc]ão/` não casa em nenhum nó do `<main>` | **5×** `.jm-meta-proj` | ❌ **DIVERGE** |
> | abas na subnav | **4** — Painel · Conversa · Memória · **Jana Pro** | **3** — Painel · Conversa · Memória | ❌ **DIVERGE** |
> | barras de progresso | 12 | 5 | ⚪ **não-conclusivo** — os dois lados têm conjuntos de metas diferentes; contagem aqui mede o DADO, não o layout |
>
> **O que isto acrescenta às 3 linhas de D4/D8 medidas em 08-17:** a divergência de título **persiste**
> (e agora se sabe de que lado está o desvio — ver o bloco de re-medição acima: o protótipo de
> *Arquivos* mede 22px, então os 19px são particulares da Jana). E aparecem **duas divergências que
> nenhuma medição anterior tinha**: a projeção ausente do card, e a **aba `Jana Pro`**, que **produção
> tem e o protótipo não** — no protótipo a Pro só é alcançável por botão (`onGoTab("pro")` em
> `jana-merge.jsx:728` e `:947`), nunca pela barra de abas do `JmTabs`.
>
> ⚠️ **Nesta última linha a produção está À FRENTE do protótipo** — o que torna "igualar" uma decisão
> de produto, não um conserto: copiar o protótipo aqui seria **remover** uma entrada de navegação que
> existe e funciona. Decisão [W].
>
> **O que NÃO foi medido, e por quê:** KPI (tipografia/alinhamento do valor) ficou de fora porque o
> papel não é resolvível por seletor nos dois lados — em produção os cards são `<Card>`/`<CardContent>`
> do shadcn, com utilitárias Tailwind e **sem classe semântica**; no protótipo são `.jm-meta*`. O
> seletor `[class*="kpi"]` que a sonda usaria casa **`cockpit`** por substring e devolveu 3 falsos
> (`cockpit`, `cockpit-tweaks`, `cockpit-tweaks-fab`) — registrado porque o número parecia medido e
> não era. Fechar essa dimensão pede um `data-` de papel nos dois lados.

> **Como ler:** ✅ existe e equivale · 🟡 existe mas diverge · ❌ não existe na tela viva · ⛔ existe e **não deve** ser copiado · 🟢 só na viva.

---

## ⚠️ Correções — a versão anterior deste documento afirmava 6 ausências que já não existiam

A redação de 2026-08-17 (commit `dbff9d182`) foi escrita **antes** de dois PRs do mesmo dia e
carregava uma sétima linha que **já era falsa quando foi escrita**. Registro com o recibo porque
apagar seria pior que o erro — e porque o chip que originou esta onda **repetiu** quatro delas.

| linha que dizia ❌/🟡 | o que a medição mostra | desde |
|---|---|---|
| R1 · botão de reapuração `❌` | `JanaAreaHeader.tsx:114-120` — `reapurar()` faz `router.reload` e só avança a hora no `onSuccess` | **#5429**, 2026-08-07 — a linha nasceu falsa, 10 dias depois do fato |
| R1 · ação Configurar `🟡 promessa` | abre `_components/JanaConfigDrawer.tsx` | #5878, 2026-08-17 |
| R6 · cabeçalho `🟡 sem o subtítulo` | `JanaCockpit.tsx:564-566` renderiza *"clique num card pra ver de onde vem o número"* | anterior a esta janela |
| R8 · drawer Configurar `❌` | idem R1 | #5878 |
| R9 · carregando `❌` | `_components/JanaCockpitSkeleton.tsx` + `carregandoCockpit` | #5862, 2026-08-17 |
| R10 · persistência `localStorage` `❌` | `useJanaConfig.ts` grava em `oimpresso.jana.cfg` | #5878 |
| R3 · chips do brief `❌` | os chips **existem** — são **três** e nenhum tem `onClick`. É `🟡` (botão morto), não `❌` (ausente). Âncora de SÍMBOLO: `grep -n "Ver top devedores" resources/js/Pages/Jana/_components/JanaCockpit.tsx`. Travado por **UC-JPAIN-16** desde 2026-08-27 | — |

**A lição de método, porque a classe reincide:** um documento de comparação é *derivado*, e derivado
citado depois do prazo vira afirmação. Antes de usar qualquer linha daqui como veredito,
**re-meça o lado vivo** — `grep` no componente, não a memória desta tabela.

---

## R1 · Header e identidade

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| barra de identidade | `JanaHeader` — empresa, `biz=`, "Atualizado HH:MM" | `JanaAreaHeader` (PageHeader canon) | ✅ equivalente |
| botão de reapuração | `onRefresh` → recalcula + toast | `reapurar()` → `router.reload`, hora avança só no `onSuccess` | ✅ (sem o toast — ver R9) |
| selo de plano | `jm-plano` — "plano Pro/Grátis", clicável → abre Configurar | página separada `/ia/pro` + botão "Jana Pro" na seção Metas | 🟡 diverge |
| ação Configurar | `JmConfigDrawer` (drawer real) | `JanaConfigDrawer` — deliberadamente **menor** que a âncora (charter v8) | ✅ |
| ação Exportar | dropdown — Painel PDF · Metas CSV · Fatos LGPD | `<Button title="(em breve)">` sem rota | 🟡 **decisão [W] aberta** |

## R2 · Navegação da área

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| abas | `JmTabs` — Painel · Metas* · Conversa · Memória | `JanaSubNav` | ✅ equivalente |
| contador nas abas | `n` por aba (nº de conversas, nº de metas) | — | ❌ **precisa de backend** |
| aba Metas | opcional (`metasMode="aba"`); default é seção do Painel | Metas é bloco da própria tela | ✅ equivalente ao default |

> **Por que o contador não é wiring de frontend.** O `JanaSubNav` não conhece aba nenhuma: ele lê
> `shell.menu` (`usePage().props.shell`), declarado pelo `DataController` do módulo, e repassa os
> `ghosts` ao `PageHeaderTabs`. Um contador tem que nascer lá, com a contagem escopada por
> `business_id` — e ⚠️ o `JanaSubNav` é **compartilhado pelas 4 telas** da área (Painel · Conversa ·
> Memória · Pro), então mexer nele mexe nas quatro.

## R3 · Brief diário

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| bloco do brief | `BriefDiario` — texto rico com ênfase por tom | bloco de brief no `JanaCockpit` | ✅ |
| chips de pergunta | 4 chips clicáveis que semeiam a conversa | **3 chips, todos sem `onClick`** | 🟡 **botão morto** |
| ouvir áudio (TTS) | `onAudio` condicionado ao toggle `cfg.audio` | botão presente, `title="(em breve)"` | 🟡 |
| gate por plano | sem Pro → card de upsell no lugar do brief | — | ❌ produto |
| aviso de viewport | `jm-nota-mob` | `md:hidden` no topo do Painel — casa o `@media (max-width:768px)` do protótipo | ✅ **(#5881)** |

> **Por que os chips não viraram clicáveis nesta onda.** Semear a conversa é exatamente o que o
> charter §Anti-hooks proíbe prometer: medido em 2026-08-07, `ChatController@novaConversa` **não**
> aceita pergunta inicial e o `Chat.tsx` **não** lê query param. Fazer o chip navegar pra
> `/ia/conversa` sem semear entregaria uma conversa em branco sob um rótulo que promete um assunto
> ("Disparar régua WhatsApp pros N atrasados") — trocaria um botão morto por um botão que mente.
> O conserto honesto é backend + Page, num PR próprio.

## R4 · KPIs

| # | protótipo | tela viva | veredito |
|---|---|---|---|
| 1 | Receita mês | **Receita 30 dias** | ✅ **divergência DELIBERADA** — ver nota |
| 2 | A receber vencido (com `emphasize`) | **A receber vencido** | ✅ **(#5881)** — e o nome novo é mais preciso: é `overdueValue`, o que venceu e não foi pago |
| 3 | Ticket médio | Ticket médio | ✅ |
| — | *(o protótipo não tem 4º KPI)* | ~~PIX hoje~~ **removido 2026-08-31** | ✅ **paridade fechada** (UC-JPAIN-18) |
| — | KPI clicável quando existe análise do mesmo dado (`JM_KPI_DRILL`) | 2 dos **3** abrem drill | ✅ |

> **O 4º KPI: fechado em 2026-08-31, e a nota anterior estava errada.** Ela dizia que o protótipo
> traz `Frota utilização` no 4º slot. **Medido e falso:** `grep -in 'frota\|truck' jana-merge.jsx`
> → **rc=1, zero ocorrências**, com controle positivo no mesmo arquivo (`grep -c JM_KPI_DRILL` →
> 2, rc=0). E `getJanaData().kpis` publica **3** entradas, não 4 — não existe 4º slot pra disputar.
> A frota sobrevive só no `chat-jana.jsx` (não-âncora), e **nem lá é KPI**: é o ícone `truck:` e a
> classe CSS `jc-an-frota`.
>
> O que a nota antiga acertava e **segue valendo**: o veredito [W] de 2026-08-07 (*"Frota
> utilização são alucinação, ninguém usa"*) e a ausência de fonte (`Vehicle` é do `OficinaAuto`;
> a `/ia` do núcleo atende ROTA LIVRE, vestuário). Nada disso muda — só deixou de haver tentação
> na âncora. Por decisão [W], o slot foi **fechado** removendo o `PIX hoje`, e a ordem dos três
> restantes passou a casar 1:1 com a do protótipo (`KpiGrid cols={3}`).
>
> ⛔ **[W] revogou a objeção em 2026-08-31** — textual: *"essa ressalva deve ser por isso que não
> fica igual. deve ser revogado. que igual."* A regra: **o conjunto de KPIs é o que a âncora
> renderiza**, e não se re-litiga. O **dado** (`pixHojeTotal`) ficou — a ação "PIX adoção" e a
> linha do brief seguem consumindo. Segue valendo só a regra de FONTE: derivar do `chat-jana.jsx`
> o que ele **não** renderiza (frota) é proibido; espelhar o que a âncora renderiza é paridade.

> **Nota sobre o 1º KPI — a divergência de rótulo é intencional.** O protótipo diz `Receita mês`;
> a tela viva diz `Receita 30 dias`, porque o dado é `whereBetween(transaction_date, [hoje-29,
> hoje])` — 30 dias **deslizantes**. O UC-JPAIN-14 corrigiu a palavra com esse fundamento, e no
> protótipo ela é coerente só porque lá o delta ao lado é "vs mai/25" (mês contra mês). **Aqui é o
> protótipo que está atrás**; copiar a copy dele reintroduziria bug conhecido.

## R5 · Metas

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| seção | `JmMetasSecao` — "METAS ATIVAS" com seletor de período | `SectionTitle` (`.jc-h2`) "Metas ativas" + controles em `ml-auto` | ✅ **(2026-09-18)** — era 🟡 com 4 linhas; ver nota |
| seletor de período | 3 janelas clicáveis (`JM_PERIODOS`) | — | ❌ **precisa de backend** |
| "Nova meta" | botão no cabeçalho da seção | `<a href>` **nativo** pra `/ia/metas/create` | ✅ **(#5881)** — ver nota |
| card | `JmMetaCard` — farol + período + valor/alvo + **barra de progresso** + % + **projeção** | `MetaCard` — farol + período (#5881) + alvo + **barra** + % + sparkline | 🟡 **sem a projeção** — ver nota |
| série histórica | `JmSerie` — 12 barras no drawer | 12 barras no `JanaMetaDrawer` + sparkline no card | ✅ **(esta onda)** |
| abrir a meta | `JmMetaDrawer` — **drawer na própria tela** | `JanaMetaDrawer` — drawer; o caminho pra tela própria virou "Abrir a meta" no rodapé | ✅ **(esta onda)** — era o buraco #1 |
| empty state | dois textos distintos (vazio × erro) + CTA correspondente | um empty state | 🟡 — o payload não distingue erro |

> **A projeção é divergência deliberada, não pendência de wiring.** O protótipo projeta o fechamento
> **no frontend** (`jmMeta()`: extrapola o ritmo quando a meta acumula, projeta a tendência da série
> quando é média/taxa). Portar isso repetiria letra por letra o defeito que o charter já catalogou no
> farol — §Anti-hooks *"⛔ Cálculo de farol no frontend — fonte autoritativa `ApuracaoService::farol`"*.
> Projeção é veredito sobre o futuro: nasce no servidor ou não nasce. No lugar dela, o drawer mostra
> **"% do alvo"**, que é aritmética sobre os dois números já exibidos.
>
> **Idem a "nota" por meta** (*"mix de produto puxando pra baixo"*): o payload de `/ia` não tem esse
> campo, e escrevê-la seria a mentira com selo de autoridade que o `JanaDrillDrawer` existe pra evitar.
>
> **Por que o seletor de período precisa de backend:** `IndexController::buildMetasPayload` carrega
> só `periodoAtual`. Trocar a janela no cliente exigiria a série de períodos no payload — não há o
> que filtrar.

> **CABEÇALHO FECHADO em 2026-09-18 — de 4 linhas para 1, por decisão [W].** Prod tinha badge
> `METAS` + `Acompanhamento contínuo` + h2 de 20px `Metas ativas` + a contagem
> `N metas ativas — visão consolidada do business`. A âncora `JmMetasSecao` tem **uma** linha:
> `<h2 class="jc-h2"><JcIcon name="target"/> METAS ATIVAS <span class="jm-per">…` com os controles
> em `margin-left:auto`. Agora a tela usa o mesmo `SectionTitle` da réplica `.jc-h2`
> (**UC-JPAIN-27**) com os botões existentes no `ml-auto`.
>
> **A trava era de CONTRATO, não de forma, e ela foi levantada explicitamente.** `Metas ativas` e
> `Acompanhamento contínuo` eram copy **pinada** em `governance/design/contracts/jana-painel.contract.json`
> §`painel-metas-header`, e a `_nota_metas_header` de 2026-08-31 registrava a divergência dizendo
> *"não corrigida aqui porque copy pinada é lei [W]"*, com duas saídas oferecidas — remover ou
> manter como acréscimo consciente. **[W] escolheu REMOVER** quando a pergunta lhe foi feita
> diretamente (2026-09-18: *"agora é o Protótipo quem manda, e a paridade deve ser o objetivo"*).
> O contrato foi atualizado **no mesmo PR** — sem isso o gate `contrato-de-tela` reprova —, e a
> revogação ficou escrita nele, ao lado da nota original, que **não** foi apagada.
>
> **Duas pegadinhas medidas, registradas porque custam tempo a quem repetir:**
> 1. **O `data-contract` tem que ser a string LITERAL no arquivo do `alvo`.** Passar `dataContract`
>    camelCase pro `SectionTitle` entrega o atributo no DOM e **ainda assim** reprova
>    (`X seção "painel-metas-header" sem âncora data-contract no alvo`) — o gate faz busca textual.
>    A prop do componente chama-se `'data-contract'`, com hífen, por isso.
> 2. **O mock do `JanaCockpit` no `janaMetaCardRodape.spec.tsx` quebrou** ao ganhar o export
>    `SectionTitle`: 5 casos do UC-JPAIN-21 abortaram com `No "SectionTitle" export is defined on
>    the … mock`, e a falha **não se anuncia como de mock** — parece que o card de meta quebrou. O
>    stub precisa renderizar os children (os botões do cabeçalho moram lá dentro agora), que é a
>    mesma armadilha que aquele arquivo já documentava para o stub do cockpit.
>
> ⚠️ **O seletor de período e o `Farol | Cadastro` NÃO vieram** — seguem ❌ backend, pela razão do
> parágrafo acima. O cabeçalho fechou na FORMA e na COPY; a capacidade continua pendente.

## R6 · Análises

| # | protótipo | tela viva | veredito |
|---|---|---|---|
| 1 | Inadimplência (buckets de aging) | Inadimplência | ✅ |
| 2 | Faturamento (sparkline 24m) | Faturamento (30 dias) | 🟡 janela diferente |
| 3 | Concentração (Pareto Top 10/50/100) | Top 5 clientes | 🟡 recorte diferente |
| 4 | Churn ouro (LTV alto inativos) | — | ❌ sem fonte |
| 5 | Frota (donut) | — | ❌ sem fonte (ver nota R4) |
| 6 | Cheques previsão | — | ❌ sem fonte |
| — | — | Métodos de pagamento | 🟢 só na viva |
| — | cabeçalho "ANÁLISES PRINCIPAIS" + subtítulo "clique num card pra ver de onde vem o número" | `SectionTitle` **com** o subtítulo | ✅ |
| — | drill-down por card | `JanaDrillDrawer` | ✅ |
| — | toggle por análise no Configurar | `analisesVisiveis` (4, não 6) | ✅ pro que existe |
| — | gate por plano (sem Pro → upsell) | — | ❌ produto |

⛔ **Não copiar da âncora:** os 6 `Analise*Service` que o protótipo cita como fonte **não existem no repo** (medido em 2026-08-17 no espelho **e** no Cowork vivo: `SellsCockpitAggregator` aparece 0×). O anti-hook do charter continua valendo — a fonte citada no drill tem que existir. Use `app/Services/Sells/SellsCockpitAggregator.php`. Idem `MetricasApurador::farol`, citado no `JmMetaDrawer`: a classe existe, o método **não** — o real é `ApuracaoService::farol`, e é ele que o `JanaMetaDrawer` cita.

## R7 · Ações sugeridas — comparado linha a linha (o que faltava em 2026-08-17)

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| seção | "AÇÕES QUE **JANA** SUGERE" (`data.person.name`) | "Ações que **Jana** sugere" | ✅ **(2026-09-18)** — era ❌, ver nota |
| linha de ação | `AcaoRow` com CTA por tom | equivalente (ícone + título + sub + CTA) | ✅ |
| ao clicar o CTA | `JmAcaoModal` — confirma antes de disparar (HITL) | `JanaAcaoModal` — prévia do servidor + **Aprovar** grava em `jana_acao_aprovacoes` | ✅ **(esta onda)** — registra a decisão; o **disparo** é PR próprio (por isso o CTA diz "Revisar") |
| gate por plano | só no Pro | — | ❌ produto |

> ⚠️ **O ✅ da primeira linha era FALSO-VERDE, de 2026-08-17 até 2026-09-18 — e a notação foi o
> vetor.** Escrito `"AÇÕES QUE <NOME> SUGERE"` × `"Ações que <Nome> sugere"`, o par abstraiu num
> placeholder comum exatamente o que divergia: **quem é o nome**. Na âncora é `data.person.name`
> — a **Jana** (`{ name: "Jana", role: "Analista IA" }`, `chat-jana.jsx:59`). Na tela viva era
> `firstNameUpper`, derivado de `userName` — o **usuário logado**. Prod dizia "AÇÕES QUE WAGNER
> SUGERE" e atribuía ao leitor sugestões que o servidor derivou de 5 regras sobre o dado dele.
> Não era divergência de copy: era troca de **sujeito**, e o `<Nome>` a escondeu por 32 dias.
>
> **O antes→depois REAL em prod é `VOCÊ` → `Jana`.** Medido em 2026-09-18 (`/ia` autenticado,
> biz=1, dark, DOM estabilizado): o h2 renderizava `Ações que VOCÊ sugere`, porque `userName`
> chega **falsy** e o fallback `|| 'você'` de `:318` está ativo — o discriminante é a saudação,
> que sai `Boa tarde.` sem nome. **Causa medida:** a tabela `users` **não tem coluna `name`**
> (migration `2014_10_12_000000:17-27`; `app/User.php` sem `getNameAttribute`, 0 hits, controle
> positivo `getUserFullNameAttribute:310`), e **6** controllers leem `->name` — 5 em
> `Modules/Jana/` e um em `Modules/KB/…/MemoriaController.php:56` (aba Memória). São **dois
> defeitos empilhados**. ⛔ O conserto do outro **não** é `user_full_name`: `surname` é PREFIXO
> (`profile.blade.php:78` = `business.prefix`), o que daria `Boa tarde, Sr.`. É `first_name`, em
> PR de backend próprio.
>
> **Corrigido** em `_components/JanaCockpit.tsx` (o título passa a nomear a Jana; `firstName`
> segue personalizando a SAUDAÇÃO, que é uso legítimo e a âncora também personaliza). Travado por
> `tests/janaAcoesAutoria.spec.tsx` (**UC-JPAIN-24**), com mordida provada por mutação: restaurado
> o `firstNameUpper`, 4 dos 8 casos caem por `AssertionError` — incluindo
> `expected 'Ações que WAGNER sugere' to be 'Ações que LARISSA sugere'`, que exibe o defeito, e
> `expected 'Ações que VOCÊ sugere' to contain 'Jana'`, que reproduz o estado de produção.
>
> **A lição de método, porque ela vale além desta linha:** comparação registrada com placeholder
> (`<NOME>`, `<X>`, `…`) só é honesta quando o placeholder é o MESMO dado dos dois lados. Quando
> ele abstrai a própria variável em disputa, o veredito mede a FORMA da frase e cala sobre o
> conteúdo — e um ✅ assim é pior que ausência, porque desliga a cobrança. Ao registrar par de
> copy que interpola, escreva o VALOR resolvido de cada lado, não o molde.

**As linhas, uma a uma.** A âncora tem **4 ações fixas** (dados do Martinho); a viva **deriva** as
suas de 5 regras sobre o dado real, então a contagem varia por tenant. O par:

| âncora | viva | veredito |
|---|---|---|
| Régua de cobrança · clientes >90d sem contato | `regua-whatsapp` — "Régua WhatsApp · N vendas vencidas" · CTA **Revisar régua** | ✅ mesmo par |
| Reativação · clientes "ouro" inativos | — | ❌ depende da análise **Churn ouro**, que não tem fonte |
| Outbound · caçambas paradas >7d | — | ❌ depende de **Frota** (ver R4) |
| Limpeza · títulos candidatos a baixa (>365d) | — | ❌ **o dado EXISTE** (`ageingBuckets['>365d']`), falta a regra — mas ver a nota |
| — | `negociar-top` · `investigar-ticket` · `pix-adocao` · `preventivo-pendentes` | 🟢 quatro regras só na viva |

> **Por que "Limpeza" não entrou naquela onda, mesmo com o dado na mão.** Toda ação da viva tinha CTA
> **morto** (`title="(HITL — em breve V2)"`, zero `onClick`). Acrescentar uma quinta linha morta não
> aproximava da âncora — aproximava do problema que a âncora resolve com o `JmAcaoModal`. A ordem
> certa era HITL primeiro, linha depois.
>
> _**Atualizado 2026-08-18 (o HITL landou).** O CTA não é mais morto: abre o `JanaAcaoModal`, com
> prévia do servidor e aprovação registrada. A trava da "Limpeza >365d" deixou de ser essa — sobra o
> trabalho dela mesma: a **regra** que a deriva no `JanaCockpit` §acoes, a chave em
> `AcaoHitlService::ACOES` (sem ela o botão abre e morre em 404 — o teste do UC-12 conta as duas
> pontas) e o texto da prévia. Não entrou aqui porque este PR é o HITL; é PR próprio, e pequeno._

## R8 · Overlays

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| drill "de onde vem o número" | `JmDrillDrawer` | `JanaDrillDrawer` | ✅ |
| drawer de meta | `JmMetaDrawer` | `JanaMetaDrawer` | ✅ **(esta onda)** |
| modal de ação (HITL) | `JmAcaoModal` — 4 prévias em texto FIXO (biz=164), citando `Analise*Service` inexistentes | `JanaAcaoModal` — prévia **do servidor** (`GET /ia/acoes/{key}/previa`) | ✅ **(esta onda)**, menor de propósito |
| drawer Configurar | `JmConfigDrawer` — 6 análises, brief on/off + hora, áudio, retenção | `JanaConfigDrawer` — 4 análises + HITL travado | ✅ menor **de propósito** (charter v8) |

## R9 · Estados e feedback

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| carregando | `JmPainelSkeleton` (variante compacta por aba) | `JanaCockpitSkeleton` | ✅ |
| `<Deferred>` na prop deferida | — | `coworkAggregates` é `Inertia::defer` e a Page **não** embrulha | 🟡 guarda por `?.`/`?? []` (allowlist do gate) |
| vazio | EmptyState com copy própria + CTA "Ir para a Conversa" | EmptyState "Nenhuma meta cadastrada ainda" | 🟡 |
| erro | EmptyState `variant="error"` + "Tentar de novo" com estado `tentando` | — | ❌ o payload não distingue erro de vazio |
| toast | `jm-toast` em reapuração, export, ações | aprovação de ação toasta pelo handler **global** do `app.tsx` (`router.on('success')` → `flash.success`); reapuração e export seguem sem | 🟡 **parcial (esta onda)** — a Page **não** monta toast próprio: seria em dobro |
| aviso mobile | "O painel foi desenhado pro escritório (1280px)…" | idem, `md:hidden` | ✅ **(#5881)** |

> ⚠️ **O gate de pixel NÃO enxerga a seção de ações — medido em 2026-08-18, no PR #5895.**
> A tela `Jana` está no manifesto do visreg e o gate **roda** (`it Jana bate com a baseline de pixel`,
> e o mapeamento resolve `_components/JanaCockpit.tsx` → `Jana` corretamente). Mas
> [`database/seeders/VisregTenantSeeder.php`](../../../database/seeders/VisregTenantSeeder.php) semeia
> **zero** `transactions` — e as 5 regras de `acoes` dependem de venda (`overdueCount`, `deltaTicket`,
> `pixHoje`, `totalPendentes`). Sem venda, `acoes` sai vazio, o `{acoes.length > 0 && …}` não renderiza
> e a seção inteira fica **fora do DOM**.
>
> Consequência: o #5895 trocou os 5 rótulos do CTA e acrescentou um modal, e o pixel-diff deu verde
> **sem ter visto um pixel disso**. Vale pra qualquer mudança futura naquela seção — e, pelo mesmo
> mecanismo, para todo bloco desta tela que só renderiza com dado (o brief, os KPIs derivados de
> `coworkAggregates`, os buckets de inadimplência).
>
> **Verde real, não skip-as-pass** — o job executou 12 min. O que falta não é execução, é **dado**.
> Conserto possível: semear venda vencida no tenant do visreg (PR próprio; conserta o ponto cego pra
> sempre). Enquanto não houver, "visual-regression verde" nesta tela **não** é evidência de que a
> mudança foi vista — e dizer que é seria a classe LC-13 (verde por não-execução) num eixo novo: verde
> por **ausência de dado**.

## R10 · Plano e upsell

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| selo do plano | `jm-plano` no header | — | ❌ produto |
| upsell inline | card no lugar do brief / das análises quando fora do Pro | link "Jana Pro" → `/ia/pro` | 🟡 |
| persistência da config | `localStorage` `oimpresso.jana.cfg` | idem, via `useJanaConfig` | ✅ |

---

## Rodada MEDIDA de 2026-08-21 — design-diff + sonda escopada (as 4 telas)

> **Por que esta seção existe.** As comparações anteriores deste doc foram feitas por leitura e
> por presença de texto. Nesta rodada o veredito veio do `design-diff.mjs` (mesma sonda injetada
> nos dois lados, computed style) e de uma sonda escopada ao container da tela. Duas coisas
> mudaram de figura, e uma delas **derruba um achado anterior meu**.

### O veredito da máquina (`design-diff --compare`, dark × dark)

| dim | campo | produção | protótipo | veredito |
|---|---|---|---|---|
| D2 | layout (contagem/overflow) | ok | ok | ✅ IGUAL |
| D4 | título font-size | **22px** | **19px** | ❌ DIVERGE (Δ3px · banda ±1px) |
| D4 | kpi valor font-size | **24px** | **22px** | ❌ DIVERGE (Δ2px · banda ±0px) |
| D6 | cor (accent/texto) | ok | ok | ✅ IGUAL |
| D8 | kpi.tag | **BUTTON** | **DIV** | ❌ DIVERGE |

O título é **22px nas 4 telas** (`/ia`, `/ia/conversa`, `/ia/memoria`, `/ia/pro`) — a divergência é
**sistemática do shell**, não do Painel.

⚠️ **A linha `kpi valor font-size` só existe a partir de hoje.** A sonda media `valueFontPx` desde
sempre e o `--compare` **nunca lia o campo** — a dimensão D4 se anunciava como "tipografia"
medindo só o título, e saía verde. Consertado em [#6098](https://github.com/wagnerra23/oimpresso.com/pull/6098),
com o selftest passando a morder o campo (controle negativo: bloco desligado ⇒ `rc=1`).

### Achado ANTERIOR meu que a máquina REFUTOU

Eu havia reportado `text-align` inconsistente entre os KPIs de produção (`left` em dois, `start` em
dois). **Falso como divergência visual:** o comparador normaliza `start`→`left` — são equivalentes
em LTR e os quatro renderizam igual. A inconsistência existe no **código**, não na tela. Achado
retirado.

### Landmarks — lacuna nova, e ela NÃO é regressão da migração

| landmark | protótipo | produção |
|---|---|---|
| `<main>` | **0** | **0** |
| `<nav>` | 1 | **0** |
| `<h1>` | 1 | 1 |

**Sem `<main>`, leitor de tela não tem como pular a sidebar e ir ao conteúdo.** O protótipo tem o
mesmo defeito, então isto é **lacuna de origem**, não algo que a migração quebrou. O `<nav>`, esse
sim, produção perdeu.

Medido: `SiteLayout.tsx` (site público) emite `<main>`; o **AppShell do cockpit não emite**, e
nenhum teste trava isso. Nem o pixel-diff nem o `design-diff` olham landmark — o eixo é cego nos
dois. ⚠️ **Consertar toca o shell de 213 telas** (visreg no parque inteiro): é decisão [W], não
conserto de PR de tela.

### Tamanho de conteúdo — MEDIDO mas INCONCLUSIVO, e o registro é este

Sonda escopada ao container da tela (`.main` nos dois lados, achado subindo do `h1` até sair do
shell — o `body` inteiro incluía a sidebar e inflava tudo):

| tela | protótipo | produção |
|---|---|---|
| Painel | 2925 | 1685 |
| Conversa | 1603 | 456 |
| Memória | 1135 | 466 |

**Não conclua "faltam capacidades" daqui.** O protótipo roda com dado MOCK populado (conversas,
memórias, metas); a produção medida é `biz=1`, que está com **0 metas e sem histórico**. Ausência
de renderização não é ausência de capacidade — é a lápide §5 2026-08-18, e ela vale exatamente
aqui. Para o número virar veredito seria preciso medir um tenant com dado equivalente.

### O que ficou fora da rodada

`Jana/Pro` **não tem âncora** (`node scripts/design/ancora.mjs Jana/Pro` → *"charter sem
related_prototype nem -page.jsx"*): o `jana-pro.jsx` é um dos **21 de 116** arquivos que o shell do
espelho referencia e que nunca desceram. Sem a fonte, a tela não é comparável — e o bloqueio é o
mesmo das partes do payload.
### As duas regiões que faltavam — metas e análises

A rodada acima mediu **brief** e **KPIs** e deixou **metas** e **análises** sem medir. A causa era o
coletor, não as telas: a sonda por região delimitava cada bloco pelo elemento-**folha** do seu
cabeçalho, e nenhum desses dois títulos é folha — `METAS ATIVAS` divide o `<h2>` com os chips de
período, e `ANÁLISES PRINCIPAIS` divide com um `<span>` de subtítulo.

**Controle negativo, rodado antes do conserto** (protótipo, mesma página):

| alvo | nós que contêm o texto | folhas (REGRA ANTIGA) | mais-fundo (REGRA NOVA) |
|---|---:|---:|---:|
| `ANÁLISES PRINCIPAIS` | 8 | **0** | 1 (`h2.jc-h2`) |
| `METAS ATIVAS` | 11 | 1 | 1 (`h2.jc-h2`) + 1 `<script>` |

O `0` da linha de cima é o defeito inteiro — a regra antiga não achava o cabeçalho, então a região
não existia pro coletor. **O mesmo defeito me fez reportar antes que a produção não tinha os
títulos de seção. Ela tem** (`JanaCockpit.tsx:675` e `:871`).

⚠️ A regra nova tem uma armadilha própria, que só aparece neste protótipo: ele compila JSX em
runtime, então o **texto-fonte vive num `<script>`** e casa a busca. O coletor passou a exigir nó
**renderizado** (`clientHeight > 0`, fora de `SCRIPT|STYLE|TEMPLATE`) — sem isso, mede-se o código
em vez da tela.

#### Condições da medição — e por que elas mudaram

| | valor | por quê |
|---|---|---|
| viewport | **2560×951**, nos dois lados | a janela do Chrome em produção está maximizada e **não aceitou resize** (`outerWidth` seguiu 2561 após duas tentativas + Win32). Igualei pelo lado que eu controlo: o protótipo. |
| coluna de conteúdo | **2300px nos dois** | é a prova de que ficou pareado — não há `max-width` na tela (`maxWidth: none`) |
| tema | `dark` nos dois | |
| sidebar | expandida nos dois | ela muda a largura útil (1224 → 1020 a 1280px), logo muda toda altura |
| raiz que rola | `div.jc-page` (protótipo) · `main.main-body` (produção) | resolvida como *ancestral rolável mais próximo do cabeçalho* — a heurística "maior scroller da página" pegava a **sidebar** |

> **Estes números não continuam a tabela do brief acima.** Aquela rodada não registrou o viewport,
> e a `.jc-page` dela media 1519 contra os 1484/1646 que reproduzo aqui. Reportar as duas como se
> fossem uma série seria comparar em condições diferentes (§5 2026-07-26). O que **calibra** esta
> rodada é a produção: meço o brief em **242,8px** contra os **239** registrados — Δ3,8px.

#### ⛔ Achado que precede o veredito: o espelho do protótipo está DEFASADO

Antes de medir, a fonte foi provada. O servido é byte-idêntico ao
`prototipo-ui/cowork/Wagner/jana-merge.jsx` de `origin/main` (md5 `32262939ad3c`) — o espelho **é** canon.
Mas o canon **não é o design vivo**:

| | linhas | bytes |
|---|---:|---:|
| Cowork vivo (`DesignSync.get_file`) | **1117** | 58.381 |
| espelho no git | 944 | 48.324 |

**24 hunks · +234 −72 linhas** que nunca desceram — e a **primeira** divergência cai dentro de
`JM_METAS_BASE`. O que o espelho não tem: uma 6ª meta **sem apuração na janela**, que degrada pro
farol `cinza` e mostra *"Aguardando apuração…"* em vez de inventar veredito, mais a nota de que o
farol é veredito do **servidor** (`ApuracaoService::farol`) e o frontend só consome.

Medi então o **design vivo**, servindo-o à parte (cópia em scratchpad — o working tree do repo não
foi tocado). O custo da defasagem, isolado:

| região | espelho (git) | Cowork vivo |
|---|---|---|
| brief | 4 cores · 6 svg · 4 gaps | idêntico |
| **metas** | 4 cores · **35** nós de texto · fontes sem `13,3px` | **5** cores · **38** nós · `13,3px` presente |
| análises | 7 cores · 12 svg · 7 gaps | idêntico |

A defasagem atinge **exatamente uma** das duas regiões desta rodada. As tabelas abaixo usam o
**Cowork vivo**.

#### O medido

| dim | região | protótipo (vivo) | produção | veredito |
|---|---|---|---|---|
| altura | brief | 246,5 | 242,8 | ✅ ~igual (Δ3,7) |
| altura | metas | 144 | 480 | ⚠️ **não comparável** — ver abaixo |
| altura | análises | 501,4 | 945,5 | ⚠️ 1,9× — ver abaixo |
| fontes | brief | 13,5 · 13 · 12 · 10 | 14 · 12 · 10,5 | ❌ escala diferente |
| fontes | metas | 20 · 13,3 · 12,5 · 11,5 · 11 · 10,5 | 20 · 16 · 14 · 12 | ⚠️ estado vazio |
| fontes | análises | 21 · 17 · 13,5 · 12 · 11,5 · 11 · 10,5 · 10 | 14 · 12 · 11 · 10,5 · 10 | ❌ **a âncora tem 21 e 17px; produção para em 14** |
| gaps | análises | 7 (5·6·7·8·10·12·14) | 6 (4·8·10·12·16·24) | ❌ token vs escala Tailwind |
| svg | análises | 12 | 7 | ❌ |
| cores | análises | 7 | 4 | ❌ produção é mais monocromática |

#### Metas — o veredito é *não comparável*, e isso é um achado, não uma desistência

A região de metas em produção está em **estado vazio**: *"0 metas ativas"*, *"Nenhuma meta
cadastrada ainda"*. O tenant logado é **WR2 Sistemas**, que não tem meta nenhuma.

Comparar 144px de 5 cards populados (mock) contra 480px de um empty state e chamar a diferença de
divergência de design seria medir dado, não desenho. **A rodada anterior já tinha avisado disso** —
é o mesmo parágrafo do §"Tamanho de conteúdo" acima, agora confirmado numa segunda amostra
(biz=1 lá, WR2 aqui: os dois com 0 metas).

O que **é** comparável, e fecha certo: o empty state de produção é **contratado**, não improvisado —
`Index.tsx:389` carrega `data-contract="painel-metas-vazio"` e o `Index.casos.md:114` descreve a
frase exata. Só o **texto** diverge da âncora, que diz *"Nenhuma meta ativa neste período"* e
distingue `vazio` de `erro` (*"Não consegui apurar as metas"*, `JmMetasSecao({ vazio, erro })`) —
distinção que produção não expõe.

##### Não existe tenant com metas — medido, não suposto (2026-08-21)

A frase que estava aqui dizia que *"para medir a região populada é preciso um tenant com metas
cadastradas"*, o que sugere que exista um. **Não existe.** Medido em produção no mesmo dia:

| tabela | linhas |
|---|---:|
| `jana_metas` | **0** |
| `jana_meta_periodos` | 0 |
| `jana_meta_apuracoes` | 0 |

Em **todos os 88 businesses**. Controle positivo do instrumento, na mesma sessão: `business` = 88,
`transactions` = 75.349, `DB::connection()->getDatabaseName()` = a base de produção — o zero é do
dado, não de uma consulta que não rodou (§5 2026-08-01).

**Consequência, e ela reposiciona o achado acima:** o estado vazio não é o que *este* tenant vê — é
o **único estado de metas que a produção já renderizou**, para qualquer usuário. Logo a divergência
de copy contra a âncora (*"Nenhuma meta cadastrada ainda"* × *"Nenhuma meta ativa neste período"*)
não é nota de rodapé: é a **única** UI de metas que existe em produção hoje. E os 5-6 cards
populados da âncora nunca foram exercitados fora do mock.

> ⚠️ **Armadilha paga, para quem repetir a consulta:** a tabela **não** é `copiloto_metas`. Essa
> existe (legada, das migrations `2026_04_24_*`) e responde `0` **sem erro** — um zero plausível
> vindo da tabela errada. O `Meta` do `IndexController` declara `protected $table = 'jana_metas'`;
> resolva o model antes de contar (§5 2026-07-15: varredura contada, não semelhança de nome).

#### Análises — 6 na âncora, 5 em produção, e nenhuma das 2 ausências é gap

| # | âncora (Cowork vivo) | produção | veredito |
|---|---|---|---|
| 1 | Inadimplência · Top 20 devedores | Inadimplência · 1 venda vencida | ✅ |
| 2 | Faturamento · Curva 24 meses | Faturamento · 30 dias | ✅ (janela difere) |
| 3 | Concentração · Top clientes Pareto | Top 5 clientes · concentração | ✅ |
| 4 | Churn ouro · LTV alto inativos | Churn ouro · maior LTV parado | ✅ |
| 5 | **Frota · 91 caçambas avulsas** | — | ✅ **ausência CORRETA** |
| 6 | **Cheques previsão** | — | ✅ **ausência CORRETA** |
| 7 | — | **Métodos de pagamento · top 3** | 🟡 só em produção |

- **Frota** — `[W] MATOU a análise Frota em 2026-08-07`, registrado no cabeçalho do próprio
  `JanaCockpit.tsx` (*"este componente resiste por conta própria… mantenha assim"*) e no Non-Goal do
  `Index.charter.md`. Bate com a lápide §5 2026-08-10, que proíbe ressuscitar `Frota utilização`
  como KPI, card, aba ou análise. **A âncora ainda carrega o conceito** (`m3 "Utilização de frota"`,
  `truck: "frota"`) — quem derivar dela sem conferir reabre um item morto.
- **Cheques previsão** — `Index.casos.md:187`: *"churn, frota e cheques são a ordem 7 do mapa
  (fonte de dado que não existe)"*.
- **Métodos de pagamento** entra por `JANA_ANALISES` (`useJanaConfig.ts:35-41`), que tem **5**
  entradas, na ordem exata dos 5 cards medidos, com guarda em `UC-JPAIN-10`.

Ou seja: **zero ausências inexplicadas em análises.** A altura 1,9× maior e o `21/17px` que some
não são "faltam cards" — são densidade e escala tipográfica dos cards que existem nos dois lados.

#### Dois consertos de documentação que a medição cobra

1. **A prosa diz 4 cards; a tela renderiza 5.** `Index.charter.md:140` (*"6 toggles… quando a tela
   renderiza **4** cards"*) e `Index.casos.md:187` ficaram para trás quando o churn ouro entrou pelo
   UC-13. O próprio charter se contradiz 6 linhas abaixo (`:146`, *"`JANA_ANALISES` (5, desde o
   churn ouro)"*). Medido: `JANA_ANALISES.length === 5` e 5 cards no DOM. Pela precedência, quem
   perde é a prosa — correção em PR próprio.
2. **A tabela de landmarks acima envelheceu.** Ela registra `<main> 0 · <nav> 0` em produção. Medido
   agora: **`<main> 1 · <nav> 1 · <h1> 1`** — a lacuna foi fechada por
   [#6101](https://github.com/wagnerra23/oimpresso.com/pull/6101). O protótipo segue com `<main> 0`.

#### Como reproduzir

Sonda de região, injetada idêntica nos dois lados: resolve a raiz rolável como *ancestral rolável
mais próximo do cabeçalho*; aceita título composto pelo nó **mais fundo** que contém o texto e é
renderizado; delimita a região pelo **maior ancestral que não invade outro cabeçalho**; posição
relativa via `rect.top − (raiz.rect.top − raiz.scrollTop)`. Frescor da fonte por
`DesignSync{get_file}` comparado ao md5 do arquivo servido.

⚠️ Três armadilhas pagas, para quem repetir:

- o protótipo guarda a rota em `localStorage['oimpresso.route']` e reabre onde parou — mexer em
  `.app.className` derruba a SPA pra outra tela, e o reload **não** conserta; clique no item de menu;
- ele também carimba `app--mobile` no load e **não** reavalia no resize: sem recarregar depois de
  mudar o viewport, mede-se o layout errado (brief 288 vs 266 no mesmo viewport);
- a sonda passou a ter guarda de identidade: sem `METAS ATIVAS` **e** `ANÁLISES PRINCIPAIS` no
  texto, ela devolve `ERRO: TELA ERRADA` em vez de medir a tela errada em silêncio.

---

## Resumo — o que falta, e o que trava cada um

| ordem | entrega | região | trava |
|---|---|---|---|
| ~~1~~ | ~~Modal de ação HITL~~ — **entregue**: prévia do servidor + aprovação registrada | R7/R8 | ✅ charter v10 · UC-JPAIN-12. **Sucessora:** o DISPARO (WhatsApp/e-mail) + a fila `/ia/acoes` |
| 2 | Seletor de período + projeção | R5 | **backend** — payload só traz `periodo_atual`; projeção é veredito de servidor |
| 3 | Chips do brief que semeiam a conversa | R3 | **backend + Page** — `novaConversa` não aceita pergunta |
| 4 | Contador nas abas | R2 | **backend** — nasce no `DataController` (⚠️ afeta as 4 telas da área) |
| 5 | Exportar (PDF/CSV/LGPD) | R1 | **decisão [W]** — some, `disabled` com motivo, ou entrega? |
| 6 | Toast + estado de erro | R9 | payload não distingue erro de vazio |
| 7 | Análises 4-6 (churn/frota/cheques) | R6 | **sem fonte de dado** |
| 8 | Gate de plano + selo | R1/R3/R6/R10 | **produto** |

### Fechado em 2026-08-17, por DOIS PRs em paralelo

Dois trabalhos atacaram este documento no mesmo dia. O crédito fica separado porque
é assim que a próxima sessão sabe onde procurar:

| entrega | quem |
|---|---|
| Aviso de viewport (`md:hidden`) · "Nova meta" (`<a href>` nativo) · período no card · rótulos dos KPI 1 e 2 | **#5881** |
| **Drawer de meta** (ordem 1 — o clique não tira mais o usuário da tela) · barra de progresso no card · re-medição deste documento | **#5882** |

> **Uma lição da colisão, e ela é técnica.** O #5882 também fazia "Nova meta", com
> `<Link>` do Inertia. O #5881 mediu que `MetasController@create` devolve **Blade** — e
> `<Link>` numa rota não-Inertia vira **clique no-op silencioso**. A versão do #5881 ficou;
> a do #5882 foi descartada na reconciliação. Antes de apontar `<Link>` pra uma rota da
> Jana, confira se o destino é Inertia ou Blade.

## Fora de escopo — decisões [W] ainda abertas

- **Golden PT-04 `draft` → `live`** — aprovação de screenshot (F1.5); trava o `ciclo-completo` desta tela **e** de outras duas.
- **`related_prototype`** — hoje `jana-merge.jsx`; o check `pt_declarado` só casa `PT-0X`, então o ciclo reprova enquanto ficar assim.
- **Título "Dashboard" × "Painel"** — a aba diz Painel, a rota é `/ia`, mas título, breadcrumb e o componente exportado dizem Dashboard.

## Paridade das ABAS — fechada em 2026-09-02 (3 PRs, uma aba cada)

> Fecha a linha *"abas: protótipo 6 × prod 3"* do handoff 2026-08-31 §Paridade Painel. Fonte
> provada antes de comparar: `jana-merge.jsx` re-baixado do Cowork vivo pela máquina (#6600 — o
> `JmTabs` passou a renderizar por `CliTabs`; a LISTA de abas não mudou) e `jana-telas-novas.jsx`
> re-lido pelo `get_file` (inline, 616 linhas nos dois lados). D0: os símbolos são `JmTabs` /
> `JmAlertas` / `JmAcoesFila` / `JmPlataforma`, não o cockpit de cobrança.

| aba (ordem da âncora) | protótipo | tela viva | veredito |
|---|---|---|---|
| Painel · Conversa | `JmTabs` | ghosts `dashboard` · `copiloto` | ✅ já era |
| **Alertas** (3ª) | `JmAlertas` — lista de desvios, chips, config em `localStorage` | `Jana/Alertas` (#6607): lista do `AlertaService::calcular`, chips de severidade, kebab "Abrir a meta" | ✅ **estrutura** · 🟡 **decisão** — silenciar/perguntar/config ficaram fora (servidor não honra; charter §Anti-hooks) |
| **Ações** (4ª) | `JmAcoesFila` — 5 ações fixas, recibo por `setTimeout` | `Jana/Acoes` (#6608): `AcaoHitlService::fila()`, `JanaAcaoModal` reusado, recibo gravado | ✅ |
| Memória | `JmTabs` | ghost `memorias` | ✅ já era |
| **Plataforma** (6ª, só superadmin) | `JmPlataforma` — alerta de gate + 2 listas + instalação `21·4·24` | `Jana/Plataforma`: gate REAL no menu e na rota, listas cruas, contagens do disco | ✅ · 🟡 alerta da âncora **caducou** (#6421) e ficou fora |
| contador `n` nas abas | `nConversas` · `nAlertas` | — | ❌ segue **backend** (R2) |

**O que ainda diverge do protótipo nesta tela (Painel):** o card **Cheques** × `metodos` — sem
fonte (ver `Index.casos.md` §Pendência do UC-JPAIN-18); decisão [W] sobre migrar
`FINANCEIRO_CHEQUE`. Medido em 2026-08-31, não re-litigado.

## Rodada MEDIDA de 2026-09-03 — Painel × `jana-merge.jsx` (mesma sonda, dark × dark, viewport 2560)

> Pedido [W]: *"quais camadas ainda faltam pra ficar com o design do protótipo — tabs em posição
> errada, KPIs feios, conversa diferente, memórias"*. Fonte provada antes de comparar: espelho
> `jana-merge.jsx` re-baixado em 2026-09-02 (#6600); `ancora.mjs Jana/Index` → `jana-merge.jsx`;
> `--preview-ds` **PREVIEW COMPLETO** (10 deps repostas); render pelo shell do espelho
> (`oimpresso.com.html`, tema `dark`, `oimpresso.jana.tab=painel`) × `/ia` em prod, mesma viewport.
> D0 (identidade de view): `data-screen-label="Jana — Painel"` nos dois lados. Veredito da
> máquina (`design-diff --compare --check`, snapshots em scratchpad): `DIVERGE(bug): 2` — título
> 22×19px, `kpi.tag` BUTTON×DIV — + 39 itens de SHELL a classificar (sidebar, fora desta tela).
> O que a sonda oficial NÃO mede (posição da tablist, anatomia do KPI) foi medido com a mesma sonda
> ad-hoc nos dois lados — números abaixo.

### Header + abas (→ **Onda 1**, UC-JPAIN-19, charter v13)

| item | âncora | produção (antes) | veredito |
|---|---|---|---|
| tablist | `nav` filho de `.jc-page` · `left=284 w=2237 h=36` · 14px abaixo do header (41px) | inline na Zona C do `PageHeader` · `left=1654 w=451 h=33` · `top=38` (mesmo do h1) | ❌ bug → **corrigido** (slot `below` do `PageHeader` canon) |
| aba | 13px/500 · ativa 600 + `border-b 2px accent` + bg `oklch(0.33 0.09 295/.5)` · ícone 14px · `padding 0 14px` | 13px/500 · ativa 600 + mesmo underline/pill · ícone 14px · `14px` | ✅ **(2026-09-18)** — `density="compact"`; ver nota |
| Zona R | `Atualizado 09:42` (dot) → `plano Pro` → Configurar → Exportar | `plano Pro` → Configurar → Exportar → **Conversar** (primary); "Atualizado" no subtítulo | ❌ → **corrigido** (Atualizado 1º da Zona R; primary removido) |
| subtítulo | mono 11.5px `OIMPRESSO MATRIZ · biz=164 · v1404…` | sans 12px | 🟡 → mono (`versão` é dado que a prod não tem) → ✅ **FECHADO 2026-09-03 (#6655)** — `font-mono tracking-wide` no `subtitle` do [`JanaAreaHeader`](../../../resources/js/Pages/Jana/_components/JanaAreaHeader.tsx); a `versão` segue ausente por falta de dado |
| título | 19px/700 | 22px/700 | ⛔ ~~DECLARADA · decisão [W]~~ **REVOGADO 2026-09-18** — `PageHeader` canon (ADR 0189) é Fundação/Shell de 37 telas |

> ⚠️ **A justificativa da linha `aba` estava FALSA, e isso é o achado — não a métrica.** Ela dizia
> *"13×14px fica (fidelidade travada em `pageHeaderTabsFidelity.spec`)"*. **Medido por mutação em
> 2026-09-18:** trocado o default do `PageHeaderTabs` para `compact`, aquele spec segue
> **13/13 VERDE**. Ele trava radius, underline `--accent`, pill do contador e o peso da aba
> **ATIVA** — font-size, padding e o peso da **inativa** passavam livres. O item não estava
> travado por teste nenhum: estava **não-feito**, e a frase dava a isso aparência de decisão
> técnica. É a mesma família do falso-verde do `<NOME>` registrado no §R7: **artefato afirmando
> uma garantia que não tem** desliga a cobrança melhor que um buraco declarado.
>
> **FECHADO em 2026-09-18 por `density="compact"`** — prop nova no `PageHeaderTabs`, usada só
> pelo `JanaSubNav`. Decisão [W], escolhida sobre outras três (deixar como está · componente de
> abas próprio da Jana · rever o protótipo do Clientes): o `JanaSubNav` **delega inteiramente** ao
> compartilhado e não tem markup de aba próprio, então "réplica local" custaria duplicar a barra
> inteira — diferente do `JanaKpiCard`, que replicava um card. O `default` segue **byte-idêntico**
> ao protótipo do Clientes (fixado por [W] em 2026-07-14) e as outras **5 áreas** (Financeiro,
> Forja, Governança, Patrimônio, Ponto) não passam a prop.
>
> **A rede que faltava agora existe:** `tests/pageHeaderTabsDensity.spec.tsx` (**UC-JPAIN-26**),
> 6 casos, sendo um deles exatamente *"o DEFAULT não se mexe"*. Bite-test comparativo, na MESMA
> mutação: o `fidelidade` fica **13/13 verde** e o `density` **cai em 2** (`default perdeu
> text-sm` + a comparação de className inteira). Sem esse caso, trocar a métrica das 6 áreas
> passaria em toda a suíte.
>
> ⚠️ **O ícone e o badge NÃO eram gaps.** A coluna dizia *"sem ícone"* — já estava corrigido — e o
> `badge` opt-in **existe no componente desde antes** (pill do contador, com cores de ativo/inativo
> travadas por 5 casos do próprio spec de fidelidade). O que falta para as abas da Jana mostrarem
> `Conversa 3` / `Alertas 3` é o **contador chegar do `DataController`** — backend, e com raio nas
> 4 telas da área, não UI ausente.
| avatar | 40×40 · r8 · accent | 40×40 · r8 · accent (`size-10 rounded-lg bg-primary`) | ✅ |

### KPIs (→ **Onda 2**, chip)

| item | âncora `.jc-kpi` | produção `KpiCard` shared | veredito |
|---|---|---|---|
| grid | `repeat(4, 1fr)` · gap 10 · 3 cards ocupam 3/4 | `grid-cols-3` · gap 12 · 3 cards ocupam tudo (`w=738`) | ❌ → ✅ **FECHADO 2026-09-03 (#6662)** — `KpiGrid cols={4}` + `gap-2.5` (10px) |
| card | `h=98` · pad `12px 14px 14px` · r **8px** · gap 3px · bg `surface` | `h=125` · pad `16px` · r **12px** · gap 8px · bg `card` | ❌ → ✅ **FECHADO 2026-09-03 (#6662)** — r8 por `--radius`, `pt-3 px-3.5 pb-3.5`, `gap-[3px]` |
| label | **mono** 10px/700 uppercase `.06em` · ícone 15px **inline** à direita do label (`.jc-kpi-h`) | sans 11px/600 uppercase · ícone dentro de **caixa 36×36** `bg-muted rounded-lg` | ❌ (é o "feio": a caixa de ícone e o padding) → ✅ **FECHADO 2026-09-03 (#6662)** — a caixa 36×36 sumiu; ícone 15px inline |
| valor | 22px/700 (`--fs-7`) · **28px** no `.emph` | 22px/600 · 22px no danger | 🟡 peso 700×600; emph 28px ausente → ✅ **FECHADO 2026-09-03 (#6662)** — 700 nos dois; `emph` no degrau `--fs-8` |
| delta/sub | `small` 11px `text-3` (`-68% vs mai/25` · `4.255 títulos · 76% inadimplência`) | `description` 12px só no vencido | 🟡 → ✅ **FECHADO 2026-09-03 (#6662)** — `small` 11px nos dois, e o delta passou a existir |
| card em alarme | `.emph`: bg `--neg-soft` (`oklch(0.36 0.12 25)`), borda `neg 22%`, ícone e texto `--text` | `tone=danger`: bg `destructive/5`, borda `destructive/20` | ❌ tinta sólida × 5% → ✅ **FECHADO 2026-09-03 (#6662)** — `bg-destructive-soft` (tinta do token), não mais 5% |
| tag | `DIV` (não clicável, salvo `jm-an-hit` por fora) | `BUTTON` (KpiCard com `onClick`) | 🟡 a sonda acusa; prod é clicável por design (drill) — classificar com [W] → ✅ **FECHADO 2026-09-03 (#6662)** — o card voltou a `DIV`; quem recebe o clique é o wrapper |


> **As 7 linhas acima FECHARAM em 2026-09-03, e a tabela ficou sem o ponteiro por 18 dias.**
> Ela preserva o retrato do dia — os `h=125`, `r 12px`, `sans 11px/600` e a **caixa 36×36** eram
> verdade quando foi escrita — e o conserto entrou às **17:44Z do MESMO dia** (a hora da
> medição não está registrada; a do merge, sim), pela
> [#6662](https://github.com/wagnerra23/oimpresso.com/pull/6662) (*"Onda 2 da paridade — os KPIs viram RÉPLICA do `.jc-kpi`"*), que criou
> [`JanaKpiCard.tsx`](../../../resources/js/Pages/Jana/_components/JanaKpiCard.tsx) em vez de mexer
> no `KpiCard` shared de 37 telas (réplica local, ADR 0388 §D-1).
>
> **A medição do DEPOIS não se repete aqui** (§5 2026-07-17: dois docs com o mesmo número drifam):
> ela está em [`Index.casos.md`](../../../resources/js/Pages/Jana/Index.casos.md)
> §*"Medição de runtime — mesma sonda nos dois lados (2026-09-03)"*, **13 campos**, com a única
> diferença residual declarada ali (4px de altura, `line-height` do `small` herdado do body de cada
> bancada). Travado por **UC-JPAIN-20** em
> [`tests/janaKpiReplica.spec.tsx`](../../../tests/janaKpiReplica.spec.tsx), mordida provada por
> mutação (`rounded-xl` + `bg-destructive/5` de volta ⇒ 3 de 13 caem).
>
> ⚠️ **O `emph` vale um parágrafo porque a linha `valor` acima confunde dois eixos.** A réplica os
> separou: `emphasis` rege **fundo + borda + o degrau `--fs-8`**, e `valueTone` rege **só a cor do
> valor**. No dataset da âncora os dois caem no mesmo card (N=1), e era por isso que pareciam um.
>
> ⚠️ **O `delta` existir não quer dizer que o CONTEÚDO bata.** A célula da âncora cita
> `-68% vs mai/25` e `4.255 títulos · 76% inadimplência`; o que fechou foi a **forma** (`small` 11px,
> cores por direção, o `%` escrito no texto). Quais deltas a prod publica depende do payload, e
> **isso não foi re-medido nesta passada**.

> ⚠️ **ESTA TABELA É O RETRATO DO *ANTES*, e ela derrubou uma sessão em 2026-09-21.** A coluna
> diz *"produção `KpiCard` shared"* — e o `JanaKpiCard` nasceu **no mesmo dia desta rodada**
> (2026-09-03, [#6662](https://github.com/wagnerra23/oimpresso.com/pull/6662), a "Onda 2" que a
> própria linha de ondas abaixo marca). A medição do **DEPOIS** existe e mora no arquivo irmão:
> **[`Index.casos.md` §"Medição de runtime — mesma sonda nos dois lados (2026-09-03)"]**, com
> 13 de 14 campos ✅.
>
> **FECHADO — re-medido na TELA VIVA em 2026-09-21** (staging autenticado, dark × dark, 1440,
> mesma sonda ad-hoc nos dois lados, canário acusando em ambos): **24 de 26 campos idênticos**.
> Os ponteiros `→ ✅ FECHADO` por linha vieram do [#7642](https://github.com/wagnerra23/oimpresso.com/pull/7642),
> de uma sessão irmã no mesmo dia; esta nota acrescenta o **re-teste** que os confirma no runtime — label `10px · 700 · mono ·
> ls 0.6px · uppercase`; ícone `15×15` inline sem caixa (`iconCaixaW: null`); valor `22px · 700 ·
> lh 22 · ls -0.44`. Grid, raio (8), padding (12/14/14/14), gap (3) e tag (DIV) idem.
>
> As duas diferenças restantes **não são dívida de forma**, e estão decompostas no
> `Index.casos.md` §UC-JPAIN-34: o `small` ausente no 1º card é **dado** (staging sem delta de
> receita), e os 4px de altura são `+6` (o card 2 da âncora está em `emph` por ter vencido real,
> o staging tem vencido zero) `−2` (borda que o render do espelho não pintou). ⚠️ Isso **refuta** a
> causa registrada em 2026-09-03 (*"line-height do `small` herdado do body"*): o `<small>` tem
> `line-height: 16.5px` idêntico nos dois lados.
>
> **O que a re-medição ACHOU de dívida real** foi outra coisa, num eixo que nenhuma rodada tinha
> medido: o **breakpoint do grid** (`.jc-kpis` quebra em 1100px; o `KpiGrid` em 1024/640). Isso
> bate com a queixa literal de [W] — *"quantidade de colunas de kpi"*. Fechado por réplica local
> `JanaKpiGrid`; medição por viewport e as **duas tentativas refutadas** (arbitrary variant sai
> inerte no Tailwind 4) em `Index.casos.md` §UC-JPAIN-34.

### Metas — não comparável hoje (0 metas em todos os tenants, medido 2026-08-21/08-31); âncora `METAS ATIVAS` mono + 5 cards em linha + `Nova meta` à direita × prod pill `METAS` + h2 + 3 botões + empty. Fica pra quando existir dado.

> ⚠️ **Este §Metas caducou em DOIS eixos — o título acima fica como fato datado (§5 2026-09-03).**
>
> **Eixo FORMA — fechado.** O `METAS ATIVAS` mono e o `Nova meta` à direita **existem hoje**: o
> cabeçalho passou a usar o mesmo `SectionTitle` réplica da `.jc-h2`
> ([#7555](https://github.com/wagnerra23/oimpresso.com/pull/7555), 2026-09-18, **UC-JPAIN-27**) e o botão vive num `ml-auto`
> ([`Index.tsx`](../../../resources/js/Pages/Jana/Index.tsx) §`painel-metas-header`). O
> `pill METAS + h2 + 3 botões` que esta linha descreve foi o que aquele PR **removeu** — a trilha
> completa está na nota *"CABEÇALHO FECHADO em 2026-09-18"* do §R5, e não se repete aqui.
>
> **Eixo DADO — a premissa "0 metas em todos os tenants" não vale mais.** Ela sustentou três
> rodadas (08-21 · 08-31 · 09-07) e o *"fica pra quando existir dado"*. Em 2026-09-21 a seção
> **virou comparável**: a contagem viva e a tabela item-a-item estão na rodada de 2026-09-21
> §**METAS** ([#7639](https://github.com/wagnerra23/oimpresso.com/pull/7639)) — **medição de sessão irmã, não desta**, e o número
> mora lá, não aqui.
>
> ⚠️ **"5 cards em linha" fechou como ESTRUTURA, não como densidade.** A prod renderiza os cards
> em grade quando há dado (deixa de cair no `EmptyState`), mas a grade é
> `sm:grid-cols-2 xl:grid-cols-3` com `gap-4` contra `auto-fit minmax(232px,1fr)` + gap 10px da
> âncora — **dívida aberta**, medida naquela rodada. Não carimbe esta linha como fechada por
> inteiro.


### Ondas (resolvidas em PRs separados, ≤300 linhas cada)

| onda | escopo | estado |
|---|---|---|
| **1** | abas em faixa própria · Atualizado na Zona R · sem primary · ícones · Conversa com "Nova conversa" | **este PR** |
| 2 | KPI no desenho `.jc-kpi` (réplica ADR 0388 em `_components/`, tokens; grid 4; `.emph`) | **[#6662](https://github.com/wagnerra23/oimpresso.com/pull/6662)** (2026-09-03) · anatomia re-medida ✅ em 2026-09-21 · o **breakpoint** do grid saiu depois, no UC-JPAIN-34 |
| 3 | Conversa: histórico como card `jm-hist` (busca ⌘K · chips · itens ricos · atalhos) · thread header `título · só sua` · composer com chips de sugestão | chip — medição em `Chat-visual-comparison.md` §2026-09-03 |
| 4 | Memória: largura toda · barra `busca + chips + n de m` · linha `jm-fato` com meta mono e botões-texto | chip — medição em `Memoria-visual-comparison.md` §2026-09-03 |
| 5 | título 19×22px (Fundação) · Exportar em menu 3 itens · contador nas abas (backend) | decisão [W] |


## Rodada MEDIDA de 2026-09-07 — por SEÇÃO, mesma sonda nos DOIS lados (dark × dark, 1440)

> **Por que esta rodada existe:** [W] cobrou — *"esta errada, comparou por seção com ancoragem dupla?
> em todas as camadas?"*. A resposta honesta era **não**: a onda 2.1 (#6944) foi validada por Pest +
> jsdom + um smoke que mediu 4 propriedades soltas do DOM. Comparação **por seção** não tinha sido
> feita. Esta é.

**Como foi medido** (o fluxo da skill `comparar-design-prod`, sem pular passo):

| passo | o que foi feito |
|---|---|
| âncora | `ancora.mjs Jana/Index` → `prototipo-ui/cowork/Wagner/jana-merge.jsx` (frescor verificado 2026-09-07) |
| lado design | espelho local servido em :5621, rota `chat` + tema dark, esperando `__oiLazyDone` **e** `.jm-sk` sumir **e** 1,5 s de janela quieta (1039 nós) |
| lado produção | `/ia` autenticado, biz=1, dark, 1440px, duas leituras iguais (1061 nós) |
| sonda por papel | `design-diff.mjs --probe` **byte-idêntica** nos dois lados (CSP da prod barra script externo → injetada inline) |
| sonda por seção | mapa de 9 seções do `alvos/jana--index.secoes.json`, com os seletores da prod colhidos do DOM (nunca de lembrança) |
| canários | 4, obrigatórios antes de qualquer veredito |

### O veredito da máquina foi **NÃO MEDI** (exit 2), e ele está certo

```
✗ DIVERGE(bug): 0     ⛔ NÃO MEDI — o lado design veio do espelho local e a fidelidade
                          dele não está provada. Última rodada é PARCIAL: 1/257 medidos.
```

Registrado como está: **"0 divergências" ali significa "igual a uma cópia de frescor desconhecido"**,
não "igual ao design". Fechar a rodada de frescor é pré-requisito de qualquer veredito por papel.

### Dois PONTOS CEGOS do comparador, achados por canário (e são de máquina, não desta tela)

Os canários provaram a sonda viva — e, no mesmo movimento, o que ela **não** vê:

| canário | esperado | obtido | leitura |
|---|---|---|---|
| `title.fontPx` 22 → 40 | DIVERGE | **DIVERGE** ✓ | sonda e comparador vivos |
| `kpi.textAlign` → center | DIVERGE | **DIVERGE** ✓ | pega o defeito histórico que criou a ferramenta |
| `primary.bg` → hue oposto | DIVERGE | **DIVERGE** ✓ | D6 viva |
| `title.weight` 600 → 900 | DIVERGE | **IGUAL** ❌ | **a D4 nunca compara `weight` do título**, embora o docblock dela declare "font-size/weight" |

**Consequência viva nesta rodada:** o `h1` da prod é **700** e o do alvo é **600** — e o relatório
saiu `[D4] IGUAL`. Segundo ponto cego, mesma família: quando um dos lados tem cor **não-parseável**
(o `primary` do alvo é `rgba(0,0,0,0)`), a D6 não compara e cai no `IGUAL` genérico em vez de
`SEM-DADO`. É a doença que o próprio arquivo documenta pra linha de tabela: *"a mecanização
implementava MENOS que a dimensão declarava"* — agora no título e na cor. **Não consertado aqui**
(é máquina de outro dono, e conserto sem FP medido é a armadilha do §5).

### Comparação POR SEÇÃO — o que a rodada por papel não alcançava

Medido: nós · filhos · altura · `display` · `gap` · `grid-template-columns` · tipografia.

| seção | campo | alvo (protótipo) | produção | veredito |
|---|---|---|---|---|
| **kpis** | display · gap · colunas | grid · 10px · 4 | grid · 10px · 4 | ✅ **IGUAL** (a onda 2 fechou) |
| **header** | display · altura | flex · 80px | block · **130px** | ❌ DIVERGE |
| **tabs** | altura · gap | 36px · 0px | **33px** · **2px** | ❌ DIVERGE → ❌ **RE-CONFERIDO 2026-09-21: SEGUE ABERTO** — o `density="compact"` (2026-09-18) fechou fonte e padding da *aba*, **não** este eixo: o `gap-0.5` (2px) do container `role="tablist"` segue intocado ([`PageHeaderTabs.tsx`](../../../resources/js/Components/shared/PageHeaderTabs.tsx) §`flex items-center gap-0.5`) |
| **brief** | display · gap · filhos | block · normal · 7 | **flex** · **24px** · **1** | ❌ DIVERGE (estrutura) |
| **análises (grade)** | colunas · gap | **3** · 12px | **2** · **16px** | ❌ DIVERGE → ✅ **FECHADO 2026-09-21 (#7638)** — 3 colunas + gap 12px; travado por **UC-JPAIN-31** |
| **h2 análises** | tamanho · peso · tracking · cor | 11px · 700 · 0.88px · `text-3` | **14px** · **600** · **1.4px** · mais claro | ❌ DIVERGE → ✅ **FECHADO 2026-09-18 (#7555)** — mesmo `SectionTitle` réplica que fechou o `h2 ações` ao lado; **UC-JPAIN-27**. Re-medido no runtime em 2026-09-21: **8/8** propriedades batem |
| **h2 ações** | idem acima | 11px · 700 · 0.88px | 11px · 700 · 0.88px | ✅ **(2026-09-18)** — era `14px · 600 · 1.4px`; ver nota |
| **ações** | gap | normal | **24px** | ❌ DIVERGE → ⚠️ **RECLASSIFICADO 2026-09-21 (#7638)** — medição certa, propriedade **inerte**: o `Card` tem 1 filho e `gap` sem 2º filho não separa nada. O respiro de 24px era o `py-6` do `Card` canon, removido naquele PR |
| **corpo** | fonte base | 13px | **13,5px** | 🟡 direção a decidir — 13,5px é o `--fs-4` do RAMP canon; **o protótipo é que está fora dele** |
| **metas** | — | 5 cards | **empty state** | ⬜ NÃO COMPARÁVEL → ⚠️ **DEIXOU DE SER NÃO-COMPARÁVEL em 2026-09-21** — a premissa "0 metas em todos os tenants" caducou; a seção foi medida COM cards renderizados. Veredito item-a-item na rodada de 2026-09-21 §**METAS** (#7639) — medição de sessão irmã, e o número mora lá |


> **Ponteiros de fechamento acrescentados em 2026-09-21 — e o que NÃO foi reavaliado.**
> Quatro linhas desta tabela ganharam ponteiro acima; as outras **não foram re-medidas nesta
> passada**, e dizer que seguem valendo seria afirmar sem medir. O estado declarado:
>
> | linha | estado em 2026-09-21 |
> |---|---|
> | `kpis` | já trazia `✅ IGUAL`; confere com a réplica da [#6662](https://github.com/wagnerra23/oimpresso.com/pull/6662) — ver §KPIs da rodada de 09-03 |
> | `análises (grade)` · `h2 análises` · `ações` | **fechadas / reclassificada** — ponteiro na própria linha |
> | `tabs` | **re-conferido, SEGUE ABERTO** — o `gap-0.5` do container continua lá |
> | `header` · `brief` · `corpo` | **NÃO reavaliados** — exigiriam sonda no DOM, fora do recorte deste PR (doc-only) |
> | `metas` | deixou de ser `⬜ NÃO COMPARÁVEL` — ver §Metas logo abaixo |
>
> **Por que isto existe:** em 2026-09-21 três sessões irmãs receberam ordem de consertar itens
> daqui, mediram antes de editar e acharam o item **já correto em produção**. A tabela estava certa
> como história e enganosa como estado — e o `h2 análises` era o caso mais duro, porque o
> `h2 ações` **ao lado** recebeu o `✅` do mesmo conserto e ele não: o documento se contradizia a
> duas linhas de distância. Obedecer teria reescrito código correto e derrubado teste.

**Caixa alta dos h2:** ambos os lados têm `text-transform: uppercase` — a dúvida registrada em
2026-09-04 (*"sentence case no código pode estar sendo uppercase no CSS"*) fica **resolvida: é
uppercase nos dois**. O que diverge é tamanho, peso e tracking, não a caixa.

> **FECHADO em 2026-09-18 — o `SectionTitle` virou RÉPLICA LOCAL da `.jc-h2`.** Era o h2 do golden
> `governance/Dashboard` (`text-sm font-semibold tracking-widest`, = `14px/600/1.4px`); passou a
> `font-mono text-[11px] font-bold tracking-[0.08em] gap-[7px]`, mais `mt-1.5 mb-2.5` pelo
> `margin: 6px 0 10px` da âncora. O `uppercase` **não mudou** — nunca foi divergência, e o teste o
> trava como invariante, não como correção.
>
> **Réplica LOCAL de propósito** (ADR 0388 §D-1): o `SectionTitle` é função interna do
> `JanaCockpit.tsx` e não sai dele, então a forma da Jana **não** é imposta às outras telas — o
> mesmo caminho que o `JanaKpiCard` tomou em vez de mexer no `KpiCard` compartilhado.
>
> **`font-mono` é tradução PROVADA, não suposta:** o próprio espelho declara
> `--mono: var(--font-mono)` (`prototipo-ui/cowork/Wagner/styles.css:6446`), que é o token do
> projeto — o mesmo mapeamento que o `JanaKpiCard` já usava para `.jc-kpi-h`.
>
> **Sub-rótulo (`.jm-h2-sub`, `jana-merge.css:6`) junto:** `ml-1` → **`ml-auto`** (a âncora o
> empurra pra direita da faixa, não o cola no título), mono 10.5px/400, `tracking-[0.02em]`. A
> copy já era byte-idêntica desde 2026-08-31.
>
> ⚠️ **A COR do sub NÃO foi tocada, e isso é decisão declarada, não esquecimento.** A âncora usa
> `var(--text-dim)`, que **não é definido no escopo desta tela** — `chat-jana.css` e
> `jana-merge.css` não o declaram; ele só aparece em `estoque-page.css` e `mockup-pages.css`, de
> outras telas. Sem token resolvível, trocar a cor seria adivinhar. Fica medido e aberto.
>
> Travado por **UC-JPAIN-27** (`tests/janaSectionTitleReplica.spec.tsx`), 5 casos, mordida provada
> por mutação: restaurada a métrica do golden, 2 de 5 caem — um por **ausência** da nova
> (`sem font-mono`) e outro por **presença** da antiga (`ainda tem text-sm`), que é o par que
> impede tanto a regressão quanto o meio-termo.
>
> ⚠️ **Frescor da fonte, declarado:** `cowork-mirror-freshness --sla` dá **⬜ INCONCLUSIVO**, não
> SYNC — o `--compare` está completo e dentro do SLA (705/705 sync, 2026-09-17), mas **5 arquivos
> do vivo não estão no espelho** (`.gitignore`, `.thumbnail` e 3 do `_ds/`, incluindo
> `styles.css`). O `chat-jana.css`, dono dos números acima, **está entre os sync**, e o eixo novo
> "vê AUSÊNCIA, nunca MODIFICAÇÃO". Logo os valores aplicados vêm de arquivo provado fresco; o que
> permanece por provar é o que o `_ds/` ausente poderia redefinir — e é justamente por isso que a
> cor ficou de fora.

### Metas — segue NÃO COMPARÁVEL, agora com data nova

O alvo renderiza **5 cards**, no formato exato que a onda 2.1 implementou
(`<realizado> de <alvo>` na linha do valor, `<pct>% do alvo` no rodapé, projeção à direita, e
`Aguardando apuração… alvo <X>` na meta sem apuração). A produção mostra o **empty state**:
**zero metas em biz=1**, confirmado também na aba Plataforma (as duas tabelas vazias). Terceira
medição seguida com o mesmo resultado (08-21 · 08-31 · **09-07**).

> ⚠️ **Ponteiro de 2026-09-21: a quarta medição QUEBROU a série.** As três leituras acima
> (08-21 · 08-31 · 09-07) estão corretas nas datas delas e **ficam**. O que caducou é a conclusão
> operacional — *"a onda 2.1 não foi verificada em produção"* deixou de ser consequência de não
> haver dado: em 2026-09-21 a seção foi medida **com** cards renderizados, e o veredito item-a-item
> está na rodada de 2026-09-21 §**METAS** ([#7639](https://github.com/wagnerra23/oimpresso.com/pull/7639)).
>
> Duas coisas que aquela rodada mostrou e que esta seção não poderia saber: **(a)** o
> `Nova meta`/`Jana Pro`/`Conversar` que as leituras antigas contaram como divergentes **também
> existem na âncora** — os *"3 botões"* nunca foram gap; **(b)** a projeção marcada como ausente
> **existe na prod com a mesma copy** — a busca antiga procurou a palavra *"projeção"* no DOM, e a
> prod renomeou o rótulo (rótulo do protótipo não é chave de busca em código que renomeou —
> §5 2026-07-15 · LC-08). A dívida que **sobrou** é de densidade (grade e card), e é nova.


**Portanto a onda 2.1 NÃO foi verificada em produção** — só por Pest (arquivo) e por render jsdom
(DOM, 6 casos com mordida provada). O card em si continua sem prova na tela real, e isso não é
opinião: é a consequência de não haver dado.

### O que esta rodada NÃO cobriu (declarado, não escondido)

- **D1 rede** (partial-reload) — não exercitada.
- **Shell/sidebar** — `__SB_ROLES` não declarado nos dois lados ⇒ o comparador diz SEM-DADO.
- **Linha de tabela** — a tela não tem tabela; papel `tableRow` nulo dos dois lados.
- **Contraste par-a-par** — não calculado.
- **Camadas UI-0013** — o que se mediu foi **Shell** (header/tabs) e **Módulo** (seções da tela).
  **Fundações** entrou só de raspão (a fonte base 13 × 13,5px), e **Padrão de Tela** não foi
  avaliado contra nenhum PT — o Painel não declara PT no charter.

---

## Rodada MEDIDA 2026-09-21 — grade de Análises (3 colunas) e bloco de Ações

> **Escopo:** o CONTAINER da seção de Análises (grade + bloco de Ações). O conteúdo dos cards
> (gráficos, KPIs, Metas, `h1`) é de chips irmãos e **não** foi medido aqui.

### Como foi medido (o que torna esta rodada auditável)

| item | valor |
|---|---|
| fonte provada | `cowork-mirror-freshness --sla`: **708 sync · 0 stale**, rodada de 2026-09-21T10:43Z. Os **4** arquivos do eixo `--live-only` são `.gitignore`, `.thumbnail` e 2 JSON de `_ds/` — **nenhum é fonte visual**, logo não desqualificam esta tela |
| âncora | `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JanaPage`, sha256 `7bb8e713130a9f80` (idêntico ao do repo principal — conferido por hash, com controle positivo de 2 arquivos que DEVEM diferir) |
| D0 · identidade da view | os **3** `.jc-h2` dos dois lados são `METAS ATIVAS` · `ANÁLISES PRINCIPAIS` · `AÇÕES QUE JANA SUGERE`, com **5** cards de análise — assinatura da view **Painel**, não Chat/Alertas/Memória (`jana-merge.jsx` serve 3 telas) |
| ambiente | **mesmo Chrome, mesma janela**, viewport **2560×951** nos dois lados, `data-theme=dark` nos dois |
| container | **2237px idêntico** nos dois lados ⇒ a diferença de colunas **não** vem de largura disponível |
| sonda | uma só, byte-idêntica nos dois lados (sha256 `32da053c84740ca1ea0f63fa7452ae5e`), papéis mapeados por `data-sec` pelo MESMO critério (texto do `h2`) |
| canário | rodado **nos dois lados**: forcei em cada um o estado do outro; a sonda acusou e reverteu limpo (`revertido: true`) |

⚠️ **Viewport 2560, não 1440** — e por quê: `resize_window` reportou sucesso e foi **inerte**
(`outerW` seguiu 2563; janela maximizada em monitor 3840). Em vez de aceitar o número, igualei os
DOIS lados na mesma janela. A conclusão não depende disso: a âncora quebra em 1100px e a prod
usava `lg:` (1024px), então 1440 e 2560 caem na mesma faixa dos dois lados.

⚠️ **COR não foi medida neste render do protótipo, e não é veredito omitido:** `--text-3` resolve
para `var(--text-mute)`, que não é declarado no escopo — todas as cores do lado âncora computam
`rgb(0,0,0)`. Layout e tipografia **carregaram** (`.jc-grid` e `.jc-h2` resolveram valores não-default),
então o que se afirma abaixo é só o que o instrumento de fato mediu.

### O que a medição devolveu

| bloco | campo | âncora | prod (antes) | veredito |
|---|---|---|---|---|
| **análises (grade)** | colunas | **3** (`737.66px` × 3) | **2** (`1110.5px` × 2) | ❌ **DÍVIDA A FECHAR** → fechada nesta rodada |
| **análises (grade)** | gap | **12px** | **16px** | ❌ **DÍVIDA A FECHAR** → fechada nesta rodada |
| **h2 análises** | 8 propriedades | `700 11px/11px` mono · ls `0.88px` · uppercase · `m 6px 0 10px` · `gap 7px` | **idêntico** | ✅ **IGUAL** — já estava fechado (2026-09-18, UC-JPAIN-27) |
| **h2 ações** | idem | idem | idem | ✅ **IGUAL** |
| **ações** | `padding` | **0** | **24px 0** | ❌ **DÍVIDA A FECHAR** → fechada nesta rodada |
| **ações** | `gap` | `normal` | `24px` | ⚠️ **medição certa, dívida enganosa** — ver abaixo |
| **ações** | `border-radius` | 12px | 12px | ✅ **IGUAL** |

> **A linha `h2 análises` da rodada de 2026-09-07 está CADUCA.** Aquela tabela a marca `❌ DIVERGE`
> (`11px/700/0.88px × 14px/600/1.4px`), mas a nota de fechamento logo abaixo dela, no mesmo doc,
> registra o conserto de 2026-09-18 — e só a linha do `h2 ações` recebeu o ✅. Medido hoje no
> runtime: os dois h2 batem com a âncora em **8 de 8** propriedades. A tabela preserva o fato do
> dia; não é o estado de hoje (§5 2026-09-03). Três sessões irmãs chegaram a isso em paralelo.

> **O `gap: 24px` das Ações era o sintoma legível, não a causa.** Medido: o `Card` tem **UM** filho,
> e gap sem segundo filho não separa nada. Quem produzia o respiro de 24px é o **`py-6`** do `Card`
> canon (`ui/card.tsx:29`). A rodada de 09-07 mediu certo e nomeou a propriedade inerte.

### O que NÃO entrou, e por quê (escopo declarado, não omissão)

- **`margin-bottom` 18px (âncora) × 16px (prod).** Medido: o 16px **não é da grade** — vem do
  `space-y-4` do container da página (a className da grade não declara margem), logo ele rege
  **todas** as seções: KPIs, Metas, Análises, Ações. Convergir para 18px é mudar o ritmo vertical
  da tela inteira, tocando território de 4 chips irmãos vivos. Fica **medido e aberto**, decisão [W].
- **Conteúdo dos cards** (o sparkline de Faturamento, em especial). Não medido aqui. ⚠️ Consequência
  declarada da mudança: a largura do card cai de **1110,5px → ~737,7px** (−33,6%) nessa viewport, e
  o `<svg>` do sparkline é `preserveAspectRatio="none"`, logo a curva **comprime horizontalmente**
  (a altura segue travada em 40px). A sessão irmã que mede os gráficos foi avisada antes de medir.
- **D1 rede**, **shell/sidebar**, **contraste par-a-par**: fora do recorte desta rodada.

### Enforcement

**UC-JPAIN-31** — [`tests/janaGradeAnalisesReplica.spec.tsx`](../../../tests/janaGradeAnalisesReplica.spec.tsx),
6 casos, mordida provada por mutação (2 asserts caem ao restaurar `gap-4 lg:grid-cols-2`; 1 ao
remover `py-0 gap-0`), com restauração conferida por hash. O detalhe — incluindo por que os
breakpoints são `min-[761px]`/`min-[1101px]` e a prova de que o Tailwind os gera — está no
**UC-JPAIN-31** de [`Index.casos.md`](../../../resources/js/Pages/Jana/Index.casos.md); aqui não
se repete, para os dois não drifarem.
## Rodada MEDIDA de 2026-09-21 — o eixo RESPONSIVO dos KPIs (o que nenhuma rodada tinha medido)

> **Por que esta rodada existe:** chip pedindo pra fechar a dívida visual do KPI, descrevendo a
> anatomia interna (rótulo sans, caixa de ícone 36×36, valor 22 × 24px). Re-medida a tela viva,
> **essa dívida não existia mais** — fechou em 2026-09-03 ([#6662](https://github.com/wagnerra23/oimpresso.com/pull/6662)).
> O que a re-medição achou foi outro eixo, e ele bate com a queixa literal de [W]:
> *"quantidade de colunas de kpi"*.

**Como foi medido** (fluxo da skill `comparar-design-prod`, sem pular passo):

| passo | o que foi feito |
|---|---|
| fonte | `ancora.mjs Jana/Index` → `jana-merge.jsx`; `--sla` ⬜ INCONCLUSIVO (compare completo 708/708 · 4 do vivo fora do espelho, **todos config/meta**: `.gitignore`, `.thumbnail`, 2 JSON do `_ds/`) |
| D0 | design `data-screen-label="Jana — Painel"` (`jana-merge.jsx:1057`) · prod `/ia` + `.cockpit` + h1 "Jana · Analista IA" |
| tema | dark × dark (`corTexto` claro nos dois) |
| sonda | ad-hoc, **byte-idêntica** nos dois lados — a oficial (`--probe`) mede só `valueFontPx`/align por KPI e **não** cobre anatomia nem responsivo |
| canário | padding forçado a 40px em cada lado ⇒ **os dois acusaram** (`12 14 14 14 → 40 40 40 40`) |

### Anatomia — 24 de 26 campos IGUAL

Grid 4 col · gap 10 · raio 8 · pad `12 14 14 14` · gap interno 3 · `flex column` · rótulo
`10px/700/mono/ls 0.6/uppercase` · cabeçalho `flex space-between` · `mb 4` · ícone `15×15` sem
caixa · valor `22px/700/lh 22/ls -0.44` · tag `DIV` · largura 275,5.

As 2 restantes **não são dívida de forma** — estão decompostas no `Index.casos.md` §UC-JPAIN-34
(`small` ausente no 1º card = dado; 4px de altura = `+6` do `emph` não disparado no staging (vencido zero) `−2`
da borda que o render do espelho não pintou). ⚠️ A segunda **refuta** a causa registrada em
2026-09-03 (*"line-height do `small`"*): ele é `16.5px` idêntico nos dois lados.

### O achado: o BREAKPOINT do grid — **DÍVIDA A FECHAR**, e foi fechada

| viewport | âncora `.jc-kpis` | produção (antes) | depois |
|---|---|---|---|
| 1440 | 4 col · 276px | 4 col · 276px | 4 ✅ |
| 1280 | 4 col · 287px | 4 col · 287px | 4 ✅ |
| **1080** | **2 col · 483px** | **4 col · 237px** ❌ | **2** ✅ |
| **1050** | **2 col · 468px** | **4 col · 229px** ❌ | **2** ✅ |
| 900 | 2 col · 393px | 2 col · 393px | 2 ✅ |
| **600** | **2 col · 279px** | **1 col · 552px** ❌ | **2** ✅ |

A `.jc-kpis` é `repeat(4,1fr)` e quebra em `@media (max-width:1100px)` para `repeat(2,1fr)` —
**sem degrau de mobile**. O `colsMap[4]` do `KpiGrid` compartilhado quebra em `lg:`(1024) e
`sm:`(640), deixando as três faixas acima. Fechado por **réplica local** `JanaKpiGrid`
(ADR 0388 §D-1 — o `KpiGrid` serve 37 telas e não recebe a forma da Jana). Prova em bancada com
o CSS buildado: **6/6 viewports** batendo, com controle antes × depois discriminando em 3.

> ⚠️ **ARMADILHA DO TAILWIND 4, e ela custa um PR inteiro se ninguém avisar.** O caminho óbvio —
> `className="max-[1100px]:grid-cols-2"` no `KpiGrid` — **sai INERTE**, e a segunda tentativa
> (`min-[1101px]:grid-cols-4`) também. O Tailwind 4 emite os variants **arbitrários** (`min-[…]`,
> `max-[…]`) **ANTES** dos nomeados (`sm:`, `lg:`); com a especificidade igual, vence quem vem
> depois — sempre o `colsMap`. Medido no CSS buildado:
>
> ```
> .max-[1100px]:grid-cols-2  @264896  <  .lg:grid-cols-4  @271781   -> lg vence
> .min-[1101px]:grid-cols-4  @264997  <  .lg:grid-cols-2  @271756   -> lg vence
> ```
>
> Passa em typecheck, lint e CI sem mover um pixel (LC-30). **O que pegou foi a bancada com
> canário**, não a leitura: remover a classe devolveu o MESMO número, assinatura de classe
> inerte. E a primeira "prova de ordem" que escrevi media `max-width: 1100px` de **CSS legado**
> (`.sells-cowork`, `.fin-cowork`) em vez da utility — proxy plausível lido como alvo.

### ⚠️ O que ficou MEDIDO E ABERTO

- **`margin-bottom` 18 × 16** (2px, todas as viewports). Não vem de classe no grid: vem do
  `space-y-4` do container pai (`gridMarginBottom: 16px` · `paiClasses: "space-y-4"` ·
  `irmaoMarginTop: 0px`). `mb-[18px]` na utility seria **inerte** — especificidade `(0,1,0)`
  contra `(0,2,0)` do pai. Fechar exige tocar o `space-y-4`, que governa **todas** as seções da
  tela e é território de chips irmãos vivos. Declarado, não consertado.
  > ⚠️ **FECHADO por OUTRA sessão enquanto este PR esperava, e a minha análise do conserto
  > estava ERRADA** — `UC-JPAIN-33` ([#7653](https://github.com/wagnerra23/oimpresso.com/pull/7653),
  > 2026-09-21). Eu medi certo *de onde vinha* em produção (o `space-y-4` do container) e concluí
  > errado *como se fecha*: escrevi que exigiria tocar o container e o espaçamento de todas as
  > seções. A medição deles na **âncora** mostra o oposto — lá o 18px **não vem de um container,
  > vem de cada seção** — e por isso o conserto coube por seção, sem raio nas vizinhas.
  > Medir a produção não substitui medir a âncora: eu parei na primeira.

- **Cor de fundo, borda e texto do lado DESIGN não foram medidas.** No render do espelho o
  `colors_and_type.css` carregou com **0 regras** e `--surface`/`--border`/`--text-3` ficaram
  vazios. Nenhum dos 24 campos ✅ depende deles (`--r-2`, `--fs-7` e `--mono` resolveram); os de
  cor simplesmente **não entram em veredito** nesta rodada.
- **D1 (rede/partial-reload)** não exercitada nesta rodada.
## Rodada MEDIDA de 2026-09-21 — GRÁFICOS (o eixo que nenhuma rodada anterior tinha sondado)

> **Por que esta rodada existe:** [W] relatou que os gráficos estão diferentes. As rodadas de
> 08-17, 08-21, 09-02, 09-03, 09-07 e a errata de 09-18 mediram header, abas, brief, KPIs, metas,
> análises e ações — **gráfico nunca foi papel de sonda em nenhuma delas**.
>
> ⚠️ **Precisão sobre a premissa do chip.** Ele dizia "nenhuma rodada tem item de gráfico". Isso
> é **quase** verdade, e a diferença importa: as linhas 207, 208 e 261 deste doc **citam** série,
> barra e sparkline — mas como itens de **EXISTÊNCIA** (tem/não tem), nunca como **medição de
> forma**. O que faltava não era a menção, era o número. É esta rodada.

### Como foi medido

| passo | o que foi feito |
|---|---|
| âncora | `ancora.mjs Jana/Index` → `prototipo-ui/cowork/Wagner/jana-merge.jsx` (frescor verificado contra o Cowork vivo em 2026-09-21T10:43Z) |
| **dep de render** | o gráfico do Painel **não mora na âncora**: `jana-merge.jsx:889` consome `AnaliseCard` de `window`, e quem o define é `chat-jana.jsx:723`, junto com `Sparkline` e `Donut`. É **dependência de render do shell** — o que a skill chama de LC-07 ("só âncora = cego pra infra"). ⚠️ Isto **não** reabre a lápide §5 2026-08-10: ela baniu `chat-jana.jsx` como `related_prototype` (âncora de tela), não como dep de render |
| frescor da dep | `DesignSync.get_file(019dcfd3…, chat-jana.jsx)` → `Sparkline` e `Donut` do espelho são **byte-idênticos** ao vivo, incluindo o espaçamento duplo em `offset="0%"   stopColor`. **Prova ESCOPADA**: vale para as funções medidas mais o `Object.assign` final, não para o arquivo inteiro (o `get_file` voltou inline, 33 KB — sem JSON em disco a rota `--export-from` não se aplica, e transcrever é proibido) |
| lado design | espelho servido em :5632 na **raiz do worktree**, rota `/prototipo-ui/cowork/Wagner/oimpresso.com.html`, dark, esperando `__oiLazyDone` + `.jm-sk` sumir + 2 leituras iguais (1068 nós) |
| lado produção | `https://oimpresso.com/ia` autenticado, biz=1, dark, 2560px, 2 leituras iguais (1117 nós) |
| sonda | **papel de gráfico não existe no `design-diff.mjs --probe`** (ele tem 6: `filterControls`, `kpi`, `primary`, `shell`, `tableRow`, `title`). Escrita sonda nova sob a convenção `window.__DD_ROLES.charts`, **injetada byte-idêntica nos dois lados** |
| canários | **5**, todos obrigatórios antes de qualquer veredito |

### ⚠️ Um erro de setup que quase virou medição falsa — registrado porque quase passou

A **primeira** rodada do lado design saiu com `--text-dim`, `--sunken`, `--bg` e `--border`
**vazios**, e toda cor de texto e de trilho caindo em `rgb(0,0,0)`. Não era o design: era o meu
servidor. O shell resolve a base do DS por
`location.pathname.indexOf` do trecho `/prototipo-ui/cowork/` (`oimpresso.com.html:18`), e eu
havia servido a raiz *dentro* de `prototipo-ui/cowork` — então o pathname não continha o trecho,
a base caía no fallback `_ds/` inexistente, e `colors_and_type.css` carregava com **0 regras**.
Os arquivos existiam em disco o tempo todo (19.917 e 5.705 bytes).

**Aquela medição foi descartada inteira**, não corrigida em cima. Se tivesse sido aceita, eu teria
reportado divergência de cor onde havia defeito de preview — a família §5 2026-07-29
(não colapsar "não consegui medir" num estado do objeto).

### Canários — a sonda discrimina (5/5)

| canário | esperado | obtido | pegou |
|---|---|---|---|
| C1 · `stroke-width` 2 para 5 | 5 | 5 | sim |
| C2 · `vector-effect` removido | `none` | `none` | sim |
| C3 · altura do trilho 5 para 12px | 12 | 12 | sim |
| C4 · path de área removido (2 para 1) | 1 | 1 | sim |
| C5 · espessura efetiva com x sem `non-scaling-stroke` | difere | 0,75 x 0,08 user-units | sim |

Estado revertido e conferido nos 5 (`REVERTIDO_OK: true`).

⚠️ **Um canário FALHOU antes do C5, e ele está aqui porque mudou o método.** A primeira tentativa
de medir a espessura efetiva usou `getBoundingClientRect()` do `polyline` com e sem
`non-scaling-stroke`: deu **idêntico** (`PEGOU: false`). O `getBoundingClientRect` de um polyline
devolve a caixa **geométrica escalada, sem o stroke** — a sonda era cega ali. Não concluí daquela
leitura; troquei para `isPointInStroke()`, que é a API exata, e só então o canário pegou.

### Papel 1 · Sparkline de análise (Faturamento) — o gráfico que [W] vê

Âncora: `chat-jana.jsx:271` §`Sparkline`, consumido por `AnaliseCard` quando `kind==='sparkline'`.
Produção: `resources/js/Pages/Jana/_components/JanaCockpit.tsx:951`.

| # | propriedade | protótipo | produção | veredito |
|---|---|---|---|---|
| G1 | elemento do traço | `path` | `polyline` | **DÍVIDA A FECHAR** |
| G2 | nº de desenhos | **2** (área + linha) | **1** (linha) | **DÍVIDA A FECHAR** |
| G3 | área preenchida | sim — `url(#jcSparkGrad)` | **não** — `fill:none` | **DÍVIDA A FECHAR** |
| G4 | gradientes | **1** (2 stops, `--pos` 0.26 → 0) | **0** | **DÍVIDA A FECHAR** |
| G5 | forma do traço | **curva Bézier** (46 comandos `Q`/`T`) | **reta** (30 pontos, 0 curvas) | **DÍVIDA A FECHAR** |
| G6 | cor do traço | `oklch(0.76 0.18 150)` — `--pos`, **verde** | `oklch(0.7 0.15 295)` — `--accent`, **roxo** | **DÍVIDA A FECHAR** |
| G7 | `stroke-width` declarado | 2 | 1,5 | **DÍVIDA A FECHAR** |
| G8 | `vector-effect` | `non-scaling-stroke` | **`none`** | **DÍVIDA A FECHAR** |
| G9 | **espessura EFETIVA na tela** | **2px uniforme** | **13,46px horizontal x 1,5px vertical** | **DÍVIDA A FECHAR** |
| G10 | `viewBox` | `0 0 280 60` | `0 0 120 40` | **DÍVIDA A FECHAR** |
| G11 | altura renderizada | 60px | 40px | **DÍVIDA A FECHAR** |
| G12 | `preserveAspectRatio` | `none` | `none` | IGUAL |
| G13 | label · `font-size` | 10,5px | 10px | **DÍVIDA A FECHAR** |
| G14 | label · `font-family` | IBM Plex Sans | IBM Plex Sans | IGUAL |
| G15 | label · `justify-content` | `space-between` | `space-between` | IGUAL |
| G16 | largura renderizada do card | **737,66px** (3 colunas) | **1110,5px** (2 colunas) | **DÍVIDA A FECHAR** — ver nota |

**G9 é o item mais caro, e é o único que exigiu medição indireta.** A produção combina
`preserveAspectRatio="none"` **sem** `vector-effect`, então o traço é deformado pela escala do
viewBox. Medido: escala **x = 8,971 · y = 1** (bbox render dividido pelo viewBox), e o traço ocupa
**1,5 user-units** (`isPointInStroke`, meia-largura 0,75) → **13,456px** de espessura perpendicular
a um segmento vertical, contra 1,5px num horizontal. **Razão de deformação: 8,97x.** O protótipo
não tem esse efeito porque declara `non-scaling-stroke`.

⚠️ **E aqui o dado de hoje ESCONDE o defeito, o que vale registrar:** a série de Faturamento de
biz=1 é **plana** — medido, os 30 pontos têm **1 único valor de y** (`y=39`). Uma linha horizontal
reta não exibe deformação nenhuma. O G9 é real e está calculado a partir de escala medida, mas
**só se manifesta na tela quando a série tiver variação**. Quem olhar o print de hoje não vê.

> **Nota do G16 — o número NÃO é meu, e a atribuição importa.** Eu medi os dois lados em viewports
> diferentes (design 1280, prod 2560) e ia registrar `NÃO COMPARÁVEL`. A sessão irmã que trabalha
> a grade de Análises mediu os dois na **mesma viewport 2560**, com o container em **2237px
> idêntico nos dois lados** — e com isso a largura **é** comparável: a âncora usa **3 colunas de
> 737,66px com gap 12px**, a produção **2 colunas de 1110,5px com gap 16px**. Crédito da medição a
> ela; eu não a refiz. Quando o PR dela mergear, o card cai para ~737,7px (−33,6%) e a curva
> comprime horizontalmente — os demais itens do Papel 1 (altura 40px, `stroke-width`, cor, labels)
> **não** dependem da largura e seguem valendo.

### Papel 2 · Barras horizontais de análise

| # | propriedade | protótipo | produção | veredito |
|---|---|---|---|---|
| G17 | nº de trilhos no Painel | **12** (8 `.jc-bar-track` + 4 `.jc-bk-bar`) | **12** | IGUAL |
| G18 | altura do trilho | **7px** | **6px** (`h-1.5`) | **DÍVIDA A FECHAR** |
| G19 | preenchimento (Pareto e Métodos) | **gradiente** `linear-gradient(90deg, oklch(0.76 0.15 295), oklch(0.7 0.15 295))` | **sólido** `oklch(0.7 0.15 295)` | **DÍVIDA A FECHAR** |
| G20 | preenchimento (buckets) | cor por faixa — `--warn`, mix warn/neg, `--neg`, `--text-3` | 3 sólidas — `oklch(0.55 0.17 18)`, `oklch(0.68 0.13 162)`, `oklch(0.7 0.15 295)` | **parcial** — a paleta difere, mas o dado de biz=1 não exercita as 4 faixas |
| G21 | `background` do trilho | `oklch(0.23 0.006 240)` | `oklch(0.235 0.01 240)` | equivalente |
| G22 | `border-radius` do trilho | `999px` | `3.35544e+07px` | equivalente no efeito |

### Papel 3 · Barra de progresso da meta — **NÃO MEDI**

Produção biz=1 tem a seção METAS ATIVAS e o botão Nova meta, mas **zero meta cadastrada**
(0 `svg[width=120][height=32]`, 0 `[data-contract="painel-meta-sem-historico"]`). Sem meta não há
card, não há barra e não há drawer. Consistente com o que este doc já registra desde 08-21.

Do CSS — **leitura, não medição**: protótipo `.jm-meta-track{height:5px}` contra produção `h-1.5`
(6px). Fica como **hipótese a medir** quando houver meta, nunca como veredito.

### Papel 4 · Série de 12 barras (drawer da meta) — **NÃO MEDI na produção**

Mesma causa: sem meta, o drawer não abre. **No lado design foi medida em parte**, e o que ficou
de fora está declarado:

| propriedade | protótipo (medido) |
|---|---|
| nº de colunas | 12 |
| `gap` | 4px |
| altura do container | 88px |
| `padding` | `6px 0px` |
| `align-items` | `flex-end` |
| `border-radius` da barra | `3px 3px 0 0` |
| **cores** | **2** — normais `oklch(0.7 0.15 295 / 0.7)`, **última** `oklch(0.7 0.15 295)` (destaque) |
| label `.jm-serie-range` | 10,5px · **IBM Plex Mono** · `space-between` |
| **largura** | **NÃO MEDI** — o `aside` do drawer renderiza colapsado (w=1px) no preview; forçar `width:520px` não propagou (controle rodado, falhou) |

A produção implementa a série em `JanaMetaDrawer.tsx:119` com a **mesma fórmula de altura**
(`max(4, v/max*100)%`) e `flex-1 rounded-t bg-primary/70`. Comparar isso com o protótipo exige
meta cadastrada — **hipótese registrada, não veredito**: o protótipo destaca a **última** barra
com cor cheia (`.jm-serie-col:last-child`), e no código da produção não há regra equivalente.

### Papel 5 · Donut — IGUAL (ausente dos dois)

`Donut` existe em `chat-jana.jsx:298` e é exportado, mas o Painel **não o usa**: 0 `.jc-donut` no
design, 0 na produção. Confirma a lápide §5 2026-08-10 (Frota utilização não volta) pelo lado da
medição, não da leitura.

### D1 · rede — partial

Clique no card do sparkline (`BUTTON.w-full rounded-lg text-left…`): **0 requisições** (fetch e
XHR instrumentados), `beforeunload` não disparou, URL inalterada, drawer abriu com o texto
"de onde vem". O drill é client-side.

### Placar

**22 itens comparáveis · 16 DÍVIDA A FECHAR · 5 IGUAL · 1 parcial** · 3 papéis NÃO MEDI
(2 por ausência de dado em biz=1, 1 por artefato de preview).

A concentração é no **Papel 1**: 12 de 16 dívidas estão no sparkline de análise, e elas não são
ajustes de token — são de **natureza do desenho** (curva x reta, com área x sem área, verde x
roxo). O protótipo desenha uma curva suave verde com área em gradiente; a produção desenha uma
poligonal roxa sem preenchimento. É a resposta medida ao relato de que os gráficos estão
diferentes.

### O que esta rodada NÃO cobriu (declarado)

- **Contraste par-a-par** dos labels de gráfico — não calculado.
- **Série e progresso na produção** — bloqueados por ausência de meta, não por limite de sonda.
- **Sparkline do card de meta** (`Index.tsx:98`) — mesma causa. Pela leitura do código ele é
  `polyline` reto `text-primary` `strokeWidth=2` `w=120 h=32`, e o protótipo **não tem**
  sparkline no `JmMetaCard` — mas isso é **leitura, não medição**, e não vira veredito aqui.
- **Margem vertical das seções** — a sessão irmã mediu que os 16px vêm do `space-y-4` do container
  da página (a âncora usa 18px) e deixou fora do PR dela de propósito, porque mexer ali muda o
  ritmo de todas as seções. Registrado aqui para não virar achado novo depois.

### Conserto aplicado no MESMO dia — Papel 1, 11 das 16 dívidas

> **Por que o conserto entrou nesta sessão, se o chip dizia "não corrija":** [W] cortou no meio
> da medição — *"precisa melhorar e muito o código"* / *"tu não consegue fazer melhor?"*. Pedido
> do dono é decisão, não proposta (ADR 0382), e supersede a instrução anterior do chip. A tabela
> acima **fica como está** — é o fato datado do estado ANTES; esta seção é o depois.

`resources/js/Pages/Jana/_components/JanaCockpit.tsx` — componente `SparkArea` novo, portado de
`chat-jana.jsx:271`, substituindo o `<polyline>` inline do card de Faturamento.

| item | antes | depois | como foi provado |
|---|---|---|---|
| G1 | `polyline` | `path` | sonda, pós-fix |
| G2 | 1 desenho | 2 (área + linha) | sonda, pós-fix |
| G3 | sem área | `fill=url(#gid)` | sonda, pós-fix |
| G4 | 0 gradientes | 1 (0.26 → 0) | sonda, pós-fix |
| G5 | reta | **58 comandos** `Q`/`T` | sonda, pós-fix |
| G6 | `oklch(0.7 0.15 295)` roxo | `oklch(0.68 0.13 162)` positivo | sonda, pós-fix |
| G7 | `stroke-width` 1,5 | 2 | sonda, pós-fix |
| G8 | `vector-effect` ausente | `non-scaling-stroke` | sonda, pós-fix |
| G9 | 13,46 × 1,5px (razão 8,97×) | **2px uniforme** | consequência de G8 |
| G10 | `viewBox 0 0 120 40` | `0 0 280 60` | sonda, pós-fix |
| G11 | altura 40px | **60px** | `.h-\[60px\]{height:60px}` no CSS do build |
| G13 | label 10px | **10,5px** | `.text-\[10\.5px\]{font-size:10.5px}` no CSS do build |

**Segue aberto e é deliberado:** G16 (largura) depende do PR da grade em sessão irmã; G17-G22
(barras horizontais) não foram tocados; G18/G19 exigem decidir se o gradiente das barras entra —
não entrou neste porte, para não misturar intents.

#### O que NÃO foi copiado da âncora, e por quê

- **A cor** sai de `text-success` (token da produção, `oklch(0.68 0.13 162)`), não do literal
  `--pos` da âncora (`oklch(0.76 0.18 150)`). O papel semântico é o mesmo; o valor difere, e
  igualar isso é mexer em **Fundações** (UI-0013), que muda a tela inteira e é decisão [W].
  **A dívida de fundação fica declarada aqui, não silenciada.**
- **A copy** `D-30` / `hoje` ficou — é o recorte real de 30 dias da produção, enquanto a âncora
  mostra `mai/24` / `mai/26` (24 meses de mock). Copy adaptada de propósito **não é dívida**.
- **`useId` no gradiente**, que a âncora não tem: id de `<defs>` é global no documento, e a
  âncora usa id fixo (`jcSparkGrad`) porque só tem uma instância. Duas instâncias colidiriam e a
  segunda herdaria o preenchimento da primeira. É correção de bug latente, não desvio de forma.
- **Guarda de série de 1 ponto:** a âncora faz `w/(n-1)`, que com `n=1` dá `Infinity` e produz um
  path `NaN`. Aqui degrada para um ponto em `x=0`. Mesmo caso: buraco da âncora, não forma dela.

#### Verificação (a Regra 0 — CI verde não prova runtime, LC-30)

| prova | resultado |
|---|---|
| `tsc --noEmit` | 0 erros em `JanaCockpit.tsx` (os que saem são pré-existentes em Forja, Officeimpresso e PaymentGateway) |
| `eslint` no arquivo | exit 0 |
| `vitest` — `janaPainelEstadoVazio` + `janaPainelGatingPro` | **20/20 passed** — UC-JPAIN-07 ("Sem histórico") e UC-JPAIN-08 (skeleton) preservados |
| `npm run build:inertia` | exit 0; `non-scaling-stroke` presente em `JanaCockpit-utepVQE0.js` |
| forma pós-fix | **9 de 10 itens** conferidos contra a âncora por injeção medida na produção, com estado revertido (`restaurado: true`) |
| G11 / G13 | classes arbitrárias **geradas pelo build**, com regra literal conferida |

⚠️ **O que esta verificação NÃO prova:** que está no ar. Não houve deploy — isso é R10, e é do
[W]. O que está provado é que o código **produz** a forma da âncora, não que a produção já a
serve.

#### Três sondas minhas mentiram nesta sessão, e o controle pegou as três

Registro porque o padrão é mais útil que os casos:

1. **CSS do DS com 0 regras** no preview → toda cor caindo em preto. Era a raiz do servidor, não
   o design. Medição inteira **descartada**, não corrigida em cima.
2. **`getBoundingClientRect()` do `polyline`** para medir espessura de traço: deu idêntico com e
   sem `non-scaling-stroke` (`PEGOU: false`) porque ignora o stroke. Troquei para
   `isPointInStroke()`, e só então o canário pegou.
3. **Regex com escaping de shell** ao contar classes no CSS: o controle negativo
   (`h-[9997px]`, que ninguém usa) devolveu **38** ocorrências — impossível. Os colchetes viraram
   classe de caractere. Refeito com busca literal, e aí o controle deu 0.

Em nenhum dos três o defeito apareceu como erro: apareceu como **número plausível**. O que os
separou de conclusão errada foi o controle, nunca a leitura do resultado.

### ⚠️ ERRATA do mesmo dia — a causa do `NÃO MEDI` dos Papéis 3 e 4 estava ERRADA

Acima eu escrevi, duas vezes, que a produção biz=1 tem **"zero meta cadastrada"**. **É falso**, e o
registro fica porque o erro é do tipo que se propaga: quem lesse aquilo concluiria que destravar a
medição exige *criar meta*, quando o que falta é outra coisa.

**Medido em produção, `/ia`, biz=1, após aviso de sessão irmã** (que afirmou existirem 5 metas — ela
estava certa em me mandar re-medir):

| sonda | valor | leitura |
|---|---|---|
| botões de card de meta | **5** | as metas EXISTEM: Clientes atendidos · Faturamento mensal · Margem de contribuição · Ticket médio · Vendas no mês |
| `[data-contract="painel-meta-apurando"]` | **5** | **todas as 5 em "Aguardando apuração…"** |
| `[data-contract="painel-meta-sem-historico"]` | 0 | o card em `apurando` nem chega a renderizar o sparkline |
| `svg[width=120][height=32]` | 0 | idem — nenhuma série desenhada |

**A causa correta:** não faltam metas, falta **apuração**. Sem `apuracoes_recentes` não há série para
o `Sparkline` do card nem para a `Serie` do drawer; e sem `realizado` o `progresso` é `null`, então a
barra de progresso não renderiza por contrato (`Index.tsx`: `{progresso !== null && …}`).

**O veredito `NÃO MEDI` dos Papéis 3 e 4 permanece** — mas por esta razão, não pela que estava
escrita. E a ação que destrava passa a ser **rodar apuração**, não cadastrar meta.

**Por que eu errei, e é a lição reutilizável:** sondei por `svg[width=120][height=32]` e por
`painel-meta-sem-historico`, os dois ausentes, e li a ausência **do gráfico** como ausência **da
meta**. São proposições diferentes, e a segunda não decorre da primeira — é a mesma família do
§5 2026-09-15 (concluir conteúdo único porque o NOME não existia do outro lado): a medição parou
um nível acima do fato.

### G16 — re-medido após o merge do #7638, e ele ainda NÃO está em produção

O [#7638](https://github.com/wagnerra23/oimpresso.com/pull/7638) (grade de Análises a 3 colunas)
mergeou em `main`, mas a produção **ainda serve 2 colunas**: `grid-template-columns` medido agora =
`1110.5px 1110.5px`, `gap: 16px`, e o card do sparkline segue em **1076,5px**.

Ou seja: **merge não é deploy**, e o G16 continua com o número de 2 colunas até o deploy rodar. Fica
como está, datado — quem reler depois do deploy vai medir ~737,7px e não deve ler a diferença como
regressão.

---

## Rodada MEDIDA 2026-09-21 (2ª) — ritmo vertical entre seções (o `margin-bottom` que ficara aberto)

> A rodada anterior deixou este item **aberto e declarado** como decisão [W] — *"o 16px vem do
> `space-y-4` do container, logo rege TODAS as seções"*. [W] decidiu: arrumar. Esta rodada mede e
> fecha.

**Método:** espaço **VISUAL** entre blocos consecutivos (`top` do próximo − `bottom` do atual), não
a propriedade isolada — que engana quando há padding no meio. Chrome, 2560, dark, os dois lados na
mesma janela.

| de → para | âncora | prod (antes) | veredito |
|---|---|---|---|
| brief → kpis | **18** | 16 | ❌ **DÍVIDA A FECHAR** → fechada |
| kpis → METAS | **18** | 16 | ❌ **DÍVIDA A FECHAR** → fechada |
| METAS → h2 Análises | **6** | 16 | ❌ **DÍVIDA A FECHAR** → fechada |
| h2 → grade | 10 | 10 | ✅ **IGUAL** |
| grade → h2 Ações | **18** | 16 | ❌ **DÍVIDA A FECHAR** → fechada |
| h2 Ações → ações | 10 | 10 | ✅ **IGUAL** |

**6 de 6 transições comparáveis** batem depois da mudança (prova de runtime no DOM da produção,
com as regras do CSS compilado, revertida limpa).

### O que a medição mudou no conserto "óbvio"

A correção intuitiva — trocar `space-y-4` por `space-y-[18px]` e pronto — **pioraria** a transição
de METAS: ela iria de 16 → 18px, onde a âncora quer **6px**. Trocaria um erro de 10px por um de
12px, no sentido oposto. Por isso o wrapper ganhou `mb-1.5` explícito: na âncora, `.jm-metas`
também foge do ritmo.

### O erro de instrumento, que vale mais que o acerto

A **primeira** prova de runtime disse que `h2 → conteúdo` ia de 10 para **18px** — ou seja, que a
mudança quebrava duas transições corretas. Era falso: injetei as regras num `<style>` **fora de
`@layer`**, e CSS fora de layer vence o que está dentro **independente de especificidade**. A regra
do `space-y` do app vive em `@layer utilities`. Refeita a injeção dentro do layer, o `h2` fica em
10px (o `mb-2.5` vence o `:where()` de especificidade 0) e as 6 transições batem.

**Simulação de cascata que não reproduz a CAMADA mede outra cascata** — e o sintoma foi um
falso-negativo plausível, do tipo que não se denuncia.

### Aberto e medido: o `pt-6` de METAS

Do último KPI até o **texto** "METAS ATIVAS" são **46px** em prod (16 margem + 24 `pt-6` + 6
`mt-1.5`) contra **24px** na âncora (18 + 6). É *padding*, não margem, e o bloco pertence a outro
chip. A sessão de METAS mediu em paralelo e confirmou a decomposição: **nenhum dos dois consertos
sozinho acerta** — só este dá 48, só o dela dá 16, **os dois juntos dão 24 exatos**.

**Enforcement:** UC-JPAIN-33, `tests/janaRitmoVerticalReplica.spec.tsx`, 3 casos, mordida provada
nos dois lados com restauração por hash. Detalhe no `Index.casos.md`; aqui não se repete.
