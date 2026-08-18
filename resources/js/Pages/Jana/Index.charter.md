---
id: resources-js-pages-jana-index-charter
page: /ia
component: resources/js/Pages/Jana/Index.tsx
related_prototype: prototipo-ui/cowork/jana-merge.jsx
owner: wagner
status: live
last_validated: "2026-08-18"
parent_module: Jana
parent_adr: memory/decisions/0052-memoria-jana-3-angulos-faturamento.md
related_adrs: [26, 31, 35, 36, 52, 93, 94, 107, 114]
related_us: [US-COPI-010, US-COPI-011, US-COPI-012, US-COPI-146, US-COPI-148]
related_charters:
  - resources/js/Pages/Jana/Chat.charter.md
  - resources/js/Pages/Jana/Cockpit.charter.md
related_specs:
  - memory/requisitos/Jana/SPEC.md (US-COPI-010, US-COPI-011, US-COPI-012)
runbook: memory/requisitos/Jana/RUNBOOK-index.md
tier: A
charter_version: 10
permissao: copiloto.access
---

# Page Charter — `/copiloto/dashboard`

> **Status:** `live` — implementada e em uso prod biz=1 desde 2026-04. Charter retroativo Wave M 2026-05-16.

---

## Mission

Visão consolidada das **metas ativas do business** com farol verde/amarelo/vermelho, série temporal últimas 12 janelas e projeção linear. Substitui análise manual em planilha — dono/gestor abre, vê rumo, decide.

Audiência primária: **dono/gestor de business** (Wagner, Larissa). Acesso `business_id` scoped — superadmin vê escopo via switch.

---

## Goals

- **Barra ÚNICA da área Jana** — `JanaAreaHeader` (em `Pages/Jana/components/`) É o `<PageHeader>` canon: título `Jana · Analista IA` + business/`biz=` + "Atualizado HH:MM" (botão de reapuração) na Zona L, `JanaSubNav` no slot `subnav`, ações da tela + primary "Conversar" na Zona R. Compartilhado com Chat.tsx e Memoria.tsx. Ver `memory/requisitos/Jana/Chat-header-tabs-visual-comparison.md` (gate F1.5).
- Render < 200ms p95 com `Inertia::defer()` em `metas` paginated + `apuracoes` 12 janelas
- Farol calculado server-side via `ApuracaoService::farol(meta, agora)` — frontend só consome
- Click em meta → drilldown `/copiloto/metas/{id}` (US-COPI-011) com série completa
- CTA "Conversar com a Jana" abre `Chat.tsx` com contexto da meta selecionada
- **Drill-down "de onde vem esse número" (v3 — 2026-08-07):** card de análise abre drawer
  (`_components/JanaDrillDrawer.tsx`) com **Fonte** (tabelas · regra do recorte · método que
  calcula) + **Escopo** (`business_id` da sessão). Um KPI só é clicável quando existe análise
  do **MESMO dado** — "ticket médio não abre faturamento". Hoje 2 dos 4 KPIs abrem
  (Receita mês → Faturamento; A receber vencido → Inadimplência); Ticket médio e PIX hoje
  não têm análise do mesmo dado e permanecem estáticos. Âncora:
  `prototipo-ui/cowork/jana-merge.jsx` §`JmDrillDrawer` + §`JM_KPI_DRILL` — âncora de SÍMBOLO
  (ref de linha apodrece no 1º refactor, §5 2026-07-26; re-localize com
  `grep -n "JmDrillDrawer\|JM_KPI_DRILL" prototipo-ui/cowork/jana-merge.jsx`).
  _Recibo 2026-08-11: no arquivo versionado (`SYNC` com o vivo, sha256 normalizado
  `057bd8ae081bfd1c…`) os símbolos caem em `:640` e `:887` — as duas refs que a v3 citava
  **conferem**. Ficam como símbolo, não linha, porque o número é que é frágil, não a citação._

