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

---

## UC-FCKP-10 — A tela diz, por superfície, o que é leitura real e o que é demonstração `[must]`

**Dado** um cockpit que serve, lado a lado, superfícies alimentadas por consulta real e superfícies alimentadas por dado fixo no código
**Quando** a contadora liga a procedência no cabeçalho
**Então** cada superfície ganha um selo dizendo `leitura real` ou `demonstração`, com uma frase explicando de onde vem o dado — e o número que o selo descreve continua visível, do mesmo jeito.

- **Regressão que defende:** a persona-alvo é a **contadora**, e para ela `SEFAZ-SP operacional` e `184 NF-e autorizadas no período` são indistinguíveis de leitura real. O SDD §5.4.1 mediu isto e foi explícito: *"o código é honesto sobre si (`TODO[CL]` em todos), mas a tela não é"*. É a família da lápide [proibicoes §5](../../../../memory/proibicoes.md) 2026-07-17 na forma mais aguda — o número **não vem de sistema nenhum**.
- **Decisão [W] que o destravou:** em 2026-09-04, [W] escolheu a **saída (a)** do CU-FISC-16 (marcar a procedência) e descartou (c) declarar Non-Goal. Foi o contrato que faltava para o `[BACKLOG]` virar UC — ele estava sem id de propósito justamente porque UC órfão bloqueia o merge de quem for atendê-lo.
- **Também defende (LC-30 — a declaração que envelhece em silêncio):** o vetor real não é de runtime, é de manutenção. Quando o [#6541](https://github.com/wagnerra23/oimpresso.com/pull/6541) trocou a lista mockada pelo `NotasUnifiedService`, o protótipo e o SDD §5.4.1 **continuaram dizendo "demonstração"** para `notas` e `savedViewCounts` — dois documentos descrevendo um código que já tinha mudado. Um selo com essa doença não é neutro: ele **mente com autoridade**. Daí o caso assertar as três pontas (método que produz · linha que declara · chave que a tela sela), e não a aparência do selo.
- **Por que a chave é o eixo, e não `props.procedencia`:** `chave="viewCounts"` é uma string atravessando PHP → JSON → TSX. Chave divergente **não levanta erro**: o selo simplesmente não aparece, e a superfície volta a parecer leitura real. Nenhum typecheck alcança isso — é o mesmo silêncio cross-language que o `UC-FCKP-08` cobre nos `goto`/`icon` dos alertas.
- **Teste:** `Modules/Fiscal/Tests/Feature/ProcedenciaCockpitTest.php` — `it('UC-FCKP-10 · CU-FISC-16 · todo método mock* do controller tem superfície declarada como demonstração')`, `it('UC-FCKP-10 · CU-FISC-16 · nenhuma superfície declarada demonstração sobrevive ao método mock* sumir')`, `it('UC-FCKP-10 · CU-FISC-16 · toda chave que a tela sela existe na declaração do controller')`, `it('UC-FCKP-10 · CU-FISC-16 · toda superfície declara origem do vocabulário fechado e uma explicação')`. **Estático de propósito** (sem DB, sem HTTP): assim ele roda também na lane advisory em SQLite, onde `CockpitControllerTest` e `CockpitCacheTest` pulam — e teste que pula sai com `0 failed` sem ter medido nada (LC-13).
- **Bite-test (2026-09-04, local, PHP 8.4 — a lógica dos 4 asserts fora do Pest, que é CT 100):** com o código real, verde — 8 superfícies declaradas, 4 `demonstracao`, 4 métodos `mock*`, 8 chaves seladas na tela. Quatro mutações, cada uma mordida pelo assert certo: marcar `sefaz` como `real` → falha o 1º; renomear `mockEventos()` para `buscaEventosReais()` mantendo a declaração → falha o 2º (**é literalmente a regressão do #6541**); trocar `chave="viewCounts"` por `"viewCount"` no `.tsx` → falha o 3º; esvaziar uma `explica` → falha o 4º. Restaurado, verde de novo.
- **Bite-test de RUNTIME (2026-09-04, vitest local — LC-30):** o teste acima é estático, e passar no CI sem o selo aparecer na tela seria a definição da classe. Rodei 5 casos de render contra o `Cockpit` real: desligado, o botão existe com `aria-pressed="false"` e há **zero** selos no DOM; ligado, cada superfície declarada ganha o seu (`kpis=real`, `sefaz=demonstracao`, `writeoff=demonstracao`), o ribbon passa a ler `Emitidas` · `leitura real` · `7` — o número **continua lá** — e o card de baixas segue exibindo 2.470; a preferência grava `"1"`/`"0"` na chave do protótipo; e sem mapa declarado o botão não é desenhado. 5/5 verde. Três mutações, todas mordidas: o selo ignorar o toggle → 2 falhas; a preferência parar de persistir → 1; o botão aparecer sem mapa → 1. **Achado do próprio bite-test:** o store é module-level de propósito (a preferência sobrevive à navegação SPA entre as telas do Fiscal), e por isso vaza entre casos de teste — quem escrever a versão commitada precisa desligá-lo no `beforeEach`, senão o 3º caso lê o estado deixado pelo 2º e falha por engano, como o meu falhou na primeira rodada.
- **Achado de acessibilidade, corrigido no mesmo PR:** o `Badge` do DS é um `<span>`, e `<span>` não recebe foco. Com o `TooltipTrigger` sobre ele, o Radix só abriria a explicação no **hover** — inalcançável por teclado e por leitor de tela. Um selo que diz `demonstração` sem dizer **por quê** é meia informação, e a explicação é metade deste contrato. `tabIndex={0}` resolve; provado por bite-test (o foco abre o tooltip e o texto aparece) e por mutação (sem o `tabIndex`, `expected null to be '0'`). Os selos só entram no tab-order quando o operador liga a procedência — ação deliberada.
- **Por que o teste de runtime NÃO entra neste PR:** o limite de 8 arquivos já está no teto (lei do `commit-discipline`). Ele entra no PR das telas restantes, junto com a lane própria — que é quando passa a haver mais de uma tela para ele cobrir.
- **Limite honesto:** o teste prova que a declaração **concorda com os métodos `mock*` do controller**. Ele não prova que `computeKpis()` de fato consulta o banco — para isso a régua é o `UC-FCKP-03` e o `CockpitMultiTenantTest`. Uma superfície nova, real e sem chave declarada, também passa: o assert cobre o sentido que apodrece (mock → real), porque é o que já aconteceu uma vez.
- **O que este caso NÃO cobre, de propósito:** as outras 3 telas do módulo. `Dfe` (`historicoMock`) e `Config` (`seriesMock`) têm superfícies de demonstração próprias e ganham selo em PR seguinte; `Eventos` foi medida em 2026-09-04 e **não tem nenhuma** — serve `NfeEvento` real de ponta a ponta.
- **Âncora:** `CU-FISC-16` do [SDD §6.5](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md) + decisão [W] de 2026-09-04 + o alvo `prototipo-ui/cowork/fiscal-page.jsx:60` (botão) e `fiscal-actions.jsx:93-99` (selo).
- **Status:** 🧪 veredito da lane pendente — Pest roda no CT 100 (ADR 0062) e o checkout de lá está em 2026-07-23, sem os arquivos deste PR; o veredito real vem do CI, que faz checkout do PR. O que existe hoje é o bite-test acima.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[ATENDIDO em 2026-09-04 → `UC-FCKP-10`] As 4 superfícies de demonstração que SOBRARAM** — o item nasceu sem id porque faltava contrato dizendo qual das três saídas era a certa (fonte real × esconder atrás de flag × declarar Non-Goal). [W] decidiu pela primeira — marcar a procedência na tela — e o caso virou `UC-FCKP-10` acima, com teste que o cita. _As quatro seguem servindo dado inventado: o que mudou é que a tela passou a dizer isso._
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
