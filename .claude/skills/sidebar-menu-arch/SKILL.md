---
name: sidebar-menu-arch
description: Reconhecer, auditar e modificar a arquitetura do sidebar do AppShellV2 — DataController por módulo + agrupamento visual via SIDEBAR_GROUPS no frontend.
trust_level: L2
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
---

# Skill — Arquitetura do Sidebar (AdminSidebarMenu + SIDEBAR_GROUPS)

## Quando ativar

Sempre que a tarefa envolver:
- Adicionar, remover ou mover item no sidebar
- Criar um novo grupo visual (RH, Fiscal, Produtividade, etc.)
- Auditar ordem ou permissões de um item
- Entender por que um módulo não aparece no sidebar
- Mover módulo de um grupo visual para outro

## Princípio canônico (ratificado 2026-05-05)

> **Cada módulo publica SEU PRÓPRIO dropdown via `DataController::modifyAdminMenu()`. O agrupamento visual (RH/Fiscal/IA…) acontece NO FRONTEND, via lookup table `SIDEBAR_GROUPS` em `resources/js/Components/cockpit/Sidebar.tsx`.**

Padrão validado em `Modules/Financeiro` (módulo próprio) + grupo "Estoque" (itens core agrupados visualmente). Estendido em 2026-05-05 para HRM/Essenciais (RH), NFSe/NfeBrasil (FISCAL) e TeamMcp (IA & PRODUTIVIDADE).

**Não fazer:** `Menu::dropdown('NomeGrupo', ...)` cross-módulo dentro de `AdminSidebarMenu.php`. Causa acoplamento (PHP precisa conhecer todos os módulos do grupo) e gera bugs de escopo de closure (`use ($seg)` em PHP é não-óbvio). Histórico: bug "Undefined variable $seg" 2026-05-05 reverteu o pattern.

## Stack do sidebar (do backend ao React)

```
AdminSidebarMenu (middleware)
    └─ Menu::create('admin-sidebar-menu')   ← itens core UltimatePOS (order 5–85)
    └─ $moduleUtil->getModuleData('modifyAdminMenu')  ← DataController de cada módulo publica seu dropdown
         ↓
LegacyMenuAdapter.build()   ← converte nwidart → ShellMenuItem[] flat (children preservados)
         ↓
HandleInertiaRequests.share('shell.menu')
         ↓
AppShellV2.tsx → <SidebarMenu items={shellMenu} />
         ↓
Sidebar.tsx → SIDEBAR_GROUPS lookup → SidebarGroup (accordion) → SidebarMenuItem (popover-2 se children)
```

## Arquivos canônicos

| Arquivo | Papel |
|---------|-------|
| `app/Http/Middleware/AdminSidebarMenu.php` | Itens core UltimatePOS (order 5–85). **NÃO criar grupos cross-módulo aqui.** |
| `Modules/<Nome>/Http/Controllers/DataController.php` | `modifyAdminMenu()` publica o dropdown próprio do módulo |
| `app/Services/LegacyMenuAdapter.php` | Converte nwidart → JSON ShellMenuItem[] |
| `app/Services/ShellMenuBuilder.php` | Orquestra LegacyMenuAdapter |
| `resources/js/Components/cockpit/Sidebar.tsx` | **SIDEBAR_GROUPS + MENU_ICON_MAP** — agrupamento visual e ícones |
| `resources/css/cockpit.css` | Estilos `.sb-item`, `.sb-group`, `.sb-item.is-open` |

## Padrão único — DataController + SIDEBAR_GROUPS

### Backend — `Modules/<Nome>/Http/Controllers/DataController.php`

```php
public function modifyAdminMenu(): void
{
    $module_util = new ModuleUtil();

    if (auth()->user()->can('superadmin')) {
        $is_enabled = $module_util->isModuleInstalled('Nome');
    } else {
        $business_id = session()->get('user.business_id');
        $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
            $business_id, 'nome_module', 'superadmin_package'
        );
    }
    if (! $is_enabled) return;

    $usuario_pode_ver = auth()->user()->can('superadmin')
        || auth()->user()->can('nome.access');
    if (! $usuario_pode_ver) return;

    Menu::modify('admin-sidebar-menu', function ($menu) {
        $menu->dropdown('Label do Módulo', function ($sub) {
            $sub->url(route('nome.index'), 'Subitem', [
                'icon'   => 'fa fas fa-icon',
                'active' => request()->segment(2) === 'sub',
            ]);
            // ... mais subitens com guards de permissão ...
        }, [
            'icon'   => 'fa fas fa-icon-grupo',
            'active' => request()->segment(1) === 'nome',
        ])->order(NN);
    });
}
```

