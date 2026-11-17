---
title: "RUNBOOK — Smoke fiscal SEFAZ NFC-e (homologação, biz=1) — registro de maio"
module: NfeBrasil
owner: W
status: historical
last_validated: "2026-05-10"
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
related_us: [US-NFE-002]
---

# RUNBOOK — Smoke fiscal SEFAZ NFC-e (homologação, biz=1) — registro de maio

> **Este arquivo não é mais o roteiro.** O passo a passo vivo do smoke está em
> [RUNBOOK-smoke-sefaz.md](RUNBOOK-smoke-sefaz.md), revisado em 2026-11-11 (auditoria NfeBrasil, lote 5.2).
> As duas cópias tinham o mesmo roteiro; esta ficou só com o que era dela: o estado medido em maio.

## Estado medido em 2026-05-10 (fato datado, não é o estado de hoje)

Verificado via SSH + MySQL no Hostinger, naquele dia:

- a flag global `NFEBRASIL_AUTO_EMISSION_NFCE` estava ligada no `.env`;
- biz=1 tinha CNPJ cadastrado, NCM padrão, ambiente `2` (homologação) e certificado A1 ativo;
- havia 40 vendas `final` + `paid` em biz=1 e 0 emissões NFC-e: o listener só reage a evento novo e não
  retroage, então o smoke precisava de uma venda nova.

## O que mudou depois (2026-11-11)

- **O certificado de biz=1 venceu em 2026-08-06.** Em 2026-10-26 a SEFAZ-SC recusava as NFC-e de biz=1 com 781
  ("emissor não habilitado"), por falta do credenciamento NFC-e (SPEC, US-NFE-002).
- **A flag global não basta.** A emissão também depende da trava da empresa
  (`nfe_business_configs.auto_emission_enabled`, chave **Emissão automática** na tela Tributação). O roteiro vivo
  liga as duas.
- **O CNPJ da empresa saiu deste arquivo.** O dado está no cadastro da empresa; a consulta do roteiro vivo o lê de
  lá quando precisa. Conferir que o campo não está vazio é o que importa para o smoke.
- Ninguém emitiu NFC-e autorizada (cStat 100) por biz=1 até hoje: é a US-NFE-054, ainda aberta.

## Refs

- [RUNBOOK-smoke-sefaz.md](RUNBOOK-smoke-sefaz.md) — o roteiro
- [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) — teste automatizado usa o tenant
  98; o smoke fiscal manual contra a SEFAZ segue em biz=1 e nunca em biz=4
- [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) — runtime split