- **Configurar (v8 — 2026-08-17):** a ação "Configurar" do `JanaAreaHeader` abre
  `_components/JanaConfigDrawer.tsx` — quais das 4 análises aparecem no painel, persistido em
  `localStorage['oimpresso.jana.cfg']` (prefixo `oimpresso.jana.*`, canon do `Chat.charter.md`).
  Âncora: `jana-merge.jsx` §`JmConfigDrawer` — âncora de SÍMBOLO
  (`grep -n "JmConfigDrawer" prototipo-ui/cowork/jana-merge.jsx`).
  O drawer é **deliberadamente menor que a âncora**: ver §Anti-hooks abaixo.

- **A meta abre NA PRÓPRIA TELA (v9 — 2026-08-17):** o clique num card de meta abre
  `_components/JanaMetaDrawer.tsx` — Situação (realizado · alvo · % do alvo · delta vs a janela
  anterior), Série de até 12 janelas em barras, e "De onde vem esse número". O caminho pra tela
  própria **não se perdeu**: virou "Abrir a meta" (`/ia/metas/{id}`) no rodapé do drawer.
  Âncora: `jana-merge.jsx` §`JmMetaDrawer` — âncora de SÍMBOLO
  (`grep -n "JmMetaDrawer" prototipo-ui/cowork/jana-merge.jsx`).
  O drawer **não projeta o fechamento**: ver §Anti-hooks abaixo.

- **Ação sugerida vira decisão REGISTRADA (v10 — 2026-08-18):** o CTA de cada linha
  da seção "Ações que … sugere" abre `_components/JanaAcaoModal.tsx` — prévia do que a
  ação faria + aprovação auditada em `jana_acao_aprovacoes` (`AcaoHitlService`,
  `business_id` NOT NULL, Tier 0). **A PRÉVIA NASCE NO SERVIDOR**, lida do mesmo
  agregado que pinta a linha (`SellsCockpitAggregator::buildInsightsAggregates`) —
  pela mesma razão do farol e da fonte do drill: texto que afirma número é veredito.
  **Este passo NÃO envia nada** — o disparo (WhatsApp/e-mail) e a fila `/ia/acoes` são
  PR próprio, e é por isso que os rótulos viraram "Revisar …": ver §Anti-hooks.
  Âncora: `jana-merge.jsx` §`JmAcaoModal` — âncora de SÍMBOLO
  (`grep -n "JmAcaoModal" prototipo-ui/cowork/jana-merge.jsx`).

## Non-Goals

- ⛔ Edição inline de meta (vai em `/copiloto/metas/{id}/edit` — US-COPI-013)
- ⛔ Criação de meta (vai em chat US-COPI-004 ou wizard US-COPI-012)
- ⛔ Comparativo entre business (superadmin tem `/copiloto/admin/governanca`)

## UX targets

- 1 viewport scroll desktop 1280px (ROTA LIVRE monitor)
- Mobile responsivo — stack vertical cards, swipe horizontal não-essencial
- Dark mode obrigatório (`@/Layouts/AppShellV2` default)
- Toast `sonner` em mutations (arquivar meta)
- `KpiCard` shared component pra cada meta (consistência cross-module)
- `EmptyState` shared component se 0 metas — CTA "Pergunte algo a Jana"
- **Demo polish (v2 — CYCLE-06 G3):** badge `JANA V2` no header, KPI strip 3 colunas (Memória ativa / Última conversa / Brain B hoje — placeholders pra Brain B preencher futuro via `Inertia::defer`), card "Próxima ação sugerida" (mock didático), empty state com ícone `Sparkles` + CTA `Pergunte algo a Jana` em vez de texto plano.
  _A **prescrição de COR** deste bullet foi **revogada por [W] em 2026-08-12** (ver v5). Ela dizia
  `badge gradient violet→fuchsia→pink` e `card "Próxima ação sugerida" violet-tinted` — escala crua
  Tailwind, fora do sistema de token e sem par no escuro. A **estrutura** (badge, strip, card, empty
  state) segue valendo tal como o v2 a definiu; só a **cor** passa a vir de token semântico
  (`primary`/`success`/`warning`/`destructive`)._

## Anti-hooks

