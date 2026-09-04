---
id: resources-js-pages-fiscal-cockpit-casos
casos: Cockpit Fiscal · /fiscal
irmaos: Cockpit.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "revalidado em 2026-09-04 ao paginar a lista (Onda 3): UC-FCKP-01..06 relidos um a um — nenhum toca a fonte nem o recorte da lista (são gate, agregação, ribbon, alertas, escopo e cache), logo seguem válidos. UC-FCKP-07 (dado real) segue válido: a paginação recorta a MESMA lista, não muda a fonte. UC-FCKP-08 (fila de alertas) segue válido: o rodapé entra depois da tabela e não encosta na seção de alertas. UC-FCKP-09 nasce aqui, com lane e teste próprios, verde local 4/4 e mordida provada em 2 mutações. Veredito das lanes pendente do CI deste PR"
related_us: [US-FISCAL-002, US-FISCAL-019]
---

# Casos de Uso & Aceite — Cockpit Fiscal

> **Revalidação `last_run` 2026-09-01 — Onda 1 Fiscal (saneamento `fx-*` → DS):** mudança de
> **apresentação apenas** — 3 `fx-chip-action` e 4 `fx-btn` → `<Button>`, `fx-search` +
> `<input type="search">` → `<Input>`, e o `fx-ribbon-cta` ("Fechar mês") → `<Button>`.
> Conferi os 6 UC um a um: **todos assertam backend** — gate `fiscal.access` (T0), as três
> leituras numa resposta só, as 7 medidas do ribbon, alertas determinísticos (sem IA na tela),
> soma cross-tenant (T0) e o cache por business com invalidação pela mesma chave (T0).
>
> **O `UC-FCKP-03` fala do RIBBON e por isso foi conferido de perto — o `fx-ribbon` FOI
> MANTIDO**, e é decisão declarada: ele é faixa horizontal rolável com bordas topo/base e
> divisores verticais entre itens (`border-right` por item, com `:first-child`/`:last-child`
> especializados). O par DS mais próximo — `KpiGrid` + `KpiCard` — produz **grade de cards**
> (`grid gap-3 grid-cols-*`). Trocar seria **redesenho**, não troca de primitiva, e esta onda é
> cirúrgica por decisão [W]. A âncora `data-contract="fiscal-cockpit-kpis"` e as 6 copies do
> contrato seguem no lugar. **Nenhum teste re-executado** (Pest = CT 100).

> **Revalidação `last_run` 2026-08-28 — o que foi conferido:** este PR muda a tela em **um único ponto**: o atributo `data-contract="fiscal-cockpit-kpis"` no wrapper, âncora do mapa [`fiscal-cockpit.map.json`](../../../../memory/requisitos/Fiscal/fiscal-cockpit.map.json). Conferi o diff do `.tsx` contra a lista de UC deste arquivo — **nenhum UC depende de atributo de DOM**, logo nenhum aceite mudou. **Nenhum teste foi re-executado** nesta revalidação (Pest = CT 100); os vereditos seguem como estavam.

> Persona: **Eliana [E] (contadora)** — leitura consolidada do estado fiscal do mês.
>
> **Âncora:** `CU-FISC-01`, `CU-FISC-12` e `CU-FISC-13` do
> [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md). Os UC derivam do **CU**, nunca do `.tsx`.
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, **veredito pendente da lane** · ⬜ não verificado · ❌ quebrou.

## Força do veredito — qual lane, e se ela **bloqueia merge**

| Teste | Lane | Bloqueia merge? |
|---|---|---|
| `CockpitMultiTenantTest` | `PHP / Pest (NfeBrasil · MySQL)` | ✅ **sim** — required no [baseline](../../../../governance/required-checks-baseline.json) e na allowlist do workflow |
| `CockpitControllerTest` · `CockpitCacheTest` | `Pest Fiscal` (SQLite — **pulam** por exigirem schema MySQL) + suíte noturna CT 100 | ❌ **não** — advisory |

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FCKP-01 | gate de acesso ao cockpit | `[must]` `[T0]` | CU-FISC-13 | `CockpitControllerTest` | 🧪 |
| UC-FCKP-02 | a tela entrega as 3 leituras | `[must]` | CU-FISC-01 | `CockpitControllerTest` | 🧪 |
| UC-FCKP-03 | as 7 medidas do ribbon | `[must]` | CU-FISC-01 | `CockpitControllerTest` | 🧪 |
| UC-FCKP-04 | alerta determinístico, sem IA | `[must]` | CU-FISC-01 | `CockpitControllerTest` · `CockpitMultiTenantTest` | 🧪 |
| UC-FCKP-05 | KPI não conta outro business | `[must]` `[T0]` | CU-FISC-12 | `CockpitMultiTenantTest` | 🧪 |
| UC-FCKP-06 | cache separado por business | `[must]` `[T0]` | CU-FISC-12 | `CockpitCacheTest` | 🧪 |
| UC-FCKP-08 | o alerta é desenhado e leva a algum lugar | `[must]` | CU-FISC-01 | `CockpitControllerTest` | 🧪 |
| UC-FCKP-09 | a tela serve uma PÁGINA, e filtrar volta à 1ª | `[must]` | CU-FISC-01 | `fiscal-cockpit-paginacao.test.tsx` | 🧪 |
| UC-FCKP-10 | cert A1 VENCIDO entra na fila de alertas | `[must]` | CU-FISC-01 | `CockpitControllerTest` | 🧪 |

