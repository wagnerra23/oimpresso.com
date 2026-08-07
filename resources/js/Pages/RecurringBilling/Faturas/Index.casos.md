---
id: resources-js-pages-recurring-billing-faturas-index-casos
casos: Faturas da cobrança recorrente · /recurring-billing/faturas
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: fatura é dinheiro — cancelar a errada, duplicar a certa ou perder o filtro tem custo direto.
owner: wagner
last_run: "2026-07-28"
---

# Casos de Uso & Aceite — Faturas (`/recurring-billing/faturas`)

> **Âncora:** `CU-RB-11` (consultar faturas), `CU-RB-06` (cancelar fatura no gateway) e `CU-RB-03`
> (gerar as faturas do ciclo) do
> [SDD §6.1](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md).
> Os UC derivam do **contrato**, nunca do `.tsx`.
>
> **Por que o gerador e o refund moram aqui:** eles não têm tela própria, e esta é a tela **do
> artefato que eles produzem e desfazem** (a fatura). Criar um arquivo paralelo pra "fluxo sem tela"
> seria tipo novo — proibido ([ADR 0351](../../../../../memory/decisions/0351-sdd-from-source.md) D-B).
>
> ⚠️ **Força do veredito.** _(atualizado 2026-08-02; a redação anterior dizia "zero linhas do módulo
> em `.github/ci-sqlite-pest.list`" — era verdade até
> [PR #5194](https://github.com/wagnerra23/oimpresso.com/pull/5194) e deixou de ser.)_
>
> Os `it()` passaram a carregar o **UC-id no título** (antes só no docblock, o que os deixava fora do
> manifesto G-7 por construção) e os arquivos desta tela entraram na **lane sqlite** do `ci.yml`, que
> o `casos-results-publish` colhe — **os 4, desde 2026-08-03**.
>
> ### `UC-RBFAT-08` — o que a lane revelou, e a errata do meu próprio diagnóstico
>
> _(A redação de 02/08 dizia que **o contador do serviço** estava errado e que o remédio era `[V0]`
> de [W]. **Estava errado — o defeito era do teste.** Fica registrado, não apagado.)_
>
> Quando a lane rodou `InvoiceGeneratorServiceTest` pela primeira vez (run 30778559754),
> `2. Idempotência: 2x run() nao duplica invoice` reprovou em `skipped == 1` (veio `0`).
>
> O que estava realmente acontecendo: o teste simulava a re-execução com
> `$sub->update(['next_due_date' => …])` sobre uma instância **stale**. O 1º run já gravara
> `2026-08-15` no banco, mas o modelo em memória ainda acreditava valer `2026-07-15` — então
> `getDirty()` vinha **vazio** e o Eloquent **não emitia UPDATE nenhum**. A linha continuava em
> agosto, o 2º run **não achava candidato**, e `generated == 0` passava por **ausência de trabalho**.
>
> Ou seja: o assert que "passava" (linha 225) passava pelo motivo errado — **verde por não-execução**
> (`LC-13`) dentro do teste que afirma provar idempotência. O `skipped == 1` era o **único** assert
> que denunciava isso, e eu o li como "o contador mente".
>
> Fix: `UPDATE` de query-builder (que sempre emite SQL) + **pré-condição anti-vácuo** provando que a
> assinatura voltou a ser candidata antes de afirmar qualquer coisa sobre o 2º run.
> **O serviço está correto e não foi tocado** — zero mudança em código que mexe com valor.
>
> Status segue **🧪**: quem carimba ✅ é o cron `casos-results-publish` (07:30 BRT); não rodei nada
> localmente (CT 100).

## Rastreabilidade

| UC | Caso de uso | Prio | CU | Teste | Status |
|----|-------------|------|----|-------|--------|
| UC-RBFAT-01 | Filtros de status · gateway · período · busca combinam | must | `CU-RB-11` 1 | `Wave7FaturasIndexTest` | 🧪 |
| UC-RBFAT-02 | KPIs agregam pago-mês, pendente, atrasado e total | must | `CU-RB-11` 2 | `Wave7FaturasIndexTest` | 🧪 |
| UC-RBFAT-03 | Fatura de outro business não aparece | must `[T0]` | `CU-RB-11` 3 | `Wave7FaturasIndexTest` | 🧪 |
| UC-RBFAT-04 | Paginação reporta meta correta | must | `CU-RB-11` 4 | `Wave7FaturasIndexTest` | 🧪 |
| UC-RBFAT-05 | **Fatura paga NUNCA é cancelada** — 422 "use estorno" | must `[V0]` | `CU-RB-06` 2 | `AssinaturaCobrancaServiceTest` | 🧪 |
| UC-RBFAT-06 | Cancelar de novo é no-op — não chama o gateway | must `[V0]` | `CU-RB-06` 3 | `AssinaturaCobrancaServiceTest` | 🧪 |
| UC-RBFAT-07 | Fatura que nunca foi ao gateway cancela local | must | `CU-RB-06` 4 | `AssinaturaCobrancaServiceTest` | 🧪 |
| UC-RBFAT-08 | Uma fatura por competência — 2× o job não duplica | must `[V0]` | `CU-RB-03` 2 | `InvoiceGeneratorServiceTest` | 🧪 |
| UC-RBFAT-09 | Vencimento avança sem transbordar o mês | must `[V0]` | `CU-RB-03` 3 | `InvoiceGeneratorServiceTest` | 🧪 |
| UC-RBFAT-10 | Pausada/cancelada não faturam · dry-run não escreve · lead antecipa | must | `CU-RB-03` 4 | `InvoiceGeneratorServiceTest` | 🧪 |
| UC-RBFAT-11 | O job de um business não toca o outro | must `[T0]` | `CU-RB-03` 5 | `InvoiceGeneratorServiceTest` | 🧪 |
| UC-RBFAT-12 | **Sem a flag, o estorno não sai** | must `[V0]` | `CU-RB-08` 1 | `RefundCobrancaAsaasJobTest` | 🧪 |
| UC-RBFAT-13 | Estorno é idempotente e recusa documento alheio | must `[V0]` `[T0]` | `CU-RB-08` 2/3/4 | `RefundCobrancaAsaasJobTest` | 🧪 |

---

## UC-RBFAT-01 · Os filtros combinam · `must`
- **Persona:** Eliana [E] procura "quem está atrasado no Inter" pra cobrar.
- **Aceite:** Dado faturas de status e gateways variados · Quando aplico `status=paid` +
  `gateway=inter` + `periodo=atrasados` + busca por nome do cliente **ou** número do documento ·
  Então só sobram as linhas que satisfazem **todos** os filtros ao mesmo tempo.
- **Teste:** [`Wave7FaturasIndexTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave7FaturasIndexTest.php) — `R-RB-WAVE7-2`.
- **Contrato:** `CU-RB-11` item 1.
- **Regressão que defende:** filtro virar `orWhere` (mostra o que não devia) ou a busca deixar de
  cobrir o número do documento — que é como o cliente identifica o boleto ao telefone.
- **Status: 🧪**

---

## UC-RBFAT-02 · Os KPIs agregam o que dizem agregar · `must`
- **Aceite:** Dado faturas pagas, pendentes e vencidas · Quando a tela carrega · Então
  `pago_mes` + `pendente` + `atrasado` + `count_overdue` + `total_faturas` refletem o conjunto real.
- **Teste:** [`Wave7FaturasIndexTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave7FaturasIndexTest.php) — `R-RB-WAVE7-5`.
- **Contrato:** `CU-RB-11` item 2.
- **Regressão que defende:** o hero "Pago este mês" contar fatura cancelada/estornada.
- **Status: 🧪**

---

## UC-RBFAT-03 · Fatura de outro business não aparece · `must` `[T0]`
- **Aceite:** Dado faturas em biz=1 · Quando um usuário biz=99 abre a lista · Então nenhuma linha
  de biz=1 aparece.
- **Teste:** [`Wave7FaturasIndexTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave7FaturasIndexTest.php) — `R-RB-WAVE7-3`.
- **Contrato:** `CU-RB-11` item 3 + `CU-RB-10` + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
  Teste biz=1 vs biz=99, **nunca biz=4** ([ADR 0101](../../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)).
- **Regressão que defende:** ver o valor a receber de outra empresa é o pior bug do projeto.
- **Status: 🧪**

---

## UC-RBFAT-04 · A paginação reporta meta correta · `must`
- **Aceite:** Dado N faturas · Quando pagino · Então `current_page`/`last_page`/`per_page`/`total`
  batem — e os eager loads (cliente, assinatura, plano) vêm juntos (sem N+1).
- **Teste:** [`Wave7FaturasIndexTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave7FaturasIndexTest.php) — `R-RB-WAVE7-1`, `-4`.
- **Contrato:** `CU-RB-11` itens 4 e 5.
- **Regressão que defende:** meta errada faz o operador achar que cobrou tudo quando faltam páginas.
- **Status: 🧪**

---

## UC-RBFAT-05 · Fatura paga NUNCA é cancelada · `must` `[V0]`
- **Persona:** Eliana clica "Cancelar" numa linha errada. O sistema tem que **impedir**, não obedecer.
- **Aceite:** Dado uma fatura `status='paid'` · Quando peço o cancelamento · Então **422** com
  *"Invoice já paga. Use estorno em vez de cancelamento."*, o gateway **não** é chamado, e o status
  permanece `paid`.
- **Teste:** [`AssinaturaCobrancaServiceTest`](../../../../../Modules/RecurringBilling/Tests/Feature/AssinaturaCobrancaServiceTest.php)
  — `bloqueia cancelamento quando invoice paga e sugere estorno`.
- **Contrato:** `CU-RB-06` item 2 + [proibicoes](../../../../../memory/proibicoes.md) §REGRA MESTRE
  valor — dinheiro recebido não some por clique de tela.
- **Regressão que defende:** o guard virar `if (!in_array($status, [...]))` mal escrito e deixar
  `paid` passar → o título é baixado no banco e o dinheiro já entrou: divergência silenciosa entre
  conciliação e sistema.
- **Status: 🧪**

---

## UC-RBFAT-06 · Cancelar de novo é no-op · `must` `[V0]`
- **Aceite:** Dado uma fatura já `canceled` · Quando peço o cancelamento outra vez · Então a resposta
  é `ok` com `skipped='already_canceled'` e **o gateway não é chamado** (`gateway_call:false`).
- **Teste:** [`AssinaturaCobrancaServiceTest`](../../../../../Modules/RecurringBilling/Tests/Feature/AssinaturaCobrancaServiceTest.php)
  — `retorna idempotente quando invoice já canceled (skip gateway)`.
- **Contrato:** `CU-RB-06` item 3 + [ADR tech/0001](../../../../../memory/requisitos/RecurringBilling/adr/tech/0001-idempotencia-charge-attempts-e-webhooks.md).
- **Regressão que defende:** duplo-clique disparando 2 cancelamentos no banco — o segundo pode voltar
  erro do gateway e assustar o operador com um problema que não existe.
- **Status: 🧪**

---

## UC-RBFAT-07 · Fatura que nunca foi ao gateway cancela local · `must`
- **Aceite:** Dado uma fatura sem `gateway`/`gateway_ref` (nunca virou boleto) · Quando cancelo ·
  Então ela vira `canceled` **localmente**, com `gateway_call:false` e sem erro.
- **Teste:** [`AssinaturaCobrancaServiceTest`](../../../../../Modules/RecurringBilling/Tests/Feature/AssinaturaCobrancaServiceTest.php)
  — `cancela local quando invoice nunca foi enviada ao gateway`.
- **Contrato:** `CU-RB-06` item 4.
- **Regressão que defende:** tentar cancelar no gateway uma referência que não existe → exceção que
  trava a tela por um caso trivial.
- **Status: 🧪**

---

## UC-RBFAT-08 · Uma fatura por competência · `must` `[V0]`
- **Persona:** o cliente recebe **um** boleto por mês. Dois = ligação de reclamação e risco de
  pagamento duplicado.
- **Aceite:** Dado uma assinatura vencendo · Quando `InvoiceGeneratorService::run()` roda **duas
  vezes** no mesmo dia · Então existe **exatamente uma** fatura naquela competência `YYYY-MM` e a 2ª
  execução conta `skipped`.
- **Teste:** [`InvoiceGeneratorServiceTest`](../../../../../Modules/RecurringBilling/Tests/Feature/InvoiceGeneratorServiceTest.php)
  — `1. Cria invoice…` + `2. Idempotência: 2x run() nao duplica invoice`.
- **Contrato:** `CU-RB-03` itens 1 e 2 + [ADR tech/0001](../../../../../memory/requisitos/RecurringBilling/adr/tech/0001-idempotencia-charge-attempts-e-webhooks.md).
- **Regressão que defende:** a chave de idempotência mudar de competência pra `created_at` — o job
  reexecutado (retry, cron duplicado) passaria a cobrar 2×.
- **Status: 🧪** · **cross-check `[V0]`:** o 2º caminho independente é
  [`tests/Feature/Calculo/CalculoRecurringBillingTest`](../../../../../tests/Feature/Calculo/CalculoRecurringBillingTest.php),
  que trava a cópia `plan.valor → invoice.valor` a partir de fora do módulo.

---

## UC-RBFAT-09 · O vencimento avança sem transbordar o mês · `must` `[V0]`
- **Persona:** cliente com âncora no dia 31. Em fevereiro, o vencimento tem que cair no **último dia
  de fevereiro** — não em 3 de março.
- **Aceite:** Dado `next_due_date` = dia 31 e ciclo mensal · Quando o job avança o ciclo · Então usa
  `addMonthsNoOverflow` (31/jan → 28 ou 29/fev), **nunca** transbordando pro mês seguinte.
- **Teste:** [`InvoiceGeneratorServiceTest`](../../../../../Modules/RecurringBilling/Tests/Feature/InvoiceGeneratorServiceTest.php)
  — `3. Avanca next_due_date += 1 mes (monthly) preservando anchor`.
- **Contrato:** `CU-RB-03` item 3.
- **Regressão que defende:** trocar por `addMonth()` cru — o vencimento deslizaria pra frente todo
  mês e a assinatura "andaria" no calendário. É `[V0]` porque muda **quando** o dinheiro entra.
- **Status: 🧪** · **cross-check `[V0]`:** `CalculoRecurringBillingTest` caracteriza as **3**
  implementações divergentes de "próximo vencimento" (`SDD §9.2` · US-RB-056) e declara esta como a
  fonte de verdade — é ela que fatura.

---

## UC-RBFAT-10 · Pausada não fatura · dry-run não escreve · lead antecipa · `must`
- **Aceite:** Dado assinaturas `paused`/`canceled` · Quando o job roda · Então elas são puladas. Dado
  `dryRun=true` · Então o job **conta** sem gravar nada. Dado `leadDays=3` · Então vencimentos até
  hoje+3 entram (e com `leadDays=0`, não).
- **Teste:** [`InvoiceGeneratorServiceTest`](../../../../../Modules/RecurringBilling/Tests/Feature/InvoiceGeneratorServiceTest.php)
  — `4. Skipa subscription paused/canceled`, `5. dry-run nao escreve mas conta`, `6. lead-days antecipa`.
- **Contrato:** `CU-RB-03` item 4.
- **Regressão que defende:** faturar assinatura pausada é cobrar quem pediu pra parar — dano de
  reputação direto. E `dry-run` que escreve destrói a única ferramenta segura de simulação (que a
  REGRA MESTRE exige antes de qualquer escrita em prod).
- **Status: 🧪**

---

## UC-RBFAT-11 · O job de um business não toca o outro · `must` `[T0]`
- **Aceite:** Dado assinaturas vencidas em biz=1 · Quando `run(99)` executa · Então **nada** de biz=1
  é lido, faturado ou avançado. E cada fatura gerada deixa rastro na timeline
  (`SubscriptionEvent kind=charge`).
- **Teste:** [`InvoiceGeneratorServiceTest`](../../../../../Modules/RecurringBilling/Tests/Feature/InvoiceGeneratorServiceTest.php)
  — `7. Cross-tenant Tier 0: biz=99 NAO vaza biz=1`, `8. Logga SubscriptionEvent kind=event-charge`.
- **Contrato:** `CU-RB-03` itens 5 e 6 + `CU-RB-10`.
- **Regressão que defende:** um job de fila sem `$businessId` explícito no construtor lê `session()`
  (que não existe em fila) e vira "business 0" — o vetor Tier 0 nº 1 dos jobs.
- **Status: 🧪**

---

## UC-RBFAT-12 · Sem a flag, o estorno não sai · `must` `[V0]`
- **Persona:** [W] é o único que pode decidir devolver dinheiro. O sistema **não** decide sozinho.
- **Aceite:** Dado `ASAAS_REFUND_ENABLED=false` (**o default**) · Quando o
  `RefundCobrancaAsaasJob` roda · Então **nenhuma** chamada `POST /v3/payments/{id}/refund` é feita —
  só um `warning` de log com o TODO.
- **Teste:** [`RefundCobrancaAsaasJobTest`](../../../../../Modules/RecurringBilling/Tests/Feature/RefundCobrancaAsaasJobTest.php)
  — `3. flag ASAAS_REFUND_ENABLED=false → NÃO chama API, só loga TODO`.
- **Contrato:** `CU-RB-08` item 1 + [proibicoes](../../../../../memory/proibicoes.md) §FSM —
  *"Wagner ativa manual no `.env` após validação homolog"*.
- **Regressão que defende:** o dia em que alguém "simplificar" a guarda e o job passar a estornar em
  produção sozinho. Este é o UC mais barato de escrever e o mais caro de perder.
- **Status: 🧪**

---

## UC-RBFAT-13 · Estorno é idempotente e recusa documento alheio · `must` `[V0]` `[T0]`
- **Aceite:** Dado uma charge **já** `REFUNDED` · Então o job é **no-op** (não estorna 2×). Dado um
  `businessId` diferente do dono do documento · Então **`RuntimeException`**. Dado `doc_type` que não
  é `boleto_asaas` · Então **`RuntimeException`**.
- **Teste:** [`RefundCobrancaAsaasJobTest`](../../../../../Modules/RecurringBilling/Tests/Feature/RefundCobrancaAsaasJobTest.php)
  — `2. idempotência…`, `4. cross-tenant…`, `5. doc_type != boleto_asaas…`.
- **Contrato:** `CU-RB-08` itens 2, 3 e 4.
- **Regressão que defende:** estorno duplo (devolve 2× o mesmo dinheiro) e estorno cross-tenant
  (devolve dinheiro de outra empresa) — os dois piores desfechos possíveis deste fluxo.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Sem a permissão `recurringbilling.invoice.cancel` → **403** antes de tocar o gateway
  (`CU-RB-06` item 1). O código existe (`InvoiceController@cancel`); **nenhum teste hoje o prova**.
- **[BACKLOG]** A auditoria (`activity('recurringbilling.invoice')`) grava **sucesso E falha**, com
  quem/quando/motivo/erro (`CU-RB-06` item 5) — exigência Procon/LGPD do charter.
- **[BACKLOG]** `C6Driver::cancelar()` deixa de lançar `BadMethodCallException` (CNAB ocorrência 02) —
  hoje é stub declarado na US-RB-042. Vira UC junto com a implementação.
- **[BACKLOG]** Fatura em atraso dispara a régua de dunning (3/7/15d) — hoje a régua é **texto
  hardcoded na tela de Configurações**, não motor. Sem código, não há UC.
- **[BACKLOG]** `POST /recurring-billing/invoices/{id}/charge` (US-RB-004) com `ChargeResult`
  distinguindo soft × hard decline — endpoint **não existe** (SDD D6).

---

## Refs

- Charter (lei): [`Index.charter.md`](Index.charter.md)
- SDD (âncora dos CU): [`SDD-cobranca-recorrente-v1.0.md`](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md) §6.1 · §5.3 F2/F5/F7
- SPEC (US): US-RB-003 · US-RB-004 · US-RB-042
- Símbolos: `InvoiceController@cancel` · `AssinaturaCobrancaService::cancelInvoice` ·
  `InvoiceGeneratorService::processarSubscription` · `RefundCobrancaAsaasJob`
- Gate: `scripts/casos-coverage-guard.mjs` ([ADR 0264](../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))
