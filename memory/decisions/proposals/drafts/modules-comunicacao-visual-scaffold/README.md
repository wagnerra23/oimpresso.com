# Drafts — scaffold Modules/ComunicacaoVisual (Sprint 1)

> **Status:** drafts revisaveis (NAO copiados em Modules/ ainda)
> **Owner Sprint 1:** Felipe [F]
> **SPEC:** [memory/requisitos/ComunicacaoVisual/SPEC.md](../../../../requisitos/ComunicacaoVisual/SPEC.md)
> **RUNBOOK base:** [memory/requisitos/Infra/RUNBOOK-criar-modulo.md](../../../../requisitos/Infra/RUNBOOK-criar-modulo.md)
> **Validado contra:** Modules/ADS (2026-05-03) + Modules/ConsultaOs (2026-05-04)

## O que tem aqui

8 arquivos draft cobrindo as 8 pecas obrigatorias do RUNBOOK-criar-modulo:

| # | Arquivo | Peca RUNBOOK |
|---|---|---|
| 1 | `composer.json` | psr-4 + provider list |
| 2 | `module.json` | nWidart provider list + alias `comvis` |
| 3 | `Providers/ComunicacaoVisualServiceProvider.php` | boot + register + loadTranslations |
| 4a | `Routes/web.php` | 3 rotas Install OBRIGATORIAS + admin stack canonica |
| 4b | `Routes/api.php` | placeholder Sprint 1 |
| 5 | `Http/Controllers/InstallController.php` | extends BaseModuleInstallController |
| 6 | `Http/DataController.php` | 3 hooks UltimatePOS (superadmin_package + user_permissions + modifyAdminMenu) |
| 7 | `database/migrations/2026_07_01_000001_create_comunicacao_visual_settings_table.php` | scaffold settings inicial (NAO domain tables) |

## O que NAO tem (proposital)

- ❌ Models/Entities (Material/Orcamento/OS) — entregam em PRs por US
- ❌ Services (OrcamentoCalculator/PosCalculoService) — idem
- ❌ Pages React (`resources/js/Pages/ComunicacaoVisual/*`) — exigem charter + visual-comparison MWART
- ❌ Migrations das tabelas de dominio — uma por US (`comvis_materiais`, `comvis_orcamentos`, etc)
- ❌ Listeners (BoletoPagoEmiteNFCe) — reuso US-RB-044, entrega em US-COMVIS-009 adapter

Razao: Sprint 1 entrega APENAS o esqueleto pra modulo aparecer instalavel em
`/manage-modules` com botao Install funcional. Features sao incrementais.

## Passo-a-passo Felipe (transformar drafts em PR real)

### 1. Criar pasta real (depois de aprovacao Wagner)

```powershell
# Da raiz do repo, em worktree dedicada:
git worktree add .claude/worktrees/sprint-1-comvis-scaffold -b feature/comvis-scaffold

# Copiar drafts pra Modules/ComunicacaoVisual/
mkdir Modules/ComunicacaoVisual
mkdir Modules/ComunicacaoVisual/Providers
mkdir Modules/ComunicacaoVisual/Routes
mkdir Modules/ComunicacaoVisual/Http
mkdir Modules/ComunicacaoVisual/Http/Controllers
mkdir Modules/ComunicacaoVisual/Database
mkdir Modules/ComunicacaoVisual/Database/Migrations
mkdir Modules/ComunicacaoVisual/Config
mkdir Modules/ComunicacaoVisual/Resources
mkdir Modules/ComunicacaoVisual/Resources/lang
mkdir Modules/ComunicacaoVisual/Resources/lang/pt-BR

# Copiar cada draft pra seu destino real.
```

### 2. Ajustes obrigatorios apos copiar

- `Http/DataController.php` → mover de `Http/DataController.php` pra
  `Http/Controllers/DataController.php` (nao colocar fora de Controllers/ —
  UltimatePOS espera ali). O arquivo no draft ja tem o namespace correto
  `Modules\ComunicacaoVisual\Http\Controllers`.
- `Database/Migrations/<timestamp>_create_comunicacao_visual_settings_table.php` →
  ajustar timestamp pro dia real do PR.
- Criar `Config/config.php` minimo (ADS tem como referencia).
- Criar `Resources/lang/pt-BR/comvis.php` retornando array `[]` vazio
  (placeholder — labels do menu ficam hardcoded mesmo, ver pegadinha
  `__('comvis::xxx')` no DataController).
- Criar `Providers/RouteServiceProvider.php` imitando ADS (mapWebRoutes + mapApiRoutes).

### 3. Registrar no nWidart

Adicionar em `modules_statuses.json` (raiz do repo):

```json
"ComunicacaoVisual": true
```

Sem essa entrada o modulo NAO aparece em /manage-modules.

### 4. Pest LOCAL antes de PR (regra Wagner 2026-05-09)

> Auto-mem: `feedback_tenancy_changes_require_pest_local` — Wagner NAO autoriza
> mudancas tocando scope/Controller/Model multi-tenant baseadas em analise estatica.

