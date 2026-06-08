# RUNBOOK — Migração tela FORM (`{{MODULE}}/{{TELA}}`)

> Template do tipo **FORM** (cadastro/edição). Use quando o Blade legacy é `create.blade.php` ou `edit.blade.php`.
> Substituir `{{PLACEHOLDERS}}` antes de salvar em `memory/requisitos/{{MODULE}}/RUNBOOK-{{TELA_KEBAB}}.md`.
> Skill: [migracao-blade-react](../../.claude/skills/migracao-blade-react/SKILL.md) · ADR: [0141](../decisions/0141-skill-migracao-blade-react.md)

---

## 1. Identificação

- **Módulo:** {{MODULE}}
- **Tela:** {{TELA}} (kebab: {{TELA_KEBAB}})
- **Tipo:** FORM (Create + Edit — mesmo Page com prop `entity?`)
- **Blade legacy:** `{{BLADE_PATH_CREATE}}` + `{{BLADE_PATH_EDIT}}`
- **Controller:** `{{CONTROLLER_PATH}}@create|store|edit|update`
- **FormRequest:** `{{FORM_REQUEST_PATH}}`
- **Rotas:** `GET {{ROUTE_URI}}/create`, `POST {{ROUTE_URI}}`, `GET {{ROUTE_URI}}/{id}/edit`, `PUT {{ROUTE_URI}}/{id}`
- **Mockup Cowork:** `prototipo-ui/prototipos/{{MODULE_KEBAB}}/visual-source.html`
- **Pages destino:** `resources/js/Pages/{{MODULE}}/{{TELA}}/Form.tsx`

## 2. Snapshot paridade

### 2.1 Campos do form

| Campo | Tipo | Required | Default | Validação Laravel | Dependent |
|-------|------|----------|---------|--------------------|-----------|
| {{CAMPO_1}} | text | ✅ | — | `required\|string\|max:255` | — |
| {{CAMPO_2}} | select | ✅ | — | `required\|exists:{{TABLE}},id` | — |
| {{CAMPO_3}} | select | 🟡 | — | `nullable\|exists:{{TABLE}},id` | depende de `{{CAMPO_2}}` |
| {{CAMPO_4}} | currency | ✅ | 0.00 | `required\|numeric\|min:0` | — |
| {{CAMPO_5}} | date | 🟡 | hoje | `nullable\|date` | — |
| {{CAMPO_6}} | file | 🟡 | — | `nullable\|file\|mimes:pdf,jpg\|max:5120` | — |
| {{CAMPO_7}} | textarea | 🟡 | — | `nullable\|string\|max:2000` | — |

### 2.2 Validações Laravel (do FormRequest)

```php
public function rules(): array {
    return [
        '{{CAMPO_1}}' => 'required|string|max:255',
        // ... espelhar todas as rules do FormRequest atual
    ];
}
```

### 2.3 Mensagens custom

```php
public function messages(): array {
    return [
        '{{CAMPO_1}}.required' => '{{MSG_CAMPO_1}}',
        // ...
    ];
}
```

### 2.4 Permissões

- `{{PERM_CREATE}}` — acesso à tela `create`
- `{{PERM_EDIT}}` — acesso à tela `edit` (+ ownership: `business_id`)

### 2.5 Eventos disparados

- `{{EVENT_CREATED}}` no `store()`
- `{{EVENT_UPDATED}}` no `update()`
- `UserCreatedOrModified` (se afetar usuário/empresa — hook DataController)

### 2.6 Hooks DataController multi-tenant

- `business_id` injetado automaticamente no store
- Validar ownership no update (Tier 0 IRREVOGÁVEL)

## 3. Shared components obrigatórios

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { Button } from '@/Components/ui/button';
import { useForm } from '@inertiajs/react';
```

| Componente | Obrigatório? | Por quê |
|------------|:---:|---------|
| `AppShellV2` | ✅ | Layout canônico |
| `PageHeader` | ✅ | Título + breadcrumb |
| `Card` | ✅ | Agrupa fieldsets |
| `Input/Select/Textarea` | ✅ | shadcn ui consistente |
| `Button` | ✅ | submit + cancelar |
| `useForm` (Inertia) | ✅ | Validação + loading state automático |

## 4. Esqueleto TSX

```tsx
import { type ReactNode, type FormEvent } from 'react';
import { useForm, router } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';

interface FormData {
  {{FIELDS_TS}}
}

