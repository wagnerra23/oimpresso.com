# ADR 0024 — Padrão Inertia + React + UltimatePOS pra módulos novos

**Status:** ✅ Aceita
**Data:** 2026-04-25
**Escopo:** Plataforma — afeta todo módulo nwidart que renderiza UI Inertia

## Contexto

Wagner pediu padronização da forma de criar telas Inertia integradas com UltimatePOS, pra reusar consistentemente em Financeiro, Copiloto, NfeBrasil, RecurringBilling e demais módulos novos. Stack: Laravel 13.6 + Inertia v3 + React 19 + Vite 6 + Tailwind 4 + shadcn/ui + lucide-react + sonner.

## Decisão

Adotar este pattern como **template oficial** pra toda tela Inertia em módulo novo:

### Estrutura de arquivos

```
Modules/<Modulo>/
  Http/Controllers/<Recurso>Controller.php   # Inertia::render + form actions
  Http/Requests/<Acao><Recurso>Request.php   # validação
  Routes/web.php                              # ['web', 'auth', 'language', 'CheckUserLogin']
  Resources/menus/topnav.php                  # menu declarativo

resources/js/Pages/<Modulo>/
  <Recurso>/
    Index.tsx                                 # Page Inertia
    components/                               # Sheets, Forms, etc.

tests/Feature/Modules/<Modulo>/
  <Recurso><Acao>Test.php                     # Pest (isolamento + idempotência)
```

### Controller (template)

```php
namespace Modules\<Modulo>\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class <Recurso>Controller extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = $request->session()->get('business.id');
        // query com where('business_id', $businessId) — tenant isolation
        return Inertia::render('<Modulo>/<Recurso>/Index', [...]);
    }
}
```

### Page React (template)

```tsx
// @memcofre tela=/<modulo>/<recurso> module=<Modulo>

import AppShell from '@/Layouts/AppShell';
import { Head } from '@inertiajs/react';
import { PageHeader } from '@/Components/shared/PageHeader';
import { DataTable } from '@/Components/shared/DataTable';

interface Props { /* shape espelhado do controller */ }

function Index(props: Props) {
  return <>
    <Head title="..." />
    <PageHeader title="..." actions={...} />
    <DataTable rows={...} columns={...} />
  </>;
}

Index.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>;
export default Index;
```

## Regras NÃO-NEGOCIÁVEIS

| # | Regra | Por quê |
|---|---|---|
| 1 | `// @memcofre tela=... module=...` no header da Page | Rastreabilidade SPEC ↔ código |
| 2 | `interface Props` em TS espelhando shape do controller | Type-safe, autocomplete |
| 3 | `Index.layout = page => <AppShell>{page}</AppShell>` | NÃO envolver em `<AppShell>` dentro do componente — [preference_persistent_layouts](../preference_persistent_layouts.md) |
| 4 | `useForm` do Inertia v3 (NÃO axios direto) | Inertia v3 removeu axios; tem HTTP client interno |
| 5 | Errors inline via `form.errors[field]` | Inertia auto-popula |
| 6 | Sheet shadcn pra forms ≥ 5 campos (não Modal) | Não bloqueia leitura da lista |
| 7 | `sonner` (`toast.success/error`) — NÃO alert/confirm nativo | Já configurado em [app.tsx](../../resources/js/app.tsx) |
| 8 | Componentes shared antes de criar novo | [Components/shared/](../../resources/js/Components/shared/): PageHeader, DataTable, KpiGrid, KpiCard, StatusBadge, PageFilters, EmptyState, BulkActionBar |
| 9 | Routes com middleware `['web', 'auth', 'language', 'CheckUserLogin']` | Padrão UPos — language carrega traduções, CheckUserLogin valida sessão |
| 10 | Test Pest junto: `<Recurso>IsolamentoTenantTest` + `<Recurso><Acao>IdempotentTest` | [feedback_testes_com_nova_feature](../feedback_testes_com_nova_feature.md) |
| 11 | Atomic upsert: `Model::updateOrCreate([key], [payload])` + UNIQUE constraint no schema | Idempotência |
| 12 | DataTable usa locale pt-BR já configurado | [reference_datatables_locale](../reference_datatables_locale.md) |

## Integração com hooks UltimatePOS

| Hook | Onde registrar | Propósito |
|---|---|---|
| `modifyAdminMenu` | `<Modulo>ServiceProvider::boot` via `ModuleUtil::moduleData(...)` | Sub-menu na sidebar admin |
| `user_permissions` | mesmo | Permissões Spatie `<modulo>.<recurso>.<acao>` |
| `superadmin_package` | mesmo | Pacotes Free/Pro/Enterprise no Superadmin |
| `Transaction::observe(...)` | `registerObservers()` | Reagir a evento core sem editar core |

## Dependências críticas

- React 19+ (necessário pelo Inertia v3)
- @inertiajs/react ^3.0
- inertiajs/inertia-laravel ^3.0
- @types/react ^19
- vite ^6.2
- tailwindcss ^4 + @tailwindcss/vite

## Bundle separado pro Inertia

Vite tem 2 entrypoints (decisão pré-existente):
- `vite.config.js` — pipeline AdminLTE legado
- `vite.inertia.config.mjs` — pipeline React/Tailwind4 isolado
- `npm run build:inertia` gera bundle em `public/build-inertia/` (manifest separado)
- Blade `resources/views/layouts/inertia.blade.php` usa `@vite([...], 'build-inertia')`

NÃO mergir os dois pipelines — AdminLTE é instável/legado, Inertia precisa ESM-only/Tailwind 4.

## Performance

| Métrica | Meta |
|---|---|
| TTFB rota Inertia | < 200ms (com cache) |
| Bundle inicial app.tsx | ~360 KB → 112 KB gzip (atual) |
| Per-page chunk | < 30 KB gzip |
| Hidratação React | < 1.5s ROTA LIVRE 1280px |

## Consequências

### Positivas
- Onboarding de dev em módulo novo: copy-paste do template, ajusta nomes
- Consistência visual e de comportamento entre módulos
- Tests obrigatórios desde o nascimento da feature
- Componentes shared evoluem junto com o pattern

### Negativas
- Curva de aprendizado pra dev novo (Inertia + nwidart + UPos hooks)
- Boot da app em test é caro: tabela `system` do core UPos é necessária — testes Pest novos precisam DB completa migrada (em andamento, ver `memory/sessions/2026-04-25-financeiro-mvp-progresso.md`)

### Neutras
- Não impede usar React fora do AppShell (ex: tela pública / login) — apenas convenção

## Referências
- ADR 0023 (Inertia v3 upgrade)
- [preference_persistent_layouts](../preference_persistent_layouts.md)
- [project_shell_nav_architecture](../project_shell_nav_architecture.md)
- [reference_ultimatepos_integracao](../reference_ultimatepos_integracao.md)
- [feedback_testes_com_nova_feature](../feedback_testes_com_nova_feature.md)
- [reference_datatables_locale](../reference_datatables_locale.md)
- Exemplo template: `Modules/Essentials/Http/Controllers/DocumentController.php` + `resources/js/Pages/Essentials/Documents/Index.tsx`
