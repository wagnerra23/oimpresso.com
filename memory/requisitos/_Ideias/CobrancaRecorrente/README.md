---
status: shipped
priority: -
problem: "Promovido para `requisitos/RecurringBilling/` em 2026-04-24 — não é mais ideia, é spec formal"
persona: "(ver requisitos/RecurringBilling/README.md)"
estimated_effort: "(ver requisitos/RecurringBilling/README.md — 12-14 semanas, 7 ondas)"
references:
  - memory/requisitos/RecurringBilling/
related_modules:
  - Financeiro
  - NfeBrasil
---

# CobrancaRecorrente → RecurringBilling — promovido em 2026-04-24

Esta ideia virou módulo real (status `spec-ready`).

**Spec atual em** [`memory/requisitos/RecurringBilling/`](../../RecurringBilling/) com:

- README com frase de posicionamento e revenue thesis (R$ 149-999 + take rate 0,8%)
- SPEC com 12+ user stories agrupadas por sub-módulo + 14 regras Gherkin
- ARCHITECTURE com 6 sub-módulos event-driven (RecurringBilling + PaymentGateway + PixAutomatico + NFSe + Dunning + Boleto)
- 9 ADRs separados (arq/0001-0004 + tech/0001-0003 + ui/0001-0002)
- GLOSSARY (vocabulário billing recorrente BR) + CHANGELOG

**Renomeado** de `CobrancaRecorrente` → `RecurringBilling` para alinhar com convenção PascalCase em inglês dos outros módulos (`PaymentGateway`, etc.).

Conteúdo histórico desta pasta (evidências de conversas Claude mobile) **mantido aqui** para rastreabilidade. Não editar — evolução acontece em `requisitos/RecurringBilling/`.
