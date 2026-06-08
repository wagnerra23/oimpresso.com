---
slug: feedback-inertia-defer-null-guard-first-paint
title: "Inertia::defer: null-guard obrigatório no first paint (undefined antes resolver)"
type: feedback-rule
authority: canonical
lifecycle: ativo
session_date: '2026-05-19'
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
related_skills:
  - inertia-defer-default
related_files:
  - memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md
---

# Regra — Inertia::defer + null-guard first paint

## Bug catalogado

Sessão 2026-05-19 prod biz=WR2: tela `/financeiro/cobranca` renderiza **completamente branca**. Console:

```
TypeError: Cannot read properties of undefined (reading 'filter')
  at Be (build-inertia/assets/Index-bfwwWTo5.js:0:4106)
  at Object.useMemo (...)
```

## Causa-raiz

`Inertia::defer()` props chegam **undefined** no first paint até a promise do servidor resolver. `useMemo` consumindo a prop direto sem null-guard crasha:

```tsx
// ❌ ERRADO — crasha quando cobrancas é undefined
const filtered = useMemo(() => {
  return cobrancas.filter(c => ...);  // ← TypeError
}, [cobrancas, ...]);
```

## Pattern correto

```tsx
// ✅ CORRETO — null-guard explícito
const filtered = useMemo(() => {
  return (cobrancas ?? []).filter(c => ...);
}, [cobrancas, ...]);

// ✅ Defaults props NÃO bastam — Inertia passa null/undefined explícito
function Page({ cobrancas, kpis, funil }: Props) {
  // Fallback EXPLÍCITO no body:
  kpis = kpis ?? KPI_FALLBACK;
  funil = funil ?? FUNIL_FALLBACK;
  // ...
}
```

## Por que defaults inline NÃO funcionam

```tsx
// ❌ FRÁGIL — só funciona se prop for omitida
function Page({ cobrancas = [], kpis = KPI_FALLBACK }: Props) {
  // Inertia::defer passa `cobrancas: undefined` EXPLICITAMENTE
  // → default value é APLICADO em destructuring ES2015...
  // ... MAS se Inertia passa null (não undefined), default NÃO se aplica.
  // Em alguns paths Inertia passa null literal pré-resolve.
}
```

**Conclusão**: prefira sempre `prop ?? fallback` no BODY do component, não default inline da signature.

## Backend canônico (RUNBOOK)

```php
// Controller — Inertia::defer DEFAULT em props caras
return Inertia::render('Module/Page/Index', [
    'today' => $hoje->toDateString(),       // eager — leve
    'filtros' => $filtros,                  // eager — leve

    'cobrancas' => Inertia::defer(fn () => $query->listar(...)),  // ← DEFER
    'kpis' => Inertia::defer(fn () => $query->kpis(...)),
    'funil' => Inertia::defer(fn () => $query->funil(...)),
]);
```

## Frontend — checklist obrigatório

Ao consumir prop `Inertia::defer` no `.tsx`:

- [ ] Wrap em `<Deferred data="prop_name" fallback={<Skeleton />}>` no render JSX
- [ ] **TODOS useMemo/useCallback** que consomem a prop usam `(prop ?? [])` OU `(prop ?? FALLBACK)`
- [ ] **TODOS .map() / .filter() / .reduce()** diretos no JSX guardam `prop?.map(...)` OU `(prop ?? []).map(...)`
- [ ] Componentes filhos que recebem a prop **NÃO** dependem dela em useEffect sem null-check
- [ ] Default constants exportadas no top do file (`KPI_FALLBACK`, `FUNIL_FALLBACK`) pra reuso

## Validação Pest

```php
it('renderiza sem crash quando props deferred não vieram (first paint)', function () {
    $response = $this->actingAs($user)
        ->withSession([...])
        ->get('/route');

    // Resposta deve ser OK mesmo sem ter resolvido defer ainda
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Module/Page/Index'));
});
```

(Pest não consegue testar erro JS client-side. Validação real é via Chrome MCP screenshot pós-deploy.)

## Skill pareada

`inertia-defer-default` (Tier B) — auto-trigger ANTES de Edit em Controller `Inertia::render(...)`. Lembra de aplicar defer em props caras.

**Adicionar a esta skill**: null-guard CHECKLIST frontend acima — Skill atualmente foca no backend Inertia::defer, mas falha em alertar consumidor sobre null-guards no .tsx.

## Histórico

| Data | Evento | Bug |
|---|---|---|
| 2026-05-15 | RUNBOOK-inertia-defer-pattern.md criado (D-14 incident) | — |
| 2026-05-19 14:30 | F3 PaymentGateway UI mergeado #1135 sem null-guard | tela branca prod biz=WR2 |
| 2026-05-19 14:38 | Wagner reporta + Chrome MCP captura TypeError | — |
| 2026-05-19 14:42 | Hotfix #1141 — null-guard em Cobrança/Index + Settings | resolveu |

## Refs

- [RUNBOOK-inertia-defer-pattern.md](../requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md)
- Skill [`inertia-defer-default`](../../.claude/skills/inertia-defer-default/SKILL.md)
- PR #1141 hotfix
