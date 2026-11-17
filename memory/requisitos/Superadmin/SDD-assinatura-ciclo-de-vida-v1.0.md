---
id: requisitos-superadmin-sdd-assinatura-ciclo-de-vida-v1-0
slug: superadmin-sdd
title: "SDD — Ciclo de vida da assinatura da empresa (domínio Superadmin)"
type: sdd
module: Superadmin
status: ativo
owner: W
version: 1.0.0
last_updated: "2026-11-16"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - SCOPE.md
  - SUPERFICIE.md
  - RUNBOOK-assinaturas.md
  - RUNBOOK-minha-assinatura.md
  - RUNBOOK-pagar.md
  - RUNBOOK-pacotes.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0170-onda5-simplificada
  - 0170-paymentgateway-extracao-camada-cobranca
  - 0351-sdd-from-source
related_us:
  - US-SUPER-001
  - US-SUPER-002
  - US-SUPER-003
---

# SDD — Ciclo de vida da assinatura da empresa (domínio Superadmin)

> **Unidade:** o fluxo, não uma tela. Ele atravessa quatro telas (Negócios, Assinaturas, Minha assinatura, Pagar), dois módulos (`Superadmin`, `PaymentGateway`) e o núcleo (`app/Utils/ModuleUtil.php`).
>
> **Regra deste documento:** ele descreve o que o código faz em `origin/main` 580be57 (2026-11-16), lido do fonte, **inclusive os buracos**. Onde o comportamento futuro depende de decisão do dono, a seção diz isso e para. As pendências são **B1/B2/B3** (§9) e são decisão [W]. Nada aqui foi medido em runtime, salvo onde dito.
>
> Toda referência `arquivo:linha` é de 580be57. Linha anda; o símbolo citado ao lado é o que re-localiza.

## 0. Base empírica 🖐 curado (foto de 2026-11-16)

- Fonte 1 (código): leitura dos arquivos citados no §5, em 580be57.
- Fonte 2 (Blade legada): `subscription/pay.blade.php` + partials `pay_*` — o pagamento ainda servido sem a flag `useV2SuperadminPagar`.
- Fonte 3 (Delphi): o único elo com o legado é o flag `business.officeimpresso_bloqueado`, lido pelos endpoints de licença do desktop (`Modules/Connector/.../LicencaComputadorController.php`, `OImpressoRegistroController.php`, `Modules/Officeimpresso/...`). Não há `ANTI-REGRESSAO-*` deste fluxo.
- Recibo de produção, datado e não remedido: o docblock de `SuperadminServiceProvider.php:100-107` registra que em **2026-09-09** a tabela `cobrancas` tinha **0** linhas `origem_type = subscription_license`. Até essa data o fluxo de cobrança automática nunca tinha emitido cobrança em prod.
- Auditoria de origem: lote L6 da auditoria do Superadmin de 2026-11-05 (eixo 3).

## 1. Visão geral ⚙️ derivado

A assinatura (`subscriptions`) é o que decide, por empresa, **quais módulos por pacote ficam ligados** e **se a empresa está assinante**. O ciclo tem sete etapas:

1. empresa nova
2. trial
3. cobrança
4. pagamento
5. habilitação de módulo
6. renovação
7. inadimplência e expiração

As telas que tocam o fluxo:

| Tela | Quem usa | Papel no fluxo |
|---|---|---|
| `superadmin/Negocios/Index` (drawer Adicionar assinatura) | operador | cria assinatura manual já `approved` |
| `superadmin/Assinaturas/Index` | operador | aprova, vence, cancela e edita vigência via `SubscriptionLifecycleService` |
| `superadmin/Pacotes/Index` | operador | edita o pacote e, com `update_subscriptions`, regrava o `package_details` das vigentes |
| `superadmin/MinhaAssinatura/Index` | cliente | vê a ativa e o histórico do próprio negócio |
| `superadmin/Pagar/Index` (flag) ou `subscription/pay.blade.php` | cliente | paga; a tela nova só oferece PIX Automático e offline (UC-SAPG-03) |

## 2. Público-alvo e personas 🖐 curado — [W] valida

- **Operador da plataforma (Wagner, biz=1):** cria negócio, aprova pagamento offline, ajusta vigência. Toda cobrança SaaS é emitida **em nome do biz=1** (`EmitTrialExpiredCobrancasCommand.php:39`).
- **Cliente (dono da empresa assinante):** vê a assinatura e paga em `/subscription/*`, com a permissão `superadmin.access_package_subscriptions`. Exemplos e testes deste fluxo usam tenants fictícios, **nunca** biz=4.

