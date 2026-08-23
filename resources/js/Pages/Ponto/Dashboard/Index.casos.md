---
id: resources-js-pages-ponto-dashboard-index-casos
casos: Painel do Ponto · /ponto
irmaos: Index.charter.md (lei) · prototipo-ui/contrato/ponto-painel.contract.json (contrato de tela) · SDD-espelho-e-jornada-v1.0.md §6.5 (invariantes)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o painel é a porta do módulo — o que ele deixa de mostrar (divergência, fila parada) vira mês fechado errado; o que ele mostra a mais vira vazamento entre empregadores.
owner: wagner
last_run: "2026-08-23"
last_run_ci: "Run 32654078783 (lane `PHP / Pest (Ponto · MySQL)`, 2026-08-23): a lane inteira deu **4 failed · 56 passed (241 assertions)**, e as 4 falhas eram TODAS deste arquivo novo — os 11 arquivos pré-existentes passaram. UC-PTDASH-01 e -04 passaram e ficam ✅ (não foram tocados depois). As outras 4 reprovaram por DEFEITO DO TESTE, não do produto: (a) `test()->admin` acessa propriedade `protected` de PontoTestCase a partir de função global → Error em UC-PTDASH-02; (b) `toContain()` do Pest é variádico e `toHaveKey($k,$v)` toma o 2º argumento como VALOR — passei mensagem nos dois, então a mensagem virou needle/valor e o assert reprovou por motivo alheio ao caso (UC-PTDASH-03/05/06). Corrigido no mesmo PR trocando por assertStringContainsString/assertArrayHasKey/assertContains (mensagem-por-último é contrato estável) e passando o id do autor por parâmetro. Os 4 voltam a 🧪 até a próxima run publicar."
---

# Casos de Uso & Aceite — Painel do Ponto

> **Âncora:** o **contrato de tela** [`prototipo-ui/contrato/ponto-painel.contract.json`](../../../../../prototipo-ui/contrato/ponto-painel.contract.json)
> (copy + ordem, schema ADR 0286), o **charter** `Index.charter.md` (§Goals · §Non-Goals ·
> §Automation hooks · §Anti-hooks) e os invariantes transversais `CU-PONTO-12` / `CU-PONTO-13` do
> [SDD §6.5](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md).
>
> Os UC derivam do **contrato**, nunca do `Index.tsx` — teste derivado do código é tautológico
> ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-06-05).
>
> ⚖️ **Força do veredito:** quem responde "esta lane bloqueia merge?" é
> [`governance/required-checks-baseline.json`](../../../../../governance/required-checks-baseline.json),
> não este arquivo — artefato que afirma o próprio enforcement em tempo presente apodrece no primeiro
> flip ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-07-16). *Fato datado:* a lane
> `PHP / Pest (Ponto · MySQL)` foi promovida a **required** em **2026-08-05** (ADR 0369, emenda 0314).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PTDASH-01 | Os 6 KPIs aparecem na ordem e com a copy do contrato | must | contrato §`painel-kpis` | `PontoDashboardContratoTest` | ✅ verde (run 32654078783) |
| UC-PTDASH-02 | Nenhum dado de outro empregador entra no painel | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `PontoDashboardContratoTest` | 🧪 sem veredito |
| UC-PTDASH-03 | Fila vazia continua visível, com a frase de vazio | must | contrato §`painel-fila-aprovacoes` estado `vazio` | `PontoDashboardContratoTest` | 🧪 sem veredito |
| UC-PTDASH-04 | O painel é read-only — nenhuma escrita parte dele | must `[T0]` | charter §Anti-hooks + `CU-PONTO-13` | `PontoDashboardContratoTest` | ✅ verde (run 32654078783) |
| UC-PTDASH-05 | O polling recarrega só props de leitura | must | charter §Automation hooks | `PontoDashboardContratoTest` | 🧪 sem veredito |
| UC-PTDASH-06 | A nota de fechamento fica acima dos KPIs, nos 3 estados | must | contrato §`painel-nota-fechamento` + `ordem` | `PontoDashboardContratoTest` | 🧪 sem veredito |

