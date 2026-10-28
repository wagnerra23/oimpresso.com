---
id: resources-js-pages-repair-dashboard-index-casos
casos: Painel do Repair · /repair/dashboard
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + criterio de aceite verificavel (Dado/Quando/Entao)
por_que: um painel so vale pelo que ele NAO faz — read-only puro, sem escrita, sem job, sem cruzar tenant; e o KPI precisa continuar contando o que o rotulo promete
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Painel do Repair

> Derivados do [Index.charter.md](Index.charter.md) (lei) — o charter mais detalhado das três telas,
> com Non-Goals e Anti-hooks já escritos. O `DashboardController` foi lido para **confirmar** o
> comportamento, nunca para derivar o caso (§5 2026-06-05).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **O charter promete oito Pest GUARD que não existem.** A seção "Métricas vivas (Pest GUARD — a
> escrever em F1)" cita `Modules/Repair/Tests/Charters/RepairDashboardCharterTest.php`. Medido em
> 2026-09-05: o arquivo **e o diretório** não existem, e `git grep RepairDashboardCharterTest`
> devolve **um único hit — o próprio charter**. Promessa de teste inexistente é instrução ativa para
> confiar em defesa que não há (canon: *grep antes de confiar*). Os UCs abaixo pagam **quatro** dos
> oito; os outros quatro estão nomeados no rodapé, sem fingir cobertura.

---

## UC-RDSH-01 · Abrir o painel não escreve nada e não enfileira nada
- **Persona:** ninguém — o caso existe porque a violação é **silenciosa**.
- **Aceite:** Dado o estado atual do tenant · Quando faço `GET /repair/dashboard` · Então nenhuma
  linha de `repair_job_sheets` é criada, alterada ou removida, e **nenhum job entra na fila**.
- **Regressão que defende:** o charter tem sete Anti-hooks para esta tela ("não escreve no banco",
  "não muda status de OS", "não roda jobs em fila ao abrir", "não dispara emails"). Um painel que
  "aquece o cache" gravando, ou que dispara um job de recálculo ao abrir, viola todos eles sem
  nenhum sintoma na tela.
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

## UC-RDSH-02 · O KPI de cima conta ORDENS DE SERVIÇO, e se mexe quando entra folha
- **Persona:** [W] olhando o painel e lendo o número de cima.
- **Aceite:** Dado 3 folhas novas no MESMO status pendente · Quando abro o painel · Então
  `kpis.pending` sobe 3 — e o número de status distintos **não** muda.
