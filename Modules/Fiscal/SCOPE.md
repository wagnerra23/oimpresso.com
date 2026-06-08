---
module: Fiscal
purpose: "Cockpit fiscal unificado — agregador thin sobre NfeBrasil + NFSe (sem duplicação). 7 sub-páginas (Cockpit, NF-e, NFS-e, DF-e, Eventos, Config, SPED) conforme design Cowork KB-9.75 todas entregues. Ações cancelar/CC-e/inutilização/retransmitir + ⌘K palette cross-fiscal + gerador SPED EFD-ICMS/IPI v3.1.1 perfil A em produção pós-Waves 1-9 (PRs #1183, #1185, #1189, #1190, #1249, #1253, #1257, #1259, #1261)."
contains:
  - "AcoesController — PR #4 thin delegate NfeService::cancelar + ManifestacaoService (4 ações DF-e)"
  - "CockpitController — sub-página 1 (KPIs + alertas + sparklines + quick links)"
  - "ConfigController — sub-página 6 (Cert A1 + regime + tributação default read-only)"
  - "DataController"
  - "DfeController — sub-página 4 (Manifesto DF-e + prazo 90d)"
  - "EventosController — sub-página 5 (timeline append-only CC-e/Cancel/EPEC/Manifesto)"
  - "InstallController"
  - "NfeCockpitController — sub-página 2 (NF-e/NFC-e + drawer SEFAZ guiado)"
  - "NfseCockpitController — sub-página 3 (NFS-e modelo 56 nacional NT 2024-001)"
  - "PaletteSearchController — PR #7 ⌘K palette cross-fiscal (US-FISCAL-015 — busca global notas + DF-e)"
  - "SpedController — sub-página 7 + endpoint gerar() download EFD-ICMS/IPI MVP (PR #8)"
  - "Services/SpedIcmsIpiGeneratorService — gerador TXT EFD-ICMS/IPI v3.1.1 perfil A (PR #8 saídas MVP — sem PIS/COFINS, sem Bloco E apuração, sem Bloco H inventário)"
not_contains:
  - "Emissão fiscal (XML + SEFAZ) → Modules/NfeBrasil (lê via Service)"
  - "NFSe federal LC 214/2025 → Modules/NFSe (futura sub-página 3)"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/ProjectMgmt"
