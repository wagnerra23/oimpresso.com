---
owner: W
last_validated: "2026-06-08"
slug: officeimpresso-runbook-migracao-react
title: "Officeimpresso — Runbook migração Blade → Inertia/React (MWART aplicado a módulo superadmin)"
type: runbook
module: Officeimpresso
status: ativo
date: 2026-05-10
related:
  - 0104  # Processo MWART canônico (mãe)
  - 0011  # Padrão Jana
  - 0023  # Inertia v3
  - 0061  # Zero auto-mem
  - 0093  # Multi-tenant Tier 0
  - 0101  # Tests biz=1 nunca cliente
---

# RUNBOOK — Officeimpresso migração Blade → React

> **Tipo:** runbook MWART aplicado a um **módulo superadmin-only**
> **Não substitui:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — segue F1→F5 sem desvio
> **Adiciona:** regras específicas pra módulo superadmin (placement no menu, Spatie perm, parent dropdown href)
> **Estado origem:** 14 telas Blade em `Modules/Officeimpresso/Resources/views/` + layout próprio (`master.blade.php` + `nav.blade.php`)
> **Estado alvo:** Pages Inertia em `resources/js/Pages/Officeimpresso/<Tela>.tsx` + AppShellV2 com Vibe `daylight` (admin) + entrada no **sidebar principal** (grupo ACESSOS RÁPIDOS — uso pesado pra gestão de licenças desktop dos clientes legacy WR Sistemas)
> **Persona alvo:** Wagner (superadmin único hoje) — usa pra gestão de licenças desktop Delphi e auditoria de máquinas dos clientes legacy WR Comercial. Eventualmente delegação pra Felipe (suporte) com permission `officeimpresso.access`.

---

## Regra crítica — onde módulos superadmin VIVEM no menu