- **A troca foi deliberada, e esta linha é o registro dela.** Até 2026-09-09 este UC afirmava o
  contrário: cravava `kpis.total_repairs == count($job_sheets_by_status)`, o número de **linhas de
  status**. Era contrato honesto do defeito — com 6 status configurados, o painel mostrava ~6 com
  3 ou 3.000 OS. O próprio UC deixava a pergunta aberta pra [W] (*"o nome da chave contradiz o
  valor"*) e dizia que fixava o vigente *"para que a mudança, se vier, seja deliberada"*. Ela veio:
  o protótipo (`repair-page.jsx` região `Painel`) manda **Folhas pendentes** no topo, e no eixo
  FORMA ele é soberano ([ADR UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).
  O perdedor foi corrigido no MESMO PR — charter, este caso e o teste —, reescrito, nunca
  desabilitado ([§Precedência](../../../../../memory/proibicoes.md)).
- **O que o teste embute pra poder ficar vermelho:** as 3 folhas nascem no MESMO status. Se o KPI
  voltasse a ser `count($job_sheets_by_status)`, o delta seria 0 e o UC reprovaria — controle
  negativo do defeito antigo, não só asserção do novo.
- **Coerência checada junto:** `pending_unassigned` e `overdue` são subconjuntos de `pending`.
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardContratoTest.php`
- **Status: 🧪**

## UC-RDSH-03 · "Top aparelhos" entrega a consulta que o Controller já roda
- **Persona:** operador procurando qual aparelho mais dá trabalho.
- **Aceite:** Dado OS com `device_id` preenchido · Quando abro o painel · Então
  `trending_devices_chart` traz linhas `{device, count}` — nunca mais um `[]` literal.
- **O FIXME estava meio-resolvido, e agora fechou.** O Controller calculava
  `getTrendingDevices($business_id)` e **descartava o resultado**, mandando `[]` na linha 86,
  enquanto o ramo Blade recebia o dado de verdade. O `.tsx` já tinha painel, `Deferred`, skeleton e
  `emptyMsg`: tudo typava, tudo renderizava, e mostrava "Sem dados de aparelhos" pra sempre — a
  forma da classe [LC-30](../../../../../memory/LICOES_CODE.md) (passa no CI inteiro, inerte no
  runtime). Este UC dizia que ligar o dado seria *"um ato deliberado com teste que muda junto"*:
  é este PR, e o teste mudou junto.
- **O bloqueio que o próprio UC citava caiu:** ele registrava que mexer no `.tsx` exigia RUNBOOK do
  Dashboard, *"que não existe (a F1 não foi feita)"*. A F1 foi feita:
  [RUNBOOK-repair-dashboard.md](../../../../../memory/requisitos/Repair/RUNBOOK-repair-dashboard.md).
- **O que o verde prova, e o que não prova:** `categories` está **vazia no staging inteiro**
  (0 linhas, medido 2026-09-05) — sem taxonomia não há `device_id` pra preencher. A perna forte
  (com `device_id`, a OS TEM de aparecer) fica condicional no teste em vez de fabricada: quem semeia
  ambiente é o seed, não o teste ([§5 2026-08-24](../../../../../memory/proibicoes.md)). Sem ela, o
  que se prova é a FORMA do contrato (array de `{device,count}`), não o conteúdo.
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardContratoTest.php`
- **Status: 🧪**


## UC-RDSH-05 · O rótulo da barra não chega vazio
- **Persona:** quem olha "OS por status" e precisa saber QUAL status é cada barra.
- **Aceite:** Dado o painel aberto · Quando leio qualquer das 5 séries · Então cada linha tem
  `label` **e** `count`, e o `label` não é vazio nem nulo.
- **O defeito que ele fecha, e ele era invisível:** o `BarChartCard` lê `r.label`, e o Controller
  mandava `status` / `staff` / `brand` / `model`. Os **4 gráficos** desenhavam a barra com o
  número certo e o rótulo **VAZIO** em produção. O `.tsx:17` afirmava que *"toda série é
  normalizada pelo Controller pra {label,count}"* — certo sobre a intenção, errado sobre o fato
  (a família [LC-10](../../../../../memory/LICOES_CODE.md): artefato afirmando o que não cumpre).
- **Por que nenhum teste pegava:** todos somavam `count`, que era justamente a metade que batia.
  Este UC trava a OUTRA metade — a que o olho vê.
- **Anti-vácuo embutido:** exige que pelo menos uma série tenha linha, senão o `foreach` passaria
  por vacuidade e o caso viraria carimbo.
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardContratoTest.php`
- **Status: 🧪**

## UC-RDSH-04 · Nenhum dos agregados enxerga OS de outro tenant
- **Persona:** dois clientes na mesma instalação — o painel de um não pode contar o trabalho do outro.
- **Aceite:** Dado OS no tenant 98 e OS no tenant 99 · Quando abro o painel como usuário do 98 ·
  Então nenhuma contagem (`kpis`, status, equipe, marcas, modelos) inclui linha do 99.
- **Regressão que defende:** os agregados de marca e modelo são construídos com `leftJoin` cru e
  `where('repair_job_sheets.business_id', ...)` **explícito** — não dependem de global scope. Perder
  esse `where` num refactor de join vaza contagem entre tenants, que é Tier 0 irrevogável
  ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)); e vaza no formato
  mais difícil de notar, um número levemente maior.
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

## Indicadores da OS (US-REPA-006)

> Tudo sai da FSM (ADR 0143): `sale_stage_history` e `repair_job_sheets.current_stage_id`. Nenhum
> contador paralelo. Janela fixa de 90 dias. O teste fixa o "agora" em 2031 e põe as transições em
> dez/2030, para que a janela só veja as linhas dele.

## UC-RDSH-06 · Tempo médio por etapa vem das transições
- **Fonte:** US-REPA-006 (SPEC) · charter §Goals "Indicadores da OS".
- **Persona:** dono da oficina procurando onde a OS demora.
- **Aceite:** Dado a OS A 10h em execução e a OS B 20h em execução · Quando abro os indicadores ·
  Então "em execução" mostra 15h sobre 2 passagens. Etapa de onde a OS ainda não saiu não entra.
- **Regressão que defende:** média calculada por OS em vez de por passagem, ou par de transições
  trocado entre OS diferentes. Os dois dariam outro número que não 15h.
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardIndicadoresContratoTest.php`
- **Status: ⬜** _(primeira execução é a da lane Verticais MySQL)_

## UC-RDSH-07 · OS parada além do prazo conta na etapa onde está
- **Persona:** gerente decidindo qual OS cobrar primeiro.
- **Aceite:** Dado uma OS em execução com entrega vencida, uma com entrega futura, uma sem prazo e
  uma entregue com prazo vencido · Então só a primeira conta, na linha "em execução". Etapa
  terminal nunca é "parada".
- **Regressão que defende:** contar OS já entregue ou sem prazo como atrasada, o que encheria o
  painel de falso alarme.
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardIndicadoresContratoTest.php`
- **Status: ⬜**

## UC-RDSH-08 · Retorno em garantia divide pelas OS entregues na mesma janela
- **Persona:** dono da oficina medindo a qualidade do conserto.
- **Aceite:** Dado 2 OS entregues na janela, 1 delas com garantia acionada, e uma 3ª OS entregue
  antes da janela e com garantia acionada dentro dela · Então `entregues 2 · acionadas 1 · taxa 50`.
  A 3ª fica fora do numerador: com ela a taxa poderia passar de 100%.
- **Regressão que defende:** numerador e denominador de universos diferentes (taxa acima de 100%).
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardIndicadoresContratoTest.php`
- **Status: ⬜**

## UC-RDSH-09 · Produtividade por técnico
- **Persona:** gerente distribuindo OS entre os técnicos.
- **Aceite:** Dado A concluída 10h e B 20h depois de abertas, do mesmo técnico, e F entregue sem
  passar por "concluído" · Então o técnico tem 2 concluídas e 15h de média. F não conta.
- **Regressão que defende:** contar como produção a OS devolvida sem conserto (orçamento rejeitado).
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardIndicadoresContratoTest.php`
- **Status: ⬜**

## UC-RDSH-10 · Indicadores não contam OS de outro tenant
- **Persona:** duas empresas na mesma instalação.
- **Aceite:** Dado uma OS do tenant 99 com transição gravada no histórico com o `business_id` do 98
  e do 99 · Então nada dela aparece no 98.
- **Regressão que defende:** `transaction_id` é compartilhado entre venda e OS. Sem a junção com
  `repair_job_sheets` do mesmo business, um id colidente entra na conta.
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardIndicadoresContratoTest.php`
- **Status: ⬜**

## UC-RDSH-11 · Os indicadores chegam como prop adiada
- **Persona:** quem abre o painel e espera ver os KPIs antes dos indicadores.
- **Aceite:** A primeira resposta não traz `indicadores` e lista a prop em `deferredProps`. O
  partial reload traz as 5 chaves.
- **Regressão que defende:** a leitura de 90 dias do histórico rodando no primeiro paint e
  atrasando a tela inteira.
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardIndicadoresContratoTest.php`
- **Status: ⬜**

---

## Contrato ainda sem UC (prosa honesta, sem gate)

- **[BACKLOG]** Faturamento de serviço no painel. Mexe em valor: exige dupla prova e antes → depois
  aprovados pelo [W] antes de virar UC.

> Os quatro GUARD do charter que estes UCs **não** pagam — nomeados para que a promessa deixe de
> parecer cobertura.

- **[BACKLOG]** `renders under 800ms p95` — alvo de performance; exige medição no CT 100, não asserção
  de unidade. Um teste que cronometra um `get()` mede a máquina, não a tela.
- **[BACKLOG]** `renders at 1280px without horizontal scroll` — é Pest Browser (viewport real), não
  teste de Feature. A tela hoje tem **E2E 0** no `screen-coverage-map`.
- **[BACKLOG]** `shows PT-BR empty state on every list` — os cinco `emptyMsg` estão no `.tsx`; cobrir
  é teste de componente (vitest) ou Browser.
- **[BACKLOG]** `contains exactly 2 KPIs and 4 lists` — o charter diz **4** listas e o `.tsx` renderiza
  **5** painéis (o quinto é o de UC-RDSH-03). Escrever o GUARD como o charter manda o deixaria
  vermelho hoje; qual dos dois números é o certo depende da decisão de [W] em UC-RDSH-03.
- **[BACKLOG]** O `.tsx` embrulha os cinco painéis em `<Deferred>`, mas o Controller **não** usa
  `Inertia::defer` (medido: zero ocorrências). As props chegam eager, então o `fallback` de skeleton
  nunca aparece — o comentário do topo do arquivo ("charts em Inertia::defer + skeleton") descreve
  algo que o servidor não faz. Inerte, não quebrado; some junto se o painel for revisto.