## 3. Governança aplicável ⚙️ derivado

- **Tier 0 multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)):** o fluxo é cross-tenant por natureza. A cobrança vive em biz=1 e a assinatura pertence ao tenant. Cada bypass de escopo leva `// SUPERADMIN: <razão>` (ex.: `EmitTrialExpiredCobrancasCommand.php:100,124`).
- **REGRA MESTRE valor/estoque (`memory/proibicoes.md`):** `package_price`, preço do pacote e emissão de cobrança são VALOR. Mudança aqui exige dupla prova + tabela antes→depois.
- **Cobrança:** a camada de cobrança é do `Modules/PaymentGateway` ([ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md), [ADR 0170 Onda 5](../../decisions/0170-onda5-simplificada.md)). Este módulo só reage aos eventos `CobrancaPaga` / `CobrancaVencida`.

## 4. Design system aplicável ⚙️ derivado

Fora do escopo deste SDD (é um fluxo, não uma tela). Cada tela tem charter e RUNBOOK próprios, listados no §11.

## 5. Arquitetura ⚙️ derivado

### 5.1 Visão em camadas

| Etapa | Entrada | Código |
|---|---|---|
| Empresa nova | `Business::created` | `Modules/Superadmin/Observers/BusinessAutoSubscriptionObserver.php` (registrado em `SuperadminServiceProvider.php:164`) |
| Cobrança do trial | cron `paymentgateway:emit-trial-expired`, 08:00, só `app.env = live` | `SuperadminServiceProvider.php:119-125` → `Modules/PaymentGateway/Console/Commands/EmitTrialExpiredCobrancasCommand.php` |
| Pagamento pelo cliente | `POST /subscription/{package_id}/confirm` | `SubscriptionController::confirm` (`:355`) → `confirm_paymentgateway` (`:435`) |
| Pago | evento `CobrancaPaga` | `Listeners/OnCobrancaPagaUpdateSubscription.php` (registrado em `SuperadminServiceProvider.php:182`) |
| Vencido | evento `CobrancaVencida` | `Listeners/OnCobrancaVencidaBloqueaSubscription.php` (`SuperadminServiceProvider.php:183`) |
| Ação do operador | tela Assinaturas | `Services/SubscriptionLifecycleService.php` (`approve` :46 · `expire` :86 · `cancel` :126) |
| Leitura de acesso | qualquer controller do ERP | `app/Utils/ModuleUtil.php` (`isSubscribed` :146 · `hasThePermissionInSubscription` :167) → `Subscription::active_subscription` |
| Aviso de fim | cron `pos:sendSubscriptionExpiryAlert`, diário, só `live` | `Console/SubscriptionExpiryAlert.php` |

### 5.2 Modelo de dados (núcleo)

- `subscriptions`: `business_id` (o tenant dono), `package_id`, `status`, `start_date`, `end_date`, `trial_end_date`, `paid_via`, `payment_transaction_id`, `package_price`, `package_details` (JSON: limites + `<modulo>_module`), `created_id`.
- O enum de `status` é `approved | waiting | declined | expired | cancelled`. Os dois últimos foram acrescentados em 2026-08-19 (`Database/Migrations/2026_08_19_000001_widen_subscriptions_status_enum.php:44`).
- `packages`: `price`, `interval` (`days|months|years`), `interval_count`, `trial_days`, `custom_permissions`, `is_active`, `is_private`.
- `system`: `default_saas_package_id` (pacote do auto-onboarding) e `package_expiry_alert_days`.
- `business.officeimpresso_bloqueado`: o bloqueio que o **desktop** lê.
- `cobrancas` (do PaymentGateway): `origem_type = subscription_license`, `origem_id = subscriptions.id`, `business_id = 1`.

### 5.3 Fluxos críticos

**F1 — Empresa nova → assinatura `waiting`.**

`BusinessAutoSubscriptionObserver::created` roda em todo `Business::create`, tanto pela tela quanto pela API do Connector. Ele **pula** quando:
- o business é o biz=1 (`:42`);
- `app.env = demo` (`:46`);
- o business já tem assinatura (`:50-53`);
- `default_saas_package_id` está vazio (`:55-62`);
- o pacote está inativo ou não existe (`:64-72`).

Quando age, cria a assinatura (`:78-90`) com:
- `status = waiting`;
- `start_date` e `end_date` nulos;
- `trial_end_date` = hoje + `trial_days` (ou hoje, se o trial é 0);
- `paid_via = paymentgateway_pix_automatico`;
- `package_details` copiado do pacote.

