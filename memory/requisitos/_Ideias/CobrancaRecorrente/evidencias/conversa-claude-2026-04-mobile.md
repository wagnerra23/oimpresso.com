---
type: evidencia
origin_url: https://claude.ai/chat/dda41749-c416-4e78-9a3a-e5255d7282c0
origin_title: "Estado da arte em módulos de cobrança recorrente"
extracted_at: 2026-04-24
extraction_method: claude.ai data export (conversations.json)
created_at: 2026-04-24T01:52:40
updated_at: 2026-04-24T02:04:50
message_count: 6
---

# Estado da arte em módulos de cobrança recorrente

**URL:** https://claude.ai/chat/dda41749-c416-4e78-9a3a-e5255d7282c0


## 1. Wagner — 2026-04-24 01:52

Módulo de cobrança automática de mensalidades/contratos. O que tem de mais moderno estado da arte para controlar recorrência


## 2. Claude — 2026-04-24 01:53

 Vou buscar informações atualizadas sobre o estado da arte em módulos de cobrança recorrente.
```
This block is not supported on your current device yet.
```


```
This block is not supported on your current device yet.
```


```
This block is not supported on your current device yet.
```


```
This block is not supported on your current device yet.
```

Tenho material suficiente. Vou estruturar uma resposta organizada sobre o estado da arte em módulos de cobrança recorrente, com foco técnico e adequado ao contexto brasileiro.

# Estado da arte em cobrança recorrente de mensalidades/contratos

## 1. Arquitetura do módulo — padrões modernos

O que diferencia um módulo moderno de um "CRUD de boletos" é a separação clara entre quatro camadas, com arquitetura **event-driven**:

- **Catálogo de produtos/planos** (price book): planos, add-ons, descontos, trials, ciclos (mensal/anual/customizado), moeda, impostos.
- **Ciclo de vida da assinatura** (subscription lifecycle): trialing → active → past_due → unpaid → canceled, com eventos disparando webhooks para o resto do sistema.
- **Motor de faturamento** (billing engine): gera faturas, calcula proração para upgrades/downgrades mid-cycle, aplica créditos, cupons e impostos.
- **Motor de cobrança e recuperação** (collection + dunning): tenta cobrança no gateway, aplica retries inteligentes, dispara régua de comunicação.

Plataformas modernas como Lago, Orb, Metronome e Flexprice usam arquitetura event-driven com message queues reagindo a triggers como "Subscription Created" em tempo real, o que desacopla a geração de fatura, a tentativa de cobrança, o envio de notificações e o provisionamento/bloqueio de acesso.

## 2. Recorrência — o ponto central

Boas práticas:

- **Anchor date vs. calendar billing**: decida se cobra no dia da assinatura (anniversary) ou em data fixa do mês; isso muda toda a lógica de proração.
- **Proração automática** em upgrade/downgrade, pausas e mudanças de quantidade (ex.: seats).
- **Idempotência**: toda geração de fatura e tentativa de cobrança precisa de chave de idempotência — é o que evita cobrança duplicada em retries de webhook ou jobs.
- **Scheduler resiliente**: cron distribuído (ex.: Temporal, Sidekiq, BullMQ) ao invés de cron simples, para sobreviver a falhas de infra sem pular ciclos.
- **Clock de billing independente do clock do servidor** — facilita testes com "avanço de tempo" em sandbox.

## 3. Meios de pagamento (Brasil 2026) — o grande salto é o Pix Automático

Esse é o ponto mais atual e o que você deve priorizar no Brasil:

- **Pix Automático** (Banco Central): permite programar pagamentos recorrentes com autorização única do cliente, sem necessidade de autenticação a cada transação. Substitui débito automático tradicional em cobranças interbancárias.
- Uma transação via Pix pode ser, em média, até 14 vezes mais econômica que o processamento por cartão de crédito, e reduz falhas de pagamento causadas por limite insuficiente ou dados de cartão expirados.
- Em caso de falha, o sistema pode realizar até três novas tentativas de débito em dias diferentes, dentro de um prazo de sete dias corridos.
- O cliente pode definir um limite máximo por cobrança, criando uma camada extra de proteção — seu módulo precisa lidar com esse limite.

