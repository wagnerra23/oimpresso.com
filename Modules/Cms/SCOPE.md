---
module: Cms
purpose: "Mini CMS — landing page, blog, contact-us. Routes /, /c/page/{x}, /c/blogs."
contains:
  - "CmsController"
  - "CmsPageController"
  - "DataController"
  - "InstallController"
  - "SettingsController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/ProjectMgmt"
  - "MCP server admin → Modules/TeamMcp"
trust_required: L4
owner: wagner
permission_prefix: cms.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /cms/*
drift_alerts: []
---

# Modules/Cms

## Missão

Mini CMS — landing page, blog, contact-us. Routes /, /c/page/{x}, /c/blogs.

## Trust level

**L4** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
