---
slug: 0456-paymentgateway-dono-dos-drivers-de-cobranca
number: 456
title: "Modules/PaymentGateway é o dono dos drivers, credenciais e webhooks de cobrança; o RecurringBilling consome"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-23"
module: recurringbilling
quarter: 2026-Q4
tags: [paymentgateway, recurringbilling, drivers, credenciais, webhooks, fronteira-de-modulo]
supersedes: []
supersedes_docs: # ADR de módulo (fora do padrão NNNN-slug do schema), mesmo tratamento da 0172
  - memory/requisitos/RecurringBilling/adr/arq/0005-escopo0-payment-gateway-adapter.md
supersedes_partially: []
superseded_by: []
related:
  - 0170-paymentgateway-extracao-camada-cobranca
  - 0170-onda5-simplificada
  - 0170-bancos-nativos-top5-drivers-separados
  - 0455-recurringbilling-pix-automatico-jornada-2
  - 0093-multi-tenant-isolation-tier-0
pii: false
review_triggers:
  - "a pilha de boleto do RecurringBilling (Services/Boleto/Drivers + rb_boleto_credentials) terminar de migrar para o PaymentGateway"
  - "um módulo além do RecurringBilling e do Financeiro passar a emitir cobrança"
---

# ADR 0456 — PaymentGateway é o dono dos drivers de cobrança

## Contexto

### A ARQ-0005 do módulo

A ARQ-0005 do RecurringBilling ([`adr/arq/0005-escopo0-payment-gateway-adapter.md`](../requisitos/RecurringBilling/adr/arq/0005-escopo0-payment-gateway-adapter.md), 2026-05-06, `accepted`) decidiu que, no MVP, os drivers ficariam em `Modules/RecurringBilling/Services/Boleto/Drivers/`, com credenciais em `rb_boleto_credentials`, e que não haveria `Modules/PaymentGateway` separado nem tabela própria de credencial. O critério de extração era "≥2 tenants com gateways diferentes OU cobrança avulsa fora de assinatura".

O critério foi atingido e o módulo foi criado. No `main` de hoje:

- `Modules/PaymentGateway/Services/Drivers/` tem 6 drivers: Asaas, BcbPix, C6, Inter, Pagarme e SicoobApi (`ls Modules/PaymentGateway/Services/Drivers/`).
- As credenciais ficam em `payment_gateway_credentials` (migration `2026_05_19_120000_create_payment_gateway_credentials_table.php`), com o comando `MigrateCredentialsCommand` para trazer as do RB.
- O Pix Automático e o cartão salvo do RB já usam o PaymentGateway: `MandatoPixAutomatico` lê `PaymentGatewayCredential` e chama o `BcbPixDriver` ([ADR 0455](0455-recurringbilling-pix-automatico-jornada-2.md)).

Ao mesmo tempo, o RB mantém **uma segunda pilha** para boleto: `Services/Boleto/Drivers/{Asaas,C6,Inter}Driver.php` e `rb_boleto_credentials`, usada por `BoletoService`, `AssinaturaCobrancaService` e pelos jobs `CancelarCobrancaAsaasJob` e `RefundCobrancaAsaasJob`. São duas implementações para os mesmos bancos e duas tabelas de credencial, e nenhuma ADR ativa dizia qual era a dona (auditoria do módulo de 2026-11-19, eixo 3 §5, gravidade ALTA).

```bash
ls Modules/PaymentGateway/Services/Drivers/ Modules/RecurringBilling/Services/Boleto/Drivers/
git grep -ln "Boleto\\\\Drivers\|BoletoService" -- Modules/RecurringBilling/Services Modules/RecurringBilling/Jobs
```

### O caso da ADR 0170

A extração do PaymentGateway foi desenhada na [ADR 0170 — extração da camada técnica de cobrança](0170-paymentgateway-extracao-camada-cobranca.md) (2026-05-19). Três fatos sobre ela explicam por que esta ADR é necessária:

1. **Ela está arquivada.** Em 2026-07-10 o [#4071](https://github.com/wagnerra23/oimpresso.com/pull/4071) a marcou `status: deprecated`, `lifecycle: arquivado`, num lote de "propostas que não vão adiante". O corpo dela ainda diz "🟡 Proposto (F0 brief)". Só que a extração **foi adiante**: o módulo existe, tem drivers, credenciais, webhooks e lanes de teste. O arquivamento descreve o estado da proposta, não o do sistema.
2. **Ela segue citada como a fronteira.** O SDD do RB (§5.5) e os docs do PaymentGateway (`SPEC.md`, `README.md`, `CONTRACTS.md`, `CAPTERRA-FICHA.md`) apontam para ela. Ou seja: a fronteira em uso se apoia numa ADR arquivada.
3. **O número 0170 tem três arquivos.** Além dela existem a [0170 — Onda 5 simplificada](0170-onda5-simplificada.md) (2026-05-19, `aceito`) e a [0170 — bancos nativos, drivers separados](0170-bancos-nativos-top5-drivers-separados.md) (2026-05-25, `aceito`). As duas são **emendas** (`amends: [0170-paymentgateway-extracao-camada-cobranca]`) e continuam ativas, emendando uma ADR arquivada. A colisão está registrada em `memory/decisions/_INDEX-LIFECYCLE.md` (`numbering_collisions` e Bloco 11) e é protegida pelo `AdrNumberCollisionTest`; renumerar é barrado pelo append-only, então ela fica como está.

```bash
ls memory/decisions/0170*
git log --format="%h %ad %s" --date=short -- memory/decisions/0170-paymentgateway-extracao-camada-cobranca.md
```

Esta ADR não desarquiva a 0170 nem edita as três. Ela registra, numa ADR ativa e com número próprio, a fronteira que o sistema já pratica.

## Decisão

1. **`Modules/PaymentGateway` é o dono** dos drivers bancários, das credenciais de gateway e da recepção dos webhooks de cobrança. Driver novo, banco novo ou credencial nova nascem lá.
2. **O RecurringBilling consome.** Ele decide *o que* cobrar (assinatura, fatura, valor, vencimento, régua) e pede ao PaymentGateway *como* cobrar. Ele não cria driver nem tabela de credencial.
3. **A pilha de boleto do RB é legado a migrar.** `Services/Boleto/Drivers/*` e `rb_boleto_credentials` continuam funcionando até a migração terminar; código novo não as estende. A migração do RB para o PaymentGateway está em andamento (sessão `rb-l2b-gateway`, sem PR aberto em 2026-11-23).
4. **Quem cuida da fatura paga no Financeiro é o Financeiro.** O RB dispara `InvoicePaid`; transformar isso em título e baixa é responsabilidade de um consumidor do Financeiro (decisão b2 da auditoria). Esta ADR não decide o desenho dessa ponte.

## Justificativa

- É o estado real do sistema: os fluxos novos do RB (Pix Automático, cartão salvo, US-RB-057) já dependem do PaymentGateway.
- Duas pilhas para o mesmo banco dobram o risco nos pontos onde está o dinheiro (credencial, webhook, estorno). Declarar o dono é o que permite apagar uma delas.
- O critério de extração que a própria ARQ-0005 escreveu foi cumprido.

## Consequências

**Positivas:** a fronteira passa a ter uma ADR ativa para citar; o SDD e o BRIEFING do RB deixam de se apoiar numa ADR arquivada.

**Negativas / trade-offs:** enquanto a migração não termina, o RB tem duas pilhas, e o boleto ainda usa `rb_boleto_credentials`. Quem mexer em boleto precisa saber qual pilha está tocando.

**Append-only:** a ARQ-0005 e as três ADRs 0170 não foram editadas.