**Stack recomendada no Brasil**: Pix Automático como padrão + cartão de crédito recorrente como fallback + boleto híbrido com QR Code Pix para quem não autoriza recorrência. Integre via gateways que já expõem tudo em uma API (Asaas, Iugu, Pagar.me, PagBrasil, QI Tech, Stripe para global).

## 4. Dunning inteligente (recuperação de inadimplência)

Esse é o maior diferencial de plataformas modernas. Retry "burro" (tentar todo dia por 5 dias) é obsoleto.

- **Smart Retries com ML**: o modelo do Stripe prediz o tempo ótimo para retentar um pagamento usando algoritmos de ML treinados em bilhões de pontos de dados e usa atributos como histórico de transações bem-sucedidas e falhas do cartão, código de recusa em tempo real, sazonalidade (hora do dia, dia da semana), geografia e moeda.
- **Diferenciar soft decline de hard decline**: um soft decline ("Do Not Honor") pode ser retentado mais cedo, enquanto um hard decline ("Invalid Card") não deve ser retentado sem um novo método de pagamento.
- **Parar após 3-4 tentativas**: depois de 3-4 tentativas falhas em duas semanas, a probabilidade de sucesso cai drasticamente e continuar gera custos de transação sem retorno.
- **Account Updater**: serviços dos bandeiras (Visa/Mastercard) que atualizam automaticamente cartões com validade expirada ou reemitidos — plugue no módulo.
- **Régua de comunicação multicanal**: e-mail + SMS + WhatsApp + push + in-app, com tom empático, link direto para portal de autoatendimento. Bom dunning recupera 20% a 40% de pagamentos falhos.
- **Portal de self-service**: o cliente atualiza cartão/Pix sozinho. Reduz tickets de suporte drasticamente.

## 5. Entitlements em tempo real

Separe o "cliente pagou" do "cliente tem acesso". Um **entitlement engine** expõe via API "esse usuário tem direito à feature X agora?" e reage instantaneamente a eventos de assinatura (past_due → degrada acesso; paid → libera). Event-driven, não batch diário.

## 6. Contratos e faturamento B2B

Para cobrança de contratos (não só SaaS self-service):

- **CPQ → contrato → billing schedule**: contratos com cronograma customizado (ex.: setup fee + 12 parcelas + renovação com reajuste IPCA).
- **Reajuste automático** por índice (IPCA, IGP-M) em data de aniversário, com notificação prévia legal.
- **Emissão automática de NFS-e** integrada à fatura (no Brasil isso é quase obrigatório).
- **Split de pagamentos** se você opera marketplace.
- **Revenue recognition** (ASC 606 / IFRS 15) — reconhecimento de receita diferente de caixa recebido.

## 7. Atenção à Reforma Tributária 2026 (Brasil)

Impacto direto no módulo: split-payment altera fluxo de caixa — tributos CBS e IBS agora são retidos na fonte no momento do recebimento, e o crédito fiscal só pode ser utilizado após 60 dias. Seu módulo precisa conciliar valor bruto cobrado vs. líquido recebido vs. crédito fiscal a compensar.

## 8. Stack de referência

Se for **construir**: PostgreSQL + outbox pattern + Temporal/Inngest para workflows + Redis para locks de idempotência + webhooks assíncronos. Estude os open-source **Lago** e **Kill Bill** como blueprint.

Se for **integrar**: Stripe Billing (global), Asaas/Iugu/Vindi (Brasil, cobrem Pix Automático nativo), Chargebee/Recurly (camada de subscription management sobre gateway).

---

**Resumindo o "state of the art" em uma frase**: arquitetura event-driven, Pix Automático como padrão no Brasil, smart retries com ML, entitlements em tempo real, portal de self-service e régua multicanal — tudo desacoplado por eventos para que cada camada (faturamento, cobrança, acesso, comunicação) evolua independente.

Quer que eu aprofunde alguma parte — modelo de dados, workflow de dunning, integração Pix Automático ou comparativo de gateways brasileiros?


## 3. Wagner — 2026-04-24 01:58

Módulo do ultmatepos 6.7, mysql. Quantos módulos isso geraria ? Boletos/nfse/banco/. Como estruturar ?


## 4. Claude — 2026-04-24 01:59

 Ótima pergunta. Vou contextualizar primeiro: o UltimatePOS é um ERP PHP (Laravel) + MySQL modular, então o padrão recomendado é seguir a convenção dele — **cada capability vira um Module** separado em `/Modules`, com suas próprias migrations, controllers, views, rotas e service providers. Isso mantém tudo plugável e atualizável sem mexer no core.

