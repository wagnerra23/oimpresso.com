---
name: sidebar-menu-arch
description: Reconhecer, auditar e modificar a arquitetura do sidebar do AppShellV2 — DataController por módulo + agrupamento visual via SIDEBAR_GROUPS no frontend. ATENÇÃO 2026-05-25: ADR 0180 (aceito 2026-05-21) supersede o padrão `Menu::dropdown` com sub-itens — agora canon é href direto + ghosts no PageHeader. Ler "Princípio canônico v3" abaixo antes de aplicar.
trust_level: L2
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
tier: B
parent_adr: 0095
supersedes_partially_via_adr: 0180
---

# Skill — Arquitetura do Sidebar (AdminSidebarMenu + SIDEBAR_GROUPS)

> ⚠️ **CONFLITO HISTÓRICO RESOLVIDO 2026-05-25:** Esta skill foi escrita 2026-05-05 com padrão v2
> (`Menu::dropdown('Label', sub-items)` popup-menu). [ADR 0180](../../../memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md)
> aceita 2026-05-21 **supersedes parcialmente** este padrão:
> - **Item sidebar é SINGLE-LINK** (`'href' => '/destino'`) — NUNCA dropdown popup-menu
> - **Sub-views (ghosts) viram tabs no PageHeader Zona C** — não vivem mais no sidebar
> - **Cmd+K cobre power-user** que quer pular direto
>
> Pattern justificado: "Sidebar é mapa de DESTINOS, não de AÇÕES" (ADR 0180 § Justificativa).
> Skill mantida pra documentar o **lifecycle backend** (DataController + SIDEBAR_GROUPS grouping)
> que continua válido, mas a parte de "como compor sub-itens" foi reescrita pelo contrato
> DataController v2 (ADR 0180 § Contrato DataController v2 — `'href'` + `'ghosts'[]`).
>
> Anti-padrão AP19 (LEARNINGS PageHeader sessão 2026-05-25):
> usar `Menu::dropdown('X', $sub => $sub->url(...))` com popup é PROIBIDO no canon v3.

## Quando ativar

Sempre que a tarefa envolver:
- Adicionar, remover ou mover item no sidebar
- Criar um novo grupo visual (RH, Fiscal, Produtividade, etc.)
- Auditar ordem ou permissões de um item
- Entender por que um módulo não aparece no sidebar
- Mover módulo de um grupo visual para outro

## Princípio canônico v3 (ratificado 2026-05-21 via ADR 0180 — supersede v2 de 2026-05-05)

> **Cada módulo publica UM ÚNICO item single-link via `DataController::items()` contrato v2 (ADR 0180).
> O item tem `'href' => /destino'` direto + `'ghosts' => [...]` que viram tabs no PageHeader Zona C
> da tela destino. O agrupamento visual (VENDER/OPERAR/FINANÇAS/PESSOAS/SISTEMA) acontece NO
> FRONTEND, via lookup `SIDEBAR_GROUPS` em `Sidebar.tsx` (5 keys canon, não 11).**

**3 mudanças vs v2:**
1. ❌ `Menu::dropdown('Label', $sub => $sub->url(...))` com popup-menu → ✅ item single-link `'href' => '/X'`
2. ❌ Sub-items vivendo no sidebar (popup ao hover) → ✅ ghosts viram **tabs no PageHeader Zona C** da tela
3. ❌ 11 grupos visuais → ✅ 5 grupos canônicos (VENDER · OPERAR · FINANÇAS · PESSOAS · SISTEMA) + 3 topo (IA · ATENDIMENTO · EQUIPE)

**Razão (ADR 0180 § Justificativa):** "Sidebar persistente como mapa de DESTINOS, não de AÇÕES.
Sub-funções são contextuais da tela — ghost ARIA tablist preserva acessibilidade. Hierarquia
in-screen escala 5→50 features sem restructure (padrão Linear/Notion/Vercel/Stripe)."

### Princípio canônico v2 (HISTÓRICO — descontinuado por ADR 0180)

~~Cada módulo publica SEU PRÓPRIO dropdown via `DataController::modifyAdminMenu()` com `Menu::dropdown('Label', $sub => ...)` agrupando sub-itens.~~