- ⛔ Re-fetch polling de apuracoes — usa `Inertia::defer()` server-side
- ⛔ Cálculo de farol no frontend — fonte autoritativa `ApuracaoService::farol`
- ⛔ Segunda barra de header na tela — identidade/ações vivem no `JanaAreaHeader` (PageHeader canon), nunca num `<header>` próprio de componente filho
- ⛔ Mutation otimista sem rollback — usar `router.patch` com `onError`
- ⛔ **Citar no drawer de drill-down fonte/serviço que não existe no repo.** O drawer se chama
  "de onde vem esse número" — nome fictício ali é mentira com selo de autoridade. Até
  **2026-08-13** o protótipo listava `AnaliseInadimplenciaService`/`AnaliseFaturamentoService`/etc,
  e **nenhuma das seis existia** (medido 2026-08-07 e re-medido 2026-08-13:
  `git grep -E '(class|interface) Analise[A-Za-z]*Service'` → rc=1). ⚠️ **Correção 2026-08-17:**
  esta frase dizia que "nessa data a tabela `FONTE` do `jana-merge.jsx` passou a citar
  `SellsCockpitAggregator::<metodo>`". **Não passou** — medido nos dois donos do inventário
  (espelho e Cowork vivo): os seis `Analise*Service` seguem lá e `SellsCockpitAggregator` aparece
  **0×**. Aquele conserto nunca landou no protótipo.
  A **regra segue inteira**: a fonte citada no drawer vem lida do código real
  (`app/Services/Sells/SellsCockpitAggregator.php`), nunca dos nomes do protótipo. Onde o back não
  tem método (`churn`), declara-se isso em texto, e o render só veste de `<code>` o que contém
  `::`. Mexeu no aggregator, mexe no `JANA_DRILL_FONTES` no mesmo PR.
  _Guard: `prototipo-ui/ancora.mjs` acusa símbolo de backend citado na âncora que não exista no
  repo — e desde 2026-08-13 enxerga também o formato `Classe::metodo` (antes ficava cego nele)._
- ⛔ **Oferecer no drawer de configuração um controle que o servidor não honra.** É a mesma família
  do anti-hook acima, no eixo da CONFIGURAÇÃO em vez do da FONTE. Medido em 2026-08-17: o
  `JmConfigDrawer` da âncora oferece brief diário on/off + hora (o brief é gerado server-side por
  `BriefingAgent` — nenhum cron lê o `localStorage` de um navegador), áudio/TTS (não existe; o
  próprio protótipo diz *"entra na M2"*), retenção *"ela esquece sozinha"*
  (`jana:retention-purge` foi **descartado por [W]** — *"num ERP não se apaga PII"*) e 6 toggles de
  análise quando a tela renderiza **4** cards. Toggle que não muda nada é a promessa do rodapé do
  brief com outra roupa — e foi por isso que o contrato manteve os botões "(em breve)" fora dele.
  Entra no drawer só o que é verdade **e** de fato local (quais análises aparecem); preferência que
  vale pra empresa toda aponta pro dono server-side que já existe (`PATCH /ia/alertas/config` →
  `business.essentials_settings.alertas`, per-business), em vez de ganhar um segundo dono.
  _Guard: `UC-COPI-PAINEL-10` conta os `<Switch` do drawer (2: análises + HITL travado) e as
  entradas de `JANA_ANALISES` (4) — toggle novo derruba o caso. A asserção é estrutural de
  propósito: buscar a palavra "Frota" proibiria o próprio comentário que registra a decisão
  (§5 2026-07-26)._
- ⛔ **Projetar o fechamento de meta no frontend.** É o §Anti-hooks do farol no eixo da PROJEÇÃO,
  e vale pela mesma razão: projeção é veredito sobre o futuro, e veredito nasce no servidor. A
  âncora faz o contrário — o `jmMeta()` do `jana-merge.jsx` extrapola o ritmo quando a meta acumula
  (`atual × 1.3`) e projeta a tendência da série quando é média/taxa, tudo no cliente. Portar isso
  daria à tela autoridade sobre um número que nenhum serviço apurou, e o rótulo "(tendência)" da
  âncora não conserta: ele explica o método, não a fonte. O `JanaMetaDrawer` mostra **"% do alvo"**,
  que é aritmética sobre dois números já exibidos. Se a projeção virar produto, o dono é
  `ApuracaoService` (onde `farol` já mora) — e aí a tela só consome, como consome o farol.
  _Pelo mesmo motivo o drawer não traz a `nota` por meta (`"mix de produto puxando pra baixo"`):
  o payload não tem o campo, e escrevê-la seria a mentira com selo de autoridade que o
  `JanaDrillDrawer` existe pra evitar._