## Quantos módulos? Recomendo 6 módulos

Não faça um módulo gigante de "financeiro". Separe por **responsabilidade de domínio** — cada um com contrato claro, comunicando por eventos/services:

**1. `SubscriptionBilling`** (núcleo da recorrência)
Contratos, planos, ciclo de vida da assinatura, geração de faturas recorrentes, proração, reajuste por índice, trial, pausa/cancelamento. É o "cérebro" — os outros são executores.

**2. `PaymentGateway`** (abstração de meios de pagamento)
Camada de adapters: Asaas, Iugu, Pagar.me, Stripe, etc. Interface única (`ChargeInterface`) com implementações por gateway. Todo o resto do sistema fala com essa interface — nunca direto com SDK do gateway.

**3. `Boleto`** (pode estar dentro do PaymentGateway se quiser enxugar)
Emissão, registro CNAB 240/400, baixa automática por retorno bancário, boleto híbrido com QR Code Pix. Se usar só via gateway (Asaas/Iugu já entregam boleto), não precisa desse módulo — vira adapter.

**4. `PixAutomatico`** (merece módulo próprio em 2026)
Gestão de autorizações (JRC — Jornada de Recorrência de Consentimento), QR Code de autorização, webhooks de consentimento aprovado/revogado, limites definidos pelo pagador, tentativas automáticas (3x em 7 dias). Tem regras específicas do BCB que não cabem no gateway genérico.

**5. `NFSe`** (emissão fiscal)
Integração com prefeituras (cada cidade tem seu padrão — ABRASF, Ginfes, etc.) ou provedor agregador (NFE.io, PlugNotas, Focus NFe). Fila de emissão, retry, cancelamento, carta de correção, armazenamento de XML/PDF. **Separe do billing** — emissão fiscal falha muito e não pode travar cobrança.

**6. `Dunning`** (recuperação de inadimplência)
Régua de cobrança, smart retries, comunicação multicanal (e-mail/WhatsApp/SMS), bloqueio/liberação de acesso, portal de autoatendimento para atualizar método de pagamento.

*Opcional 7:* **`BankReconciliation`** se você for integrar Open Finance / extrato bancário para conciliação automática de recebimentos que entram fora do gateway (TED, PIX manual, etc.).

## Como se comunicam

Tudo via **eventos Laravel** (o UltimatePOS já usa esse padrão internamente):

```
SubscriptionBilling dispara → InvoiceGenerated
  ├─ PaymentGateway escuta → tenta cobrança
  ├─ NFSe escuta → emite nota
  └─ Dunning escuta → agenda régua se falhar

PaymentGateway dispara → PaymentSucceeded / PaymentFailed
  ├─ SubscriptionBilling escuta → atualiza status da assinatura
  ├─ Dunning escuta → para régua ou escala
  └─ NFSe escuta → confirma emissão
```

## Estrutura de pastas (padrão UltimatePOS)

```
/Modules
  /SubscriptionBilling
    /Config, /Database/Migrations, /Entities, /Http/Controllers,
    /Resources/views, /Routes, /Providers, /Services, /Jobs, /Events
  /PaymentGateway
  /PixAutomatico
  /NFSe
  /Dunning
```

## Modelo de dados MySQL — tabelas mínimas

**SubscriptionBilling:**
- `billing_plans` (plano, ciclo, valor, moeda, índice de reajuste)
- `subscriptions` (contact_id, plan_id, status, anchor_date, trial_end, next_billing_at, canceled_at)
- `subscription_items` (quantidade, add-ons)
- `billing_invoices` (subscription_id, period_start, period_end, amount, status, due_date, idempotency_key)
- `billing_invoice_items`

**PaymentGateway:**
- `payment_methods` (contact_id, gateway, token, tipo, last4, expira)
- `payment_attempts` (invoice_id, gateway, status, response_code, attempt_number, retry_scheduled_at, idempotency_key)
- `payment_transactions` (attempt_id, gateway_transaction_id, paid_at, amount)

**PixAutomatico:**
- `pix_auto_authorizations` (contact_id, status, limite_max, data_autorizacao, revogado_em, jornada_id)
- `pix_auto_charges` (authorization_id, invoice_id, status, tentativa_num, e2e_id)

