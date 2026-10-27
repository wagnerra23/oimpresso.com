---
id: resources-js-pages-recurring-billing-index-casos
casos: Cobrança recorrente — carteira de assinaturas · /recurring-billing
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento de cobrar é durável — a assinatura que fatura hoje tem que faturar depois do refactor.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "2026-10-26 (PR #9870, Pix Automatico BCB) — a tela ganhou o bloco Pix Automatico no drawer (PixAutomaticoBloco, so com o recurso ligado), coberto pelo UC-RBPA-14 deste arquivo; MandatoPixAutomaticoContratoTest rodou no CT 100 MySQL no head combinado da pilha (29 passed, 104 assertions, com os testes de webhook, cobranca e driver) e o vitest do bloco deu 6 passed. Os demais UCs nao tocam o drawer de mandato. ---- REGISTRO ANTERIOR: last_run 2026-10-13. 2026-10-13 (PR contratos de tela, recorrente thread 01) — REVALIDACAO ANALITICA, suite NAO executada. A mudanca na tela sao so atributos data-contract (ancoras do contrato de tela), sem dado, handler, copy nem classe; nenhum UC afirma ancora. ---- REGISTRO ANTERIOR: last_run 2026-09-11. 2026-09-08 (PR tokenizacao da superficie) — REVALIDACAO ANALITICA, suite NAO executada. Por que: Pest so roda no CT 100 (proibicao Tier 0) e o checkout de la nao contem esta mudanca, entao rodar ali produziria recibo de OUTRO codigo. O que a mudanca E: 518 trocas de `className` (cor crua -> token do DS: bg-white->bg-card, text-stone-*->text-foreground/muted-foreground, ring/border-stone-*->ring/border-border). Zero linha de logica, controller, service ou query. MEDIDO, nao lido: (a) grep por `className|bg-white|text-stone|oklch|css|tailwind|style=` nos 14 arquivos de teste citados por estes casos.md devolveu 0 hits em TODOS, com controle positivo (8 a 33 asserts casam por arquivo, logo o grep funciona); (b) as cores de STATUS ficaram INTACTAS — o diff toca emerald/amber/rose em 2 pontos e ambos sao vizinhanca neutra (`border-stone-300`->`border-border` ao lado de um `focus:ring-rose-100` preservado, e o cinza do icone NAO-pinado ao lado de um `fill-amber-500` preservado), o que mantem o UC-RBSUB-07 ("Larissa/Wagner leem a lista por cor") valido; (c) contraste medido no runtime antes/depois: identico (425 passam AA / 27 reprovam / pior 1.00 nos dois estados). Isto NAO substitui execucao: o Status continua vindo do manifesto G-7, que a lane do PR alimenta. ---- REGISTRO ANTERIOR: 2026-08-28 (PR desta sessão) — REVALIDAÇÃO ANALÍTICA, suíte NÃO executada. Por quê: Pest só roda no CT 100 (proibição Ti..."
---

> ℹ️ **`last_run` 2026-09-08 → 2026-09-11 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` no [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) foi **1 linha(s) de COMENTÁRIO** —
> o path do protótipo (`prototipo-ui/cowork/…` → `prototipo-ui/cowork/Wagner/…`, topologia por dono da ADR 0397).
> Zero JSX estrutural, zero handler, zero prop, zero copy alterada (verificado: `git diff origin/main...HEAD -- resources/js/Pages/RecurringBilling/Index.tsx`
> só tem linhas iniciadas por `//`, `*` ou `{/*`). **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo
> significa na prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run — mesmo tratamento do #6913.

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
| UC-RBSUB-05 | **Assinatura criada tem que ser faturável** | must `[V0]` | `CU-RB-02` 5 | `PlanoSemFaturaContratoTest` · `AssinaturaNegociadaSchemaRealContratoTest` | 🧪 |
| UC-RBSUB-06 | MRR normaliza o ciclo e churn ignora trial | must `[V0]` | `CU-RB-14` 2/3 | `Wave4PresenterIndexTest` | 🧪 |
| UC-RBSUB-07 | Status visual deriva do estado do banco (5 estados) | must | `CU-RB-14` 1 | `Wave4PresenterIndexTest` | 🧪 |
| UC-RBSUB-08 | Ciclo de vida completo (criar → pausar → retomar → cancelar) sem vazar tenant | must `[T0]` | `CU-RB-05` · `CU-RB-10` | `CustomerJourneyTest` | 🧪 |

