---
date: 2026-05-13 09:40 BRT
slug: modulos-cascata-auditoria-instalacao
prs_session: [750, 751, 752, 756, 760, 762]
prs_mergeadas_session: [750, 751, 752, 756, 760, 762]
prs_pendentes_decisao_wagner: []
total_session_linhas: ~210 (168 PR #750 + ~40 PRs hotfix Auditoria + skill pegadinha #8)
contexto_anterior: handoffs/2026-05-12-1700-wave-ab-consolidacao-bloqueios.md
---

# Handoff 2026-05-13 09:40 — Módulos não instaláveis (cascata Auditoria 5 PRs)

## TL;DR pra próxima sessão

Sintoma Wagner: "OficinaAuto não aparece no menu. Indica que já foi instalado mas não instala."

Diagnóstico via Chrome MCP + SSH revelou **3 problemas distintos em 6 módulos**. Primeiro PR (#750) resolveu 5 dos 6 com 5 agents paralelos. Auditoria sozinho disparou **cascata de 4 bugs latentes** (PRs #751→#752→#756→#760) porque o módulo estava mergeado em PR #474 (semanas atrás) mas **nunca `[Enabled]` em runtime** — bugs invisíveis no CI.

Skill `criar-modulo` ganhou **pegadinha #8 + 4 checks pré-merge** (PR #762) pra travar esse padrão.

**6 PRs em ~1h25.** Sidebar prod agora mostra Oficina Auto + Comunicação Visual + Auditoria. Vestuario instalável (Wagner clica quando quiser). Grow/IProduction ignorados explicitamente pelo Wagner.

## Estado MCP no momento do fechamento

**`cycles-active`** — CYCLE-05 (Inter PJ prod + WhatsApp governança) · 17% decorrido · 10 dias restantes. Goal: Inter PJ Banking em prod com canary 7d + FICHA WhatsApp v2 aprovada + audit log shell. **Esta sessão não tocou no goal do cycle** — foi fix tangencial puxado por Wagner.

**`my-work` @wagner** — 30 tasks ativas (2 DOING / 9 BLOCKED / 19 TODO):
- DOING: US-WA-040 (multi-phone driver) + US-COPI-100 (NarrarSaudeEcosistemaJob)
- 9 BLOCKED (6 são US-NFE-043..048 Gold dormentes + FIN-4 ROTA LIVRE + COPI-23 HyDE + CMS-1 cms_pages)

**`decisions-search`** — ADR 0024 (Instalação 1-clique padronizada) é mãe do trabalho desta sessão; ADR 0002 (nWidart Laravel Modules) é base. Nenhuma ADR nova proposta — fixes ficam dentro do contrato existente.

Estado git canônico:
- **main HEAD:** 7b455903 (após PR #762)
- **Branches stale a limpar:** worktree `youthful-aryabhata-e32de3` foi deletado durante a sessão; branches `claude/fix-auditoria-enabled`, `claude/fix-auditoria-install-controller`, `claude/fix-sidebar-activity-log`, `claude/fix-auditoria-datacontroller-menu`, `claude/skill-criar-modulo-pegadinha-8`, `claude/youthful-aryabhata-e32de3` podem ser deletadas remoto (admin merge não removeu)

## PRs nesta sessão

| PR | Tipo | Linhas | Domínio | Status |
|---|---|---|---|---|
| [#750](https://github.com/wagnerra23/oimpresso.com/pull/750) | fix(modules) | 168 (8 arquivos) | Kebab moduleSystemKey + rotas Install + Pest convention test + skill pegadinha #6/#7 (5 agents paralelos) | ✅ merged |
| [#751](https://github.com/wagnerra23/oimpresso.com/pull/751) | fix(modules) | 1 | `"Auditoria": true` em modules_statuses.json | ✅ merged |
| [#752](https://github.com/wagnerra23/oimpresso.com/pull/752) | fix(auditoria) | 31 | InstallController reescrito (import errado + métodos abstract faltando) | ✅ merged |
| [#756](https://github.com/wagnerra23/oimpresso.com/pull/756) | fix(sidebar) | 7 | AdminSidebarMenu resilient `Route::has('auditoria.index')` fallback | ✅ merged |
| [#760](https://github.com/wagnerra23/oimpresso.com/pull/760) | fix(auditoria) | 6 | DataController `MenuBuilder::url()` correto (era `add()` que não existe) | ✅ merged |
| [#762](https://github.com/wagnerra23/oimpresso.com/pull/762) | docs(skill) | 28 | criar-modulo pegadinha #8 — ativar e fumigar antes do merge | ✅ merged |

**Total session:** ~241 linhas (vs ~6226 do handoff anterior — sessão tática focada).

## Diagnóstico técnico — 3 problemas em 6 módulos

### Sintoma original
Tela `/manage-modules` em prod (via Chrome MCP) mostrava 6 módulos com botão "Instalar" não funcional:
- OficinaAuto + ComunicacaoVisual ("Instalar" mesmo após install rodar)
- Auditoria + Grow + IProduction + Vestuario (botão Install com `href="#"` — link morto)

### Causa 1 — kebab moduleSystemKey (OficinaAuto + ComunicacaoVisual)

[`app/Utils/ModuleUtil.php:31`](../../app/Utils/ModuleUtil.php) faz `System::getProperty(strtolower($module_name).'_version')`. Pra `'OficinaAuto'` busca `oficinaauto_version` (sem hífen). Mas `InstallController::moduleSystemKey()` retornava `'oficina-auto'` (kebab) → install gravava chave errada em `system` table → `isModuleInstalled()` sempre false → `getModuleData('modifyAdminMenu')` pulava DataController → sidebar nunca montava item → tela continuava "Instalar".

Confirmado em prod via SSH `SELECT key, value FROM system WHERE key LIKE '%_version'`: tabela tinha `oficina-auto_version=0.1.0` e `comunicacao-visual_version=0.1.0` (install ROUDOU — só a chave estava errada).

**Fix:** moduleSystemKey() lowercase **sem hífen** (alinhado com 29 outros módulos canônicos: `consultaos`, `nfse`, `productcatalogue`, `recurringbilling`, `teammcp` etc).

**Pós-merge requereu UPDATE SQL prod renomeando chaves órfãs:**
```sql
UPDATE system SET `key`='oficinaauto_version'       WHERE `key`='oficina-auto_version';
UPDATE system SET `key`='comunicacaovisual_version' WHERE `key`='comunicacao-visual_version';
```

### Causa 2 — rotas Install ausentes (Grow, IProduction, Vestuario)

Sem `Route::get('install', ...)` registrada, o helper `action()` em [`app/Http/Controllers/Install/ModulesController.php:57`](../../app/Http/Controllers/Install/ModulesController.php) retorna `'#'` → botão Install fica visível mas sem ação. **Pegadinha §críticas da skill `criar-modulo` já catalogada** — mas faltou seguir.

Fix: adicionadas as 3 rotas canônicas (index/uninstall/update) em cada módulo. Vestuario também ganhou `InstallController` novo (era scaffold-only).

### Causa 3 — Auditoria cascata de 4 bugs latentes

Auditoria mergeado em PR #474 (semanas atrás) **sem entrada em `modules_statuses.json`** → nWidart marcava `[Disabled]` → providers/rotas/menu jamais executados → bugs invisíveis no CI.

Habilitar (PR #751) expôs **3 fatais em sequência**:

| Camada | Bug | Fix |
|---|---|---|
| `InstallController` | `use App\Http\Controllers\Install\BaseModuleInstallController` (namespace inexistente — canônico é sem subfolder `/Install`) + property `$moduleName` em vez dos 3 métodos abstract obrigatórios | PR #752 reescreveu inteiro espelhando OficinaAuto |
| `AdminSidebarMenu.php:739` (core, não módulo) | `action([ReportController::class, 'activityLog'])` quebrou porque `Modules/Auditoria/Routes/web.php:45` (ADR 0127 §F3) sobrescreve a rota legacy `/reports/activity-log` com redirect 301 | PR #756 fallback `Route::has('auditoria.index') ? route(...) : url('/reports/activity-log')` |
| `DataController::modifyAdminMenu` | `$menu->add('/auditoria', [...])` — API inexistente do MenuBuilder. Canônico é `$menu->url($url, $label, $attrs)` | PR #760 espelhou OficinaAuto/DataController:138 |

5 PRs em 1h, cada um expondo o próximo bug. Custo total ~1h25.

## Deploys + SQL prod aplicados

Cada PR mergeado disparou `quick-sync.yml` (auto) → git pull em Hostinger. Após cada deploy, rodei via SSH:
```bash
composer dump-autoload --no-scripts
php artisan route:clear && php artisan view:clear && php artisan config:clear
```

Mais o `UPDATE system` único (pós PR #750) renomeando 2 chaves órfãs.

## Lição catalogada — pegadinha #8 skill criar-modulo

> **CI verde NÃO valida módulo `[Disabled]`.** Sem entrada em `modules_statuses.json` (ou com `false`), `ServiceProvider` + `DataController` + `InstallController` + `Routes/web.php` permanecem **código morto** até alguém ativar. Bugs latentes (typo de namespace, método abstract não implementado, API errada de MenuBuilder) passam imunes.

**Antídoto adicionado em `.claude/skills/criar-modulo/SKILL.md`:**

```bash
# 1) Garantir entrada em modules_statuses.json
grep -E "\"<Nome>\"\s*:\s*true" modules_statuses.json || echo "FALTA"

# 2) Validar boot real do módulo
php artisan module:list | grep <Nome>           # deve mostrar [Enabled]
php artisan route:list --path=<prefix>/install  # 3 rotas
php artisan route:list --path=<prefix>          # rotas do módulo

# 3) Smoke runtime mínimo via tinker (executa DataController::modifyAdminMenu)
php artisan tinker --execute="
  Auth::loginUsingId(1);
  app('Illuminate\Routing\Router')->dispatch(
    Illuminate\Http\Request::create('/home', 'GET')
  );
  echo 'OK';
"
```

## Lição secundária — paralelização N agents validada de novo

Wave 1 spawnou 5 agents general-purpose simultâneos com:
- Áreas isoladas por path (sem overlap entre prompts)
- Regra Tier 0 "comparar com módulo referência ANTES de criar" (OficinaAuto pattern)
- Zero git ops nos agents (parent consolidou)
- Pre-leitura forçada de arquivos canônicos no prompt

Resultado: 5 agents entregaram em paralelo, parent consolidou em 1 PR único (168 linhas, ≤300 commit-discipline ✅). Pattern validado em handoffs anteriores (Wave A+B 2026-05-12 + FSM canon 2026-05-12) — agora reforçado.

**Pré-requisito crítico que funcionou:** worktree filha foi criada via `EnterWorktree` (não pelo agent — Claude Code spawnou). Subagents `general-purpose` na mesma worktree continuaram vivos (vs. handoff 2026-05-11-1830 frustrado quando agents foram spawned EM worktree filha = morreram).

## Estado final dos 6 módulos em prod

| Módulo | Estado pós-sessão |
|---|---|
| **OficinaAuto** | ✅ instalado, sidebar (Veículos + Ordens de Serviço) |
| **ComunicacaoVisual** | ✅ instalado, sidebar (Orçamentos + OS + Materiais + Apontamentos) |
| **Auditoria** | ✅ instalado, sidebar (link direto /auditoria) |
| **Vestuario** | ✅ botão Install funcional (ROTA LIVRE biz=4 não foi instalado formalmente ainda — Wagner clica quando quiser) |
| ⏸️ Grow | ignorado (Wagner: "esquece os outros") |
| ⏸️ IProduction | ignorado (idem) |

## Próximos passos pra próxima sessão

1. **Voltar ao goal CYCLE-05** — esta sessão foi tangencial. Inter PJ Banking em prod (US-RB-048/046/047) + WhatsApp FICHA v2 (US-WA-051/052) seguem em aberto.
2. **Branch cleanup:** deletar branches mergeadas remotas:
   ```bash
   for b in claude/youthful-aryabhata-e32de3 claude/fix-auditoria-enabled claude/fix-auditoria-install-controller claude/fix-sidebar-activity-log claude/fix-auditoria-datacontroller-menu claude/skill-criar-modulo-pegadinha-8; do
     gh api -X DELETE "repos/wagnerra23/oimpresso.com/git/refs/heads/$b" 2>&1 | head -1
   done
   ```
3. **Auditoria runtime** — agora que está ativo, vale smoke real `/auditoria/install` (Wagner clica Install pra disparar migrate + popular `auditoria_version` em `system`). Estado atual: módulo registrado mas tabelas Auditoria podem ainda não ter sido migradas (não confirmei).
4. **Pest regression** — `tests/Feature/Modules/InstallControllerKeyConventionTest.php` agora trava CI. Vale conferir que continua verde em main após todas as mudanças.

## Refs

- [ADR 0024 — Instalação 1-clique padronizada](../decisions/0024-instalacao-1-clique-modulos.md)
- [ADR 0002 — nWidart Laravel Modules](../decisions/0002-nwidart-laravel-modules.md)
- [ADR 0127 — Auditoria governança transversal](../decisions/0127-auditoria-governanca-transversal.md)
- [ADR 0121 — Oimpresso modular especializado por vertical](../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- [Skill criar-modulo (pegadinha #8 adicionada)](../../.claude/skills/criar-modulo/SKILL.md)
- [Pest convention test](../../tests/Feature/Modules/InstallControllerKeyConventionTest.php)