- ⛔ **Prometer no botão do drawer o que a rota não entrega.** `ChatController@novaConversa` não
  aceita pergunta inicial e `Chat.tsx` não lê query param (medido 2026-08-07) — por isso o CTA diz
  "Conversar com a Jana", não "Perguntar sobre isso". Semear a pergunta é PR próprio (backend + Page).

  _**Recibo v10 (2026-08-18), e ele é o conteúdo da decisão:** os 5 CTA da seção de ações eram
  MORTOS (`title="(HITL — em breve V2)"`, zero `onClick`). Ao ganharem comportamento, foram
  **RENOMEADOS** — `Disparar`/`Preparar`/`Investigar`/`Detalhe`/`Lembrar` → **`Revisar …`** — porque
  este passo registra a aprovação e **não envia**. "Disparar" abrindo um modal que não dispara
  trocaria um botão morto por um botão que MENTE, e esta regra vale igual pros dois. O rodapé da
  seção perdeu junto a frase "Próximas ondas: ações HITL real …": metade dela deixou de ser futuro.
  A paridade rótulo↔chave de backend é amarrada por teste (UC-COPI-PAINEL-12) — regra que nasce só
  no `.tsx` viraria botão que abre modal e morre em 404, que é botão morto com um passo a mais._

- ⛔ **Deixar a prévia da ação nascer no cliente.** É o §Anti-hooks da FONTE (drill) e o da PROJEÇÃO
  (meta) no terceiro eixo: o da PRÉVIA. A âncora faz o contrário — o `JmAcaoModal` traz as 4 prévias
  em texto FIXO, com números do Martinho (`biz=164`), e cita `Analise*Service` que **não existem no
  repo** (re-medido 2026-08-17 nos dois donos do inventário: espelho e Cowork vivo). A prévia do
  `JanaAcaoModal` vem de `GET /ia/acoes/{key}/previa`, gerada por `AcaoHitlService` a partir do mesmo
  agregado que pinta a linha — prévia e linha não podem divergir. E o texto GRAVADO em
  `jana_acao_aprovacoes.previa` é o do servidor, nunca o do request: aceitar o do cliente deixaria o
  front reescrever o recibo do que foi aprovado. Mexeu no aggregator, mexe no `AcaoHitlService` no
  mesmo PR.

- ⛔ **Montar toast próprio nesta Page.** `app.tsx` já faz `router.on('success')` → `showFlashToast`,
  lendo `flash.success` (a chave que `HandleInertiaRequests` expõe). Um `useEffect` + `toast()` aqui
  dá toast **em dobro**; e a chave `flash.sucesso` — que parece natural em PT-BR — **não existe** e
  falharia calada. Mutação da tela usa `->with('success', …)` no controller e para por aí.

## Skills relevantes

`brief-first` (Tier A) · `multi-tenant-patterns` (Tier A) · `inertia-defer-default` (Tier B) · `mwart-process` (Tier A)

## Charter version log

