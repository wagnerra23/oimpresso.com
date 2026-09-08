---
module: AssetManagement
purpose: "Patrimônio da empresa: cadastro de ativos físicos, alocação e devolução a colaboradores, manutenção e garantia — bem de uso interno, nunca item vendável de estoque."
migracao_ui: "liberado — endereco `resources/js/Pages/Patrimonio/**` (modulo proprio) por ADR 0394; cada tela segue o MWART da ADR 0104"
contains:
  - "AssetAllocationController"
  - "AssetController"
  - "AssetMaitenanceController"
  - "AssetSettingsController"
  - "DataController"
  - "InstallController"
  - "RevokeAllocatedAssetController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: asset.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /asset/* (dashboard · assets · allocation · revocation · asset-maintenance · settings · install)
drift_alerts: []
---

# Modules/AssetManagement

> **DECISAO 2026-09-08 [W] — `migracao_ui` sai de `bloqueado-escopo`.** O endereco de UI e
> `resources/js/Pages/Patrimonio/**` (modulo proprio), por [ADR 0394](../../decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md).
> A decisao e de **2026-09-04** e ja estava no `main` desde entao — no cabecalho do
> `.github/workflows/modules-pest.yml:36` (commit `d6457184ea`), com a lane de CI apontando para
> `resources/js/Pages/Patrimonio/**` (`:48`, `:71`). Este arquivo, que e o dono canonico, seguia
> dizendo `bloqueado-escopo`: os dois se contradiziam no `main` ate agora. **O agrupamento de
> sidebar nao muda** — Patrimonio segue ghost de Estoque no grupo `operar` ([ADR 0180](../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md));
> endereco de pasta e agrupamento de menu sao eixos independentes.

> **ERRATA 2026-09-08 [CL] — `permission_prefix` corrigido de `assetmanagement.*` para `asset.*`.**
> O valor anterior confundia a **permissao** com `assetmanagement_module`, que e o nome da *feature
> de pacote* (subscription), nao um prefixo de permissao. Medido no `main`: `assetmanagement.*` nao
> existe como permissao em lugar nenhum do repo — suas 56 ocorrencias sao nome de span OTel ou chave
> de log. O prefixo vivo e `asset.*`, declarado em `Modules/AssetManagement/Http/Controllers/DataController.php:31-:58`
> (6 permissoes) e consumido por `app/Http/Controllers/RoleController.php:101` e `:221`.
> Isso **fecha** o item 2 do RESIDUO do playbook (`asset.*` x `assetmanagement.*`), que estava
> catalogado como decisao [W]: nao era fork, era errata de doc. Recibo: `_saida-04.md` (PR #7009).

## Missão

UltimatePOS herdado.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
