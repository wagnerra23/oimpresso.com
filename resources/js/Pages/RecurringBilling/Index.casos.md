---
id: resources-js-pages-recurring-billing-index-casos
casos: Cobrança recorrente — carteira de assinaturas · /recurring-billing
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento de cobrar é durável — a assinatura que fatura hoje tem que faturar depois do refactor.
owner: wagner
last_run: "2026-07-28"
---

# Casos de Uso & Aceite — Carteira de assinaturas (`/recurring-billing`)

> **Âncora:** `CU-RB-02` (criar assinatura), `CU-RB-04` (editar cobrança) e `CU-RB-14` (consultar a
> carteira) do [SDD §6.1](../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md).
> Os UC derivam do **contrato**, nunca do `.tsx` — teste derivado do código é tautológico e trava o
> desvio em vez de pegá-lo ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> **Por que nasce agora:** o módulo tinha 6 telas, 39 testes e **zero `casos.md`** — a cadeia
> `US → CU → UC → teste` quebrava no elo do meio. O SDD nasceu no mesmo PR; estes UC o citam.
>
> ⚠️ **Força do veredito — leia antes de confiar no status.** _(atualizado 2026-08-02; a redação
> anterior dizia "nenhuma linha de `RecurringBilling` em `.github/ci-sqlite-pest.list`" — era
> verdade até [PR #5194](https://github.com/wagnerra23/oimpresso.com/pull/5194) e deixou de ser.)_
>
> Duas coisas mudaram: os `it()` passaram a carregar o **UC-id no título** (antes só no docblock, o
> que os deixava fora do manifesto G-7 por construção) e os arquivos entraram na **lane sqlite** do
> `ci.yml`, que o `casos-results-publish` colhe — **9 de 13** em 02/08, **12 de 13** desde 03/08.
>
> **`UC-RBSUB-01..04` — destravados em 2026-08-03.** _(A redação de 02/08 dizia que seguiam "SEM
> lane de PR" e atribuía a falha a "a lane sqlite não semeia as permissions Spatie". A primeira
> metade deixou de ser verdade; a segunda estava **errada** — o diagnóstico é outro, abaixo.)_
>
> `Wave21NewSubscriptionTest` (4 errors) e `Wave23EditarAssinaturaTest` (1 failure + 2 errors)
> reprovaram na primeira vez que a lane os rodou. **Não era permission Spatie faltando** — era o
> teste nunca autenticar, por duas causas somadas: (a) `authorize()` devolve `$this->user() !== null`,
> logo exige **usuário**, não permissão; (b) `Gate::before(fn () => true)` tem **zero parâmetros**, e
> o Laravel só invoca before-callback para visitante anônimo quando o callback aceita `null` no 1º
> parâmetro (`Gate::callbackAllowsGuests`) — sem usuário logado o callback **nem dispara**. Os dois
> arquivos declaravam no docblock "auth bypassado via `Gate::before`", o que era falso.
>
> Fix: `actingAs(user biz=1)` no `beforeEach`, padrão vivo do `RefundCobrancaAsaasJobTest`.
> **Zero mudança em código de produto.** `UC-RBSUB-05` continua fora de propósito (🔴 failing-first).
>
> Status segue **🧪** em tudo: quem carimba ✅ é o cron `casos-results-publish` (07:30 BRT), e eu
> não rodei nada localmente (CT 100, [ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
>
> **Legenda:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
> ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | CU | Teste | Status |
|----|-------------|------|----|-------|--------|
| UC-RBSUB-01 | Criar assinatura mapeando forma de pagamento PT → `payment_method` | must | `CU-RB-02` 1/4 | `Wave21NewSubscriptionTest` | 🧪 |
| UC-RBSUB-02 | Validação recusa contato ausente, enum inválido e data no passado | must | `CU-RB-02` 2 | `Wave21NewSubscriptionTest` | 🧪 |
| UC-RBSUB-03 | Busca de cliente não vaza outro business nem tipo errado | must `[T0]` | `CU-RB-02` 3 | `Wave21NewSubscriptionTest` | 🧪 |
| UC-RBSUB-04 | Editar cobrança: 404 cross-tenant · 422 se cancelada | must `[T0]` `[V0]` | `CU-RB-04` | `Wave23EditarAssinaturaTest` | 🧪 |
| UC-RBSUB-05 | **Assinatura criada tem que ser faturável** | must `[V0]` | `CU-RB-02` 5 | `PlanoSemFaturaContratoTest` | ❌ **vermelho esperado** |
| UC-RBSUB-06 | MRR normaliza o ciclo e churn ignora trial | must `[V0]` | `CU-RB-14` 2/3 | `Wave4PresenterIndexTest` | 🧪 |
| UC-RBSUB-07 | Status visual deriva do estado do banco (5 estados) | must | `CU-RB-14` 1 | `Wave4PresenterIndexTest` | 🧪 |
| UC-RBSUB-08 | Ciclo de vida completo (criar → pausar → retomar → cancelar) sem vazar tenant | must `[T0]` | `CU-RB-05` · `CU-RB-10` | `CustomerJourneyTest` | 🧪 |

---

## UC-RBSUB-01 · Criar assinatura mapeando a forma de pagamento · `must`
- **Persona:** Wagner (biz=1) fecha um contrato mensal e cadastra pelo drawer (`?new=1` / atalho `N`).
- **Aceite:** Dado um contato do business e um plano · Quando submeto o drawer com
  `forma_pagamento='boleto'` · Então nasce uma `Subscription` **do business da sessão** com
  `payment_method` mapeado (PT da UI → enum do modelo) e o valor/ciclo/gateway registrados em
  `metadata`.
- **Teste:** [`Wave21NewSubscriptionTest`](../../../../Modules/RecurringBilling/Tests/Feature/Wave21NewSubscriptionTest.php)
  — `R-RB-WAVE21-5`.
- **Contrato:** `CU-RB-02` itens 1 e 4 — *"`business_id` vem da sessão, nunca do request"*.
- **Regressão que defende:** o `business_id` migrar pro request (é o vetor Tier 0 #1) ou o mapa
  PT→enum sumir num refactor, gravando `payment_method` nulo.
- **Status: 🧪** — o teste cita o UC; veredito pendente da lane (hoje só nightly).

---

## UC-RBSUB-02 · A validação recusa o pedido malformado · `must`
- **Persona:** qualquer operador — errar aqui cria assinatura que cobra data errada ou gateway que
  não existe.
- **Aceite:** Dado o `StoreAssinaturaRequest` · Quando falta `contact_id`, ou `ciclo`/`gateway`/
  `forma_pagamento` estão fora do enum, ou `data_proxima_cobranca` é no **passado** · Então o pedido
  é recusado com a mensagem PT-BR correspondente.
- **Teste:** [`Wave21NewSubscriptionTest`](../../../../Modules/RecurringBilling/Tests/Feature/Wave21NewSubscriptionTest.php)
  — `R-RB-WAVE21-3` e `R-RB-WAVE21-4`.
- **Contrato:** `CU-RB-02` item 2.
- **Regressão que defende:** afrouxar o `after_or_equal:today` faz o gerador emitir fatura retroativa
  no primeiro ciclo.
- **Status: 🧪**

---

## UC-RBSUB-03 · A busca de cliente não vaza outro business · `must` `[T0]`
- **Persona:** qualquer tenant. Autocomplete é o lugar clássico onde dado alheio escapa.
- **Aceite:** Dado `GET /recurring-billing/contacts/search?q=` · Quando busco · Então só voltam
  contatos **do business da sessão**, do tipo `customer`/`both` (nunca `lead`/`supplier`), e query
  com menos de 2 caracteres não consulta nada.
- **Teste:** [`Wave21NewSubscriptionTest`](../../../../Modules/RecurringBilling/Tests/Feature/Wave21NewSubscriptionTest.php)
  — `R-RB-WAVE21-6`, `-7`, `-8`.
- **Contrato:** `CU-RB-02` item 3 + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o `where('business_id', …)` cair do query builder do autocomplete.
- **Status: 🧪**

---

## UC-RBSUB-04 · Editar a cobrança respeita tenant e estado · `must` `[T0]` `[V0]`
- **Persona:** Wagner renegocia o valor com um cliente e ajusta pelo drawer.
- **Aceite:** Dado `PUT /recurring-billing/{id}` · Quando a assinatura é de **outro** business →
  **404**; quando está **cancelada** → **422** com a mensagem do serviço; quando é minha e está ativa
  → o novo `valor`/`ciclo`/`forma` fica gravado **sem re-emitir** título já gerado.
- **Teste:** [`Wave23EditarAssinaturaTest`](../../../../Modules/RecurringBilling/Tests/Feature/Wave23EditarAssinaturaTest.php)
  — `R-RB-WAVE23-1`, `-2`, `-3`.
- **Contrato:** `CU-RB-04` (os 3 itens).
- **Regressão que defende:** a edição virar "local-only" **e** disparar re-emissão — o cliente
  receberia dois títulos do mesmo mês. O `[V0]` está aqui: mexe em valor.
- **Status: 🧪**

---

## UC-RBSUB-05 · A assinatura criada tem que ser faturável · `must` `[V0]` 🔴
- **Persona:** Wagner cadastra uma assinatura com valor negociado (ex.: R$ 187,00, que não é o valor
  cheio de nenhum plano). Espera que ela cobre.
- **Aceite:** Dado uma assinatura ativa criada pelo fluxo real (`POST /recurring-billing`) com
  `next_due_date` vencido · Quando `InvoiceGeneratorService::run()` roda pro business dela · Então
  **existe exatamente uma fatura** pra ela naquela competência e ela **não** entra em `errors`.
- **Teste:** [`PlanoSemFaturaContratoTest`](../../../../Modules/RecurringBilling/Tests/Feature/PlanoSemFaturaContratoTest.php)
  — `UC-RBSUB-05`.
- **Contrato:** `CU-RB-02` item 5 + a DoD da **US-RB-002**, que exige literalmente *"criar … **com
  customizações de valor (override do plano)**"* — override de valor é contrato declarado.
- **Regressão que defende:** nada. **Ele não defende, ele denuncia** — hoje o
  `resolvePlanIdFromCiclo()` só casa plano por `ciclo` **E** `valor` exatos; sem match, `plan_id` fica
  `null` e o gerador descarta a assinatura com uma linha de log e **zero alarme**
  ([SDD §9.1](../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md)).
- **Status: ❌ vermelho esperado** — **predição, não veredito**: eu não rodei (CT 100). O teste nasce
  failing-first de propósito. **A correção é decisão `[V0]` de [W]** — há ≥2 remédios válidos que se
  anulam (o gerador cair pra `metadata.valor` × o `store()` recusar valor sem plano × criar plano
  implícito), e escolher remédio antes do diagnóstico é o erro catalogado em
  [proibicoes §5](../../../../memory/proibicoes.md) 2026-07-15.

---

## UC-RBSUB-06 · O MRR normaliza o ciclo e o churn ignora o trial · `must` `[V0]`
- **Persona:** Wagner olha o header (`N ATIVAS · MRR R$ X · CHURN Y%`) pra decidir. Número inflado =
  decisão errada.
- **Aceite:** Dado uma assinatura **trimestral** · Quando o MRR é computado · Então ela entra pelo
  **equivalente mensal**, não pelo valor cheio. E dado assinaturas em `trialing` · Quando o churn é
  computado · Então o denominador é o total **não-trialing**.
- **Teste:** [`Wave4PresenterIndexTest`](../../../../Modules/RecurringBilling/Tests/Feature/Wave4PresenterIndexTest.php)
  — `R-RB-WAVE4-3` e `R-RB-WAVE4-4`.
- **Contrato:** `CU-RB-14` itens 2 e 3.
- **Regressão que defende:** somar valor cheio de trimestral/anual no MRR — infla o número ~3× e ~12×.
  É `[V0]` porque é agregação de valor, mesmo sem escrever no banco.
- **Status: 🧪**

---

## UC-RBSUB-07 · O status visual deriva do estado do banco · `must`
- **Persona:** Larissa/Wagner leem a lista por cor. "Falhou" tem que significar falhou.
- **Aceite:** Dado os 5 estados do banco · Quando a lista é montada · Então o status Cowork sai do
  mapa declarado: `em_dia` ← `active|trialing` sem vencida · `retentando` ← `past_due` < 3 tentativas ·
  `falhou` ← `past_due` ≥ 3 · `pausada` ← `paused` · `cancelada` ← `canceled`. E a linha da lista +
  o payload do drawer trazem os campos canônicos (contato, nota, blocos fiscais).
- **Teste:** [`Wave4PresenterIndexTest`](../../../../Modules/RecurringBilling/Tests/Feature/Wave4PresenterIndexTest.php)
  — `R-RB-WAVE4-1`, `-2`, `-5`.
- **Contrato:** `CU-RB-14` itens 1 e 4 + o mapa literal do `Index.charter.md` §Goals.
- **Regressão que defende:** o mapa virar coluna no banco (denormalizar o derivado) ou o limiar de 3
  tentativas mudar sem ninguém perceber — "retentando" viraria "falhou" na cara do operador.
- **Status: 🧪**

---

## UC-RBSUB-08 · O ciclo de vida completo não vaza tenant · `must` `[T0]`
- **Persona:** Wagner cria, pausa nas férias do cliente, retoma e um dia cancela. Cada transição tem
  que valer e deixar rastro.
- **Aceite:** Dado a jornada completa (criar → gerar fatura → pausar → retomar → cancelar) · Quando
  ela é percorrida · Então cada passo produz o efeito esperado, um business vizinho (biz=99)
  **não interfere** em nada, e as transições emitem span de observabilidade
  (criar/pausar/retomar/cancelar/update).
- **Teste:** [`CustomerJourneyTest`](../../../../Modules/RecurringBilling/Tests/Feature/CustomerJourneyTest.php)
  — `D5 — Customer Journey completo (9 passos)`, `D5 — Multi-tenant journey: biz=99 não interfere com
  biz=1`, `D9.a — Journey valida 5+ spans OTel emitidos`.
- **Contrato:** `CU-RB-05` itens 1-3 + `CU-RB-10` +
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** transições isoladas passam nos testes unitários e quebram **em
  sequência** (pausar depois de cancelar, retomar assinatura de outro tenant). É o único teste do
  módulo que exercita a máquina de estados inteira de ponta a ponta.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = **órfão** = viola o `casos-gate` e **bloqueia o
> merge de quem for atendê-lo**. Prefiro 7 UC ancorados a 30 órfãos.

- **[BACKLOG]** Assinatura de plano com trial nasce `status='trialing'` — hoje o `store()` hardcoda
  `'active'` (`CU-RB-02` item 6; a DoD da US-RB-002 exige). Vira UC junto com o fix.
- **[BACKLOG]** Cancelar com `tipo_cancelamento` (fim de ciclo × imediato) + `credit_note` do saldo +
  evento `ContractCanceled` — `CU-RB-05` item 4, tudo ausente hoje.
- **[BACKLOG]** Timeline append-only: nenhum evento pode ser editado nem apagado depois de gravado.
  `rb_subscription_events` é append-only por convenção, não por trigger — o UC nasce quando houver
  teste que prove.
- **[BACKLOG]** Reenviar NFe pelo drawer (`POST /{id}/reenviar-nfe`) resolve a emissão certa por
  `(business_id, contact_id)` — a resolução é **heurística** hoje (última autorizada do business);
  precisa de contrato antes de virar UC.
- **[BACKLOG]** `Inertia::defer` em `subscriptions`/`kpis` (`CU-RB-14` item 5) — nenhum teste hoje
  prova que a prop **não** vem no render inicial.

---

## Refs

- Charter (lei): [`Index.charter.md`](Index.charter.md)
- SDD (âncora dos CU): [`SDD-cobranca-recorrente-v1.0.md`](../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md) §6.1
- SPEC (US): [`SPEC.md`](../../../../memory/requisitos/RecurringBilling/SPEC.md) — US-RB-002, US-RB-005
- Controller: `RecurringBillingController` — `store()` · `update()` · `searchContacts()`
  (`grep -n "public function" Modules/RecurringBilling/Http/Controllers/RecurringBillingController.php`)
- Gate: `scripts/casos-coverage-guard.mjs` (G-1/G-2/G-5/G-6/G-7 — [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))