```powershell
# 1. Lint PHP (deve passar limpo)
php -l Modules/ComunicacaoVisual/Http/Controllers/InstallController.php
php -l Modules/ComunicacaoVisual/Http/Controllers/DataController.php
php -l Modules/ComunicacaoVisual/Routes/web.php
php -l Modules/ComunicacaoVisual/Providers/ComunicacaoVisualServiceProvider.php

# 2. Composer dump-autoload
composer dump-autoload --no-scripts

# 3. Confirmar que namespace registrou
composer dump-autoload --no-scripts 2>&1 | grep -i "ComunicacaoVisual"

# 4. As 3 rotas Install resolvem?
php artisan route:list --path=comvis/install
# Deve listar 3 linhas — install, install/uninstall, install/update.

# 5. Pest local — modulo carrega sem fatal?
php artisan module:list
php artisan tinker --execute="dump(\Module::find('ComunicacaoVisual')?->isEnabled());"

# 6. Criar Pest test minimo (NAO commit sem isso):
# Modules/ComunicacaoVisual/Tests/Feature/InstallTest.php cobrindo:
#   - GET /comvis/install retorna 200 (logado superadmin)
#   - apos install, system table tem `comvis_version=0.1.0`
#   - DataController.user_permissions retorna array com `comvis.access`
#   - DataController.superadmin_package retorna comvis_module
# Registrar em phpunit.xml senao CI nao roda (proibicao Tier 0).
```

### 5. Validacao manual no Herd

```
1. Login superadmin em http://oimpresso.test
2. /manage-modules → card ComunicacaoVisual aparece
3. Clicar Install → vai pra /comvis/install (NAO #)
4. Apos install, sidebar admin mostra "Comunicacao Visual" com link "Painel (em construcao)"
5. /roles → permissoes comvis.* aparecem
6. /superadmin/packages → flag `comvis_module` aparece
```

### 6. PR (commit-discipline Tier A)

```
feat(comvis): scaffold inicial Modules/ComunicacaoVisual [F]

- 8 pecas obrigatorias RUNBOOK-criar-modulo
- DataController stub (sidebar minima)
- Migration scaffold comvis_settings (multi-tenant Tier 0)
- 3 rotas Install funcionais (ADR 0024)
- Pest InstallTest verde local

Refs: SPRINT-1-COMVIS PASSO 1
SPEC: memory/requisitos/ComunicacaoVisual/SPEC.md
ADR: 0121
```

≤300 linhas, 1 PR = 1 intent (so scaffold, sem features).

## Esforco estimado

| Etapa | Esforco IA-pair (Felipe + Claude) |
|---|---|
| Copiar drafts + ajustes pos-copia (passo 1+2) | 1h |
| Config/config.php + RouteServiceProvider + lang stub (passo 2) | 0,5h |
| modules_statuses.json + composer dump-autoload (passo 3) | 0,2h |
| Pest InstallTest (passo 4) — 4 cases | 1,5h |
| Validacao manual Herd (passo 5) | 0,5h |
| Commit + PR + review Wagner (passo 6) | 0,5h |
| **Total** | **~4,2h** |

Com fator 10x IA-pair (ADR 0106) e margem 2x: **~5h Felipe + Claude**.

## Cuidados criticos

1. **Multi-tenant Tier 0 (ADR 0093) IRREVOGAVEL** — toda Eloquent Model nova
   precisa de `BusinessIdScope` global. Skill `multi-tenant-patterns` Tier A.
   Pest test obrigatorio: 2 businesses nao se enxergam.

2. **DataController PRECISA estar em `Http/Controllers/`** (nao `Http/`) —
   UltimatePOS hardcoda `Modules\<X>\Http\Controllers\DataController` no
   middleware AdminSidebarMenu. Audit 2026-04-26 catalogou esse erro.

3. **Labels NAO usar `__('comvis::xxx')` em DataController/topnav** —
   LegacyMenuAdapter le literal, sai cru "comvis::xxx.yyy" em prod.
   Hardcodar PT-BR (mesma regra NFSe/ADS).

4. **As 3 rotas Install sao OBRIGATORIAS** mesmo com modulo so admin.
   Sem elas, app/Http/Controllers/Install/ModulesController.php cai no catch
   e botao Install vira href="#" (incidente Wagner 2026-05-04 ConsultaOs).

5. **Pest LOCAL antes de PR** — regra Wagner 2026-05-09. Mudanca em scope
   multi-tenant SEM Pest verde rodado no Herd nao passa review.

## Quando esses drafts viram codigo real?

- Felipe revisar este README + 8 arquivos
- Wagner aprovar tonalidade (especialmente DataController.modifyAdminMenu — visual sidebar)
- Felipe abre worktree dedicada, copia, ajusta, roda Pest, abre PR
- Apos merge, fazer SSH composer install no Hostinger (ADR 0062 — composer.json mudou)

## Links canonicos

- [SPEC](../../../../requisitos/ComunicacaoVisual/SPEC.md)
- [RUNBOOK-criar-modulo](../../../../requisitos/Infra/RUNBOOK-criar-modulo.md)
- [ADR 0011 imitar referencias](../../../0011-alinhamento-padrao-jana.md)
- [ADR 0024 Install 1-clique](../../../0024-instalacao-1-clique-modulos.md)
- [ADR 0093 Multi-tenant Tier 0](../../../0093-multi-tenant-isolation-tier-0.md)
- [ADR 0121 Modular especializado por vertical](../../../0121-oimpresso-modular-especializado-por-vertical.md)
- [Modules/ADS](../../../../../Modules/ADS/) — referencia validada 2026-05-03
- [Modules/ConsultaOs](../../../../../Modules/ConsultaOs/) — referencia validada 2026-05-04