Se o `create` falha, o Business fica criado e o erro só vai para o log (`:99-107`).

**F2 — Durante o trial (buraco B1).**

`ModuleUtil::isSubscribed` e `hasThePermissionInSubscription` perguntam a `Subscription::active_subscription`. Esse método exige `status = approved` **e** `start_date <= hoje <= end_date` (`Subscription.php:111-125`). A assinatura do F1 é `waiting`, com datas nulas, então **não conta**. Lido do código, não medido em runtime: durante o trial, `isSubscribed` devolve false e nenhum módulo por pacote é liberado.

- Superadmin logado passa sempre (`ModuleUtil.php:171-173`).
- Os chamadores de `isSubscribed` bloqueiam ações do núcleo: `BusinessLocationController:143`, `ContactController:1761` e outros (`git grep -n "isSubscribed("`).

**F3 — Cobrança do trial vencido.**

O cron `paymentgateway:emit-trial-expired` seleciona assinaturas que tenham, ao mesmo tempo (`EmitTrialExpiredCobrancasCommand.php:86-89`):
- `status = waiting`;
- `trial_end_date <= hoje`;
- `paid_via = paymentgateway_pix_automatico`.

Para cada uma:
- pula se já existe cobrança `emitida|paga` da mesma origem no mês (`:103-107`);
- senão, emite PIX Automático pelo `PaymentGatewayContract` com idempotency `onda5b-sub-{id}-{YYYY-MM}` (`:145-153`).

O comando exige uma config de gateway `bcb_pix` ativa no biz=1 (`:63-66`).

**F3' — Cliente paga pela tela.**

`confirm_paymentgateway`:
- cria ou recicla uma assinatura `waiting` (`SubscriptionController.php` ~`:451-465`);
- emite o mandato PIX Automático com idempotency `onda5-sub-{id}` (`:500-508`);
- responde ao cliente que "a renovação acontece automaticamente todo mês" (`:520`). Essa frase depende do comportamento do mandato do lado do PaymentGateway. **Não medido.**

**F4 — `CobrancaPaga` → `approved`.**