### Frontend — `resources/js/Components/cockpit/Sidebar.tsx`

Em `MENU_ICON_MAP` (lookup label → Lucide icon):
```ts
const MENU_ICON_MAP: Record<string, LucideIcon> = {
  // ... existentes ...
  'label do módulo': IconFromLucide,  // case-insensitive
};
```

Em `SIDEBAR_GROUPS` (lookup label → grupo visual):
```ts
{
  key: 'fiscal',           // chave única, persiste estado expanded em localStorage
  label: 'FISCAL',         // header em uppercase
  items: ['NFSe', 'NF-e Brasil'],  // labels EXATOS dos dropdowns dos DataControllers
},
```

**Importante:** o `items[]` deve listar exatamente o `label` que o DataController passou para `$menu->dropdown('Label', ...)`. Lookup é case-insensitive mas literal-string.

## Grupos atuais (2026-05-05)

| Grupo (key) | Label visual | Items |
|-------------|--------------|-------|
| `office` | ACESSOS RÁPIDOS | Consulta de OS, Contatos, Produtos, Vendas, Orçamentos, Reparar |
| `fin` | FINANCEIRO | Despesas, Contas de pagamento, Contabilidade, Financeiro |
| `estoque` | ESTOQUE | Compras, Transferências, Ajuste de estoque, Gestão de ativos |
| `fiscal` | FISCAL | NFSe, NF-e Brasil |
| `rh` | RH | HRM, Essenciais, Ponto |
| `conhecimento` | CONHECIMENTO | Cofre de Memórias, Base de Conhecimento, Planilha |
| `rel` | RELATÓRIOS | Dashboard, Relatórios, Reservas, Pedidos, Cocina |
| `ia` | IA & PRODUTIVIDADE | Copiloto, ADS, CRM, Team MCP, Projeto, Project Mgmt |
| `mais` | MAIS (fallback) | Items não mapeados (ex.: Modelos de notificação) |
| **User dropdown** (footer) | (sem header) | **Gerenciamento de usuários, Configurações** — filtrados por `isUserMenuItem` em `shared.ts` |
| **Superadmin** (user dropdown cascata) | (cascata Shield) | Conector, Backup, CMS, Officeimpresso, Módulos — filtrados por `isSuperadminMenu` |

**Dois conjuntos vão pro footer (user dropdown), não pro menu principal:**
- `USER_MENU_LABELS` (shared.ts) → admin do dia-a-dia (usuários, configurações)
- `SUPERADMIN_LABELS` (shared.ts) → admin de plataforma (cascata "Superadmin")

## Checklist para adicionar item novo no sidebar

```
- [ ] 1. Criar/editar Modules/<Nome>/Http/Controllers/DataController.php
- [ ] 2. modifyAdminMenu() chama Menu::modify com dropdown próprio
- [ ] 3. Verificar 3 guards: módulo instalado + superadmin_package + permissão usuário
- [ ] 4. Escolher order() único no range 80–99 (módulos custom)
- [ ] 5. Adicionar entrada em MENU_ICON_MAP de Sidebar.tsx (lowercase)
- [ ] 6. Adicionar label em SIDEBAR_GROUPS no grupo correto
- [ ] 7. composer dump-autoload && php artisan optimize:clear
- [ ] 8. npm run build (se rodando produção) ou hot-reload pega na hora (dev)
- [ ] 9. Testar com usuário NÃO-superadmin pra validar guards
```

## Checklist para auditar item que sumiu do sidebar

```
- [ ] 1. DataController do módulo: modifyAdminMenu() retorna cedo? (check guards)
- [ ] 2. Módulo está instalado? (manage-modules → toggle Enable)
- [ ] 3. Usuário tem a permissão correta? (Roles → editar papel)
- [ ] 4. order() em conflito com outro item (mesmo número → undefined behavior)
- [ ] 5. LegacyMenuAdapter: item tem href? (items sem href são ignorados)
- [ ] 6. Label do dropdown bate com algum item de SIDEBAR_GROUPS? (case-insensitive)
       Se NÃO bate, item cai em "MAIS" (fallback).
- [ ] 7. php artisan optimize:clear resolveu?
```