- v10 (2026-08-18) — **Ação HITL: prévia + aprovação registrada** (ordem 1 do
  `Index-visual-comparison.md` — a única linha do §Resumo cuja trava era literalmente
  *"backend — sem ele, todo CTA da seção é decorativo"*). Novos: migration
  `jana_acao_aprovacoes`, `Entities/AcaoAprovacao`, `Services/AcaoHitlService`,
  `Http/Controllers/AcaoHitlController` (2 rotas) e `_components/JanaAcaoModal.tsx`.

  **O escopo parou onde a honestidade mandava.** Este PR entrega prévia + registro; o
  **disparo** e a fila `/ia/acoes` são PR próprio. Por isso os 5 rótulos viraram
  "Revisar …" e o rodapé da seção deixou de prometer — ver o recibo no §Anti-hooks.

  **Três divergências do pedido [CC] que a medição corrigiu, e ficam registradas:**
  - o pedido numerava o caso como **UC-COPI-PAINEL-11**. Esse número já é da v9 (drawer
    de meta) — o caso nasceu **12**. Numerar por cima teria apagado um contrato vivo.
  - o pedido mandava montar `usePage().props.flash.sucesso` + `toast()` na Page (a
    "ordem 6 de carona"). **A chave `sucesso` não existe** — `HandleInertiaRequests`
    expõe `flash.success` —, e mesmo com o nome certo seria **toast em dobro**: o
    `app.tsx` já tem handler global. Virou anti-hook em vez de código.
  - o pedido punha `addGlobalScope(new ScopeByBusiness)` na Entity; o canon do repo é o
    trait `HasBusinessScope` (o docblock dele manda migrar o padrão antigo), que é o que
    `Meta`/`Conversa` usam.

  _O que NÃO mudou: o par visual segue pendente. `Jana/Index` está no manifesto do
  visreg, este PR gera diff de pixel e exige aprovação [W] (gate F1.5); o golden PT-04
  continua `draft`. E a linha "Limpeza >365d" da âncora **não** entrou — o dado existe
  (`ageingBuckets['>365d']`), mas a ordem certa é HITL primeiro, linha depois, como o
  próprio `Index-visual-comparison.md` §R7 já registrava._

- v9 (2026-08-17) — **A meta abre na própria tela** (ordem 1 do `Index-visual-comparison.md`, região
  R5 — *"hoje o clique tira o usuário da tela"*). Novo `_components/JanaMetaDrawer.tsx`: Situação,
  Série de até 12 janelas e "de onde vem esse número". O card virou `<button>` e ganhou a barra de
  progresso (`jm-meta-track`); o caminho pra tela própria virou "Abrir a meta" no rodapé do drawer.
  Tipos e formatadores com dois consumidores foram pra `_components/metaFormat.ts` — arquivo de
  componente não exporta não-componente (`react-refresh`, a mesma regressão que separou o
  `useJanaConfig` na v8). `periodoLabel`/`Sparkline` **ficaram** no `Index.tsx`: são do card, e o
  drawer recebe o período já formatado.

  **Duas coisas da âncora ficaram de fora, e o motivo é o §Anti-hooks novo acima:** a projeção de
  fechamento e a `nota` por meta. Nenhuma das duas é pendência de wiring.

  ⚠️ **Trabalho paralelo, e o crédito é de quem fez:** o **#5881** landou no `main` enquanto este PR
  estava aberto, fechando do mesmo pedido o **aviso de viewport** (`md:hidden`, casando o
  `@media (max-width:768px)` do protótipo), **"Nova meta"** e o **período no card**, e renomeando
  dois rótulos de KPI (Faturamento mês → **Receita mês**; Inadimplência total → **A receber
  vencido**). Este PR fazia os três primeiros também, em versão pior num deles — o "Nova meta" saía
  com `<Link>` do Inertia, e o #5881 mediu que `MetasController@create` devolve **Blade**, o que
  faria o clique virar no-op silencioso. Na reconciliação **a versão deles ficou inteira**; deste PR
  sobrou o que era só dele.

  **Correção de fato no mesmo PR (regra de precedência):** o `Index-visual-comparison.md` afirmava
  **seis** ausências que já não existiam (reapuração, Configurar, subtítulo das análises, drawer de
  config, skeleton, `localStorage`) e chamava os chips do brief de ausentes quando eles existem e
  são apenas mortos. Uma delas — *"botão de reapuração ❌"* — **nasceu falsa**: o botão landou em
  #5429 (2026-08-07), dez dias antes. A tabela de correções ficou no topo daquele documento, com o
  recibo de cada linha.

  _O que NÃO mudou: o par visual segue pendente. `Jana/Index` está no manifesto do visreg, o PR gera
  diff de pixel e exige aprovação [W] (gate F1.5); o golden PT-04 continua `draft`._