**[BACKLOG]** (prosa honesta — vira UC quando ganhar teste que a cite):

- `[BACKLOG]` O custo do polling de 30s com `defer` não regride (charter §Pendências:
  *"Validar custo do polling 30s com defer (evitar N queries repetidas)"*). Medido nesta sessão como
  **número**, mas sem teto declarado por [W] — travar um número que ninguém decidiu criaria catraca
  sobre valor arbitrário. Ver §"Medição do polling" no fim deste arquivo.
- `[BACKLOG]` A faixa de presença (`PresenceStrip`), o gráfico de 7 dias e o inbox de alertas não têm
  âncora `data-contract` — estão no charter §Goals mas **fora** do contrato de tela, então o
  `contrato-de-tela` não os vigia. Ampliar o contrato é ato de [W] (copy é lei).

---

## UC-PTDASH-01 · Os 6 KPIs aparecem na ordem e com a copy do contrato · `must`

- **Persona:** gestor de RH abrindo o módulo. Os 6 números são a leitura de 3 segundos do dia; trocar a
  ordem ou o rótulo troca o que ele lê primeiro.
- **Aceite:** Dado o painel do ponto · Quando ele é renderizado · Então as 6 etiquetas
  `Colaboradores ativos`, `Presentes agora`, `Atrasos hoje`, `Faltas hoje`, `HE do mês`,
  `Aprovações pendentes` aparecem **todas**, **nessa ordem**, dentro da âncora `painel-kpis`.
- **Teste:** `Modules/Ponto/Tests/Feature/PontoDashboardContratoTest.php` — `UC-PTDASH-01`.
- **Contrato:** `ponto-painel.contract.json` §`painel-kpis` (copy literal) + `ordem`.
- **Regressão que defende:** reordenar KPI é a mudança mais barata de fazer e a mais cara de perceber —
  nenhum teste de payload pega, porque as chaves continuam lá. O assert é sobre a **ordem do texto na
  tela**, que é o que o gestor lê.
- **Nota de método:** este UC é medido no `.tsx` de propósito — é o único dos 6 cujo objeto **é** a
  camada de apresentação (copy e ordem visual). Não é derivar do código: o oráculo é o contrato, e o
  `.tsx` é o material sob teste. Os outros 5 se medem por comportamento HTTP.
- **Status: ✅ verde** na run 32654078783 da lane `PHP / Pest (Ponto · MySQL)`.

---

## UC-PTDASH-02 · Nenhum dado de outro empregador entra no painel · `must` `[T0]`

- **Persona:** plataforma multi-tenant. Jornada é dado sensível (LGPD Art. 7º + sigilo trabalhista), e o
  painel agrega **quatro** superfícies ao mesmo tempo — KPI, presença, feed e fila. Vazamento em
  agregado é o pior tipo: não deixa linha para ninguém notar.
- **Aceite:** Dado um colaborador com marcação e intercorrência pendente num **outro** empregador ·
  Quando abro o painel do meu · Então esse colaborador **não** aparece na presença, **não** aparece no
  feed de atividade, **não** aparece na fila de aprovações, e os KPIs **não** o contam.
- **Teste:** `PontoDashboardContratoTest.php` — `UC-PTDASH-02`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5 — *"nenhuma tela do Ponto expõe dado de outro empregador"*) ·
  [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) ·
  charter §Non-Goals (*"Não agrega dados de outro business"*).
- **Regressão que defende:** o `DashboardController` escapa por `where('business_id', $businessId)`
  explícito em **todas** as closures. Se alguém "simplificar" confiando só no global scope, a defesa
  vira única — e some junto com o trait. O caso exerce as 4 superfícies numa passada só.
- **Pré-condição anti-vácuo:** o caso primeiro prova que o **meu** dado aparece. Sem isso, "o alheio não
  está" seria verdade por lista vazia, não por isolamento (LC-13).
