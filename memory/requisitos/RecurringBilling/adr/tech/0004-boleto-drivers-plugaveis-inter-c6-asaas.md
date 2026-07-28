---
id: requisitos-recurring-billing-adr-tech-0004-boleto-drivers-plugaveis-inter-c6-asaas
---

# ADR TECH-0004 (RecurringBilling) · Drivers de boleto plugáveis — Inter, C6, Asaas

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

Escopo 2 do RecurringBilling exige emissão de boleto por tenant. Wagner usa Banco Inter PJ, C6 Bank e Asaas — todos sem taxa de emissão de boleto em contas PJ básica. Precisamos suportar os 3 sem travar o billing caso um provider fique offline.

A lib `eduardokum/laravel-boleto ^0.11` já instalada suporta:
- **Inter** via `Api\Banco\Inter` (OAuth2 + mTLS certificado .crt/.key) → boleto registrado
- **C6** via `Boleto\Banco\C6` (geração local CNAB, sem API) → boleto não-registrado
- **Asaas** → não está na lib; usa API REST própria

## Decisão

Arquitetura de drivers plugáveis por tenant:

```
BoletoService::emitir(businessId, params)
  → carrega BoletoCredential.banco por business_id
  → instancia InterDriver | C6Driver | AsaasDriver
  → retorna BoletoResult (nossoNumero, linhaDigitavel, codigoBarras, pixQrCode, pdfBase64)
```

**Credenciais** em `rb_boleto_credentials.config_json` (JSON criptografado via `Crypt::encryptString`):
- Inter: `client_id`, `client_secret` (criptografado), `certificado_crt_b64`, `certificado_key_b64`, `conta_corrente`, `cnpj_beneficiario`
- C6: `agencia`, `conta_corrente`, `codigo_cliente`, `cnpj_beneficiario`
- Asaas: `api_key` (criptografado), `ambiente` (sandbox|production)

**Certificado Inter** (mTLS, diferente do certificado A1 fiscal em `nfe_certificados`):
- Conteúdo .crt e .key guardados como base64 em `config_json`
- Driver escreve em arquivo temp (`/tmp/inter_crt_*.pem`) com chmod 600 para curl mTLS

## Consequências

**Positivas:**
- Novo driver (Nubank PJ, Cora) = implementar `BoletoDriverContract` + registrar no match
- Tenant pode ter Inter em prod e Asaas em sandbox para testes
- Failure isolation: Inter fora = só Inter falha, Asaas continua

**Negativas:**
- C6 sem API = boleto não-registrado (pode ser recusado por alguns pagadores)
- Inter requer certificado mTLS por tenant (processo manual de obtenção no portal Inter)
- Temp files de cert precisam ser limpos periodicamente (cron ou TTL por hash)