- v8 (2026-08-17) — **O botão "Configurar" deixou de ser promessa** (entrega 4 da onda de
  aproximação, região R8/R10 do `Index-visual-comparison.md`). Abre
  `_components/JanaConfigDrawer.tsx`, com o estado em `_components/useJanaConfig.ts` (hook separado
  porque arquivo de componente não exporta não-componente — `react-refresh`; a regressão apareceu no
  `lint:baseline:check` e a saída certa foi separar, não regravar o baseline).

  **O drawer é menor que a âncora, e isso é o conteúdo da decisão** — as 4 promessas do
  `JmConfigDrawer` que o servidor não honra ficaram de fora, com a medição registrada no anti-hook
  novo acima. Sobrou o que é verdade e é local: quais das 4 análises aparecem no painel, persistido
  sob o prefixo canon `oimpresso.jana.*`.

  **Uma correção de fato, no mesmo PR (regra de precedência):** este charter e o `casos.md` diziam
  que os botões "(em breve)" eram **dois**. Agora é **um** (Exportar) — a pergunta do `_pendente_w`
  segue idêntica para o que sobrou, e a de Configurar foi respondida entregando.

  _O que NÃO mudou: o par visual segue pendente. `Jana/Index` está no manifesto do visreg, então o
  PR gera diff de pixel e exige aprovação [W] (gate F1.5) — e o golden PT-04 continua `draft`._

- v7 (2026-08-17) — **O Non-Goal da análise "Frota" foi REMOVIDO por decisão [W]**, textual:
  *"frota e caçambas locações remova do charter"* + *"eu não vejo problema em fazer igual. isso vai
  me destravar, os detalhes estão me atrapalhando"*. Saíram as 26 linhas que proibiam construir a
  análise na tela `/ia` e que reproduziam o vocabulário do domínio OficinaAuto. O Painel passa a
  poder seguir o protótipo sem essa exceção. **A decisão é [W], não inferência do agente** — ele
  reverteu a própria posição de 2026-08-07/08-13, que está preservada nas entradas v3 e v6 abaixo
  como o que era verdade naquelas datas.

  **Sem obstáculo de máquina, medido hoje:** o `dominio-gate` **não** varre esta tela. Seus
  `forbidden_ui_paths` são exatamente três — `resources/js/Pages/OficinaAuto`,
  `Modules/OficinaAuto/Database/Seeders`, `Modules/OficinaAuto/Database/Migrations`
  (`memory/dominio/oficina-auto.md`). `Pages/Jana` está fora. Uma sessão anterior (esta, mais cedo)
  afirmou ao [W] que "o `dominio-gate` reprovaria" — **estava errado**, e o erro fica registrado
  aqui em vez de sumir.

  **Duas afirmações da v6 abaixo são FALSAS e não se apagam — corrigem-se aqui.** Medido em
  2026-08-17 nos DOIS donos do inventário de design (espelho `prototipo-ui/` **e** Cowork vivo via
  `DesignSync.get_file`):
  - a v6 diz que os 6 `Analise*Service` "viraram `SellsCockpitAggregator::<metodo>`". **Não
    viraram.** Os seis seguem no `jana-merge.jsx` (linhas 645-650) e `SellsCockpitAggregator`
    aparece **0×** — no espelho e no vivo. Aquele conserto nunca landou.
  - o §Non-Goals removido afirmava que `Locadas`/`caçambas`/`FROTA UTILIZAÇÃO` "não vivem no
    `jana-merge.jsx`". **Vivem**: `frota` 8× e `caçamba` 7× no arquivo vivo.

  **O que NÃO caiu com o Non-Goal:** o anti-hook *"não citar no drill fonte que não existe"* segue
  valendo — ele é regra sobre honestidade da UI, não sobre frota. Ao construir o drill, a fonte
  citada tem que existir no repo (`app/Services/Sells/SellsCockpitAggregator.php`), nunca os
  `Analise*Service` do protótipo, que continuam fictícios.