## Diagnóstico rápido via browser

```js
// Console: inspecionar shell.menu recebido pelo Inertia
window.__inertiaPage?.props?.shell?.menu

// Ver qual grupo um label cai
const lookup = ['HRM', 'NFSe', 'Team MCP'].map(l => ({
  label: l,
  group: window.__inertiaPage?.props?.shell?.menu?.find(i => i.label === l)
}));
console.table(lookup);
```

## CSS — estados do item (`.sb-item`)

```css
.cockpit .sb-item                  { color: var(--sb-text); }                      /* default ESCURO */
.cockpit .sb-item:hover            { background: var(--sb-hover); color: var(--sb-text-hi); }
.cockpit .sb-item.active           { background: var(--sb-active); color: var(--sb-text-hi); }
.cockpit .sb-item.is-open          { background: transparent; color: var(--sb-text); }  /* aberto = igual default */
.cockpit .sb-item.is-open:hover    { background: var(--sb-hover); color: var(--sb-text-hi); }
```

**Regra Wagner 2026-05-05:** itens abertos NÃO clareiam — só hover ilumina. Estado `is-open` (popover-2 expandido) deve ficar visualmente idêntico ao default.

## Anti-padrões

- ❌ Criar `Menu::dropdown('Grupo', ...)` cross-módulo em `AdminSidebarMenu.php` — usar SIDEBAR_GROUPS no frontend
- ❌ DataController de módulo A criar dropdown que inclui itens do módulo B — cada módulo publica só o seu
- ❌ Esquecer entrada em `MENU_ICON_MAP` (label cai em ícone genérico Hash)
- ❌ Esquecer entrada em `SIDEBAR_GROUPS` (label cai no grupo "MAIS" no rodapé)
- ❌ Label divergente entre `dropdown('X', ...)` e `SIDEBAR_GROUPS items: ['X']` — não casa, cai em MAIS
- ❌ Esquecer `optimize:clear` após mudança PHP → menu não atualiza
- ❌ Usar order() duplicado → renderização não-determinística
- ❌ Testar com `superadmin` quando o item tem guard de permissão específica → falso positivo
- ❌ Clarear `.sb-item.is-open` no CSS — regra: só hover ilumina

## Histórico

- **2026-05-05** — Reorg parte 3 (Wagner): OFFICEIMPRESSO renomeado → ACESSOS RÁPIDOS. Novo grupo CONHECIMENTO (Cofre de Memórias + Base de Conhecimento + Planilha) — "Base de Conhecimento" extraída do dropdown Essenciais como item top-level no Essentials DataController. MemCofre/Memória removidos de SUPERADMIN_LABELS (vão pra CONHECIMENTO). Grupo NOTIFICAÇÕES eliminado — Modelos de notificação cai em fallback MAIS. 'Project Mgmt' adicionado ao grupo IA & PRODUTIVIDADE. Lang strings: `home.home` "Iniciar"→"Dashboard", `sale.sale` "vender"→"Vendas".
- **2026-05-05** — Reorg parte 2 (Wagner): Reparar entrou em OFFICEIMPRESSO. Gerenciamento de usuários + Configurações foram pro user dropdown footer (USER_MENU_LABELS em shared.ts). Grupo PRODUTIVIDADE removido — voltou pra IA & PRODUTIVIDADE com Projeto + Team MCP juntos.
- **2026-05-05** — Reorg parte 1: Iniciar/Home/Dashboard movidos para RELATÓRIOS. PontoWr2 label "Ponto WR2" → "Ponto" + entrou em RH. 'Conector' adicionado a SUPERADMIN_LABELS (regex single-n não batia o português).
- **2026-05-05** — Refactor: removidos grupos PHP cross-módulo (RH/Produtividade/Fiscal). Padrão único = DataController + SIDEBAR_GROUPS. Bug "Undefined variable $seg" foi gatilho.
- **2026-05-05** — CSS `.sb-item.is-open` parou de clarear (Wagner: "sempre escuro, só hover ilumina").
- **2026-04-27** — `MENU_ICON_MAP` + `SIDEBAR_GROUPS` introduzidos em `Sidebar.tsx` (UI-0011).

**Última atualização:** 2026-05-05
