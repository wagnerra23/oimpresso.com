# Changelog — NfeBrasil

Formato: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) + SemVer.

## [Unreleased]

### Planejado (Fase 1 — MVP NFC-e Simples Nacional SP)

- Schema base: `nfe_certificados`, `nfe_emissoes`, `nfe_eventos`, `nfe_business_configs`, `nfe_number_sequences`
- Datasets seedados: NCM, CEST, CFOP, CSOSN, CST (datasets públicos)
- Upload + storage criptografado de cert A1 (US-NFE-001, ARQ-0003)
- Configuração inicial business (regime, ambiente, CSC, série)
- Emissão NFC-e via Observer em `Transaction` (US-NFE-002)
- DANFE PDF via `eduardokum/sped-da` (ARQ-0002)
- Status broadcast Echo (UI-0001)
- Permissões Spatie + Multi-tenant isolation tests

### Planejado (Fase 2 — NF-e completa)

- NF-e modelo 55 com Lucro Presumido / Real
- Cálculo ICMS tradicional (CST)
- Vinculação `transaction_id` ↔ `nfe_emissoes`
- Visualização emissões (US-NFE-003)

### Planejado (Fase 3 — Cancelamento + CCe)

- Cancelamento dentro do prazo (US-NFE-004)
- CCe (US-NFE-005)
- Estorno automático Financeiro via evento `NfeCancelada`

### Planejado (Fase 4 — Contingência)

- Health-check SEFAZ por UF (US-NFE-006, TECH-0002)
- Modo EPEC (NF-e) + FS-DA (NFC-e)
- Retentativa ordenada FIFO

### Planejado (Fase 5 — Motor Tributário Completo)

- ICMS-ST + MVA + DIFAL + FCP
- IPI / PIS / COFINS por NCM/UF
- Tabela `nfe_fiscal_rules` com schema flexível CBS/IBS (ARQ-0004)
- US-NFE-010 (regras tributárias)

### Planejado (Fase 6 — MDF-e + CT-e)

- Emissão MDF-e (logística)
- Emissão CT-e (transportadora)

### Planejado (Fase 7 — SPED Fiscal/EFD)

- Geração mensal blocos C100/C170/C500 (US-NFE-009)
- Token compartilhável read-only pro contador
- Validação contra layout PVA

### Planejado (Onda futura)

- Manifestação automática de NFes recebidas (US-NFE-008)
- Monitor cStat com sugestão correção (US-NFE-007, UI-0002)
- CBS/IBS preenchimento (quando legislação consolidar)
- HSM externo pra Enterprise (ARQ-0003)

## [0.0.0] - 2026-04-24

### Added

- Spec promovida de `_Ideias/NfeBrasil/` (status `researching`) para `requisitos/NfeBrasil/` (`spec-ready`)
- Estrutura completa: README + SPEC + ARCHITECTURE + GLOSSARY + 9 ADRs (arq/0001-0004 + tech/0001-0003 + ui/0001-0002)
- Frase de posicionamento e revenue model definido: Starter R$ 99 / Pro R$ 299 / Enterprise R$ 599 (subscription puro, sem take rate)
- Origem rastreada: conversa Claude mobile (`_Ideias/NfeBrasil/evidencias/conversa-claude-2026-04-mobile.md`)
