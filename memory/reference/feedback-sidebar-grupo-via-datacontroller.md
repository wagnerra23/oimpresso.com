---
slug: feedback-sidebar-grupo-via-datacontroller
title: "Sidebar: grupo via DataController, nunca hardcode label no frontend"
type: feedback-rule
authority: canonical
lifecycle: ativo
session_date: '2026-05-19'
catalogado_por: Wagner [W]
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0094-constituicao-v2-7-camadas-8-principios
related_files:
  - app/Services/LegacyMenuAdapter.php
  - resources/js/Components/cockpit/Sidebar.tsx
  - resources/js/Components/cockpit/shared.ts
---

# Regra Wagner — Sidebar grupo via DataController, nunca hardcode

## Wagner palavras textuais

> "deve ser mudado no datacontroller do modulo, nunca hardcode"

— Wagner 2026-05-19, após ver hotfix #1143 que adicionou string `'Cobrança'` diretamente em `SIDEBAR_GROUPS['fin'].items[]` no frontend.

## Anti-padrão proibido

```diff
// ❌ ERRADO — hardcode label no frontend
// resources/js/Components/cockpit/Sidebar.tsx
const SIDEBAR_GROUPS = [
  {
    key: 'fin',
    items: ['Despesas', 'Contas de pagamento', 'Financeiro',
-           'Fluxo de Caixa', 'DRE / Relatórios', 'Gateway de Pagamento',
+           'Fluxo de Caixa', 'DRE / Relatórios', 'Cobrança',   // ← VIOLAÇÃO
            'Cobrança Recorrente'],
  },
];
```

## Pattern correto

### 1. DataController do módulo declara grupo

```php
// Modules/<X>/Http/Controllers/DataController.php
$menu->url(
    url('/path/da/tela'),
    __('module::module.label_key'),
    [
        'icon'   => 'fa fas fa-icon',
        'active' => $segmento_ativo && request()->segment(2) === 'tela',
        'group'  => 'fin',    // ← grupo sidebar declarado AQUI
    ]
)->order(85.3);
```

### 2. Backend propaga via LegacyMenuAdapter

```php
// app/Services/LegacyMenuAdapter.php::convertItem()
$group = $props['group']
       ?? $props['sidebar_group']
       ?? $props['attributes']['group']
       ?? null;
if (! empty($group)) {
    $result['group'] = (string) $group;
}
```

### 3. Frontend prioriza group declarado

```typescript
// resources/js/Components/cockpit/Sidebar.tsx
function findGroupKey(item: ShellMenuItem): string {
  // 1. group declarado pelo DataController
  if (item.group && SIDEBAR_GROUPS.some((g) => g.key === item.group)) {
    return item.group;
  }
  // 2. fallback label match (legacy items sem group)
  // ...
}
```

## Por que essa regra existe

- **Anti-hardcode**: Frontend não deveria saber QUE módulo vai ter QUE label. Módulo declara seu próprio grupo.
- **Manutenção**: Próximo item novo no sidebar = 1 linha no DataController do módulo, **zero touch frontend**.
- **Refactoring**: Quando módulo muda label (ex "Boletos" → "Gateway de Pagamento" → "Cobrança"), só toca o lang + DataController. Sidebar.tsx não precisa updade.
- **Encapsulamento**: Módulo X não exige Sidebar.tsx conhecer sua tela.

## Keys de grupo canônicas

Definidas em `SIDEBAR_GROUPS` (`resources/js/Components/cockpit/Sidebar.tsx`):

- `office` — ACESSOS RÁPIDOS
- `oficina-auto` — OFICINA AUTO
- `fin` — FINANCEIRO
- `estoque` — ESTOQUE
- `fiscal` — FISCAL
- `rh` — RH
- `conhecimento` — CONHECIMENTO
- `dashboard` — DASHBOARD
- `jana` — JANA
- `governanca` — GOVERNANÇA
- `plataforma` — PLATAFORMA
- `mais` — MAIS (fallback)

## Quando hardcode é aceitável

`SIDEBAR_GROUPS[group].items[]` hardcode é **legacy compat** — items que não declararam `'group'` ainda chegam pelo label match. Permitido pra:

- Items legacy de módulos que ainda não migraram (RecurringBilling, Despesas, Accounting, etc)
- Items do core UltimatePOS herdados (não-Inertia)

**NÃO permitido pra items NOVOS**. Pull request adicionando item novo em `SIDEBAR_GROUPS.items[]` deve ser rejeitado em code review — refazer via DataController.

## Histórico

| Data | Evento | PR |
|---|---|---|
| 2026-05-19 14:34 | Bug reportado: sidebar mostra "Gateway de Pagamento" antigo | — |
| 2026-05-19 14:40 | Hotfix #1142 trocou label DataController + lang | #1142 |
| 2026-05-19 14:48 | **Tentativa errada**: hardcode 'Cobrança' em SIDEBAR_GROUPS | #1143 |
| 2026-05-19 14:52 | Wagner: *"deve ser mudado no datacontroller, nunca hardcode"* | — |
| 2026-05-19 14:55 | **Refactor proper**: ShellMenuItem.group + DataController attribute | #1144 |