**NFSe:**
- `nfse_issuances` (invoice_id, status, numero, rps, xml_path, pdf_path, erro, tentativas)

**Dunning:**
- `dunning_campaigns` (invoice_id, regua_id, status)
- `dunning_steps` (campaign_id, tipo, canal, executado_em, resultado)

## Cuidados específicos do UltimatePOS

- **Multi-tenant (business_id)**: toda tabela precisa do `business_id` — o UltimatePOS é multi-empresa.
- **Integre com `transactions` e `contacts` existentes** em vez de criar cliente novo. A fatura recorrente gerada deve virar uma `transaction` do tipo `sell` para fluir para o financeiro nativo.
- **Use a tabela `transaction_payments`** existente para registrar o pagamento — assim o relatório financeiro do UltimatePOS continua batendo.
- **Permissões via Spatie**: o UltimatePOS usa `spatie/laravel-permission` — cada módulo registra suas permissions no boot do provider.
- **Superadmin package**: se quiser vender como add-on licenciado, integre com o Superadmin que controla ativação de módulos por tenant.

## Ordem sugerida de implementação

1. `PaymentGateway` (abstração — base de tudo)
2. `SubscriptionBilling` (gera faturas, mas paga manual no começo)
3. `Dunning` simples (só e-mail)
4. `NFSe`
5. `PixAutomatico` (quando o gateway escolhido já estiver homologado no BCB)
6. Smart retries + régua multicanal no Dunning

Quer que eu detalhe o schema SQL completo de algum desses módulos, ou o contrato da interface do `PaymentGateway` para encaixar múltiplos adapters?


## 5. Wagner — 2026-04-24 02:02

Pesquise mais aprofundado


## 6. Claude — 2026-04-24 02:04

 Vou pesquisar mais a fundo sobre a arquitetura do UltimatePOS, suas convenções de módulos e integrações específicas.
```
This block is not supported on your current device yet.
```


```
This block is not supported on your current device yet.
```


```
This block is not supported on your current device yet.
```


```
This block is not supported on your current device yet.
```


```
This block is not supported on your current device yet.
```


```
This block is not supported on your current device yet.
```


```
This block is not supported on your current device yet.
```

Tenho informação técnica suficiente agora. Vou consolidar em uma resposta aprofundada e prática.

# Módulo de cobrança recorrente para UltimatePOS 6.x — arquitetura aprofundada

## Contexto técnico confirmado

Antes de decidir quantos módulos: o UltimatePOS usa o sistema de módulos implementado desde V3.x (inicialmente beta), baseado no **nwidart/laravel-modules**. A instalação de um addon consiste em colocar a pasta do módulo dentro do diretório `Modules` (no mesmo nível de `app` ou `bootstrap`, com "M" maiúsculo) e rodar `http://seusite.com/{modulo}/install/update`. Quem é superadmin é definido por `ADMINISTRATOR_USERNAMES` no `.env`. Isso te dá o padrão exato a seguir.

A versão 6.x trouxe design novo, UI responsiva e compatibilidade com módulo de AI Assistance — importante porque se você fizer um módulo legado com Bootstrap antigo vai destoar visualmente.

## Resposta direta: 6 módulos é o número certo

Revisando com mais rigor sobre o ecossistema UltimatePOS + Brasil 2026, eu **mantenho 6 módulos** mas reorganizo os nomes e responsabilidades para ficar alinhado ao padrão dos addons oficiais (que usam nomes curtos e diretos: Essentials, Manufacturing, WooCommerce, Accounting, Superadmin):

### Módulo 1 — `RecurringBilling` (núcleo)
**Responsabilidade única**: ciclo de vida de contratos/assinaturas e geração de faturas.

Tabelas (prefixe tudo com `rb_` para não colidir com o core):
- `rb_plans` — planos, ciclo (daily/weekly/monthly/quarterly/yearly/custom), valor base, moeda, trial_days, setup_fee, indice_reajuste (IPCA/IGP-M/fixo), tax_id (reaproveita `taxes` do POS)
- `rb_plan_items` — produtos/serviços vinculados ao plano (FK para `products` do core — reusa cadastro existente)
- `rb_contracts` — business_id, contact_id, plan_id, status (trialing/active/paused/past_due/canceled), anchor_date, next_billing_date, trial_ends_at, current_cycle_start, current_cycle_end, canceled_at, metadata_json, location_id
- `rb_contract_items` — customização por contrato (quantidade, add-ons, discount override)
- `rb_invoices` — **aqui o pulo do gato**: vincula com `transactions` do core via `transaction_id` nullable (gera a venda no POS quando paga) + idempotency_key único
- `rb_invoice_items`
- `rb_proration_events` — audit trail de upgrades/downgrades

