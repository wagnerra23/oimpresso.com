# RUNBOOK — Migração tela DETAIL (`{{MODULE}}/{{TELA}}`)

> Template do tipo **DETAIL** (detalhe de 1 entidade). Use quando o Blade legacy é `show.blade.php`.
> Substituir `{{PLACEHOLDERS}}` antes de salvar em `memory/requisitos/{{MODULE}}/RUNBOOK-{{TELA_KEBAB}}.md`.
> Skill: [migracao-blade-react](../../.claude/skills/migracao-blade-react/SKILL.md) · ADR: [0141](../decisions/0141-skill-migracao-blade-react.md)

---

## 1. Identificação

- **Módulo:** {{MODULE}}
- **Tela:** {{TELA}} (kebab: {{TELA_KEBAB}})
- **Tipo:** DETAIL
- **Blade legacy:** `{{BLADE_PATH}}`
- **Controller:** `{{CONTROLLER_PATH}}@show`
- **Rota legada:** `GET {{ROUTE_URI}}/{id}` (name: `{{ROUTE_NAME}}`)
- **Mockup Cowork:** `prototipo-ui/prototipos/{{MODULE_KEBAB}}/visual-source.html`
- **Pages destino:** `resources/js/Pages/{{MODULE}}/{{TELA}}/Show.tsx`

## 2. Snapshot paridade

### 2.1 Campos exibidos (do Model + relações)

| Campo | Origem | Renderização | Visibilidade condicional |
|-------|--------|--------------|---------------------------|
| {{CAMPO_1}} | {{CAMPO_1_FIELD}} | {{CAMPO_1_RENDER}} | sempre |
| {{CAMPO_2}} | {{CAMPO_2_FIELD}} | currency BRL | sempre |
| {{CAMPO_3}} | relação `{{RELACAO}}` | inline link | se `{{CAMPO_3}} !== null` |

### 2.2 Tabs / Seções

- **Tab 1 — `{{TAB_1}}`:** {{TAB_1_DESC}}
- **Tab 2 — `{{TAB_2}}`:** {{TAB_2_DESC}}
- **Tab 3 — Histórico/Audit:** events relacionados

### 2.3 Botões de ação (header + footer)

| Ação | Posição | Permissão | Confirmação | Endpoint |
|------|---------|-----------|-------------|----------|
| Editar | header | `{{PERM_EDIT}}` | ☐ | `GET {{ROUTE_URI}}/{id}/edit` |
| Excluir | header | `{{PERM_DELETE}}` | ☑ modal | `DELETE {{ROUTE_URI}}/{id}` |
| {{ACAO_CUSTOM}} | footer | `{{PERM_CUSTOM}}` | ☐ | `POST {{ENDPOINT_CUSTOM}}` |

### 2.4 Permissões

- `{{PERM_VIEW}}` — ver tela
- `{{PERM_EDIT}}` — botão Editar
- `{{PERM_DELETE}}` — botão Excluir
- `{{PERM_CUSTOM}}` — ações custom

### 2.5 Eventos disparados

- `{{EVENT_VIEWED}}` (audit log)
- `{{EVENT_CUSTOM}}` (se ação custom executada)

### 2.6 Hooks DataController multi-tenant

- Validar `business_id` da entidade == sessão antes do `Inertia::render`
- 404 se cross-tenant (Tier 0)

## 3. Shared components obrigatórios

```tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import StatusBadge from '@/Components/shared/StatusBadge';
import { Sheet, SheetContent } from '@/Components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
```

| Componente | Obrigatório? | Por quê |
|------------|:---:|---------|
| `AppShellV2` | ✅ | Layout canônico |
| `PageHeader` | ✅ | Título + status + ações |
| `Tabs` | ✅ se tem ≥2 seções | Organiza informação densa |
| `StatusBadge` | ✅ se tem status | Substitui `<span class="label-*">` |
| `Sheet` | 🟡 opcional | Modal lateral pra edição inline |
| `Card` | ✅ | Agrupa blocos de informação |

