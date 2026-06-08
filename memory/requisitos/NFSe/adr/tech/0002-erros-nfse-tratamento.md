---
name: NFSe — Hierarquia de exceções e retry policy
description: Top 10 erros SN-NFSe mapeados, exceções tipadas, retry com backoff, idempotência
type: decision
module: NFSe
status: accepted
authority: Eliana[E]
date: 2026-05-01
sources: [PMPF-RS erros_comuns_nfse, Omie rejeições NFS-e, ContaAzul soluções erros, Focus NFe docs]
---

# ADR TECH-0002 — Hierarquia de exceções NFSe + retry policy

## Status
Aceito (2026-05-01)

## Contexto

Pesquisa (2026-05-01) identificou os 10 erros mais comuns na emissão via SN-NFSe federal. Concorrentes (Omie, ContaAzul, Focus NFe) expõem mensagens amigáveis ao usuário por erro específico e permitem correção + reenvio sem deletar.

## Top 10 erros mapeados

| # | Erro | Código | Exceção |
|---|------|--------|---------|
| 1 | RPS/lote duplicado | — | `RpsDuplicadoException` |
| 2 | Schema/XML inválido | — | `NfseException(XML_INVALIDO)` |
| 3 | Cert A1 inválido/expirado | — | `CertificadoInvalidoException` |
| 4 | ISS incorreto | E501 | `IssInvalidoException` |
| 5 | Código LC 116 inválido | — | `CodigoServicoInvalidoException` |
| 6 | CNAE desabilitado | — | `NfseException(CNAE_INVALIDO)` |
| 7 | Data emissão fora do prazo | E17 | `NfseException(E17)` |
| 8 | Código município inválido | — | `NfseException(MUNICIPIO_INVALIDO)` |
| 9 | Prestador não autorizado | L1 | `PrestadorNaoAutorizadoException` |
| 10 | Timeout/rede | — | `ProviderTimeoutException` |

## Decisão

- **Exceções tipadas** herdam de `NfseException(codigo, detalhe)` — mensagem amigável em PT-BR
- **Retry automático** só para `ProviderTimeoutException`: 3 tentativas, backoff exponencial (1s, 2s, 4s)
- **Sem retry** para erros de regra de negócio (certificado, RPS duplicado, prestador não autorizado)
- **Idempotência** via `idempotency_key = SHA256(business_id|tomador|valor|descricao|competencia)`
- **Inspiração UX Omie:** mensagem inline no card da nota + botão "Corrigir e Reenviar" (UI US-NFSE-010+)

## Consequências

- 11 testes Pest cobrem os 10 cenários + golden path + idempotência + cálculo ISS
- Stack trace completo em `Log::channel('nfse')` para debugging
- Usuário nunca vê código de erro técnico — só mensagem acionável em PT-BR