**Jobs agendados**: `GenerateRecurringInvoicesJob` roda via scheduler (`app/Console/Kernel.php` do UltimatePOS já tem cron configurado — o release V5 melhorou isso: "CRON command improved").

### Módulo 2 — `PaymentGateway` (abstração)
**Responsabilidade**: adapter pattern para múltiplos PSPs.

```php
Modules/PaymentGateway/Contracts/
  ├── ChargeInterface.php          // charge(), refund(), getStatus()
  ├── SubscriptionGatewayInterface.php  // para PSPs que gerenciam assinatura
  └── WebhookHandlerInterface.php

Modules/PaymentGateway/Gateways/
  ├── AsaasGateway.php
  ├── IuguGateway.php  
  ├── PagarmeGateway.php
  ├── StripeGateway.php
  └── MercadoPagoGateway.php
```

Tabelas:
- `pg_credentials` — business_id, gateway, api_key (encrypted), environment (sandbox/prod), webhook_secret
- `pg_payment_methods` — contact_id, gateway, gateway_customer_id, gateway_token, type (credit_card/pix_auto/boleto), card_last4, card_brand, card_exp
- `pg_charge_attempts` — invoice_id, gateway, gateway_charge_id, status, response_code, response_payload_json, attempt_number, next_retry_at, idempotency_key (unique)
- `pg_webhook_events` — gateway, event_id (unique), event_type, payload_json, processed_at, error

**Observação crítica sobre idempotência**: Os webhooks do Asaas garantem entrega "at least once" — seu endpoint pode receber o mesmo evento mais de uma vez. Você deve ignorar eventos duplicados usando idempotência, registrando os eventos já processados pelo ID próprio do evento. Isso **precisa** estar na tabela `pg_webhook_events` com unique constraint em `event_id`.

### Módulo 3 — `PixAutomatico` (merece módulo próprio)
Por quê separar do PaymentGateway? Porque o fluxo de autorização (JRC) tem estados próprios que não existem em cartão/boleto. Veja o ciclo real do Asaas: PIX_AUTOMATIC_RECURRING_AUTHORIZATION_CREATED → PAYMENT_CREATED → PAYMENT_RECEIVED → PIX_AUTOMATIC_RECURRING_AUTHORIZATION_ACTIVATED, com possibilidades de EXPIRED, REFUSED, CANCELLED pelo pagador, e depois para cada cobrança subsequente: PAYMENT_CREATED → PIX_AUTOMATIC_RECURRING_PAYMENT_INSTRUCTION_CREATED → PIX_AUTOMATIC_RECURRING_PAYMENT_INSTRUCTION_SCHEDULED → PAYMENT_CONFIRMED (ou REFUSED por falta de saldo).

Tabelas:
- `pa_authorizations` — contact_id, contract_id, status (created/activated/refused/expired/cancelled), limite_max, finish_date, jornada_type, gateway_authorization_id, qr_code_data
- `pa_payment_instructions` — authorization_id, invoice_id, status, scheduled_date, e2e_id, failure_reason
- `pa_authorization_events` — log completo para auditoria e debug

**Jornada recomendada**: a Woovi/OpenPix documenta 4 jornadas diferentes e diz que a jornada 3 (PAYMENTONAPPROVAL) é a mais simples de implementar e compreender, porque ao ler o QR Code o usuário já efetua o pagamento e autoriza a cobrança, entrando a assinatura em vigor imediatamente.

### Módulo 4 — `Boleto` (apenas se emitir direto, sem gateway)
Se você usa Asaas/Iugu, **não crie esse módulo** — vira adapter dentro do PaymentGateway. Crie só se for falar com banco direto via CNAB/API OpenBanking.

Use a lib `eduardokum/laravel-boleto` que já tem suporte CNAB 240/400 para todos os grandes bancos (BB, Bradesco, Caixa, Santander, Itaú, Bancoob/Sicoob, Banrisul).