trust_required: L3
owner: wagner
permission_prefix: fiscal.*
charter_adr: 0094
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
url_prefixes:
  - /fiscal/*
drift_alerts: []
---

# Modules/Fiscal

## Missão

Cockpit fiscal unificado: agrega visão consolidada de NF-e/NFC-e (modelos 55 + 65), NFS-e (LC 214/2025), Manifesto DF-e, Eventos (CC-e / cancelamento / inutilização), Certificado A1 + Config, e SPED — substituindo telas fragmentadas de `Modules/NfeBrasil` e `Modules/NFSe`.

**Padrão:** thin agregador (espelho de `Modules/Financeiro/Unificado`). Controllers leem Models de NfeBrasil/NFSe via `HasBusinessScope` global scope — **não duplica backend**.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md). Persona dupla: Eliana (contadora — leitura, conferência, SPED) + Wagner (operador fiscal — emissão, cancelamento, retransmissão).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida:

- **Lógica de emissão XML + SEFAZ webservice** → vive em `Modules/NfeBrasil` (sped-nfe lib + `NfeEmissaoService`). Fiscal só consome via Service e exibe.
- **Cancelamento cascade** (NFe SEFAZ + Asaas/Inter refund + Whatsapp/email cliente) → orquestrado por `app/Domain/Fsm/CancelarVendaCascade` (ADR 0143). Fiscal botão "Cancelar" no drawer apenas dispatch action FSM.
- **NFS-e endpoint SEFIN** → vive em `Modules/NFSe`. Fiscal sub-página 3 lê `NfseEmissao`.

## Roadmap

| Wave | Entrega | Score impact | Status | PR |
|---|---|---|---|---|
| 1 | NF-e · NFC-e cockpit + drawer SEFAZ guiado | base 0→60 | ✅ mergeado | `8aef3d0fa` (#1183) |
| 2 | Cockpit (#1) + NFS-e (#3) + Eventos (#5) | +20pp | ✅ mergeado | `cabd29661` (#1185) |
| 3 | DF-e (#4) + Cert/Cfg (#6) + SPED (#7 placeholder) | +12pp | ✅ mergeado | `e36e1e272` (#1189) |
| **🎯 7 sub-páginas do design KB-9.75 entregues após Wave 3** | | | | |
| 4 | AcoesController — Cancelar NFe + Manifestar DF-e (4 ações SEFAZ) | +15pp | ✅ mergeado | `d10b117e1` (#1190) |
| 5 | CC-e (110110) + Inutilização faixa numérica | +4pp | ✅ mergeado | #1249 |
| 6 | Retransmitir NFe rejeitada/denegada/erro_envio (preservation contract CONFAZ Art. 14) | +3pp | ✅ mergeado | #1253 |
| 7 | ⌘K palette cross-fiscal (PaletteSearchController + CmdKPalette) | +8pp | ✅ mergeado | #1257 |
| 8 | Gerador SPED EFD-ICMS/IPI MVP saídas (SpedIcmsIpiGeneratorService + 16 registros) | +4pp | ✅ mergeado | #1259 |
| 9 | SPED Bloco E (apuração ICMS) + Bloco H (esqueleto) — 23 registros total | +2pp | ✅ mergeado | #1261 |
| **🎯 Score Capterra Fiscal cockpit ≥ 100/100 pós-Wave 9 (top-3 gaps Bling/Tiny fechados)** | | | | |
| 10 | EFD-Contribuições PIS/COFINS + saldo credor real E110 + Bloco H dados reais Stock | +3pp | 🔒 backlog | — |

---

- **v1.0.0** (2026-05-20) — SCOPE.md inicial. Módulo Fiscal criado em PR #1183 como thin agregador (sub-página NF-e · NFC-e).
- **v1.1.0** (2026-05-20) — PR #2 Wave consolidada: + CockpitController, NfseCockpitController, EventosController. Roadmap reorganizado (5 PRs vs 7).
- **v1.2.0** (2026-05-20) — PR #3 Wave final: + DfeController, ConfigController, SpedController. **7 sub-páginas do design concluídas**. Próximos PRs: mutações + ⌘K + SPED real.
- **v1.3.0** (2026-05-20) — PR #4 Wave Ações: + AcoesController thin (cancelar NFe + manifestar DF-e 4 ações). Delega Services NfeBrasil existentes.
- **v1.4.0** (2026-05-20) — PR #5 Wave CC-e + Inutilização: + NfeCartaCorrecaoService (Modules/NfeBrasil) + AcoesController.cartaCorrecao + AcoesController.inutilizar (delega NfeInutilizacaoService US-SELL-030). Score Capterra 80→85.
- **v1.5.0** (2026-05-20) — PR #6 Wave Retransmitir: + NfeService.retransmitir (UPDATE preservation contract CONFAZ Art. 14 — NUNCA forceDelete). AcoesController.retransmitir. Score 85→88.
- **v1.6.0** (2026-05-20) — PR #7 Wave ⌘K palette: + PaletteSearchController + CmdKPalette.tsx listener global Cmd/Ctrl+K + FxShell mount. Score 88→96.
- **v1.7.0** (2026-05-20) — PR #8 Wave SPED MVP: + Services/SpedIcmsIpiGeneratorService (gerador EFD-ICMS/IPI v3.1.1 perfil A — 16 registros Blocos 0+C+9) + SpedController.gerar download TXT. Score 96→100.
- **v1.8.0** (2026-05-20) — PR #9 Wave Bloco E + H: expansão SpedIcmsIpiGeneratorService com 7 registros (Bloco E apuração ICMS + Bloco H esqueleto). 23 registros canônicos total — estrutura completa pra validação PVA-EFD CONFAZ. Score 100→102 (acima cap).
