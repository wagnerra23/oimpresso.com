---
module: ConsultaOs
purpose: "Portal público (sem login) onde o cliente final consulta a OS dele. Desde 2026-10-02 (US-CONSULTA-001) lê as folhas de OS reais do Modules/Repair via RepairConsultaOsRepository e devolve só a whitelist pública (nº, aparelho, status com cor, previsão, atividades). Até 2026-10-02 rodava sobre um mock de 4 OS fixas."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "ConsultaOsController"
  - "ConsultaOsService"
  - "ConsultaOsRepositoryInterface"
  - "RepairConsultaOsRepository"
  - "ConsultaPublicaRequest"
  - "ConsultaOsHealthCommand"
  - "DataController"
  - "InstallController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
  - "Folhas de OS (repair_job_sheets) e status → Modules/Repair (ConsultaOs só LÊ, campo a campo, via RepairConsultaOsRepository — US-CONSULTA-001)"
  - "Aviso ao cliente quando o status muda → Modules/Whatsapp (listener NotifyRepairCustomer, US-CONSULTA-004)"
trust_required: L4
owner: wagner
permission_prefix: consultaos.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /consulta-os (tela pública, GET, throttle 30/min)
  - /consulta-os/* (buscar = JSON público; install = admin)
  - /repair-status (rota do Repair; só redireciona 302 pra /consulta-os desde 2026-10-02)
drift_alerts: []
---

# Modules/ConsultaOs

## Missão

Portal público de consulta de Ordem de Serviço. O cliente busca pelo nº da OS, nº da venda
ou celular (este só quando a config do Repair liga) e vê o status que a empresa escreveu no
Repair, a previsão de entrega e o histórico. O status é livre por empresa: não há pipeline fixo.

Fluxo de ponta a ponta e pendências [W]: ver [SPEC.md](SPEC.md) e o contrato vivo
`resources/js/Pages/ConsultaOs/Index.casos.md`.

## Trust level

**L4** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
- **v1.1.0** (2026-11-17) — `purpose`, `contains`, `url_prefixes` e Missão refletem a fonte real (Repair, desde 2026-10-02). A frase "dataset FAKE de 4 OS. Não lê banco" era verdade só até essa data.