## 4. Esqueleto TSX

```tsx
import { type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import StatusBadge from '@/Components/shared/StatusBadge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';

interface {{TELA}}Entity {
  id: number;
  {{FIELDS_TS}}
  status: '{{STATUS_VALUES}}';
}

interface Props {
  entity: {{TELA}}Entity;
  permissions: { edit: boolean; delete: boolean };
  relations: { {{RELATIONS_TS}} };
}

function {{MODULE}}{{TELA}}Show({ entity, permissions, relations }: Props) {
  return (
    <>
      <PageHeader
        title={entity.{{TITLE_FIELD}}}
        description={<StatusBadge status={entity.status} />}
        action={permissions.edit && (
          <Button onClick={() => router.visit(`{{ROUTE_URI}}/${entity.id}/edit`)}>
            Editar
          </Button>
        )}
      />
      <Tabs defaultValue="{{TAB_1}}">
        <TabsList>
          <TabsTrigger value="{{TAB_1}}">{{TAB_1_LABEL}}</TabsTrigger>
          <TabsTrigger value="{{TAB_2}}">{{TAB_2_LABEL}}</TabsTrigger>
          <TabsTrigger value="historico">Histórico</TabsTrigger>
        </TabsList>
        <TabsContent value="{{TAB_1}}">{/* fields */}</TabsContent>
        <TabsContent value="{{TAB_2}}">{/* fields */}</TabsContent>
        <TabsContent value="historico">{/* audit log */}</TabsContent>
      </Tabs>
    </>
  );
}

{{MODULE}}{{TELA}}Show.layout = (page: ReactNode) => (
  <AppShellV2
    title="{{TITLE}}"
    breadcrumbItems={[
      { label: '{{MODULE_LABEL}}', href: '{{MODULE_HOME}}' },
      { label: '{{TELA_LABEL}}', href: '{{ROUTE_URI}}' },
      { label: 'Detalhe' },
    ]}
  >{page}</AppShellV2>
);

export default {{MODULE}}{{TELA}}Show;
```

## 5. Adaptação Controller

```php
public function show(Request $request, int $id)
{
    $business_id = $request->session()->get('user.business_id');  // ← MANTÉM
    $entity = {{MODEL}}::where('business_id', $business_id)        // ← MANTÉM Tier 0
        ->with([{{EAGER_LOAD}}])
        ->findOrFail($id);

    return Inertia::render('{{MODULE}}/{{TELA}}/Show', [
        'entity' => $entity,
        'permissions' => [
            'edit'   => auth()->user()->can('{{PERM_EDIT}}'),
            'delete' => auth()->user()->can('{{PERM_DELETE}}'),
        ],
        'relations' => [
            '{{RELACAO_1}}' => $entity->{{RELACAO_1}},
        ],
    ]);
}
```

## 6. Pest tests

```php
test('{{MODULE_KEBAB}} {{TELA_KEBAB}} show renders for biz=1', function () {
    // happy path
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} show returns 404 for entity from other tenant', function () {
    // cross-tenant guard biz=99 — Tier 0
});

test('{{MODULE_KEBAB}} {{TELA_KEBAB}} show hides Editar for user without permission', function () {
    // permission
});
```

## 7. Checklist paridade

- [ ] Todos os campos do Blade renderizam
- [ ] Tabs cobrem todas as seções do Blade
- [ ] Botões de ação preservados
- [ ] `business_id` scope intacto (404 cross-tenant testado)
- [ ] Audit log/histórico exibido
- [ ] Screenshot aprovado por Wagner
- [ ] Pest biz=1 + biz=99 + permission passando
- [ ] PR ≤300 LOC, `Refs: ADR-0141`

---

**Refs:** [0141](../decisions/0141-skill-migracao-blade-react.md) · [0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [0093](../decisions/0093-multi-tenant-isolation-tier-0.md) · [0107](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