> ℹ️ **`last_run` 2026-07-28 → 2026-08-25 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` foi **comentário de ponteiro de design**: a linha citava um
> caminho de protótipo que não existe mais no repo, e passou a citar o vivo (ou a declarar a
> ausência com data). Zero JSX, zero handler, zero prop, zero copy alterada — `git diff --numstat`
> do PR não tem uma linha sequer fora de comentário. **Nenhum UC desta tela foi reexecutado nem
> revalidado**; o bump é o que o campo significa na prática (*trio reconciliado com a tela nesta
> data*), não afirmação de re-run.
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

## UC-RBSUB-05 · A assinatura criada tem que ser faturável · `must` `[V0]`
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
- **Desfecho (fato datado, o texto acima fica):** [W] escolheu o remédio "a" em 2026-10-19 e o #9606 ensinou o
  gerador a faturar por `metadata.valor`/`metadata.ciclo`. **Medido em 2026-10-26:** os dois testes desta linha
  (`PlanoSemFaturaContratoTest` e `Wave21NewSubscriptionTest`) usam schema sintético com `plan_id` **nulável**,
  mas a migration e a produção têm `NOT NULL` — no MySQL real o `store()` estourava `1048 Column 'plan_id'
  cannot be null` e a assinatura negociada nunca existia. A migration `make_plan_id_nullable_on_rb_subscriptions`
  fecha isso, e [`AssinaturaNegociadaSchemaRealContratoTest`](../../../../Modules/RecurringBilling/Tests/Feature/AssinaturaNegociadaSchemaRealContratoTest.php)
  prova no schema real: criada sem plano pelo `store()` e faturada pelo gerador com o valor negociado.
- **Status: 🧪** — verde no CT 100 (MySQL real); a lane `modules-pest` dá o veredito.

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

## Cobrança automática pelo Pix Automático do BCB (US-RB-021 · decisão [W] 2026-10-26)

> Motor sem tela: o comando `rb:cobrar-pix-automatico` (06:00, só com `RB_COBRAR_PIX_AUTOMATICO=true`)
> cobra a fatura das assinaturas desta tela que têm recorrência Pix Automático aprovada. Fonte:
> SPEC US-RB-021 + API Pix do BCB v2.10.0 (`/cobr` criada pelo recebedor de 10 a 2 dias antes do
> vencimento). Teste: [`CobrarPixAutomaticoContratoTest`](../../../../Modules/RecurringBilling/Tests/Feature/CobrarPixAutomaticoContratoTest.php).

## UC-RBPA-01 · Fatura da assinatura com recorrência aprovada é cobrada no ciclo, pelo valor dela · `must` `[V0]`
**Dado** uma assinatura mensal de 106,07 com recorrência `APROVADA` vencendo em 5 dias **Quando** o motor roda
**Então** a fatura é gerada (106,07, vencimento certo) e nasce 1 cobrança `pix_recv` `emitida` de 10.607 centavos,
e o banco recebe `valor.original = "106.07"` com o `idRec` da assinatura — os três batem.
Status: 🧪

## UC-RBPA-02 · Fora da janela de 10 a 2 dias, ou sem recorrência aprovada, não cobra · `must` `[V0]`
**Dado** vencimentos em 1 e em 11 dias, e assinaturas com recorrência `CRIADA` ou sem recorrência que já têm
fatura aberta dentro da janela **Quando** o motor roda **Então** nenhuma cobrança nasce e nada vai ao banco.
Status: 🧪

## UC-RBPA-03 · Rodar duas vezes no mesmo dia não cobra duas vezes · `must` `[V0]`
**Quando** o motor roda duas vezes **Então** existe 1 cobrança e a 2ª rodada reporta `ja_cobrada`.
Status: 🧪

## UC-RBPA-04 · Erro do banco fica registrado e a rodada seguinte tenta de novo · `must`
**Dado** o banco devolvendo erro na 1ª tentativa **Então** a cobrança fica `erro`, e a rodada seguinte cria outra
com chave (e txid) diferente, que sai `emitida`.
Status: 🧪

## UC-RBPA-05 · `--dry-run` mostra o que seria cobrado sem gravar nem chamar o banco · `must`
**Quando** rodo `rb:cobrar-pix-automatico --dry-run` **Então** ele informa as faturas ainda a gerar e as que seriam
cobradas, e nenhuma fatura, cobrança ou chamada ao banco acontece.
Status: 🧪

## UC-RBPA-06 · Um business não toca o outro; flag desligada não cobra · `must` `[T0]`
**Dado** 98 e 99 com assinatura e credencial **Quando** o motor roda para a 98 **Então** a 99 fica sem fatura e sem
cobrança; e com a flag desligada o comando não cobra ninguém.
Status: 🧪

## UC-RBPA-07 · Cobrança do ciclo liquidada vira paga, com o valor dela · `must` `[V0]`
Fonte: API Pix BCB v2.10.0 (webhook `cobsr[]`, status `CONCLUIDA`). Teste: `WebhookPixAutomaticoContratoTest`.
**Dado** uma cobrança `pix_recv` emitida de 10.607 centavos **Quando** chega o webhook com o `txid` dela em
`CONCLUIDA` **Então** ela fica `paga` com `valor_pago_centavos = 10607` e a data da liquidação, e sai 1
`CobrancaPaga` com esse valor (a fatura vira paga pelo listener da US-RB-057); reentrega não repete.
Status: 🧪

## UC-RBPA-08 · Recorrência aprovada atualiza o mandato; cobrança rejeitada vira erro · `must`
**Quando** chega `recs[]` com `APROVADA` **Então** o mandato da assinatura com aquele `idRec` passa a `APROVADA`
(é o que o motor exige para cobrar). E `REJEITADA` deixa a cobrança em `erro`, mas `CANCELADA` não desfaz uma
cobrança já paga.
Status: 🧪

## UC-RBPA-09 · Flag desligada: o webhook fica gravado sem processar · `must`
Status: 🧪

## UC-RBPA-10 · Um business não paga cobrança do outro · `must` `[T0]`
**Dado** a mesma `txid` numa cobrança da 99 **Quando** o evento é da 98 **Então** nada muda na 99; e o job pedido
com o business errado não carrega o evento.
Status: 🧪

## UC-RBPA-11 · Pedir a autorização do Pix Automático gera o QR com o valor da assinatura · `must` `[V0]`
Fonte: SPEC US-RB-020 + API Pix BCB v2.10.0 (jornada 2: `/locrec` → `/rec` com `loc` → `GET /rec` com `dadosQR`).
Teste: `MandatoPixAutomaticoContratoTest`.
**Dado** uma assinatura ativa com plano mensal de 106,07 e cliente com CPF **Quando** peço a autorização **Então** o
banco recebe a recorrência com `valorRec = "106.07"`, `MENSAL`, início no próximo vencimento, o CPF do cliente e o
`loc` criado; o mandato grava `id_rec`, `status = CRIADA`, o Pix copia e cola e 10.607 centavos.
Status: 🧪

## UC-RBPA-12 · Pedir de novo não cria outra recorrência · `must`
**Dado** um mandato `CRIADA` ou `APROVADA` **Quando** peço de novo **Então** volta o mesmo QR e nada vai ao banco.
Status: 🧪

## UC-RBPA-13 · A rota respeita flag, empresa e credencial · `must` `[T0]`
**Então** flag desligada → 404; assinatura de outra empresa → 404 (e o mandato dela não muda); empresa sem credencial
do Pix Automático → 422 com a mensagem; com tudo certo → o QR na resposta.
Status: 🧪

## UC-RBPA-14 · O drawer mostra o bloco Pix Automático só com o recurso ligado · `must`
**Dado** `RB_COBRAR_PIX_AUTOMATICO` desligada **Então** o drawer não recebe o bloco (`pix_automatico = null`). Ligada,
sem mandato → status nulo e o botão "Pedir autorização"; `CRIADA` → o QR e o Pix copia e cola; `APROVADA` → sem QR.
Teste: `MandatoPixAutomaticoContratoTest`.
Status: 🧪

## Cartão recorrente — cartão salvo como token (US-RB-011 · reaberta por [W] 2026-10-27)

> Motor sem tela nesta fatia: quando a fatura de uma assinatura é paga no cartão pela página hospedada
> do Asaas, o listener `SalvarCartaoDaCobrancaPaga` (só com `RB_SALVAR_CARTAO=true`) guarda o token do
> cartão e o liga à assinatura, para as próximas faturas serem cobradas nele. Fonte: SPEC US-RB-011
> (PCI: o número nunca chega ao oimpresso) + payload `payment.creditCard` do webhook do Asaas. Roda na
> lane `paymentgateway-pest` (MySQL). Teste: [`CartaoSalvoContratoTest`](../../../../Modules/RecurringBilling/Tests/Feature/CartaoSalvoContratoTest.php).

## UC-RBCC-01 · Fatura paga no cartão guarda o token e liga à assinatura · `must`
- **Persona:** cliente da assinatura que paga a 1ª fatura no cartão pela página hospedada do Asaas; o gestor quer que as próximas caiam no mesmo cartão sem pedir os dados de novo.
- **Aceite:** Dado uma fatura de assinatura paga no cartão, com o webhook do Asaas trazendo token, últimos 4 e bandeira · Quando o `CobrancaPaga` sai · Então nasce 1 cartão salvo com os últimos 4 e a bandeira, o token cifrado no banco (e fora do JSON do modelo), e a assinatura passa a apontar para ele.
- **Teste:** `Modules/RecurringBilling/Tests/Feature/CartaoSalvoContratoTest.php` — `UC-RBCC-01 · fatura paga no cartão salva o token cifrado com últimos 4 e bandeira, e liga à assinatura`.
- **Regressão que defende:** SPEC US-RB-011 (salvar só o token do gateway; PCI fora do oimpresso).
- **Status: 🧪** — passou na lane `paymentgateway-pest` (MySQL) do PR #9951, run 40492147242, 8 assertions; ✅ quando `casos:results` regravar o manifesto.

## UC-RBCC-02 · O mesmo cartão não duplica e a assinatura com cartão não troca sozinha · `must`
- **Persona:** gestor da carteira; o webhook do Asaas pode chegar repetido.
- **Aceite:** Dado um cartão já salvo · Quando o mesmo webhook é processado de novo · Então o resultado é `ja_salvo` e não nasce outra linha; Dado uma assinatura que já tem cartão · Quando outro cartão é salvo · Então ele não substitui o da assinatura (trocar é ato do cliente ou do gestor).
- **Teste:** `Modules/RecurringBilling/Tests/Feature/CartaoSalvoContratoTest.php` — `UC-RBCC-02 · o mesmo cartão não é salvo duas vezes e a assinatura que já tem cartão não troca`.
- **Regressão que defende:** SPEC US-RB-011 (idempotência do webhook; o cartão da assinatura não muda sem ato humano).
- **Status: 🧪** — passou na lane `paymentgateway-pest` (MySQL) do PR #9951, run 40492147242, 5 assertions; ✅ quando `casos:results` regravar o manifesto.

## UC-RBCC-03 · Número de cartão é recusado; boleto e flag desligada não salvam · `must` `[T0]`
- **Persona:** responsável pela segurança dos dados (PCI e LGPD).
- **Aceite:** Dado um campo com sequência de 13 a 19 dígitos · Quando o cartão seria salvo · Então nada é gravado (`recusado_numero`) e o log não leva o valor; Dado uma fatura paga no boleto · Então ela é ignorada; Dado `RB_SALVAR_CARTAO` desligada · Então nada é salvo.
- **Teste:** `Modules/RecurringBilling/Tests/Feature/CartaoSalvoContratoTest.php` — `UC-RBCC-03 · campo com sequência de número de cartão é recusado e nada é gravado; boleto e flag desligada não salvam`.
- **Regressão que defende:** SPEC US-RB-011 (nunca número nem CVV no banco ou log) e a flag desligada por padrão.
- **Status: 🧪** — passou na lane `paymentgateway-pest` (MySQL) do PR #9951, run 40492147242, 3 assertions; ✅ quando `casos:results` regravar o manifesto.

## UC-RBCC-04 · Um business não salva o cartão da cobrança do outro · `must` `[T0]`
- **Persona:** dono de outra empresa no mesmo servidor (isolamento Tier 0, ADR 0093).
- **Aceite:** Dado a cobrança do tenant 99 · Quando chega um evento dizendo tenant 98 · Então `ignorado`, nenhum cartão salvo e a assinatura do 99 intocada.
- **Teste:** `Modules/RecurringBilling/Tests/Feature/CartaoSalvoContratoTest.php` — `UC-RBCC-04 · evento com business errado não salva o cartão da cobrança do outro`.
- **Regressão que defende:** ADR 0093 (`business_id` em toda leitura e escrita) e ADR 0358 (98 grava, 99 fica intacto).
- **Status: 🧪** — passou na lane `paymentgateway-pest` (MySQL) do PR #9951, run 40492147242, 3 assertions; ✅ quando `casos:results` regravar o manifesto.

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
- **[BACKLOG]** Limite de crédito também no contrato (pedido de 2026-10-23) `[V0]` — Dado um cliente
  acima do limite de crédito, Quando crio uma assinatura para ele, Então com a empresa em "avisar" ela
  nasce com o aviso de quanto passou; em "bloquear" ela não nasce. É a mesma escolha da venda. Fonte:
  Omie (o limite vale para OS, contrato e pedido); US-CRM-081.

---

## Refs

- Charter (lei): [`Index.charter.md`](Index.charter.md)
- SDD (âncora dos CU): [`SDD-cobranca-recorrente-v1.0.md`](../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md) §6.1
- SPEC (US): [`SPEC.md`](../../../../memory/requisitos/RecurringBilling/SPEC.md) — US-RB-002, US-RB-005
- Controller: `RecurringBillingController` — `store()` · `update()` · `searchContacts()`
  (`grep -n "public function" Modules/RecurringBilling/Http/Controllers/RecurringBillingController.php`)
- Gate: `scripts/casos-coverage-guard.mjs` (G-1/G-2/G-5/G-6/G-7 — [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))
