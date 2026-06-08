# Skill — mwart-migrate

> Reusable skill para migrar uma rota Blade → Inertia/React seguindo o padrão MWART do oimpresso.
> Não auto-ativa por description matching — invocada explicitamente por Wagner ou pela skill master de migração.

## Quando usar

Wagner invoca esta skill ao migrar **uma única rota** Blade. Não usar pra refatorações maiores ou mudanças de UX — só pra port 1:1.

## Pré-requisitos

- ADR MWART-0001 mergeado (Sprint 2)
- `config/mwart.php` existe
- `HandleInertiaRequests` middleware tem `business_id` + `permissions` em shared props
- Layout canônico `AppShellV2` em `resources/js/Layouts/`
- Skill `multi-tenant-patterns` ativa (sempre que tocar Eloquent + business_id)

## Skills/memórias a carregar antes de começar

- `oimpresso-stack` (primer L13.6 + PHP 8.4 + Inertia v3 + multi-tenant)
- `multi-tenant-patterns` (`business_id` global scope obrigatório)
- `sidebar-menu-arch` (entender que NÃO mexe em sidebar)
- `publication-policy` (decide quem aprova flag flip em prod)
- Memória `preference_persistent_layouts` (`Component.layout` static, não wrapping)
- Memória `cache_estado_preservado` (`preserveState`/`preserveScroll`/`only`)
- Memória do módulo (ex: `cliente_rotalivre` se for ROTA LIVRE-affecting)

## Passos

### 1. Identificar escopo

```
Módulo:        <ex: Repair>
Rota:          <ex: repair.index>
URL:           <ex: /repair/repair>
Blade view:    <ex: Modules/Repair/Resources/views/repair/index.blade.php>
Controller:    <ex: Modules\Repair\Http\Controllers\RepairController@index>
Tabela base:   <ex: transactions com sub_type='repair'>
Permission:    <ex: repair.view, repair.view_own>
Feature flag:  <ex: MWART_REPAIR_INDEX>
```

### 2. Ler o Blade existente

Antes de escrever React, **ler o Blade inteiro** + parciais incluídos (`@include`). Anotar:

- todas variáveis usadas no view
- todos filtros do form (incluindo hidden inputs)
- todas bulk actions e endpoints
- estados (empty, loading, erro)
- atalhos JS legacy (jQuery DataTables, Select2, Bootstrap)
- assets (CSS scoped, JS específico, libs externas)
- chamadas AJAX server-side e endpoints retornando JSON

Saída: lista numerada do que precisa existir no React.

### 3. Adaptar Controller (dual-mode)

```php
public function index(Request $req)
{
    $business_id = $req->session()->get('user.business_id');

    if (! $this->authorizeIndex($req->user(), $business_id)) {
        abort(403);
    }

    $data = $this->buildIndexData($req, $business_id);

    if (config('mwart.<flag>.enabled') && $this->mwartBetaBiz('<flag>', $business_id)) {
        return Inertia::render('<Modulo>/<Page>', $data);
    }

    return $this->renderBladeIndex($req, $data);
}

private function buildIndexData(Request $req, int $business_id): array
{
    // Lógica compartilhada Blade↔Inertia.
    // Retornar dados PRONTOS pra ambos consumirem.
    // Pra Blade, view consome direto. Pra Inertia, vira Resource.
}

private function mwartBetaBiz(string $key, int $business_id): bool
{
    $beta = config("mwart.{$key}.business_ids", []);
    return empty($beta) || in_array($business_id, $beta, true);
}
```

Regras:

- `business_id` SEMPRE primeiro no WHERE (multi-tenant first)
- `select()` com whitelist de colunas (zero `SELECT *`)
- `paginate(...)->withQueryString()`
- Validar query params com `$req->validate()` (whitelist sort/dir/per_page)
- Permissions: usar `$user->can('repair.view')` direto, não criar gate paralelo

### 4. Criar Resource tipado

`Modules/<Modulo>/Http/Resources/<Page>Resource.php`:

```php
class <Page>Resource extends JsonResource {
  public function toArray($req): array { return [
    // espelhar EXATAMENTE o que o React consome
    // formatar valores monetários e datas no servidor
    // booleanos calculados (is_overdue, can_edit) também no servidor
  ]; }
}
```

### 5. Criar Page React

`resources/js/Pages/<Modulo>/<Page>.tsx`:

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader, PageFilters, DataTable } from '@/Components/shared';
import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

function <Page>({ data, filters, meta, permissions }: Props) {
  return (
    <>
      <PageHeader title="..." />
      <PageFilters>...</PageFilters>
      <DataTable>...</DataTable>
    </>
  );
}

<Page>.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
export default <Page>;
```

Regras (memória `cache_estado_preservado`):

- Filtros via `router.get()` com `preserveState: true`, `preserveScroll: true`, `only: [...]`, `replace: true`
- Bulk via `router.post()` com `onSuccess` callback
- Atalhos via `useHotkeys` (já no projeto)
- **Nunca** `window.location.reload()` ou `<a href>` interno (usa `<Link>`)
- Tipar todas props (zero `any`)

### 6. Feature flag

`config/mwart.php`:

```php
'<flag>' => [
    'enabled' => env('MWART_<FLAG>', false),
    'business_ids' => array_filter(explode(',', (string) env('MWART_<FLAG>_BIZ', ''))),
],
```

`.env`:

```env
# staging
MWART_<FLAG>=true

