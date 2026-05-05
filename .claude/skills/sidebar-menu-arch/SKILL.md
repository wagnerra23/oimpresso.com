---
name: sidebar-menu-arch
description: Reconhecer, auditar e modificar a arquitetura do sidebar do AppShellV2 — grupos, itens, ordens e DataControllers dos módulos.
---

# Skill — Arquitetura do Sidebar (AdminSidebarMenu)

## Quando ativar

Sempre que a tarefa envolver:
- Adicionar, remover ou mover item no sidebar
- Criar um novo grupo (RH, Fiscal, Produtividade, etc.)
- Auditar ordem ou permissões de um item
- Entender por que um módulo não aparece no sidebar
- Mover módulo de grupo ou de standalone para agrupado

## Stack do sidebar (do backend ao React)

```
AdminSidebarMenu (middleware)
    └─ Menu::create('admin-sidebar-menu')   ← itens core (order 5–85)
    └─ $moduleUtil->getModuleData('modifyAdminMenu')  ← DataController de cada módulo
    └─ Menu::modify(...)  ← grupos cross-módulo (RH/Fiscal/Produtividade) — ao final do middleware
         ↓
LegacyMenuAdapter.build()   ← converte nwidart → ShellMenuItem[] JSON
         ↓
HandleInertiaRequests.share('shell.menu')
         ↓
AppShellV2.tsx → <SidebarMenu items={shellMenu} />
```

## Arquivos canônicos

| Arquivo | Papel |
|---------|-------|
| `app/Http/Middleware/AdminSidebarMenu.php` | Core menu (order 5–85) + grupos cross-módulo (RH/Fiscal/Produtividade) ao final |
| `Modules/<Nome>/Http/Controllers/DataController.php` | `modifyAdminMenu()` adiciona item próprio do módulo |
| `app/Services/LegacyMenuAdapter.php` | Converte nwidart → JSON ShellMenuItem[] |
| `app/Services/ShellMenuBuilder.php` | Orquestra LegacyMenuAdapter |
| `app/Http/Middleware/HandleInertiaRequests.php` | Compartilha `shell.menu` via Inertia lazy |
| `resources/js/Layouts/AppShellV2.tsx` | Renderiza `<SidebarMenu items={shellMenu} />` |

## Ordem atual dos grupos

| Order | Item | Fonte |
|-------|------|-------|
| 3 | Módulos (manage-modules) | AdminSidebarMenu |
| 4 | Backup | AdminSidebarMenu |
| 5 | Home | AdminSidebarMenu |
| 10 | User Management | AdminSidebarMenu |
| 15 | Contacts | AdminSidebarMenu |
| 20–85 | Core business (Products, Sales, Reports…) | AdminSidebarMenu |
| 87 | PontoWr2 | PontoWr2/DataController |
| 88 | **RH** (HRM + Essenciais) | AdminSidebarMenu (grupo cross-módulo) |
| 90 | Copiloto | Copiloto/DataController |
| 92 | **Produtividade** (Team MCP) | AdminSidebarMenu (grupo cross-módulo) |
| 93 | **Fiscal** (NFSe + NF-e/NFC-e) | AdminSidebarMenu (grupo cross-módulo) |

## Dois padrões de item

### Padrão A — Módulo próprio (DataController)
Usar quando: **um módulo = um grupo** com suas próprias páginas.

```php
// Modules/<Nome>/Http/Controllers/DataController.php
public function modifyAdminMenu(): void
{
    // ... checks de módulo habilitado + permissão ...
    Menu::modify('admin-sidebar-menu', function ($menu) {
        $menu->dropdown('Label do Grupo', function ($sub) {
            $sub->url(route('nome.index'), 'Subitem', ['icon' => 'fa fas fa-icon', 'active' => ...]);
        }, ['icon' => 'fa fas fa-icon-grupo', 'active' => ...])->order(NN);
    });
}
```

### Padrão B — Grupo cross-módulo (AdminSidebarMenu.php)
Usar quando: **múltiplos módulos formam um grupo** e cada DataController sozinho criaria duplicatas.