---

## UC-FCKP-01 — Sem `fiscal.access`, o cockpit não abre `[must]` `[T0]`

**Dado** um usuário autenticado sem `fiscal.access` e sem `superadmin`
**Quando** ele abre `/fiscal`
**Então** recebe 403.

- **Âncora de contrato:** `R-FISCAL-003` do [SPEC.md](../../../../memory/requisitos/Fiscal/SPEC.md) §3.
- **Regressão que defende:** cockpit aberto por herança de sessão a quem não tem o módulo habilitado.
- **Teste:** `Modules/Fiscal/Tests/Feature/CockpitControllerTest.php` — `it('UC-FCKP-01 · GET /fiscal aborta 403 sem permission superadmin nem fiscal.access')`
- **Status:** 🧪 advisory + noturna; veredito da lane.

## UC-FCKP-02 — O cockpit entrega as três leituras do mês numa resposta só `[must]`

**Dado** uma contadora com permissão
**Quando** abre `/fiscal`
**Então** recebe a tela do cockpit já com as três leituras que a decisão dela exige: os indicadores do mês, a série dos últimos 14 dias e a fila de alertas.

- **Regressão que defende:** a promessa do charter (*"estado fiscal do mês em até 3 segundos"*) morre se alguma das três virar carregamento tardio. O charter proíbe explicitamente adiar os indicadores.
- **Teste:** `CockpitControllerTest` — `it('UC-FCKP-02 · GET /fiscal renderiza Inertia component Fiscal/Cockpit com props canon')`
- **Status:** 🧪 advisory + noturna.

## UC-FCKP-03 — O ribbon carrega as 7 medidas que a contadora usa para decidir `[must]`

**Dado** o cockpit carregado
**Quando** a contadora lê o topo da tela
**Então** encontra: quantas notas foram emitidas no mês, quantas foram autorizadas, o percentual de sucesso, quantas foram rejeitadas, o faturamento fiscal, quantos DF-e aguardam manifestação e quantos dias restam do certificado.

- **Regressão que defende:** sumir com uma medida no refactor e a contadora precisar abrir outra tela para saber o mesmo.
- **Teste:** `CockpitControllerTest` — `it('UC-FCKP-03 · props.kpis tem shape canon (6 chaves obrigatorias)')`
- **Status:** 🧪 advisory + noturna.
- ⚠️ **Nota honesta:** o nome do teste diz "6 chaves" e a lista assertada tem **7** — divergência de rótulo, não de comportamento. Não corrigida aqui para não misturar escopo; registrada no session log.

## UC-FCKP-04 — Os alertas são determinísticos: nenhum raciocínio de IA viaja para a tela `[must]`

**Dado** rejeições recentes, certificado vencendo ou DF-e pendente
**Quando** o cockpit monta a fila de alertas
**Então** cada alerta traz nível, título, subtítulo e a ação a tomar — e **nenhum campo de raciocínio de modelo de linguagem** chega à tela. Os níveis são exatamente crítico, atenção e informativo.

- **Regressão que defende:** anti-hook do charter (*"não usar LLM para gerar alertas — receita determinística por estado"*). Alerta fiscal precisa ser reproduzível e auditável; texto gerado por modelo não é.
- **Teste:** `CockpitControllerTest` — `it('UC-FCKP-04 · props.alerts é array de items deterministicos (sem campos LLM tipo thought/reasoning)')` · `CockpitMultiTenantTest` — `it('UC-FCKP-04 · computeAlerts não usa LLM — receitas determinísticas por estado')`
- **Status:** 🧪 advisory + **required** (o segundo está na lane que bloqueia).