- **Nota de tenant:** o adversário é o business fictício **99**, via `PontoTestCase::garantirBizAlheio()`
  — o idioma que a base do módulo já fornece, para não criar um segundo vocabulário. ⚠️ A
  [ADR 0358](../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
  moveu o tenant canônico de teste para o **98**; o módulo inteiro ainda usa 99/biz=1 da doutrina
  anterior. A proibição que importa segue intacta e é respeitada aqui: **nunca biz=4** (ROTA LIVRE,
  cliente real). Migrar 99→98 é trabalho próprio do módulo, registrado para não passar por conforme.
- **Status: 🧪 sem veredito.**

---

## UC-PTDASH-03 · Fila vazia continua visível, com a frase de vazio · `must`

- **Persona:** gestor num dia sem pendência. "Não há nada" é informação — some a seção e ele não sabe se
  está limpo ou se a tela quebrou.
- **Aceite:** Dado que **não** existe intercorrência pendente no meu empregador · Quando abro o painel ·
  Então a seção `Fila de aprovações` continua presente **e** exibe
  `Nenhuma intercorrência aguardando decisão.` — e o atalho `Ver fila completa` continua ali.
- **Teste:** `PontoDashboardContratoTest.php` — `UC-PTDASH-03`.
- **Contrato:** `ponto-painel.contract.json` §`painel-fila-aprovacoes`, que declara os estados
  `com-pendentes` e `vazio` — o vazio é **estado declarado**, não ausência.
- **Regressão que defende:** o idioma "lista vazia devolve nada" é o reflexo mais comum de quem "limpa"
  a tela — e apaga o empty state junto. O contrato nomeia a frase justamente para que ela não seja
  negociável.
- **Status: 🧪 sem veredito.**

---

## UC-PTDASH-04 · O painel é read-only — nenhuma escrita parte dele · `must` `[T0]`

- **Persona:** auditor MTE (P4). Marcação é append-only por força de lei (Portaria MTP 671/2021), e o
  painel é a tela mais visitada do módulo — se ela escrever, escreve muito e sem trilha.
- **Aceite:** Dado o painel · Quando o carrego · Então o único verbo que a rota atende é `GET`; e a rota
  do painel **não** aceita `POST`/`PUT`/`PATCH`/`DELETE`; e carregar o painel **não** altera a contagem
  de marcações nem de intercorrências do meu empregador.
- **Teste:** `PontoDashboardContratoTest.php` — `UC-PTDASH-04`.
- **Contrato:** charter §Anti-hooks (*"Não muta nada — dashboard é read-only"*) · charter §Non-Goals
  (*"Não bate ponto aqui"*, *"Não aprova/rejeita"*, *"Não edita marcações"*) · `CU-PONTO-13`
  (*"marcação gravada não muda nem some"*).
- **Regressão que defende:** um "atalho de conveniência" (bater ponto direto do painel, aprovar inline)
  é exatamente o tipo de feature que parece boa e viola o Non-Goal. O caso mede **efeito**
  (contagem antes→depois) além do verbo, porque rota `GET` também pode escrever.
- **Status: ✅ verde** na run 32654078783 — o painel não escreveu e recusou os 4 verbos de escrita.

---

## UC-PTDASH-05 · O polling recarrega só props de leitura · `must`

- **Persona:** gestor com o painel aberto o dia inteiro. A cada 30s a tela se atualiza sozinha — o que
  ela pede nesse ciclo é o que vai custar 120 requisições por hora.
- **Aceite:** Dado o painel aberto · Quando o ciclo de atualização pede exatamente
  `kpis`, `presenca_agora`, `atividade_recente`, `alertas`, `server_time` · Então essas props chegam
  resolvidas; e a prop **não** pedida (`serie_7dias`) **não** viaja nessa resposta.
- **Teste:** `PontoDashboardContratoTest.php` — `UC-PTDASH-05`.
- **Contrato:** charter §Automation hooks (lista literal das 5 props do reload parcial de 30s) ·
  charter §Anti-hooks (*"o polling só recarrega props de leitura"*) ·
  [RUNBOOK-inertia-defer-pattern](../../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md).
- **Regressão que defende:** `defer` mal aplicado degrada nos dois sentidos — ou o partial reload traz
  tudo (e o ciclo de 30s vira carga cheia), ou a prop pedida não resolve (skeleton eterno). O caso prova
  as duas pontas: o que foi pedido **chega**, o que não foi **não vem**.
- **Por que a ausência aqui É contrato:** o assert de que `serie_7dias` não está na resposta mede
  *presença como contrato* (é o que o `defer` promete), não presença como proxy de valor — a distinção
  que [proibicoes §5](../../../../../memory/proibicoes.md) 2026-07-26 exige que seja declarada.
- **Status: 🧪 sem veredito.**

---

## UC-PTDASH-06 · A nota de fechamento fica acima dos KPIs, nos 3 estados · `must`

- **Persona:** RH tentando fechar a competência. Dia em `DIVERGENCIA` impede a apuração de consolidar e
  faz o AFD sair com a jornada errada — ele precisa ver **antes** de fechar, não depois.
- **Aceite:** Dado o painel · Quando ele é renderizado · Então a âncora `painel-nota-fechamento` aparece
  **antes** de `painel-kpis` na ordem de leitura do DOM; e a nota existe nos **três** estados que o
  contrato declara (`com-pendencia`, `sem-pendencia`, `so-divergencia`) — inclusive quando não há nada a
  travar, caso em que ela **informa que pode consolidar**.
- **Teste:** `PontoDashboardContratoTest.php` — `UC-PTDASH-06`.
- **Contrato:** `ponto-painel.contract.json` §`painel-nota-fechamento` (os 3 `estados`) + `ordem`
  (`painel-nota-fechamento` é o **primeiro** dos 4).
- **⚠️ Divergência corrigida contra o pedido:** o pedido de origem descrevia este UC como
  *"nota de divergência acima dos KPIs, **e ausente quando não há**"*. O contrato diz o contrário:
  `sem-pendencia` é um **estado declarado** da seção, não a ausência dela — e o `Index.tsx` implementa
  os 3. Escrevi o aceite contra o **contrato**, que é a lei; sumir com a nota no dia limpo seria
  regressão, não conformidade. Registrado como achado, não corrigido no contrato (copy é ato de [W]).
- **Regressão que defende:** mover a nota para baixo dos KPIs (ou para a coluna lateral) a tira do campo
  de leitura de quem abre a tela — o aviso continua existindo e deixa de ser visto. Ordem de âncora é
  ordem de **DOM**, então não dá para "acertar" com CSS `order` sem descolar leitura de visual.
- **Status: 🧪 sem veredito.**

---

## Medição do polling (charter §Pendências) — número, sem teto

O charter pede *"validar custo do polling 30s com defer (evitar N queries repetidas)"*. O que dá para
afirmar sem rodar o ciclo em produção está registrado aqui como **contagem estática de queries por
closure**, derivada do `DashboardController`:

| Prop no ciclo de 30s | Closure | Queries |
|---|---|---|
| `kpis` | `buildKpis` | 7 agregados (5 `count` + 1 `sum` + 1 `count` de divergências) |
| `presenca_agora` | `calcularPresenca` | 2 (colaboradores + marcações do dia) — sem N+1: agrupa em memória |
| `atividade_recente` | `buildAtividadeRecente` | 1 + eager (`colaborador.user`, `rep`) |
| `alertas` | `coletarAlertas` | 3 + eager (atrasos, aprovações paradas, faltas) |
| `server_time` | — | 0 (string) |

**Total do ciclo: 13 queries + eager loads.** As duas props que ficam **fora** do `only` do polling
(`serie_7dias` e `aprovacoes`) não executam — que é exatamente o que o `defer` promete e o
`UC-PTDASH-05` prova.

**Não vira UC nem catraca** enquanto [W] não declarar um teto. Travar "≤13" seria catraca sobre número
que ninguém decidiu — e o primeiro KPI novo legítimo a quebraria
([proibicoes §5](../../../../../memory/proibicoes.md) 2026-07-17: não regravar baseline antes de saber o
que o instrumento mede).
