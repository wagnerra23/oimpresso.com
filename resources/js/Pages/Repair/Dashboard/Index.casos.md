---
id: resources-js-pages-repair-dashboard-index-casos
casos: Painel do Repair · /repair/dashboard
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + criterio de aceite verificavel (Dado/Quando/Entao)
por_que: um painel so vale pelo que ele NAO faz — read-only puro, sem escrita, sem job, sem cruzar tenant; e o KPI precisa continuar contando o que o rotulo promete
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-05"
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

## UC-RDSH-02 · O primeiro KPI conta STATUS distintos, não ordens de serviço
- **Persona:** [W] olhando o painel e lendo o número de cima.
- **Aceite:** Dado um tenant com 2 status em uso e 5 OS distribuídas entre eles · Quando abro o
  painel · Então `kpis.total_repairs` vale **2** (status distintos), não 5.
- **Regressão que defende:** a chave se chama `total_repairs` e o valor é `count($job_sheets_by_status)`
  — o número de **linhas de status**. O charter é honesto sobre isso ("KPI `total_repairs` (status
  únicos)") e o `.tsx` rotula certo na tela ("Status únicos"). O risco é o inverso do usual: alguém
  lê a **chave**, conclui que está quebrado e "conserta" para contar OS — mudando em silêncio o que
  o painel informa há meses.
- **Pergunta aberta para [W]:** o nome da chave contradiz o valor. Renomear é decisão de produto
  (quebra quem consome a prop); manter é conviver com uma armadilha de leitura. Este UC **fixa o
  comportamento vigente** para que a mudança, se vier, seja deliberada — não é endosso do nome.
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

## UC-RDSH-03 · O painel "Top aparelhos" nunca enche — e a consulta que o encheria já roda
- **Persona:** operador procurando qual aparelho mais dá trabalho, e não achando nunca.
- **Aceite:** Dado OS com aparelho (`device_id`) preenchido · Quando abro o painel · Então
  `trending_devices_chart` volta **vazio** — é `[]` literal no Controller, não resultado de consulta.
- **Regressão que defende:** este UC fixa o **contrato vigente do servidor**, para que ligar o dado
  seja um ato deliberado com teste que muda junto, e não um efeito colateral.
- **Divergência medida (2026-09-05), e é o achado desta tela:**
  - o **charter** declara Non-Goal: `❌ Painel próprio pra trending_devices_chart (FIXME US-REPAIR-DASH-1)`;
  - o **Controller** calcula `$trending_devices_chart = getTrendingDevices($business_id)` na linha 41
    e depois **descarta o resultado**, enviando `'trending_devices_chart' => []` na linha 86 — enquanto
    o ramo Blade (linha 92) recebe o dado **de verdade**;
  - o **`.tsx`** renderiza um quinto painel sob o comentário
    `{/* US-REPAIR-DASH-1 — FIXME resolvido: painel próprio pra trending_devices. */}`.
  - Ou seja: o FIXME **não** está resolvido — só a metade visual foi construída. O painel existe,
    typa, renderiza e mostra "Sem dados de aparelhos" para sempre; e o port Inertia **perdeu** um
    dado que o Blade ainda mostra. É a forma da classe LC-30 (passa no CI inteiro, inerte no runtime).
- **Correção é decisão de [W], não conserto silencioso:** ou liga-se o dado (o resultado da consulta
  está a uma linha de distância) ou remove-se o painel e o comentário. Não faço nem um nem outro
  aqui: mexer no `.tsx` exige RUNBOOK do Dashboard, que **não existe** (a F1 não foi feita), e a
  escolha entre as duas saídas é de produto.
- **O que o verde deste UC prova, e o que não prova:** a tabela `categories` está **vazia no banco
  de staging inteiro** (0 linhas, medido 2026-09-05), então não há taxonomia de aparelho para
  preencher `device_id`. O teste pina o `[]` literal e mostra que os **outros** agregados enxergam
  a OS do tenant — mas a perna forte ("mesmo com `device_id` preenchido, continua vazio") só roda
  onde a taxonomia existir, e fica condicional no teste em vez de fabricada: quem semeia ambiente
  é o seed, não o teste (§5 2026-08-24).
- **Teste:** `Modules/Repair/Tests/Feature/RepairDashboardContratoTest.php`
- **Status: 🧪** _(teste cita o UC e passa — run CT 100 2026-09-05: 12 passed, 57 assertions)_

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

---

## Contrato ainda sem UC (prosa honesta, sem gate)

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
