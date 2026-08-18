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

## R10 · Plano e upsell

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| selo do plano | `jm-plano` no header | — | ❌ produto |
| upsell inline | card no lugar do brief / das análises quando fora do Pro | link "Jana Pro" → `/ia/pro` | 🟡 |
| persistência da config | `localStorage` `oimpresso.jana.cfg` | idem, via `useJanaConfig` | ✅ |

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