# prod (rollout gradual por business_id)
MWART_<FLAG>=true
MWART_<FLAG>_BIZ=4         # primeiro só ROTA LIVRE
```

### 7. Testes

> ⚠️ Verificar `phpunit.xml` antes (CLAUDE.md §4) — se módulo não está registrado, registrar no mesmo PR.

`Modules/<Modulo>/Tests/Feature/<Page>IndexTest.php`:

- [ ] business_id scope (cria 2 businesses, valida no-leak)
- [ ] permission `view` lista tudo
- [ ] permission `view_own` filtra por created_by/owner
- [ ] filtros combinados retornam contagem correta
- [ ] paginação preserva query string
- [ ] sort whitelist; sort fora rejeitado
- [ ] flag off → `assertViewIs(...)`
- [ ] flag on + biz beta → `assertInertia(fn ($a) => $a->component('...'))`
- [ ] flag on + biz NÃO beta → ainda Blade
- [ ] sem permission → 403

`tests/js/Pages/<Modulo>/<Page>.test.tsx` (Vitest):

- render N rows
- interação primária (click filter, atalho `/`, bulk select)
- empty state

### 8. Atualizar memória

`memory/migrations.md`:

```markdown
## <YYYY-MM-DD> — <rota>

- PR: #<n>
- Flag: `MWART_<FLAG>`
- Beta: business_id <ids> (<dia início>)
- Soak staging: <YYYY-MM-DD> → <YYYY-MM-DD>
- Status: 🟡 staging | 🟢 100% prod | ⚫ deletado Blade
- Owner: <pessoa>
```

### 9. PR

Label `mwart` + label do módulo. Description template:

```markdown
Refs: SPRINT-<n>
Rota migrada: <rota>
Flag: MWART_<FLAG> (default off)

## Checklist MWART

- [ ] Blade preservado intacto (rollback via flag)
- [ ] Controller dual-mode com `buildIndexData()` privado compartilhado
- [ ] `business_id` primeiro no WHERE em toda query
- [ ] Resource tipado, sem `SELECT *`
- [ ] Página React < 400 linhas (ou extraída em sub-componentes)
- [ ] Layout via `Component.layout`, não wrapping em AppShellV2
- [ ] Filtros com `preserveState` + `preserveScroll` + `only`
- [ ] Sort por whitelist
- [ ] Permissions Spatie reusadas (sem gates novos)
- [ ] Pest Feature 9 cenários passando
- [ ] Vitest cobrindo render + interação primária
- [ ] `phpunit.xml` lista o módulo
- [ ] `memory/migrations.md` atualizado
- [ ] PT-BR em todo texto user-facing
```

### 10. Soak 48h staging

- Deploy staging com `MWART_<FLAG>=true` (todos businesses staging)
- 3 usuários internos validam (smoke test do checklist)
- Sentry zero erros JS em 48h
- p95 < target via Telescope
- Após 48h limpo: PR pra mover flag pra prod com `<FLAG>_BIZ=<beta>`

### 11. Rollout prod gradual

1. **Beta cliente piloto** (default ROTA LIVRE = `business_id=4`) por 7 dias
2. Se limpo → 25% dos businesses (escolher por volume) por 7 dias
3. Se limpo → 100% (`<FLAG>_BIZ=` vazio = todos)
4. Cada flip de business_id passa pela skill `publication-policy`

### 12. Cleanup (após 30 dias 100% on, zero rollback)

- Deletar `Modules/<Modulo>/Resources/views/<page>.blade.php`
- Remover branch `if config('mwart....')` do controller — fica só Inertia
- Remover entrada de `config/mwart.php` e `.env*`
- Atualizar `migrations.md` status → ⚫
- Commit: `chore(<modulo>): remove Blade legacy de <page>`

## Anti-padrões a evitar

- ❌ Mudar UX durante MWART (label, ordem, ícone, fluxo) — port 1:1
- ❌ Refatorar Controller além do necessário — mesmas queries, mesmos filtros
- ❌ Usar `any` no TypeScript — Resource tipado é o contrato
- ❌ Esquecer `preserveState` — perde scroll/foco a cada filtro
- ❌ Criar `LegacyMenuAdapter` ou flag em `menu.php` — sidebar fica como está
- ❌ Deletar Blade antes de 30 dias 100% on
- ❌ Wrapping em `<AppShellV2>` no JSX (vai de `Component.layout`)
- ❌ Esquecer `business_id` no primeiro WHERE
- ❌ Criar gate `User::canMwart()` paralelo às permissions Spatie
- ❌ Pular o registro em `phpunit.xml`
- ❌ `SELECT *` ou eager load `with()` sem `select()` no relation

## Output esperado quando invocada

Quando esta skill é executada com escopo definido, produz **em uma única resposta**:

1. Diff do Controller (dual-mode + `buildIndexData` extraído)
2. Arquivo Resource novo
3. Arquivo Page.tsx novo (+ sub-componentes se >400 linhas)
4. Migration Laravel pra índices novos (idempotente)
5. Diff de `config/mwart.php` (+ entrada `.env.example`)
6. Diff de `phpunit.xml` se módulo não estava registrado
7. `Modules/<Modulo>/Tests/Feature/<Page>IndexTest.php`
8. `tests/js/Pages/<Modulo>/<Page>.test.tsx`
9. Linha pra `memory/migrations.md`
10. PR description preenchida com checklist

Tudo pronto pra Wagner colar e abrir PR — sem ida-e-volta de "qual é o nome da tabela?".