Padrão validado em 2026-05-05 (Financeiro, HRM/Essenciais, NFSe/NfeBrasil, TeamMcp) mas **substituído** 16 dias depois pela arquitetura v3 da ADR 0180. Razão da substituição: popup-menu duplica navegação (sidebar + header da tela mostram as mesmas opções), violando "uma única forma de acessar uma página" (Constituição UI v2 ADR UI-0013).

**Anti-padrão v2 mantido (continua proibido):** `Menu::dropdown('NomeGrupo', ...)` cross-módulo dentro de `AdminSidebarMenu.php`. Causa acoplamento (PHP precisa conhecer todos os módulos do grupo) e gera bugs de escopo de closure (`use ($seg)` em PHP é não-óbvio). Histórico: bug "Undefined variable $seg" 2026-05-05 reverteu o pattern.

**Anti-padrão v3 NOVO (ADR 0180 v3 introduziu):** `Menu::dropdown('LabelModulo', $sub => $sub->url(...)->url(...))` MESMO se for SÓ do próprio módulo (não cross). O item sidebar deve ser SINGLE-LINK; sub-itens viram ghosts no PageHeader Zona C da tela destino. Anti-padrão AP19 catalogado em PageHeader-LEARNINGS sessão 2026-05-25.

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

## Padrão canon v3 — DataController contrato v2 (ADR 0180) + SIDEBAR_GROUPS

### Backend — `Modules/<Nome>/Sidebar/DataController.php` (contrato v2)

```php
// ADR 0180 v3 — item SINGLE-LINK + ghosts (que viram tabs no PageHeader Zona C)
public function items(int $businessId): array
{
    if (!$this->isInstalledFor($businessId)) {
        return [];  // Tier 0 multi-tenant — tenant sem módulo não declara (ADR 0093)
    }

    if (!auth()->user()->can('nome.access')) {
        return [];  // permissão usuário
    }

    return [[
        'label'    => 'Label do Módulo',             // ← label canônico
        'href'     => route('nome.index'),           // ← SINGLE-LINK direto
        'icon'     => 'icon-name',                   // ← Lucide icon name
        'group'    => 'vender',                      // ← uma das 5: vender/operar/financas/pessoas/sistema
        'shortcut' => 'G N',                         // ← atalho kbd sidebar (G + letra grupo + letra item)
        'primary'  => [                              // ← botão "+ Novo X" colorido no PageHeader Zona R
            'label'    => 'Novo X',
            'href'     => route('nome.create'),
            'shortcut' => 'N',
        ],
        'ghosts'   => [                              // ← header tabs Zona C (NÃO popup do sidebar)
            ['key' => 'todos',      'label' => 'Todos',     'href' => '/nome?tab=todos'],
            ['key' => 'sub1',       'label' => 'Sub 1',     'href' => '/nome?tab=sub1'],
            ['key' => 'sub2',       'label' => 'Sub 2',     'href' => '/nome?tab=sub2'],
            // ... até 5 ghosts inline · 6+ vira "⋯ Mais" dropdown no PageHeader
        ],
    ]];
}
```

### Backend — `Modules/<Nome>/Http/Controllers/DataController.php` (contrato v1 — DEPRECATED por ADR 0180)

> ⚠️ **NÃO USAR EM CÓDIGO NOVO.** Mantido aqui só pra entender módulos legados ainda não migrados.