## UC-FCKP-05 — Os indicadores nunca somam notas de outro business `[must]` `[T0]`

**Dado** notas do business ativo e de outro business
**Quando** os indicadores do mês são calculados
**Então** apenas as notas do business ativo entram na conta.

- **Regressão que defende:** vazamento cross-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) — aqui na forma mais silenciosa, um número inflado que ninguém questiona.
- **Teste:** `Modules/Fiscal/Tests/Feature/CockpitMultiTenantTest.php` — `it('UC-FCKP-05 · computeKpis scope per business: biz=99 não aparece em counts de biz=1')`
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FCKP-06 — O cache de indicadores é separado por business e é invalidado pela mesma chave `[must]` `[T0]`

**Dado** dois businesses com cockpit em cache
**Quando** um deles tem o cache invalidado por uma nota autorizada
**Então** o outro sobrevive intacto; e a chave que o invalidador apaga é exatamente a que a tela lê.

- **Regressão que defende:** duas de uma vez — (a) cache agregado sem `business_id` serviria o número de um cliente para outro (o charter **já mandou o oposto uma vez** e foi corrigido em 2026-07-06 justamente por isso); (b) chave que não casa com o invalidador deixa número velho na tela por 60 s sem que ninguém perceba.
- **Teste:** `Modules/Fiscal/Tests/Feature/CockpitCacheTest.php` — `it('UC-FCKP-06 · cache keys de businesses diferentes são INDEPENDENTES (multi-tenant ADR 0093)')`, `it('UC-FCKP-06 · cache key prefix bate com InvalidaCockpitCacheListener (consistency contract)')`, `it('UC-FCKP-06 · Listener invalida a key correta dado um event com business_id')`
- **Status:** 🧪 advisory + noturna.

---

## UC-FCKP-07 — A lista de notas e os contadores das visões salvas vêm de dado real `[must]`

**Dado** um business cujo cockpit é aberto
**Quando** a lista unificada e os contadores das visões salvas são montados
**Então** ambos derivam de `NfeEmissao` + `NfseEmissao` — a mesma fonte —, e sem emissão real a lista vem vazia, nunca preenchida com demonstração.

- **Regressão que defende:** a tela exibia **três números para a mesma coisa** — header "0 notas" (KPI real), lista com 10 linhas (mock) e chip "Todas 18" (outro mock). Medido na produção viva em 2026-09-02, com o certificado A1 vencido há 26 dias no mesmo cockpit: a leitura natural de quem abre é "está tudo funcionando", e não está. Derivar os contadores da MESMA lista faz chip e tabela concordarem por construção.
- **Também defende (LC-30):** as chaves dos contadores são os `id` das SAVED_VIEWS **verbatim**. O frontend lê `savedViewCounts[v.id] ?? 0` — chave divergente não dá erro, o chip mostra 0 em silêncio. Um teste que apenas contasse "6 contadores" passaria com as 6 chaves erradas.
- **Teste:** `Modules/Fiscal/Tests/Feature/NotasUnifiedServiceTest.php` — `it('UC-FCKP-07 · CU-FISC-16 · sem emissão real, a lista vem VAZIA — nunca preenchida com demonstração')`, `it('UC-FCKP-07 · CU-FISC-16 · os contadores usam os ids LITERAIS das visões salvas do Cockpit.tsx')`, `it('UC-FCKP-07 · CU-FISC-16 · os contadores derivam da MESMA lista — chip e tabela não podem divergir')`, `it('UC-FCKP-07 · CU-FISC-16 · o serviço é READ-ONLY — não escreve em nfe_emissoes nem nfse_emissoes')`
- **Âncora:** `CU-FISC-16` do SDD §6.5 + decisão [W] de 2026-09-02 (*"as notas mock remova"*).
- **Status:** 🧪 pendente do veredito das lanes — o CT 100 estava em `502` na sessão que escreveu o caso.

## UC-FCKP-08 — A fila de alertas é desenhada, e cada item leva a algum lugar `[must]`

**Dado** um business com rejeição recente, certificado vencendo ou DF-e por manifestar
**Quando** a contadora abre o cockpit
**Então** ela vê **quais** são os alertas — nível, título, motivo e a ação — e o botão de cada um a leva para a sub-página que resolve aquilo.

