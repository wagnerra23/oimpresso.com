---
name: NFSe — Service + Adapter + DTO pattern
description: Decisão de usar Service/Interface/Adapter para desacoplar a integração SN-NFSe de outras prefeituras futuras
type: decision
module: NFSe
status: accepted
authority: Eliana[E]
date: 2026-05-01
---

# ADR TECH-0001 — Service + Adapter + DTO para integração NFSe

## Status
Aceito (2026-05-01)

## Contexto

O módulo NFSe precisa integrar com o webservice do Sistema Nacional NFSe (`sefin.nfse.gov.br`). Municípios que ainda não migraram usam ABRASF municipal. A lib `nfse-nacional/nfse-php` não pode ser instalada no Hostinger enquanto `laravel/mcp` estiver no `composer.json` principal (ADR 0062).

## Decisão

Usar três camadas separadas:

1. **`NfseProviderInterface`** — contrato com 3 métodos: `emitir / consultar / cancelar`
2. **`SnNfseAdapter`** — implementação HTTP direta ao SN-NFSe (sem lib externa por ora)
3. **`NfseEmissaoService`** — orquestra validação, persistência, retry, idempotência, log

DTOs imutáveis (`NfseEmissaoPayload`, `NfseResultado`) para entrada/saída entre camadas.

## Consequências

- **Positivo:** trocar ABRASF por SN-NFSe (ou vice-versa) = trocar implementação do Adapter; Service e testes não mudam.
- **Positivo:** `nfse-nacional/nfse-php` pode ser integrada no `SnNfseAdapter` quando o split do `composer.json` for feito (ADR 0062).
- **Negativo:** mais arquivos que um controller direto; justificado pela complexidade fiscal.