interface Props {
  entity?: FormData & { id: number };  // undefined = create, defined = edit
  selectOptions: { {{SELECT_OPTIONS_TS}} };
}

function {{MODULE}}{{TELA}}Form({ entity, selectOptions }: Props) {
  const isEdit = !!entity;
  const { data, setData, post, put, processing, errors } = useForm<FormData>({
    {{DEFAULT_VALUES}}
  });

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    if (isEdit) {
      put(`{{ROUTE_URI}}/${entity!.id}`);
    } else {
      post('{{ROUTE_URI}}');
    }
  };

  return (
    <>
      <PageHeader
        title={isEdit ? 'Editar {{TELA_LABEL}}' : 'Novo {{TELA_LABEL}}'}
      />
      <form onSubmit={handleSubmit} className="space-y-6 mt-4">
        <Card>
          <CardContent className="p-6 space-y-4">
            <div>
              <Label htmlFor="{{CAMPO_1}}">{{LABEL_1}} *</Label>
              <Input
                id="{{CAMPO_1}}"
                value={data.{{CAMPO_1}}}
                onChange={(e) => setData('{{CAMPO_1}}', e.target.value)}
                aria-invalid={!!errors.{{CAMPO_1}}}
              />
              {errors.{{CAMPO_1}} && <p className="text-sm text-rose-600 mt-1">{errors.{{CAMPO_1}}}</p>}
            </div>
            {/* ... espelhar campos da seção 2.1 */}
          </CardContent>
        </Card>
        <div className="flex gap-2 justify-end">
          <Button type="button" variant="outline" onClick={() => router.visit('{{ROUTE_URI}}')}>
            Cancelar
          </Button>
          <Button type="submit" disabled={processing}>
            {processing ? 'Salvando…' : isEdit ? 'Salvar alterações' : 'Criar'}
          </Button>
        </div>
      </form>
    </>
  );
}

{{MODULE}}{{TELA}}Form.layout = (page: ReactNode) => (
  <AppShellV2 title="{{TITLE}}">{page}</AppShellV2>
);

export default {{MODULE}}{{TELA}}Form;
```

## 5. Adaptação Controller

```php
public function create(Request $request) {
    $business_id = $request->session()->get('user.business_id');
    return Inertia::render('{{MODULE}}/{{TELA}}/Form', [
        'selectOptions' => [
            '{{SELECT_1}}' => {{SELECT_1_MODEL}}::where('business_id', $business_id)->pluck('nome', 'id'),
        ],
    ]);
}

public function edit(Request $request, int $id) {
    $business_id = $request->session()->get('user.business_id');
    $entity = {{MODEL}}::where('business_id', $business_id)->findOrFail($id);  // Tier 0
    return Inertia::render('{{MODULE}}/{{TELA}}/Form', [
        'entity' => $entity,
        'selectOptions' => [/* mesmo do create */],
    ]);
}

// store/update mantêm FormRequest existente — não tocar regras
public function store({{TELA}}StoreRequest $request) {
    $business_id = $request->session()->get('user.business_id');
    $entity = {{MODEL}}::create([
        ...$request->validated(),
        'business_id' => $business_id,  // ← OBRIGATÓRIO Tier 0
    ]);
    return redirect()->route('{{ROUTE_NAME_INDEX}}')->with('success', '...');
}
```

## 6. Pest tests

```php
test('{{MODULE_KEBAB}} {{TELA_KEBAB}} form creates for biz=1', function () {
    // happy path
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} form rejects invalid data', function () {
    // 1 teste por rule do FormRequest
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} form cannot edit entity from other tenant', function () {
    // cross-tenant 404 — Tier 0
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} form persists business_id automatically', function () {
    // store nunca aceita business_id do request
});
```

## 7. Checklist paridade

- [ ] Todos os campos do Blade form aparecem
- [ ] Validações idênticas ao FormRequest original
- [ ] Mensagens custom preservadas
- [ ] File upload funciona (se aplicável)
- [ ] Dependent dropdowns funcionam (se aplicável)
- [ ] `business_id` scope intacto no edit
- [ ] `business_id` injetado no store (não vem do request)
- [ ] Events::dispatch preservados
- [ ] Screenshot aprovado por Wagner
- [ ] Pest cobre cada rule + cross-tenant + permission
- [ ] PR ≤300 LOC, `Refs: ADR-0141`

---

**Refs:** [0141](../decisions/0141-skill-migracao-blade-react.md) · [0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [0093](../decisions/0093-multi-tenant-isolation-tier-0.md)
