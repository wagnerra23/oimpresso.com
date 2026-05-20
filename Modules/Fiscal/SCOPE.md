---
module: Fiscal
purpose: "Cockpit fiscal unificado — agregador thin sobre NfeBrasil + NFSe (sem duplicação). PR #1: sub-página NF-e · NFC-e (modelos 55 + 65). Roadmap: 7 sub-páginas (Cockpit, NF-e, NFS-e, DF-e, Eventos, Config, SPED) conforme design Cowork KB-9.75."
contains:
  - "DataController"
  - "InstallController"
  - "NfeCockpitController"
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

| Sub-página | Status | PR |
|---|---|---|
| NF-e · NFC-e (#2 do design) | ✅ PR #1 em curso | #1183 |
| Cockpit (KPIs + alertas) | 🔒 backlog | PR #2 |
| ⌘K palette cross-fiscal | 🔒 backlog | PR #3 |
| Ações mutação (cancelar/retransmitir/CC-e/inutilizar) | 🔒 backlog | PR #4 |
| NFS-e | 🔒 backlog | PR #5 |
| Manifesto DF-e | 🔒 backlog | PR #6 |
| Eventos + Config + SPED | 🔒 backlog | PR #7 |

---

- **v1.0.0** (2026-05-20) — SCOPE.md inicial. Módulo Fiscal criado em PR #1183 como thin agregador (sub-página NF-e · NFC-e).