`OnCobrancaPagaUpdateSubscription::handle`:
1. ignora origem que não é `subscription_license` (`:43`);
2. **retorna sem fazer nada se a assinatura já está `approved`** (`:71`, buraco B2);
3. confere o valor pago (`valorPagoCentavos`) contra o preço da assinatura (`package_price`, ou o do pacote se for zero); divergiu, registra warning e não aprova (`:85-98`, lote L2, #11048);
4. calcula as datas (`calcPackageDates`, `:162`): início = maior `end_date` aprovado + 1 dia, ou hoje (`Subscription::end_date`, `Subscription.php:160-174`); fim = início + `interval_count` × `interval`;
5. aprova por `SubscriptionLifecycleService::approve()` (`:106`), que recusa status que não aceita aprovação (cancelada, recusada) e grava as datas, `paid_via` e `payment_transaction_id`;
6. desbloqueia `officeimpresso_bloqueado` (`:128-131`).

Até 2026-11-16 a escrita era direta no Model, sem o `SubscriptionLifecycleService` e sem conferir o valor; o lote L2 (#11048) mudou as duas coisas. O `OnCobrancaPagaCreateFinanceiroTitulo` (Financeiro) também escuta o evento.

**F5 — Habilitação do módulo.**

Com a assinatura `approved` e vigente, `hasThePermissionInSubscription($biz, 'X_module')` devolve `package_details['X_module']` (`ModuleUtil.php:176-205`). Essa é a Camada 1 de `memory/reference/feedback-habilitar-modulo-por-business.md`.

Editar o pacote com `update_subscriptions` regrava o `package_details` das assinaturas com `end_date >= hoje`, preservando a chave fora do catálogo (`PackagesController.php:404-430`, UC-PAC-08).

**F6 — Renovação (buraco B2).**

Não há processo que renove assinatura `approved`:
- o cron do F3 só olha `waiting`;
- o listener do F4 ignora `CobrancaPaga` de assinatura `approved` (`:71`).

Se o mandato PIX do F3' gerar cobranças mensais, a 2ª cobrança paga **não estende** a vigência pelo caminho do listener, salvo se outro código criar uma nova assinatura `waiting` antes. Esse outro código não foi achado no módulo; não medido no PaymentGateway.

**F7 — Inadimplência.**

`OnCobrancaVencidaBloqueaSubscription` põe `status = declined` (`:69-70`) e `officeimpresso_bloqueado = true` (`:73-75`).

O flag é lido só pelo desktop (Connector/Officeimpresso). No ERP web o "bloqueio" é implícito: sem assinatura `approved` vigente, `isSubscribed` é false.

`CobrancaVencida` hoje só é disparado pelo caminho CNAB do PaymentGateway, e a assinatura é cobrada por PIX (achado de fronteira da auditoria, não remedido aqui).

**F8 — Expiração (buraco B3).**

Nenhum processo automático grava `expired`:
- o único escritor é `SubscriptionLifecycleService::expire` (`:104`), chamado pela ação "vencer" da tela Assinaturas;
- `findOverdueApproved()` (`:193`) não tem chamador fora dos testes, embora a migration de 2026-08-19 o chame de "o sweep de cron" (`2026_08_19_000001_widen_subscriptions_status_enum.php:22`).

Na prática a expiração é **implícita**: o `end_date` passa e `active_subscription` deixa de devolver a assinatura. O aviso antes do fim é o `pos:sendSubscriptionExpiryAlert`, com antecedência em `system.package_expiry_alert_days`.

### 5.4 Onde os mundos ainda não se conversam

- **Dois escritores de status:** a tela e, desde o lote L2 (#11048), o `OnCobrancaPagaUpdateSubscription` passam pelo `SubscriptionLifecycleService`; o `OnCobrancaVencidaBloqueaSubscription` ainda escreve `declined` direto no Model (`:69`). O [RUNBOOK-assinaturas](RUNBOOK-assinaturas.md) diz que "toda escrita passa pelo Service": vale para a tela, não para o sistema.
- **Dois bloqueios:** o do desktop (`officeimpresso_bloqueado`) e o do web (falta de `approved` vigente). Os dois mudam por caminhos diferentes.
- **Connector cria uma segunda assinatura:** a API de cadastro (`Modules/Connector/Http/Controllers/Api/BusinessController.php:280-300`) cria assinatura `approved` depois do `Business::create`. Nesse ponto o Observer do F1 já criou a `waiting`, e o negócio fica com as duas. Lido do código; não medido.
- **Rota citada em comentário que não existe:** o docblock do Observer (`:20`, `:101`) manda reconciliar em `/superadmin/business/{id}/subscriptions`, ausente de `Routes/web.php`. Hoje o caminho é o drawer Adicionar assinatura.

## 6. Casos de uso ⚙️+🖐 — agente propõe, **[W] confere**

Estado de cada CU pelo veredito de teste numa lane de PR. Em 2026-11-16, `test-lane-coverage --json` listava os testes do fluxo (`BusinessAutoSubscriptionObserverTest`, `OnCobrancaPagaUpdateSubscriptionTest`, `OnCobrancaVencidaBloqueaSubscriptionTest`, `AssinaturaAtivaDeterministaTest`, `PackageManagerServiceTest`) **fora de qualquer lane**. Depois do lote L1 (2026-11-17), só o `BusinessAutoSubscriptionObserverTest` segue fora. Os CU abaixo continuam `⬜` até alguém ler o veredito desses testes na lane e marcar cada um. O CU-SUPER-06 tem teste em lane (ver nota nele).

#### CU-SUPER-01 — Empresa nova ganha assinatura de trial `[must]` ⬜
*Dado* `default_saas_package_id` apontando para pacote ativo; *quando* um Business é criado; *então* nasce 1 assinatura `waiting` com `trial_end_date` = hoje + `trial_days`.
1. `[must]` biz=1, `env=demo`, empresa que já tem assinatura e pacote inativo não geram assinatura
2. `[T0]` a assinatura pertence ao business criado, não ao do usuário logado

#### CU-SUPER-02 — Trial vencido gera uma cobrança por mês `[must]` ⬜
1. `[must]` só `waiting` com trial vencido e `paid_via = paymentgateway_pix_automatico`
2. `[V0]` segunda execução no mesmo mês não emite outra cobrança (idempotência por origem+mês)
3. `[T0]` a cobrança é do biz=1 e aponta a assinatura do tenant por `origem_id`

#### CU-SUPER-03 — Cobrança paga aprova e calcula vigência `[must]` ⬜
1. `[must]` início = maior `end_date` aprovado + 1, ou hoje; fim pelo `interval` do pacote
2. `[must]` desbloqueia `officeimpresso_bloqueado`
3. `[must]` `CobrancaPaga` de outra origem não toca assinatura

#### CU-SUPER-04 — Assinatura `approved` vigente libera os módulos do pacote `[must]` ⬜
1. `[must]` `hasThePermissionInSubscription` devolve o valor de `package_details[X_module]`
2. `[must]` com mais de uma assinatura vigente, vale a de maior `start_date`, desempate por `id` (`AssinaturaAtivaDeterministaTest`)

#### CU-SUPER-05 — Cobrança vencida bloqueia `[must]` ⬜
1. `[must]` `status = declined` e `officeimpresso_bloqueado = true`

#### CU-SUPER-06 — Operador aprova, vence e cancela pela tela `[must]` ⬜
Coberto por `SuperadminAssinaturasContratoTest` (UC-SAASS-11, 12, 13, 15, 16, 17), que **está** numa lane de PR (não aparece entre os órfãos do `test-lane-coverage`). O veredito do run não foi lido nesta sessão; vira ✅ quando for. Cancelar preserva vigência e motivo.

#### CU-SUPER-07 — Trial com acesso aos módulos `[should]` — **B1, decisão [W]**
Não implementado (F2). Este CU fica sem passos até o dono decidir se o trial libera módulo e como.

#### CU-SUPER-08 — Renovação de assinatura `approved` `[should]` — **B2, decisão [W]**
Não implementado (F6).

#### CU-SUPER-09 — Expiração automática `[should]` — **B3, decisão [W]**
Não implementado (F8).

### 6.4 Non-goals explícitos — 🖐 **só [W] preenche**

_vazio_

## 7. Requisitos não-funcionais ⚙️ derivado

- O cron só roda em `live` (`SuperadminServiceProvider.php:119`). Staging e local nunca emitem cobrança por agendamento.
- Idempotência da emissão por origem+mês (F3) e por assinatura (F3').
- As notificações do módulo vão para a fila `superadmin-mail`, criptografadas (lote L4, 2026-11-16).

## 8. Estratégia de qualidade e rollout ⚙️ derivado

- O dono de "este teste roda?" é `node scripts/governance/test-lane-coverage.mjs --json`. Em 2026-11-16 o Superadmin tinha 28 arquivos de teste e 15 fora de lane, incluindo todos os do fluxo F1-F5, F7. Depois do lote L1, em 2026-11-17, são 32 e 5 fora de lane; do fluxo, só o `BusinessAutoSubscriptionObserverTest` (F1).
- Pest só no CT 100 (`memory/proibicoes.md` §Ambiente).
- As pendências humanas da ADR 0170 (credencial BCB, homologação, smoke em biz=1, smoke de inadimplência) não têm registro de conclusão em 2026-11-16.

## 9. Riscos e dívidas 🖐 curado

| # | Pendência | Estado atual (código) | Quem decide |
|---|---|---|---|
| **B1** | Trial sem módulos | `waiting` não conta como ativa (`Subscription.php:111-125`). Não medido em runtime | [W] |
| **B2** | Renovação | `CobrancaPaga` de assinatura `approved` é ignorada (`OnCobrancaPagaUpdateSubscription.php:71`); nenhum cron emite a 2ª cobrança | [W] |
| **B3** | Expiração | nenhum processo automático grava `expired`; `findOverdueApproved()` sem chamador | [W] |
| — | Dois escritores de status | `OnCobrancaVencidaBloqueaSubscription` fora do `SubscriptionLifecycleService` (o de pagamento entrou no #11048) | engenharia, depois de B2/B3 |
| — | Segunda assinatura via Connector | `approved` + `waiting` no mesmo negócio | não medido |
| — | Fluxo sem prova em prod | 0 cobranças `subscription_license` em 2026-09-09 | [W] (smoke ADR 0170) |

## 10. Roadmap 🖐 [W] prioriza

Lote L2 da auditoria (Listeners, Observers, LifecycleService). PR 1 mergeado em 2026-11-17 (#11048). O PR 2 ainda não foi aberto; o PR 3 espera B2/B3.

## 11. Referências

- [SPEC](SPEC.md) US-SUPER-001/002/003 · [BRIEFING](BRIEFING.md) · [SCOPE](SCOPE.md)
- RUNBOOKs: [assinaturas](RUNBOOK-assinaturas.md) · [minha-assinatura](RUNBOOK-minha-assinatura.md) · [pagar](RUNBOOK-pagar.md) · [pacotes](RUNBOOK-pacotes.md) · [negocios](RUNBOOK-negocios.md)
- [ADR 0170 Onda 5](../../decisions/0170-onda5-simplificada.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- `memory/reference/feedback-habilitar-modulo-por-business.md` (Camada 1)

## Changelog (append-only — correção não apaga, vira linha)

- 1.0.0 (2026-11-16) — criado no lote L6 da auditoria do Superadmin, lido de `origin/main` 580be57.
