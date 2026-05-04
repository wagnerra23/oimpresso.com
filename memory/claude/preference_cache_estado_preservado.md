---
name: Cache/estado preservado entre navegações Inertia (não pode reload total)
description: Wagner exigiu 2026-04-26 que telas mantenham estado/dados em cache ao navegar (não recarregar tudo do zero a cada clique). Antes do upgrade Inertia v3 + Financeiro Onda 2 isso funcionava. Regressão recente quebrou. Regra dura: useForm com forceFormData:false quando aplicável; Links com preserveScroll+preserveState; tabelas com filtros guardam estado; nada de window.location.reload() ou router.reload() agressivo dentro de Pages.
type: feedback
originSessionId: 78bc6849-f503-4b7f-93a1-4c2a439cc019
---
**Regra:** páginas Inertia do oimpresso NÃO podem fazer reload total quando o usuário navega entre telas. Estado de filtros, scroll, formulários parciais, tabelas paginadas — tudo precisa preservar entre cliques quando faz sentido.

**Why:** Wagner falou explicitamente em 2026-04-26 que "as outras telas já estavam corretas" antes da Onda 2 do Financeiro / upgrade Inertia v3 / merges recentes. Algo quebrou. Recarregar tudo do zero degrada UX significativamente — usuário perde scroll position, perde filtro selecionado, perde dado parcial digitado. Para uso intensivo (operador no PDV/financeiro) é frustração diária que vira churn.

**How to apply:**

1. **Links navegação** entre telas relacionadas (ex: dashboard → detalhe → voltar):
   ```tsx
   import { Link } from '@inertiajs/react';
   <Link href="/financeiro/contas-pagar" preserveScroll preserveState>...</Link>
   ```

2. **useForm com forceFormData: false** quando o form não tem upload (default v3):
   ```tsx
   const form = useForm({...}); // sem opções extras, default já é correto
   ```

3. **Tabelas com filtros**: usar `router.get(url, params, { preserveState: true, preserveScroll: true, only: ['data'] })` em vez de `router.visit(url)` puro.

4. **NUNCA usar:**
   - `window.location.reload()` dentro de Page Inertia
   - `router.reload()` sem `only: [...]` específicos
   - `router.visit()` sem `preserveState` quando a navegação é "in-app"

5. **Sempre usar quando:**
   - `<Link only={['paginate']}>` em paginação de tabela (só recarrega o pedaço da tabela)
   - `<Link preserveScroll>` em links de navegação interna na mesma seção
   - Inertia v3 `cache.duration` em props que mudam pouco (configs, listas estáticas)

6. **Pos useForm submit que redireciona pra outra tela:**
   ```tsx
   form.post(url, {
     onFinish: () => form.reset('field1', 'field2'), // só campos específicos, não reset total
     preserveScroll: true,
   });
   ```

**Sentinela:** se um PR adicionar `router.visit()` sem `preserveState` numa Page Inertia, ou `window.location.reload()`, deve falhar code review. Marcar como regressão de UX crítica.

**Conexão com auto-memória:**
- `preference_persistent_layouts.md` (Component.layout pattern — não envolver em <AppShell>)
- `project_inertia_v3_upgrade.md` (mudança timing reset no useForm — onFinish)

**Ver também:** `memory/decisions/0023-inertia-v3-upgrade.md`. Pendência: 20+ páginas com `useForm` precisam audit (trigger agendado pra 2026-05-10).

**Status:** trigger remoto agendado pra 2026-04-27 11h UTC vai auditar telas Financeiro especificamente e propor PR de fix.