- v6 (2026-08-13) — **Dois dos três defeitos do v5 consertados; o terceiro era diagnóstico errado.**
  (1) **P-1 consertado** — os 6 `Analise*Service` inexistentes viraram
  `SellsCockpitAggregator::<metodo>`, lidos do `JANA_DRILL_FONTES`; `churn` e `frota`, que **não
  têm** método no back, declaram isso em texto, e o render deixou de vestir de `<code>` o que não é
  símbolo. (2) **P-2 RETIRADO** — não era defeito: o Non-Goal governa a tela, não o retrato da
  âncora; a remoção foi revertida por decisão [W] no mesmo dia (ver §Non-Goals). (3) **P-3
  consertado** — os 12 fundos `*-soft` de status passaram a `color-mix(cor 12%, var(--surface))`:
  o shell força os `*-soft` CLAROS nos dois temas, e no escuro isso reprovava o AA (neg 2,19 ·
  warn 1,60 · pos 1,93 → 4,22 · 5,68 · 5,08), sem regredir o claro. O `accent` FICOU como estava —
  medido, ele passa hoje (4,41) e o mesmo mix o reprovaria (2,35). Junto: o detector do
  `ancora.mjs` passou a enxergar `Classe::metodo` (ele ficaria cego no formato correto — FP medido
  antes, zero), e a **baseline de pixel da Jana foi regenerada**: a de 10/ago era anterior ao #5719
  (paridade de tema escuro) e reprovava por uma mudança de cor que já era aprovada.
- v5 (2026-08-12) — **Paridade de tema escuro** do Painel (`/ia`), sobre o pedido [CC] rev.2.
  Remove do §UX targets a prescrição de **cor** do "Demo polish (v2)" — **decisão [W] nesta data**,
  tomada com o conflito na mesa: o `Index.tsx` já contava **6 violações `no-restricted-syntax`** no
  [`config/eslint-baseline.json`](../../../../config/eslint-baseline.json) (regra
  `ds/no-raw-palette-color`, ratchet [ADR 0209](../../../../memory/decisions/0209-eslint-9-flat-config.md)),
  ou seja, o guard DS já tratava aquelas cores como **dívida** enquanto este charter as declarava
  **alvo de UX**. Os dois não podiam estar certos. Corrigido o perdedor no mesmo PR, como manda a
  regra de precedência ([`proibicoes.md`](../../../../memory/proibicoes.md) §Precedência).
  A estrutura do v2 fica intacta; muda só a origem da cor.
- v1 (2026-05-16) — Charter retroativo Wave M boost Modules/Jana 64→78
- v4 (2026-08-08) — **Fatia A** da fusão (US-COPI-148): barra ÚNICA no `<PageHeader>` canon (ver §Goals). Duas correções de fato, não de estilo: (1) o §Goals e o §Anti-hooks citavam `MetricasApurador::farol`, classe que existe (`Modules/Jana/Services/Metricas/MetricasApurador.php`) mas **não tem** método `farol` — a implementação é `ApuracaoService::farol` (`:151`, PR #5394); charter que aponta pro lugar errado manda a próxima sessão procurar a regra onde ela não está. (2) o §Goals descrevia o header antigo (dot JANA + tabs `Dashboard | Chat`), que esta onda substituiu — corrigido no mesmo PR, como manda a regra de precedência (corrigir o perdedor junto). _Nasceu numerado v3 e virou v4 no merge: a Fatia B (abaixo) landou primeiro e já tinha tomado o v3._
- v3 (2026-08-07) — Drill-down "de onde vem esse número" (`_components/JanaDrillDrawer.tsx`) nos 4
  cards de análise + nos 2 KPIs que têm análise do mesmo dado. Fonte lida do código real, não dos
  nomes fictícios do protótipo (2 anti-hooks novos). Non-Goal novo: análise "Frota" **não** será
  construída (decisão [W]; `forbidden_ui_terms` + OficinaAuto-only). Fatia B do pacote
  `JANA-FUSAO-2026-08-06`, US-COPI-148.
- v2 (2026-05-16) — Polish demo CYCLE-06 G3: badge gradient `JANA V2`, KPI strip 3 colunas, card "Próxima ação sugerida", empty state polish (ícone Sparkles + CTA "Pergunte algo a Jana"). Logic chat preservado (apenas UI surface — ChatController intacto). Ver `memory/requisitos/Jana/demo-pilot-2026-05-16/SCREENSHOT-GUIDE.md`
