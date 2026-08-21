# Painel da Jana (`/ia`) — protótipo × tela viva, por região e componente

- **Data da medição:** 2026-08-17 (**re-medido** — ver §Correções abaixo) · **âncora:** `prototipo-ui/cowork/jana-merge.jsx` (resolvida por `node prototipo-ui/ancora.mjs Jana/Index`)
- **Tela viva:** `resources/js/Pages/Jana/Index.tsx` + `_components/JanaCockpit.tsx` + `_components/JanaDrillDrawer.tsx` + `_components/JanaMetaDrawer.tsx` + `_components/JanaConfigDrawer.tsx`
- **Charter:** `resources/js/Pages/Jana/Index.charter.md` **v10**
- **Gate F1.5:** esta tela está no manifesto `tests/Browser/visreg-screens.json` como `Jana`; toda mudança aqui gera diff de pixel e precisa de aprovação [W]

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
| R3 · chips do brief `❌` | os chips **existem** (`JanaCockpit.tsx:479-500`) — são **três**, e nenhum tem `onClick`. É `🟡` (botão morto), não `❌` (ausente) | — |

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
| 1 | Receita mês | **Receita mês** | ✅ **(rótulo alinhado em #5881)** |
| 2 | A receber vencido (com `emphasize`) | **A receber vencido** | ✅ **(#5881)** — e o nome novo é mais preciso: é `overdueValue`, o que venceu e não foi pago |
| 3 | Ticket médio | Ticket médio | ✅ |
| 4 | **Frota utilização** | PIX hoje | 🟡 divergem — ver nota |
| — | KPI clicável quando existe análise do mesmo dado (`JM_KPI_DRILL`) | 2 dos 4 abrem drill | ✅ |

> **Nota sobre o 4º KPI.** O protótipo retrata o cockpit do Martinho (`biz=164`), onde frota é o negócio. A tela `/ia` do núcleo atende ROTA LIVRE (vestuário). Com o Non-Goal removido (charter v7), **não há mais proibição** de construir — mas também **não há fonte**: `Modules/OficinaAuto/Entities/Vehicle` é do OficinaAuto. Copiar exige decidir de onde vem o dado para um business que não tem frota.

## R5 · Metas

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| seção | `JmMetasSecao` — "METAS ATIVAS" com seletor de período | bloco "Metas ativas" | 🟡 |
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
| seção | "AÇÕES QUE <NOME> SUGERE" | "Ações que <Nome> sugere" | ✅ |
| linha de ação | `AcaoRow` com CTA por tom | equivalente (ícone + título + sub + CTA) | ✅ |
| ao clicar o CTA | `JmAcaoModal` — confirma antes de disparar (HITL) | `JanaAcaoModal` — prévia do servidor + **Aprovar** grava em `jana_acao_aprovacoes` | ✅ **(esta onda)** — registra a decisão; o **disparo** é PR próprio (por isso o CTA diz "Revisar") |
| gate por plano | só no Pro | — | ❌ produto |

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

`Jana/Pro` **não tem âncora** (`node prototipo-ui/ancora.mjs Jana/Pro` → *"charter sem
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
`prototipo-ui/cowork/jana-merge.jsx` de `origin/main` (md5 `32262939ad3c`) — o espelho **é** canon.
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
  entradas, na ordem exata dos 5 cards medidos, com guarda em `UC-COPI-PAINEL-10`.

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
| ~~1~~ | ~~Modal de ação HITL~~ — **entregue**: prévia do servidor + aprovação registrada | R7/R8 | ✅ charter v10 · UC-COPI-PAINEL-12. **Sucessora:** o DISPARO (WhatsApp/e-mail) + a fila `/ia/acoes` |
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
