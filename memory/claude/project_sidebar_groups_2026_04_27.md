---
name: Sidebar groups — CMS/Connector pertencem a Superadmin (OBSOLETO no Cockpit)
description: Wagner declarou em 2026-04-27 que CMS e Connector são conceitualmente do "grupo Superadmin"; estavam reordenados pra topo do sidebar do AppShell legado (PR #32). NO COCKPIT (ADR UI-0008, 2026-04-27 mais tarde) eles vão pro RODAPÉ junto com Backup/Office Impresso/Modulos.
type: project
status: superseded-by-cockpit-2026-04-27
originSessionId: c8fd0d09-0309-4bc2-a741-717921f4c8cf
---
> ⚠️ **OBSOLETO em 2026-04-27** — substituído pelo Cockpit (ADR UI-0008). No layout-mãe novo, módulos administrativos vão pro **rodapé da sidebar** (acima do user dropdown), não pro topo. Permissões Spatie permanecem mas a localização visual mudou.
>
> No AppShell legado (que sobrevive em telas administrativas isoladas tipo Showcase/Modulos), o conteúdo abaixo continua válido.

Decisão Wagner (2026-04-27 manhã): **CMS e Connector pertencem ao "grupo Superadmin"** conceitualmente.

**Why:** Reorganização gradual do menu — agrupar módulos administrativos próximos pra superadmin trabalhar mais rápido.

**How to apply:**
- PR #32 (2026-04-27) só ajustou order numerics: CMS `100→5`, Connector `89→6`. Posicionamento visual logo abaixo do **Backup** (order=4 em `app/Http/Middleware/AdminSidebarMenu.php#761`).
- Permissões mantidas como estão:
  - **CMS** já é `can('superadmin')` em `Modules/Cms/Http/Controllers/DataController.php#56`
  - **Connector** ainda usa `hasThePermissionInSubscription('connector_module', 'superadmin_package')` — afeta clientes API; tightening pra superadmin-only fica pra outro PR se Wagner pedir
- Quando for fazer um "grupo Superadmin" formal (sub-menu/dropdown agrupando esses módulos), considerar Officeimpresso (order=2), CMS (5), Connector (6) e Modules manage (3) como integrantes naturais. Backup (4) pode ficar fora — é operacional e usuário Pro pode acessar.
- Outros módulos com order baixo no sidebar não foram tocados; não inferir que estão no grupo.