```php
// ❌ DEPRECATED — usar items() contrato v2 acima (ADR 0180)
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
        // ❌ ANTI-PADRÃO v3: dropdown com sub-itens é proibido (AP19)
        // Use items() contrato v2 com 'href' direto + 'ghosts' que viram tabs no header
        $menu->dropdown('Label do Módulo', function ($sub) {
            $sub->url(route('nome.index'), 'Subitem', [...]);
        }, [...])->order(NN);
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

### v3 (ADR 0180 — banidos 2026-05-21)

- ❌ **AP19** — `Menu::dropdown('Label', $sub => $sub->url(...)->url(...))` com popup-menu, MESMO se for só do próprio módulo. Use `items()` contrato v2 com `'href'` direto + `'ghosts'[]` que viram tabs no PageHeader Zona C da tela destino. Razão: "Sidebar é mapa de DESTINOS, não AÇÕES" (ADR 0180 § Justificativa)
- ❌ Duplicar navegação (sidebar popup + header tabs mostrando as mesmas opções) — viola "uma única forma de acessar uma página" (Constituição UI v2 ADR UI-0013)
- ❌ Mais que 5 ghosts visíveis no PageHeader Zona C — overflow vira "⋯ Mais (N)" dropdown
- ❌ Sub-views no sidebar quando deveriam estar no header (`Crm > Contatos > [Fornecedores, Clientes, Grupos, Importar]` = AP19)

### v2 mantidos (continuam proibidos)

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

- **2026-05-25** — **Skill reconciliada com ADR 0180.** Detectado conflito (Wagner sessão `/contacts?type=customer` perguntou "qual link deve ter popup?"). Skill v2 ainda descrevia `Menu::dropdown('Label', sub)` com popup, mas ADR 0180 (aceito 2026-05-21) já banira esse padrão em favor de single-link `'href'` + ghosts no PageHeader Zona C. Adicionado: princípio canônico v3 no topo, contrato `items()` v2 no corpo, anti-padrão AP19, nota de conflito histórico no preâmbulo. v1 (`modifyAdminMenu()`) mantida como DEPRECATED pra entender módulos legados ainda não migrados. ADR 0180 Fase 4 (migração 17 DataControllers) NÃO completou — Cliente/Contatos ainda mostra popup em prod.
- **2026-05-21** — [ADR 0180](../../../memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) aceita. Sidebar v3 redefine arquitetura: 5 grupos canônicos (VENDER/OPERAR/FINANÇAS/PESSOAS/SISTEMA) + 3 topo (IA/ATENDIMENTO/EQUIPE). Item sidebar = single-link `'href'`. Sub-views = ghost ARIA tablist no PageHeader Zona C. Cmd+K global pra power-user. Pinned/Favoritos localStorage scoped business_id.
- **2026-05-05** — Reorg parte 3 (Wagner): OFFICEIMPRESSO renomeado → ACESSOS RÁPIDOS. Novo grupo CONHECIMENTO (Cofre de Memórias + Base de Conhecimento + Planilha) — "Base de Conhecimento" extraída do dropdown Essenciais como item top-level no Essentials DataController. MemCofre/Memória removidos de SUPERADMIN_LABELS (vão pra CONHECIMENTO). Grupo NOTIFICAÇÕES eliminado — Modelos de notificação cai em fallback MAIS. 'Project Mgmt' adicionado ao grupo IA & PRODUTIVIDADE. Lang strings: `home.home` "Iniciar"→"Dashboard", `sale.sale` "vender"→"Vendas".
- **2026-05-05** — Reorg parte 2 (Wagner): Reparar entrou em OFFICEIMPRESSO. Gerenciamento de usuários + Configurações foram pro user dropdown footer (USER_MENU_LABELS em shared.ts). Grupo PRODUTIVIDADE removido — voltou pra IA & PRODUTIVIDADE com Projeto + Team MCP juntos.
- **2026-05-05** — Reorg parte 1: Iniciar/Home/Dashboard movidos para RELATÓRIOS. PontoWr2 label "Ponto WR2" → "Ponto" + entrou em RH. 'Conector' adicionado a SUPERADMIN_LABELS (regex single-n não batia o português).
- **2026-05-05** — Refactor: removidos grupos PHP cross-módulo (RH/Produtividade/Fiscal). Padrão único = DataController + SIDEBAR_GROUPS. Bug "Undefined variable $seg" foi gatilho.
- **2026-05-05** — CSS `.sb-item.is-open` parou de clarear (Wagner: "sempre escuro, só hover ilumina").
- **2026-04-27** — `MENU_ICON_MAP` + `SIDEBAR_GROUPS` introduzidos em `Sidebar.tsx` (UI-0011).

**Última atualização:** 2026-05-25 (reconciliação com ADR 0180 — supersede parcial do padrão v2)