```php
// ao final de AdminSidebarMenu.php, APÓS $moduleUtil->getModuleData('modifyAdminMenu')
$g   = new ModuleUtil;
$bid = session()->get('user.business_id');

if (/* módulo A ou B habilitado e usuário pode ver */) {
    Menu::modify('admin-sidebar-menu', function ($menu) use ($has_a, $has_b) {
        $seg = request()->segment(1);
        // ⚠️ PHP: $seg definida aqui NÃO está disponível em closures internas sem use explícito.
        // A inner closure PRECISA de use ($seg) se ela ou seu array de opções referencia $seg.
        $menu->dropdown('Nome do Grupo', function ($sub) use ($has_a, $has_b, $seg) {
            if ($has_a) { $sub->url(url('/modulo-a'), 'Módulo A', ['icon' => '...', 'active' => $seg === 'modulo-a']); }
            if ($has_b) { $sub->url(url('/modulo-b'), 'Módulo B', ['icon' => '...', 'active' => $seg === 'modulo-b']); }
        }, ['icon' => 'fa fas fa-icon', 'active' => in_array($seg, ['modulo-a', 'modulo-b'])])->order(NN);
    });
}

// Caso com sub-dropdown aninhado (ex.: grupo que agrupa módulos complexos):
if (/* ... */) {
    Menu::modify('admin-sidebar-menu', function ($menu) {
        $seg = request()->segment(1);
        $menu->dropdown('Nome do Grupo', function ($sub) use ($seg) {
            // $seg precisa de use aqui se usada em $sub->dropdown() ou urls internas
            $sub->dropdown(
                'Sub-módulo',
                function ($s) { /* $s->url(...) */ },
                ['icon' => '...', 'active' => $seg === 'sub-modulo']  // usa $seg → precisa do use acima
            );
        }, ['icon' => '...', 'active' => $seg === 'nome-grupo'])->order(NN);
    });
}
```

Quando usar Padrão B: **desativar** o `modifyAdminMenu()` dos módulos membros:
```php
// Modules/<Membro>/Http/Controllers/DataController.php
public function modifyAdminMenu(): void
{
    // Agrupado em "Nome do Grupo" pelo AdminSidebarMenu
    return;
    // @codeCoverageIgnoreStart  ... código original mantido para referência ...  // @codeCoverageIgnoreEnd
}
```

## Checklist para adicionar/mover item no sidebar

```
- [ ] 1. Identificar se é padrão A (módulo próprio) ou B (cross-módulo)
- [ ] 2. Se padrão A: ler/criar DataController do módulo com modifyAdminMenu()
- [ ] 3. Se padrão B: adicionar em AdminSidebarMenu.php após getModuleData()
- [ ] 4. Verificar checks: módulo instalado + superadmin_package + permissão do usuário
- [ ] 5. Escolher order() respeitando a tabela acima (sem colisões)
- [ ] 6. Se movendo de standalone → grupo: desativar DataController membro
- [ ] 7. php artisan optimize:clear após deploy
- [ ] 8. Verificar no browser que o item aparece com o usuário correto
```

## Checklist para auditar item que sumiu do sidebar

```
- [ ] 1. Ler DataController do módulo: modifyAdminMenu() retorna cedo?
- [ ] 2. Módulo está instalado? (manage-modules → toggle Enable)
- [ ] 3. Usuário tem a permissão correta? (Roles → editar papel)
- [ ] 4. order() em conflito com outro item (mesmo número → undefined behavior)
- [ ] 5. LegacyMenuAdapter: item tem href? (items sem href são ignorados)
- [ ] 6. php artisan optimize:clear resolveu?
```

## Diagnóstico rápido via browser

```js
// Console do browser: inspecionar menu recebido pelo Inertia
window.__inertiaPage?.props?.shell?.menu
```

Ou via React DevTools: buscar `SidebarMenu` e verificar `items` prop.

## Anti-padrões

- ❌ Dois DataControllers criando `dropdown('Fiscal', ...)` → duas entradas "Fiscal" no sidebar
- ❌ Esquecer `optimize:clear` após mudança em PHP → menu não atualiza
- ❌ Usar order() duplicado → renderização não-determinística
- ❌ Colocar grupo cross-módulo em DataController de módulo único → acoplamento ruim
- ❌ Testar com usuário `superadmin` quando o item tem guard de permissão específica → falso positivo
- ❌ **Closure PHP sem `use` explícito** — variável definida na outer closure (`$seg`, `$has_x`) NÃO está disponível em inner closures. PHP não captura escopo externo automaticamente (ao contrário de JS). Sempre declarar `use ($seg, $has_x)` em TODA closure que referencia essas variáveis, incluindo arrays de opções passados como 3º arg de `$sub->dropdown()` que estão dentro da inner closure.

**Última atualização:** 2026-05-05