Tabelas:
- `bol_bank_accounts` — business_id, banco_codigo, agencia, conta, carteira, convenio, certificado_path
- `bol_boletos` — invoice_id, nosso_numero, linha_digitavel, codigo_barras, pdf_path, qr_code_pix (boleto híbrido), status, remessa_id, retorno_id
- `bol_remessa_files` — arquivo CNAB enviado ao banco
- `bol_retorno_files` — arquivo CNAB recebido + processamento linha a linha

### Módulo 5 — `NFSe`
Por que separado de billing: A emissão de NFe e NFSe é processada de forma assíncrona pelas prefeituras, pode demorar minutos ou falhar. Não pode travar a cobrança.

Ponto **crítico para 2026**: A NFS-e Nacional será obrigatória para todos os prestadores de serviços a partir de 1º de janeiro de 2026, conforme a Lei Complementar nº 214/2025. Seu módulo precisa suportar tanto o padrão municipal antigo quanto o Ambiente Nacional.

Adapter pattern igual ao PaymentGateway:

```php
Modules/NFSe/Providers/
  ├── FocusNFeProvider.php
  ├── PlugNotasProvider.php
  ├── NFEioProvider.php
  └── NotaasProvider.php
```

Tabelas:
- `nfse_company_config` — business_id, location_id, provider, certificado_a1_path, senha_cert (encrypted), regime_tributario, cnae_principal, servico_padrao_id
- `nfse_services` — código de serviço municipal/LC116, alíquota ISS, retenções padrão
- `nfse_issuances` — invoice_id, provider, ref_provider, status (pending/processing/authorized/rejected/cancelled), numero_nfse, serie, codigo_verificacao, xml_path, pdf_path, error_message, attempts, issued_at
- `nfse_webhook_events` — idempotência de callbacks do provider

**Recomendação de provider**: Focus NFe ou PlugNotas cobrem praticamente todas as prefeituras + NFSe Nacional. O pacote `rafwell/laravel-focusnfe` já existe pronto como referência.

### Módulo 6 — `Dunning`
Régua de cobrança desacoplada. Recebe eventos de `PaymentFailed` e conduz a recuperação.

Tabelas:
- `dun_rules` — business_id, name, trigger_event, ativo
- `dun_steps` — rule_id, ordem, delay_days_after_due, action (email/sms/whatsapp/block_access/cancel/retry_charge), template_id, channel_config_json
- `dun_campaigns` — invoice_id, rule_id, status (running/recovered/failed/cancelled), started_at, ended_at
- `dun_campaign_steps` — campaign_id, step_id, executed_at, result, response_data

## Comunicação entre módulos — obrigatoriamente por eventos

Esse é o ponto que separa amador de profissional. **Nunca** um módulo chama método de outro direto. Sempre por eventos Laravel:

```php
// RecurringBilling dispara
event(new \Modules\RecurringBilling\Events\InvoiceGenerated($invoice));

// PaymentGateway escuta e tenta cobrar
// NFSe escuta e emite nota
// Dunning fica dormente até PaymentFailed

// PaymentGateway dispara (via webhook)
event(new \Modules\PaymentGateway\Events\PaymentSucceeded($attempt));
event(new \Modules\PaymentGateway\Events\PaymentFailed($attempt, $reason));

// Quando PaymentSucceeded:
// - RecurringBilling marca fatura como paga, cria próximo ciclo
// - RecurringBilling cria a transaction no core do POS
// - NFSe confirma emissão
// - Dunning encerra campanha se tiver uma rodando

// Quando PaymentFailed:
// - Dunning inicia/avança régua
// - RecurringBilling muda status para past_due
// - Se hard decline: PaymentGateway marca método como inválido e pede novo
```

## Integração crítica com o core do UltimatePOS

Esse é o ponto mais subestimado. Se você não fizer certo, o financeiro do POS fica desalinhado:

**1. Ao confirmar pagamento de fatura recorrente:**
- Crie uma `transactions` com `type='sell'`, `status='final'`, `payment_status='paid'`
- Crie as `transaction_sell_lines` correspondentes aos itens do plano
- Crie `transaction_payments` com o método usado (adicione métodos customizados "Pix Automático", "Cartão Recorrente" via Business Settings → Payment Methods — o próprio UltimatePOS já aceita adicionar e editar Payment Method ou Pay Via options (custom payments))
- Vincule `transaction.id` de volta em `rb_invoices.transaction_id`

