---
id: requisitos-recurring-billing-adr-arq-0006-escopo3-nfe-via-modulo-nfebrasil-nao-nfse
---

# ADR ARQ-0006 (RecurringBilling) · Escopo 3 — NFe via Modules/NfeBrasil, não sub-módulo NFSe novo

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

ADR ARQ-0002 previu sub-módulo `Modules/NFSe/` para emissão fiscal. O projeto já tem:
- `Modules/NfeBrasil/` — scaffold + estrutura para NFe (nfephp-org/sped-nfe)
- `Modules/NFSe/` — scaffold com tabela `nfe_certificados`, `nfse_provider_configs`, e `NfseController` parcialmente implementado

Wagner explicitou: **quer NFe via NfeBrasil, não NFSe** para o fluxo de cobrança recorrente.

## Decisão

**Escopo 3** usa `Modules/NfeBrasil` (nfephp-org/sped-nfe) — NFe produto/serviço pela SEFAZ federal.

Módulo `Modules/NFSe/` (nota fiscal de serviço municipal) fica separado — é outro domínio (emissão na prefeitura, por município, processo diferente).

Integração via evento:
```
InvoicePaid → NFeEmissionRequested → NfeBrasilService::emitir() → SEFAZ
```

Certificado A1 reusado de `nfe_certificados` (já implementado em NFSe, disponível para NfeBrasil também).

## Consequências

- NFe federal: chave de acesso + DANFE PDF + XML autorizado (robusto, SEFAZ nacional)
- NFSe municipal: implementação separada posterior (varia por prefeitura, 5570 municípios)
- `nfe_certificados` é compartilhado entre NFSe e NfeBrasil — não duplicar