> **Atualizado 2026-05-10 (Wagner):** cascata "Superadmin" do user dropdown footer **REMOVIDA** ([PR #516](https://github.com/wagnerra23/oimpresso.com/pull/516)). Módulos de admin de plataforma vivem no **sidebar principal** como qualquer outro grupo. Histórico: cascata existiu de 2026-04-27 a 2026-05-10 (skill `sidebar-menu-arch` § Histórico).

**Placement por uso:**

| Módulo | Grupo no sidebar | Razão |
|---|---|---|
| **Officeimpresso** | **ACESSOS RÁPIDOS** | Uso pesado (gestão de licenças desktop dos clientes legacy WR Sistemas) |
| **CMS / Conector / Backup / Módulos / Personalizar** | **PLATAFORMA** (novo grupo, no fim, collapsed) | Uso esporádico (admin de plataforma — Backup mensal, CMS raríssimo, Conector setup, Módulos só ativar/desativar) |

**Implementação canônica** (não inventar):

| Camada | Arquivo | Papel |
|---|---|---|
| Backend publica menu | `Modules/Officeimpresso/Http/Controllers/DataController.php::modifyAdminMenu()` | Guard `auth()->user()->can('superadmin')` + `Menu::modify('admin-sidebar-menu', dropdown(...))` |
| Conversor nwidart→JSON | [`app/Services/LegacyMenuAdapter.php`](../../../app/Services/LegacyMenuAdapter.php) | Itera Menu instance, vira ShellMenuItem[] flat |
| **Classifier label→grupo sidebar** | [`Sidebar.tsx`](../../../resources/js/Components/cockpit/Sidebar.tsx) — `SIDEBAR_GROUPS` (`office` + `plataforma`) | Lookup case-insensitive label → grupo visual |
| Cascata Superadmin (deprecated) | `shared.ts` — `SUPERADMIN_LABELS` esvaziado, `isSuperadminMenu()` retorna false | Code path dormente sem quebrar callers |

> ✅ **Skill canônica:** [`sidebar-menu-arch`](../../../.claude/skills/sidebar-menu-arch/SKILL.md) — sempre carregar antes de mexer em sidebar/menu.

### Pegadinha #1 — parent dropdown sem `url` cai em href='#' (resolvido em PR #516)

`Menu::modify(...)->dropdown('Office Impresso', closure, ['icon' => '...'])` cria item parent **sem href**. Resolvido em [PR #516](https://github.com/wagnerra23/oimpresso.com/pull/516):
1. `MenuItem::make()` agora promove `attributes.url` → `url` no topo (mesmo pattern existente do icon)
2. `DataController.php` adiciona `'url' => '/officeimpresso/computadores'` no parent dropdown

Pra módulos novos: sempre passar `'url' => '/landing/page'` no 3º param de `Menu::dropdown(...)`. Sem isso, sidebar React renderiza item de dropdown sem URL no parent (clicar só toggla sub-menu, não navega) — UX inconsistente.

### Pegadinha #2 — Spatie permission `superadmin` precisa existir + estar atribuída

Muitas instalações reseed Spatie permissions sem incluir `superadmin`. Verificar:

```bash
php artisan tinker --execute="echo \App\User::find(1)->can('superadmin') ? 'OK' : 'FALTA'"
```

Se faltar, criar + atribuir ao role `Admin#1` + `permission:cache-reset`. Items dos módulos superadmin **simplesmente não aparecem no shell.menu** sem essa perm — `DataController::modifyAdminMenu()` guarda `if (! auth()->user()->can('superadmin')) return;` é o gate único.

---

## Inventário — 14 telas Blade

| # | Tela Blade | Rota | Page React alvo | Prioridade | Cliente |
|---|---|---|---|---|---|
| 1 | `licenca_log/index.blade.php` | `/officeimpresso/licenca_log` | `Officeimpresso/Logs/Index.tsx` | **P0** — sua dor maior (timeline de máquinas) | Wagner |
| 2 | `licenca_log/timeline.blade.php` | `/officeimpresso/licenca_log/timeline/{id}` | `Officeimpresso/Logs/Timeline.tsx` | **P0** — viz rica per-máquina | Wagner |
| 3 | `licenca_computador/index.blade.php` | `/officeimpresso/licenca_computador` | `Officeimpresso/Licencas/Index.tsx` | P1 — DataTable + KPIs | Wagner |
| 4 | `licenca_computador/computadores.blade.php` | `/officeimpresso/computadores` | `Officeimpresso/Empresa/Show.tsx` | P1 — ficha empresa + tabela máquinas | Wagner |
| 5 | `licenca_computador/businessall.blade.php` | `/officeimpresso/businessall` | `Officeimpresso/Empresas/Index.tsx` | P1 — listagem 37 empresas | Wagner |
| 6 | `licenca_computador/create.blade.php` | `/officeimpresso/licenca_computador/create` | `Officeimpresso/Licencas/Create.tsx` | P2 — pouco uso (cadastro raro) | Wagner |
| 7 | `clients/index.blade.php` | `/officeimpresso/client` | `Officeimpresso/OauthClients/Index.tsx` | P3 — OAuth tokens (raro) | Wagner |
| 8 | `licencas_log/index.blade.php` (typo path) | — | **DELETAR** (path com plural extra, não-rota) | — | — |
| 9 | `index.blade.php` | `/officeimpresso/install` (raiz módulo) | `Officeimpresso/Install/Index.tsx` | P3 | Wagner |
| 10 | `catalogue/index.blade.php` | `/officeimpresso/catalogue/{biz}/{loc}` | `Officeimpresso/Catalogue/Index.tsx` | P2 — público QR | qualquer cliente |
| 11 | `catalogue/show.blade.php` | `/officeimpresso/show-catalogue/{biz}/{prod}` | `Officeimpresso/Catalogue/Show.tsx` | P2 — público QR | qualquer cliente |
| 12 | `catalogue/generate_qr.blade.php` | `/officeimpresso/catalogue-qr` | `Officeimpresso/Catalogue/Qr.tsx` | P2 | Wagner |
| 13 | `catalogue/partials/*.blade.php` (3 partials) | (includes) | converter em sub-componentes `_components/` | P2 | — |
| 14 | `layouts/master.blade.php` + `layouts/nav.blade.php` | (layout) | **DELETAR** após migração — vira AppShellV2 | — | — |

**Total estimado** (recalibrado [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) fator 10x IA-pair + margem 2x): **~5-8 dias úteis** pras 7 P0/P1 (telas internas Wagner). P2/P3 podem ficar pra outro cycle.

---

## Ordem sugerida de migração

Cada tela = 1 epic na SPEC.md = 5 USs (uma por fase MWART) = 5 PRs ≤ 300 LOC.

```
1. licenca_log/index            ← MAIS uso, base do RUNBOOK pattern
2. licenca_log/timeline         ← detalhe da #1
3. licenca_computador/index     ← lista licenças (DataTable simples)
4. licenca_computador/computadores  ← ficha empresa
5. licenca_computador/businessall   ← lista empresas
6. licenca_computador/create    ← form (mais lento — Form shim)
7. clients/index                ← OAuth (uso raro — pode pular)
```

Catalogue (público QR) é separado e pode rodar em paralelo se Wagner quiser cliente vendo página React.

---

## Como testar — estratégia para várias telas

### F2 — Pest baseline (POR tela, antes de mexer)

```bash
# Rodar do repo root (Hostinger ou local Herd):
cd D:\oimpresso.com
vendor\bin\pest --filter Officeimpresso/LicencaLog --testdox
# Esperado: ≥5 testes passando cobrindo casos reais do GET/index e GET/show:
#   - Wagner (biz=1, superadmin) lista todas máquinas
#   - User não-superadmin de outro business: 403
#   - Filtro business_id=4 só retorna máquinas de ROTA LIVRE
#   - Soft-delete preserva máquina mas oculta da listagem
#   - Timeline ordena DESC por dt_login
```

Cada tela ganha sua suite Pest **antes** de virar React. Sem Pest baseline, F3 não pode começar (CI gate workflow `mwart-gate.yml` bloqueia).

### F3 — Audit cockpit-runbook modo B (POR PR)

```bash
# Após criar/editar Pages/Officeimpresso/Logs/Index.tsx, rodar:
# (skill cockpit-runbook modo B — script ainda manual)
node .claude/skills/cockpit-runbook/audit-modo-b.mjs resources/js/Pages/Officeimpresso/Logs/Index.tsx
# Esperado: score ≥ 70, CRITICAL=0
```

Score < 70 ou qualquer CRITICAL **bloqueia merge**.

### F4 — Smoke biz=1 + canary 7d

**Specifically para Officeimpresso (superadmin-only):**

1. Habilitar flag `useV2OfficeimpressoLogs=true` em `pos_settings` SOMENTE pra biz=1 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — biz=1 é Wagner WR2 SC, NUNCA cliente real)
2. Wagner usa em produção (Hostinger) por 7 dias só ele
3. Monitorar `storage/logs/laravel.log` — qualquer ALERT/ERROR do path `/officeimpresso/*` paralisa a feature
4. Validar 4 fluxos críticos:
   - [ ] Listar 100+ máquinas (paginação funciona)
   - [ ] Filtrar por business — `business_id=4` (ROTA LIVRE) só mostra máquinas dela
   - [ ] Bloquear máquina → status muda + audit log entry criado
   - [ ] Desbloquear → reverte
5. Backup `licenca_computador` + `licenca_log` antes de qualquer mudança de schema

### F5 — Cutover

Como o módulo é **superadmin-only**, **não há cliente externo afetado**. Cutover = simplesmente:
1. Deletar Blade legacy (após 30d sem incidente)
2. Remover flag `useV2Officeimpresso<Tela>` do `pos_settings`
3. Remover branch dual no controller
4. Audit final do `Pages/Officeimpresso/<Tela>.tsx` ≥ 80

Sem cliente avisado, sem janela de manutenção. **Vantagem grande** vs migração de telas operativas.

---

## Pré-condições antes de começar

- [ ] PR #505 (UI polish Blade — semântica botão Bloquear + AÇÕES truncate) **mergeado** ✓ (já feito 2026-05-10)
- [ ] Spatie permission `superadmin` existe no DB + Wagner tem (cuidado: ausência atual em local dev — `php artisan tinker --execute="echo \App\User::find(1)->can('superadmin') ? 'OK' : 'FALTA'"`)
- [ ] DataController do Officeimpresso tem `'url' => '/officeimpresso/computadores'` no parent dropdown (Pegadinha #1 acima) — **sem isso a cascata Superadmin tem item morto**
- [ ] Skill irmãs carregadas no agente: `mwart-process` (Tier A), `mwart-quality` (auto), `multi-tenant-patterns` (Tier A), `sidebar-menu-arch` (sob demanda), `cockpit-runbook` (sob demanda)
- [ ] Sprint do Cycle ativo tem capacidade ou Wagner aprovou trabalho fora-de-cycle (módulo é superadmin, baixo cliente-signal — [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

---

## Pegadinhas Officeimpresso-specific (além de [GOTCHAS.md cockpit-runbook](../../../.claude/skills/cockpit-runbook/GOTCHAS.md))

1. **Topnav próprio do módulo é redundante** — [`nav.blade.php`](../../../Modules/Officeimpresso/Resources/views/layouts/nav.blade.php) cria 4-5 tabs (Office Impresso/Computadores/Licenças/Log). Após migração MWART, esse topnav some — substituído pelo `<BreadcrumbModuleDropdown>` do [`AppShellV2.tsx:131`](../../../resources/js/Layouts/AppShellV2.tsx#L131) que lê de `Modules/Officeimpresso/Resources/menus/topnav.php` (já existe, só precisa garantir cobertura `'can' => 'superadmin'` em items sensíveis)
2. **Layout próprio (`master.blade.php`) é vestigial** — só 18 linhas, não usado pelas views (todas extendem `layouts.app` UltimatePOS). DELETAR no F5
3. **Auto-tabela `licenca_log` cresce rápido** — ~1k entries/dia (heartbeat de 100+ máquinas). React DataTable client-side **trava com >5k rows**. Usar server-side pagination (Inertia partial reload) desde F3
4. **`format_date` shift +3h preservado** ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)) — se mostrar `dt_cadastro`/`dt_ultimo_acesso`, usar `format_date` (não `format_now_local`) pra manter compat com Delphi
5. **Triggers MySQL imutáveis em `licenca_log`** ([2026_04_23_200100_create_licenca_log_triggers.php](../../../Modules/Officeimpresso/Database/Migrations/2026_04_23_200100_create_licenca_log_triggers.php)) — append-only por design. Não permitir UPDATE/DELETE no React form (front bloqueia + back rejeita via trigger)
6. **PiiRedactor ativado** — IPs internos (`192.168.x.x`) e CNPJs de clientes do Wagner aparecem nas logs. Em screenshots/PRs sempre `[REDACTED]`. Skill commit-discipline (Tier A) já cobre

---

## Quando NÃO migrar (rejection criteria)

- Cliente externo **não pediu** (Officeimpresso é superadmin-only — [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) sinal qualificado fraco)
- Cycle ativo focado em outras frentes (atualmente Whatsapp/Manifestação/Inter PJ — Cycle 04)
- ROI baixo: módulo já tem UI Blade decente (KPI cards padronizados, DataTables com export, filtros funcionais)

**Critério verde pra começar:** Wagner explicitamente prioriza, OR métrica detecta gargalo (ex: tempo de bloqueio > 5min/dia, friction Felipe ao auditar log)

---

## Refs

- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico (mãe)
- [Skill mwart-process](../../../.claude/skills/mwart-process/SKILL.md) — Tier A, 5 fases
- [Skill mwart-comparative V4](../../../.claude/skills/mwart-comparative/SKILL.md) — gate visual F1.5
- [Skill mwart-quality](../../../.claude/skills/mwart-quality/SKILL.md) — 9 pré-flight checks
- [Skill sidebar-menu-arch](../../../.claude/skills/sidebar-menu-arch/SKILL.md) — DataController + SIDEBAR_GROUPS + SUPERADMIN_LABELS
- [Skill cockpit-runbook](../../../.claude/skills/cockpit-runbook/SKILL.md) — modo A (write) + modo B (audit)
- [LegacyMenuAdapter](../../../app/Services/LegacyMenuAdapter.php) — convert nwidart → JSON
- [shared.ts SUPERADMIN_LABELS](../../../resources/js/Components/cockpit/shared.ts) — set canônico
- [project_officeimpresso_modulo](../../auto-mem-archive/project_officeimpresso_modulo.md) — auto-mem deprecated, ver este RUNBOOK

---

**Última atualização:** 2026-05-10
