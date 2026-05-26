---
name: Pattern sidebar ghost via NO-OP modifyAdminMenu
description: Quando módulo X "vira ghost" de hub Y no sidebar, DataController.modifyAdminMenu de X fica vazio + docblock explicando. Hub Y ganha ghost via attribute. Pattern emergente Wagner 2026-05-26, 4 aplicações.
type: reference
---

# Pattern — sidebar ghost via NO-OP `modifyAdminMenu`

Padrão emergente catalogado 2026-05-26 após 4 aplicações na mesma sessão (PRs #1588, #1591, #1595). Define como mover módulo X de **entry top-level no sidebar** para **ghost no PageHeader de hub Y** (canon ADR 0180).

## Quando aplicar

- Módulo X é **subordinado conceitualmente** a hub Y (canal de venda subordinado a Vendas, gateway subordinado a Cobrança, configuração subordinada a outra coisa).
- X tem **rotas próprias funcionais** que devem continuar acessíveis (não é deprecação, é só re-categorização visual).
- X tem **acesso ocasional** (configuração, não fluxo diário) — usuário acessa via PageHeader overflow `[...]` do hub Y, URL direta, ou Cmd+K.

## Estrutura canônica

### 1. Módulo X — `DataController.modifyAdminMenu` vira NO-OP

```php
namespace Modules\X\Http\Controllers;

use Illuminate\Routing\Controller;
// `use App\Utils\ModuleUtil;` — REMOVER se não usado em outros métodos do mesmo DataController
// `use Menu;` — SEMPRE remover (não chamamos mais Menu::modify)

class DataController extends Controller
{
    // ... superadmin_package() + user_permissions() permanecem ...

    /**
     * Camada visual sidebar — Wagner YYYY-MM-DD: NO-OP.
     *
     * X NÃO é mais entry própria do sidebar. Virou GHOST do hub Y
     * (definido em <PATH-DO-HUB>, ghost key='x-key' → /x-url).
     *
     * Justificativa Wagner YYYY-MM-DD:
     *  - <razão semântica curta — X é subordinado a Y, configuração de Y, canal de Y, etc>
     *  - Acesso via PageHeader overflow [...] da tela /y, URL direta, ou Cmd+K palette.
     *
     * Rotas /x/* CONTINUAM ativas (acesso preservado).
     * Permissions x.* CONTINUAM válidas (controlam Page render dos Controllers de X).
     *
     * Histórico (Wave/PR origem → mudança):
     *   YYYY-MM-DD — entrada original (versão de quando módulo nasceu)
     *   YYYY-MM-DD — DESLIGADO sidebar (vira ghost de Y)
     */
    public function modifyAdminMenu(): void
    {
        // No-op intencional. Ver docblock acima.
    }
}
```

### 2. Hub Y — adicionar ghost no attributes

**Caso A: Hub Y é módulo (`DataController` próprio com `Menu::url`)**

```php
$menu->url(url('/y'), 'Y Label', [
    'icon'    => '...',
    'group'   => '...',
    'primary' => ['label' => 'Ação Y', 'href' => '/y/create'],
    'ghosts'  => [
        ['key' => 'y-native',   'label' => 'Y',         'href' => '/y'],
        ['key' => 'x-as-ghost', 'label' => 'X Label',   'href' => '/x'],  // ← novo
    ],
])->order(NN);
```

**Caso B: Hub Y é dropdown legacy do core UltimatePOS (`AdminSidebarMenu.php`)**

Adicionar `'ghosts'` no 3º argumento (attributes array) do `$menu->dropdown(...)`:

```php
$menu->dropdown(
    __('label.key'),
    function ($sub) { /* children dropdown legacy preservados */ },
    [
        'icon'   => '<svg>...</svg>',
        'id'     => 'tour_stepN',
        'group'  => 'comercial',       // ← explicit pra match SIDEBAR_GROUPS
        'ghosts' => [                  // ← novo, LegacyMenuAdapter.php:322 pass-through
            ['key' => 'x-as-ghost', 'label' => 'X Label', 'href' => '/x'],
        ],
    ]
)->order(NN);
```

### 3. `resources/js/Components/cockpit/Sidebar.tsx` — limpar whitelist

Remover label de X do array `items[]` do grupo onde estava (módulo não aparece mais como entry top-level):

```diff
 {
   key: 'comercial',
   label: 'COMERCIAL',
-  items: ['Crm', 'CRM', 'Vendas', 'Oficina Auto', 'X Label', 'X Alias'],
+  items: ['Crm', 'CRM', 'Vendas', 'Oficina Auto'],
 },
```

Se X tinha ícones em `MENU_ICON_MAP`, **manter** (ghost ainda renderiza ícone via mesmo lookup).

### 4. Tests Pest atualizados

Se há test que validava entry de X no sidebar (`expect($src)->toContain("Menu::modify(...)")` ou similar), inverter:

```php
it('X DataController NÃO injeta sidebar (virou ghost)', function () {
    $src = file_get_contents(<path>);
    expect($src)->toContain('public function modifyAdminMenu(): void');
    expect($src)->not->toContain("Menu::modify");
});
```

## Aplicações 2026-05-26 (4 exemplos canônicos)

| Módulo X | Hub Y | PR | Justificativa |
|---|---|---|---|
| PaymentGateway (`/settings/payment-gateways`) | Financeiro/Cobrança (`/financeiro/cobranca`) | [#1588](https://github.com/wagnerra23/oimpresso.com/pull/1588) | Gateway = configuração de cobrança, não fluxo diário |
| ProductCatalogue (`/product-catalogue/catalogue-qr`) | Sells dropdown legacy (`/sells/*`) | [#1591](https://github.com/wagnerra23/oimpresso.com/pull/1591) | Canal de venda storefront QR |
| Woocommerce (`/woocommerce`) | Sells dropdown legacy (`/sells/*`) | [#1591](https://github.com/wagnerra23/oimpresso.com/pull/1591) | Canal de venda sync WordPress |
| Fiscal cockpit (`/fiscal`) | _removido sem virar ghost_ | [#1594](https://github.com/wagnerra23/oimpresso.com/pull/1594) | Duplicava com "Notas Fiscais" — Wagner pediu remoção pura |

## Diferença "ghost" vs "remoção pura"

| Cenário Wagner | O que fazer | Exemplo |
|---|---|---|
| "X vai para ghost de Y" | Aplicar este padrão completo (X NO-OP + Y ganha ghost) | Gateway, Catálogo QR, WooCommerce |
| "X duplicado/redundante, pode remover" | NO-OP apenas (sem ghost) — rota continua ativa via URL direta | Fiscal cockpit dashboard |
| "X deprecado" | Skill `deprecar-modulo` (escopo maior, schema diferente) | Não aplica aqui |

## Anti-patterns

- ❌ **Apagar o método `modifyAdminMenu` inteiro** — quebra contrato `AdminSidebarMenu` middleware que tenta chamar todos os DataControllers descobertos. Manter assinatura `public function modifyAdminMenu(): void` vazia.
- ❌ **Apagar rotas/Controllers de X** — esse padrão é só visual. Rotas continuam ativas. Permission gates continuam válidos.
- ❌ **Esquecer de remover imports** — após NO-OP, `use Menu;` é dead. `use App\Utils\ModuleUtil;` pode ser dead também (checar se outros métodos usam).
- ❌ **Esquecer de limpar `SIDEBAR_GROUPS.<grupo>.items[]` no frontend** — sem isso, label de X continua na whitelist (inerte, mas confunde reader futuro).

## Refs

- [ADR 0180 — Sidebar v3 5 grupos ghosts header](../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md)
- `app/Services/LegacyMenuAdapter.php:322` — pass-through de `ghosts`/`primary`/`shortcut` do attributes
- Session log catálise: [2026-05-26 sidebar canon cleanup](../sessions/2026-05-26-sidebar-canon-cleanup-comvis-fix.md)
