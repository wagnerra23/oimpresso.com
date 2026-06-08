# Skill — mwart-migrate

> Reusable skill para migrar uma rota Blade → React/Inertia seguindo o padrão MWART.
> Tier B (loaded sob demanda, não no boot).

## Quando usar

Wagner ou Opus invoca esta skill ao migrar **uma única rota** Blade. Não usar pra refatorações maiores ou mudanças de UX — só pra port 1:1.

## Pré-requisitos

- ADR MWART-0001 mergeado (Sprint 2)
- `LegacyMenuAdapter` aceitando flag `inertia`
- `config/mwart.php` existe
- `User::canMwart()` implementado

## Passos

### 1. Identificar escopo

```
- Módulo: <ex: Officeimpresso>
- Rota: <ex: officeimpresso.os.index>
- Blade view: <ex: Modules/Officeimpresso/Resources/views/os/index.blade.php>
- Controller: <ex: Modules/Officeimpresso/Http/Controllers/OsController@index>
- Feature flag: <ex: MWART_OS_INDEX>
```

### 2. Ler o Blade existente

Antes de escrever React, **ler o Blade inteiro** + parciais incluídos. Anotar:
- todas variáveis usadas (`$os`, `$filtros`, `$totais`, …)
- todos os filtros do form
- todas as bulk actions
- todos os estados (empty, loading, erro)
- todos os atalhos JS legacy
- todos os assets (CSS scoped, JS específico)

### 3. Adaptar Controller

```php
public function index(Request $req)
{
    $data = $this->buildIndexData($req);  // extrai lógica comum

    if (config('mwart.<flag>') && $req->user()->canMwart()) {
        return Inertia::render('<Page>', $data);
    }

    return view('<modulo>::<view>', $data);
}
```

- Extrair `buildIndexData()` privado pra ambos caminhos compartilharem
- Trocar Eloquent collections por Resources tipados (`<Page>Resource`)
- Validar query params com `$req->validate()`
- `paginate()->withQueryString()`

### 4. Criar página React

```
resources/js/Pages/<Modulo>/<Page>.tsx
```

Estrutura mínima:

```tsx
import AppShell from '@/Layouts/AppShell';
import { PageHeader, PageFilters, DataTable } from '@/Components/shared';

export default function PageName({ data, filtros, meta, permissions }: Props) {
  return (
    <AppShell>
      <PageHeader title="..." actions={...} />
      <PageFilters>...</PageFilters>
      <DataTable>...</DataTable>
    </AppShell>
  );
}
```

- Tipar todas props (sem `any`)
- Reusar `PageHeader`, `DataTable`, `PageFilters`, `KpiCard`, `StatusBadge`, `EmptyState`
- Filtros via `router.get()` com `preserveState` + `preserveScroll` + `only: [...]`
- Bulk actions via `router.post()` com `onSuccess` callback
- Atalhos via `useHotkeys` (já no projeto)

### 5. Resource tipado

```php
// Modules/<Modulo>/Http/Resources/<Page>Resource.php
class <Page>Resource extends JsonResource {
  public function toArray($req): array { return [...]; }
}
```

Espelhar exatamente os campos que o React consome. Nada a mais, nada a menos.

### 6. Feature flag

```php
// config/mwart.php
return [
    '<flag>' => env('MWART_<FLAG>', false),
];
```

```bash
# .env (staging)
MWART_<FLAG>=true

# .env (prod) — só após soak 48h em staging
MWART_<FLAG>=true
```

### 7. Testes

Mínimo:
- `tests/Feature/<Modulo>/<Page>Test.php` — cobertura controller (4-6 cenários)
- `tests/js/Pages/<Modulo>/<Page>.test.tsx` — render + interação primária
- E2E Cypress opcional pro fluxo dourado

### 8. Atualizar memória

Adicionar entrada em `memory/migrations.md`:

```markdown
## <data> — <rota>

- PR: #<n>
- Flag: `MWART_<FLAG>`
- Soak: <YYYY-MM-DD> → <YYYY-MM-DD>
- Status: 🟡 staging | 🟢 100% prod | ⚫ deletado Blade
```

### 9. PR

Label `mwart`. Description template:

```
Refs: SPRINT-<n>
Flag: MWART_<FLAG>
Rota migrada: <rota>

Checklist:
- [ ] Blade preservado (rollback via flag)
- [ ] Controller dual-mode
- [ ] Resource tipado
- [ ] Página React < 400 linhas
- [ ] Testes Feature + JS passando
- [ ] memory/migrations.md atualizado
```

### 10. Soak 48h

- Deploy staging com flag on
- 3 usuários internos validam
- Sentry zero erros JS
- p95 < target do controller (Telescope)
- Após 48h: flag prod gradual (10% → 50% → 100%) via `User::canMwart()`

### 11. Cleanup (após 30 dias 100% on)

- Deletar `*.blade.php` da rota
- Deletar branch do controller que retornava view Blade
- Remover flag de `config/mwart.php`
- Atualizar `migrations.md` com status ⚫

## Anti-padrões a evitar

- ❌ Mudar UX durante MWART (label, ordem, ícone, fluxo) — port 1:1, mudanças vêm em sprint dedicada
- ❌ Refatorar Controller além do necessário — manter mesmas queries, mesmos filtros
- ❌ Usar `any` no TypeScript — Resource tipado é a contract
- ❌ Esquecer `preserveState` — perde scroll/foco a cada filtro
- ❌ Mexer em `LegacyMenuAdapter` — só vira flag `inertia`
- ❌ Deletar Blade antes de 30 dias 100% on

## Output esperado

Quando esta skill é invocada com escopo definido, deve produzir:
1. Diff do Controller
2. Arquivo Resource novo
3. Arquivo Page.tsx novo
4. Diff de `config/mwart.php`
5. Diff de `menu.php` do módulo (flag `inertia: true`)
6. Testes Feature + JS
7. Linha pra `migrations.md`
8. PR description preenchida

Tudo em um único response, pronto pra Wagner colar.