- **Regressão que defende:** a prop `alerts` viajava do controller até a tela desde o primeiro PR e era consumida **só na contagem** do miolo do cabeçalho (`totalRej`, `Cockpit.tsx:319`). O cockpit anunciava *"N requerem ação"* e não dizia quais — enquanto o `computeAlerts()` já sabia. Medido em `origin/main` (árvore `8ce4de79`) em 2026-09-03: `alerts` desestruturado na linha 230, nenhum outro uso.
- **Também defende (LC-30 — passa no CI e é inerte no runtime):** os dois contratos deste caso são **cross-language e silenciosos**. `goto` não é um caminho: é o `id` de uma sub-página (`nfe` · `fiscal_config` · `dfe`), o mesmo vocabulário do `_lib/paginas-fiscais.tsx`. `icon` é o vocabulário do protótipo (`audit`/`shield`/`receipt`), não o do Lucide. Nos dois casos, um valor fora do mapa **não levanta erro** — o botão ou o ícone simplesmente não são desenhados, e a tela fica plausível. Nenhum typecheck alcança isso: são strings atravessando PHP → JSON → TSX.
- **Por que o caso assere o vocabulário, e não `props.alerts`:** um teste que percorresse os alertas de um tenant passaria **por vacuidade** num business sem rejeição, sem certificado vencendo e sem DF-e — o `foreach` não roda e a suíte fica verde sem ter medido nada (`0 failed` ≠ executou). Asserindo o que o `computeAlerts()` **emite** contra o que a tela **sabe resolver**, o caso morde sempre.
- **Teste:** `CockpitControllerTest` — `it('UC-FCKP-08 · todo `goto` de alerta é um id de navegação que a tela sabe resolver')`, `it('UC-FCKP-08 · a url de cada destino de alerta é uma rota registrada do Fiscal')` (o runtime é o oráculo — `app('router')->getRoutes()`, não a leitura do `Routes/web.php`), `it('UC-FCKP-08 · todo `icon` de alerta tem glifo no mapa da tela')`.
- **Bite-test (2026-09-03, CT 100):** com os valores reais, os 3 `goto` resolvem (`nfe`→`/fiscal/nfe`, `fiscal_config`→`/fiscal/config`, `dfe`→`/fiscal/dfe`) e os 3 `icon` mapeiam. Mutando um `goto` para `rota_que_nao_existe` e um `icon` para `glifo_inventado`, os dois eixos acusam `AUSENTE (FALHA)` — o caso reprova quando deve.
- **Âncora:** `CU-FISC-01` do SDD §6 (o cockpit entrega a leitura consolidada do mês) + o alvo `prototipo-ui/cowork/fiscal-page.jsx:125-137` (`FxAlerts`).
- **Status:** 🧪 veredito da lane pendente — o `CockpitControllerTest` pula em SQLite (schema MySQL) e o checkout do CT 100 está em 2026-07-23, sem os arquivos deste PR; o veredito real vem do CI, que faz checkout do PR.

## UC-FCKP-09 — O cockpit serve uma PÁGINA da lista, e filtrar devolve à primeira `[must]`

**Dado** um cockpit com mais notas carregadas do que cabem numa página
**Quando** a contadora navega pelo rodapé ou troca um filtro
**Então** a tabela renderiza apenas a página corrente, `Anterior`/`Próxima` trocam de fato as linhas e ficam desabilitados nos extremos, e trocar o filtro devolve à página 1.