**2. Se o módulo Accounting estiver instalado:** configure o map de transactions em Accounting Module → Settings para que sell, sell payment, purchase, purchase payment sejam automaticamente mapeados ao plano de contas. Sua fatura recorrente entra automaticamente na contabilidade.

**3. Se o módulo SaaS-Superadmin estiver presente:** você tem uma opção interessante — pode até **reutilizar as tabelas de package subscriptions** dele como base para planos internos, mas eu recomendo **não fazer isso** porque o Superadmin é para cobrar os tenants do seu SaaS, não os clientes finais de cada tenant. São dois níveis diferentes.

**4. Multi-tenant (business_id)**: toda tabela dos 6 módulos precisa de `business_id` indexado. Todo scope de Eloquent precisa aplicar `->where('business_id', request()->session()->get('user.business_id'))` via global scope ou trait.

**5. Permissões**: o UltimatePOS usa `spatie/laravel-permission`. No boot do ServiceProvider de cada módulo, registre permissões:

```php
Permission::firstOrCreate(['name' => 'recurring_billing.access']);
Permission::firstOrCreate(['name' => 'recurring_billing.contract.create']);
Permission::firstOrCreate(['name' => 'recurring_billing.contract.cancel']);
// ... e adicione ao role Admin automaticamente
```

**6. Menu lateral**: hook no evento do UltimatePOS que monta o sidebar — cada módulo injeta seus itens de menu via ServiceProvider (exemplo está no código do módulo Essentials).

## Estrutura de pastas final

```
/Modules
  /RecurringBilling
    /Config/config.php
    /Console                    ← comandos artisan (billing:run, billing:preview)
    /Database
      /Migrations              ← 7 migrations
      /Seeders
    /Entities                  ← Plan, Contract, Invoice models
    /Events                    ← InvoiceGenerated, ContractCanceled, TrialEnded
    /Http
      /Controllers
      /Requests
    /Jobs                      ← GenerateRecurringInvoicesJob, ApplyAdjustmentJob
    /Listeners
    /Providers                 ← RecurringBillingServiceProvider, RouteServiceProvider, EventServiceProvider
    /Resources/views
    /Routes/web.php, api.php
    /Services                  ← BillingEngine, ProrationCalculator, CycleAdvancer
    module.json                ← {name, alias, description, version, keywords, providers}
  /PaymentGateway
  /PixAutomatico
  /NFSe
  /Dunning
  /Boleto (opcional)
```

## Ordem de implementação (pragmática)

1. **PaymentGateway** com 1 adapter só (Asaas recomendado — cobre cartão/boleto/Pix/Pix Automático numa API só) — 2 semanas
2. **RecurringBilling** com geração de faturas + cobrança manual via botão — 3 semanas
3. **NFSe** integrado com Focus NFe ou PlugNotas — 2 semanas
4. **Dunning** com régua simples (e-mail + bloqueio) — 1 semana
5. **PixAutomatico** como adapter especializado dentro do PaymentGateway — 2 semanas (depende de homologação do PSP)
6. **Boleto** CNAB direto só se tiver cliente que exija relacionamento direto com banco — 3 semanas
7. Segundo adapter do PaymentGateway (ex.: Iugu ou Pagar.me) — 1 semana (já tem a interface pronta)

**Total realista: 12–14 semanas de dev sênior** para o pacote completo funcional e testado.

## Decisões que você ainda precisa tomar

1. **Merchant-of-record vs gateway direto**: se for vender como SaaS, considere ser MoR (você emite a NFSe da mensalidade) vs. gateway só (cliente emite a NFSe dele). Muda completamente o módulo NFSe.
2. **Se abre contratos direto no POS ou se tem um portal B2C separado** para o cliente final autoatender (atualizar cartão, ver faturas, 2ª via). O portal self-service reduz drasticamente suporte — vale fazer.
3. **Split de pagamento**: se você é marketplace, precisa de um 7º módulo `PaymentSplit`.

Quer que eu detalhe o código da interface `ChargeInterface` com um adapter Asaas completo, ou o schema SQL com índices e FKs de um módulo específico, ou o fluxo de estados da assinatura com todas as transições possíveis?

