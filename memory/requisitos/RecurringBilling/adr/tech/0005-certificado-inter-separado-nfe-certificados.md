---
id: requisitos-recurring-billing-adr-tech-0005-certificado-inter-separado-nfe-certificados
---

# ADR TECH-0005 (RecurringBilling) · Certificado Inter separado de nfe_certificados

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

Existem dois tipos de certificado digital no sistema:
1. **Certificado A1 fiscal** (.pfx) em `nfe_certificados` — assina XMLs de NFe/NFSe perante SEFAZ
2. **Certificado mTLS Inter** (.crt + .key) — autentica na API do Banco Inter para emitir boletos

São certificados emitidos por autoridades diferentes, com processos de obtenção distintos e finalidades diferentes.

## Decisão

**Não reusar `nfe_certificados`** para o certificado Inter. Mantê-los separados:

| Aspecto | nfe_certificados | rb_boleto_credentials (Inter) |
|---|---|---|
| Formato | .pfx (PKCS#12) | .crt + .key (PEM separados) |
| Emissor | AC-NFe (ICP-Brasil) | Banco Inter (portal PJ) |
| Validade | 1-3 anos | 1 ano (Inter renova via portal) |
| Escopo | Todas as NFs do tenant | Conta Inter do tenant |
| Storage | `cert_pfx_encrypted` TEXT | `config_json` base64 |

**Storage do certificado Inter:** campos `certificado_crt_b64` e `certificado_key_b64` dentro de `rb_boleto_credentials.config_json`, criptografados junto com as demais credenciais.

## Consequências

- UI de credenciais boleto faz upload de 2 arquivos separados (.crt e .key)
- Alerta de expiração separado dos certificados NF (job `CheckBoletoCredentialExpiryJob`)
- Sem conflito de domínio entre fiscal e bancário