- **Regressão que defende:** sem o reset, o operador que estava na página 3 e filtra fica preso num índice que não existe mais — a tabela vem **vazia** enquanto o filtro diz ter encontrado algo, e a seleção em lote guarda linhas que ele não vê. É o `useEffect` que o protótipo já resolvia e que a primeira leitura do pedido não previa.
- **Limite honesto, medido e declarado:** o `de N` do rodapé fala da lista **carregada**, nunca do total do negócio. `NotasUnifiedService::LIMITE` corta a fonte em **50** antes de ela chegar ao componente (o docblock de lá declara *"é resumo; a lista completa vive em /fiscal/nfe"*), e **não existe contagem total escopada por business**: `contadores()['todas']` conta a MESMA lista truncada. Por isso a copy é `N carregadas` — e por isso a paginação é client-side, que é o que o protótipo especifica.
- **O que este caso NÃO cobre, de propósito:** os atalhos `J/K`. Eles não existem nesta tela — a Onda 2 ([#6707](https://github.com/wagnerra23/oimpresso.com/pull/6707)) os entregou no `Nfe.tsx` —, e por isso o hint do protótipo foi **omitido** do rodapé em vez de copiado: anunciar atalho que a tela não tem é copy mentindo (LC-15).
- **Teste:** `tests/js/fiscal-cockpit-paginacao.test.tsx` — os 4 casos do `describe('UC-FCKP-09 · o cockpit serve uma PÁGINA da lista, não a lista inteira')`. Lane: `fiscal-cockpit-paginacao-gate.yml` (advisory, nasce neste PR).
- **Bite-test (2026-09-03, local):** 4/4 verde com o código real. Mutação 1 — remover o `setPagina(1)` do `useEffect`: **1 failed**, e é exatamente o caso do reset. Mutação 2 — trocar `filtrados.slice(...)` por `filtrados`: **3 failed** (página, Próxima e extremos). Restaurado do backup, 4/4 verde de novo.
- **Âncora:** `fiscal-page.jsx` §`FxNotasPage` do protótipo Cowork, baixado do **vivo** por `DesignSync` em 2026-09-03 (`truncated: false`) — não do espelho `prototipo-ui/cowork/`, que mediu **1 de 258** arquivos e cuja própria máquina declara que qualquer comparação contra ele é INCONCLUSIVA. De lá vêm o default **8**, as opções 8/25/50, a copy e o contador `{pagina} / {paginas}`.
- **Status:** 🧪 veredito da lane pendente — a lane nasce neste PR e o run real vem do CI; o que existe hoje é o run local acima.

## UC-FCKP-10 — O certificado A1 já VENCIDO entra na fila de alertas `[must]`

**Dado** um business cujo certificado A1 já venceu — por exemplo, há 28 dias
**Quando** a contadora abre o cockpit
**Então** a fila mostra um alerta `crit` *"Certificado A1 vencido há 28 dias"* que a leva à configuração fiscal, e o contador do cabeçalho passa a contá-lo.

- **Regressão que defende:** `computeAlerts()` guardava o bloco do certificado com `$dias <= 60 && $dias > 0` (`CockpitController.php:324`, até 2026-09-04). Como `diasAteVencimento()` devolve número **negativo** quando o cert venceu, o `> 0` descartava justamente o pior estado: **cert vencido não gerava alerta nenhum**. É a forma mais cara de falhar — o aviso some exatamente quando o problema existe, e o silêncio é indistinguível de "está tudo bem".
- **Medido em produção (biz=1, 2026-09-03):** com o certificado vencido há ~28 dias, a **mesma tela** se contradizia — a sidebar acusava *"Certificado vencido há 28 dias"* (badge da `US-NFE-001`, correto) enquanto o miolo do cabeçalho dizia *"0 requerem ação"*. O contador não é independente: `totalRej` soma `kpis.rejeitadas` + os alertas de nível `crit` (`Cockpit.tsx:332`) — logo o alerta ausente **zerava** a única leitura consolidada da tela.
- **O contrato não é novo, e o cockpit era o único fora dele:** os outros **5** consumidores de `diasAteVencimento()` classificam por `$dias < 0` — `CertHealthCheckCommand:187`, `ConfigController:61`, `NfeHealthCommand:213`, `HandleInertiaRequests:384` e `PaymentGatewaysController:96`. O caso não inventa regra: alinha a sexta ponta à cadeia que a `US-NFE-001` já defende com 4 GUARDs em `CertificadoServiceTest:303+`.
- **O `dias === 0` era um segundo vão, e caiu junto:** o `> 0` também excluía o dia do vencimento. Os 5 consumidores põem o `0` na banda de aviso, nunca num vão — `PaymentGatewaysController:97` escreve isso literalmente (`$dias >= 0 && $dias <= 30`). Um cert vencendo **hoje** ficava mudo pelo mesmo motivo; agora emite `crit` *"Certificado A1 vence hoje"*.
- **Teste:** `CockpitControllerTest` — os 4 casos `it('UC-FCKP-10 · …')`. Eles **injetam** o certificado no `$contexto` via reflection em vez de semeá-lo: sem isso o teste passaria **por vacuidade** num tenant sem certificado (a mesma armadilha que o `UC-FCKP-08` descreve acima), e o modelo não é persistido, então nenhum fixture é fabricado num tenant tratado como real.
- **Bite-test (2026-09-04, CT 100 · MySQL `oimpresso_staging`):** com o fix, **4 passed (12 assertions)** — assertions > 0, logo executou, não pulou. Com o controller **pré-fix** (`$dias > 0`, extraído de `origin/main`) e o mesmo arquivo de teste: **2 failed, 2 passed (7 assertions)**, e as duas falhas são `actual size 0 matches expected size 1` — a fila volta **vazia** nos casos "vencido há 28d" e "vence hoje". Os casos "vencendo em 47d" (`warn`) e "válido por 90d" (vazio) passam nas duas versões: são guardas de regressão da banda antiga.
- **Limite honesto, declarado:** o KPI *"Certif. A1"* do ribbon foi corrigido no mesmo PR — renderizava o literal `-28d` com o rótulo `renovar`, e agora diz `vencido` / `há 28d` (`Cockpit.tsx:336-345`) — mas **esse pedaço não tem teste automatizado**. Ele é frontend puro e não há lane de componente para esta tela; o que existe é o `tsc --noEmit` (0 erro no arquivo, com controle positivo provando que o arquivo é de fato checado). Fica declarado em vez de subentendido.
- **Âncora:** `CU-FISC-01` do SDD §6 (o cockpit entrega a leitura consolidada do mês) + o GUARD `US-NFE-001` (`CertificadoServiceTest.php:303+`), cujo docblock nomeia este exato modo de falha: *"o aviso desaparece justamente quando o problema existe"*.
- **Status:** 🧪 veredito da lane pendente — `CockpitControllerTest` é advisory (pula em SQLite por exigir schema MySQL) e o veredito de merge vem do CI. O que existe hoje é o run do CT 100 acima, com bite-test nos dois sentidos.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste · decisão [W]] As 4 superfícies de demonstração que SOBRARAM** — Dado que a lista unificada e os contadores das visões salvas passaram a servir dado real em 2026-09-02 (ver `UC-FCKP-07`), restam **quatro** inventadas no mesmo controller: eventos do cabeçalho (`mockEventos`), situação da SEFAZ (`mockSefazStatus`), pacote da contabilidade (`mockContabilData`) e resumo de baixas incobráveis (`mockWriteOffSummary`) · Quando a contadora lê a tela · Então ela precisa conseguir dizer o que é leitura real e o que é demonstração. _Âncora: `CU-FISC-16` do SDD §6.5. **Continua sem id de propósito** — a decisão [W] de 09-02 cobriu as NOTAS; para estas quatro ainda não há contrato dizendo qual é a saída (fonte real × esconder atrás de flag × declarar Non-Goal), e UC órfão bloqueia o merge de quem for atendê-lo._
- **[BACKLOG · ⬜ sem teste] A série de 14 dias sai de uma consulta agrupada, sem repetição por dia** — Dado emissões nas últimas duas semanas · Quando a série é montada · Então há um ponto por dia por status, calculado de uma vez só. _Anti-hook do charter; sem teste dedicado._
- **[BACKLOG · ⬜ sem teste] O faturamento aparece formatado em moeda brasileira** — comportamento só de frontend, sem cobertura de Feature.
- **[BACKLOG · ⬜ sem teste] Filtros, visões salvas, densidade e seleção em lote da tabela unificada** — a fonte real chegou em 2026-09-02 (`UC-FCKP-07`); vira contrato quando alguém escrever o teste de filtro/visão/densidade sobre ela.

## Como rodar a suíte

1. **Lane required:** `PHP / Pest (NfeBrasil · MySQL)` roda `CockpitMultiTenantTest` em todo PR que toque `Modules/Fiscal/Tests/**`.
2. **Advisory:** `Pest Fiscal` roda o diretório inteiro em SQLite — `CockpitControllerTest` e `CockpitCacheTest` **pulam** lá (schema MySQL).
3. **Noturna CT 100:** `phpunit.xml` inclui `./Modules/Fiscal/Tests/Feature`; é onde os dois advisory realmente correm.
4. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-07-03 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **6 UC** derivados do §6 do SDD; todos herdam testes existentes. O achado do dado de demonstração ficou como `[BACKLOG]` + `CU-FISC-16` ⬜, por ser decisão de produto.
- 2026-09-03 · [CC] Onda 1 Fiscal (Cowork): **UC-FCKP-08** — a fila de alertas passa a ser renderizada (`_components/AlertasFiscais.tsx`). O caso nasce com teste próprio e bite-test; cobre os dois contratos cross-language silenciosos (`goto`→rota, `icon`→glifo).
- 2026-09-04 · [CC] **UC-FCKP-10** — o cert A1 JÁ VENCIDO passa a gerar alerta `crit`. O `$dias > 0` de `computeAlerts()` descartava o pior estado (e também o `dias === 0`); os outros 5 consumidores de `diasAteVencimento()` já classificavam por `$dias < 0`. Bite-test nos dois sentidos no CT 100.
