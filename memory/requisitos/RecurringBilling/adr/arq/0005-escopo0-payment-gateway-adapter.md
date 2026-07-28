---
id: requisitos-recurring-billing-adr-arq-0005-escopo0-payment-gateway-adapter
---

# ADR ARQ-0005 (RecurringBilling) · Escopo 0 — PaymentGateway: sem sub-módulo separado no MVP

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

ADR ARQ-0001 previu 6 sub-módulos incluindo `Modules/PaymentGateway/` separado. Para o MVP (Escopo 0), isso seria prematuramente complexo: o único gateway no MVP é o Asaas (já tem `AsaasDriver`) e a cobrança recorrente é o caso de uso primário.

## Decisão

**MVP Escopo 0**: drivers de gateway vivem em `Modules/RecurringBilling/Services/Boleto/Drivers/` (junto do BoletoService). Sub-módulo `Modules/PaymentGateway/` separado só quando um segundo tenant precisar de gateway diferente (Iugu, Pagar.me, Stripe).

Tabela `rb_boleto_credentials` centraliza credenciais por tenant — não `pg_credentials` separado.

**Critério de extração para sub-módulo:** ≥2 tenants com gateways diferentes OU necessidade de cobrança avulsa fora de assinatura.

## Consequências

- Menos boilerplate no MVP (-1 ServiceProvider, -1 set de migrations)
- Extração futura é um refactor limpo: mover `Drivers/` + renomear tabela
- Risco: se 3 tenants pedirem gateways diferentes antes do refactor, ServiceProvider fica grande
