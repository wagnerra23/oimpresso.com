# Handoff — Inertia v3 upgrade (2026-04-25)

> Criado 2026-04-24 no fim da sessão, quando Wagner aprovou o upgrade (ADR 0023) mas decidiu começar no dia seguinte. Pré-flight já feito; próxima sessão pode ir direto pra execução.

## Decisão

- ADR [0023-inertia-v3-upgrade](../decisions/0023-inertia-v3-upgrade.md) — **Aceita**.
- Upgrade **faseado**: aplicar breakings em todas as páginas, adotar APIs novas (`useHttp`, optimistic, `useLayoutProps`) **só em código novo** (Copiloto, Chat IA, Grow).
- **Não** adotar Vite plugin oficial do Inertia agora (temos `vite.inertia.config.mjs` custom).

## Pré-flight já confirmado (2026-04-24)

| Check | Resultado |
|---|---|
| Laravel 13.6 / PHP 8.4 / React 19 / Vite 6 | ✅ atende requisitos v3 |
| `Inertia::lazy()` no backend | ✅ **zero usos** em `app/` e `Modules/` |
| `LazyProp` no backend | ✅ **zero usos** |
| axios/qs/lodash-es no nosso código | ✅ **zero usos diretos** (só vinham via Inertia) |
| `@inertiajs/react` atual | 2.0.0 → subir para ^3.0 |
| `inertiajs/inertia-laravel` atual | 2.0 → subir para ^3.0 |
| Páginas Inertia | **41 páginas** em `resources/js/Pages/` |
| `useForm` no frontend | **20+ páginas** (risco principal — semântica nova do reset) |
| Cursor paralelo | ✅ session `consolidacao-final` fechada |

## Plano de execução

1. **Push do 6.7-bootstrap atual para origin** (ele está 2 commits à frente) para base limpa
2. Criar branch `feat/inertia-v3` a partir do `6.7-bootstrap` pushado
3. **Baseline antes de tocar nada**: rodar `php artisan test` (Pest) + `npm run typecheck` — guardar evidência de verde
4. `composer require inertiajs/inertia-laravel:^3.0`
5. `npm install @inertiajs/react@^3.0`
6. `php artisan vendor:publish --provider="Inertia\\ServiceProvider" --force` + `php artisan view:clear`
7. **Breakings frontend** — grep + fix:
   - `router.cancel(` → `router.cancelAll(`
   - eventos `'invalid'`/`'exception'` → `'httpException'`/`'networkError'`
   - callbacks `onInvalid`/`onException` → `onHttpException`/`onNetworkError`
   - `useForm` — revisar cada um dos 20+ usos pra ver se depende de reset no `onSuccess` (breaking: agora só reseta `processing`/`progress` no `onFinish`)
   - `<Deferred>` — se houver, usar novo slot prop `reloading`
   - `createInertiaApp` — remover `future.*` se houver
8. **Breakings Blade root** — atributo `inertia` → `data-inertia` no template raiz
9. Rodar Pest + typecheck **pós-upgrade** — precisa continuar verde
10. **Smoke manual prioritário** (essas são as que dói se quebrar):
    - `/sells/create` (ROTA LIVRE — cliente único, não pode quebrar)
    - `/ponto/dashboard` + `/ponto/espelho/show` (polling 30s + heatmap)
    - `/memcofre/inbox` + `/memcofre/ingest`
    - `/essentials/todo/create` (useForm puro)
    - `/essentials/documents` (useForm com upload — mais sensível ao `processing`)
11. Commit + push + PR `feat/inertia-v3` → `6.7-bootstrap`

## Estado atual do repo (snapshot 2026-04-24)

- Branch principal: `6.7-bootstrap` @ `ad97754d`, **2 commits à frente do origin**
- Worktree de trabalho: `D:/oimpresso.com/.claude/worktrees/quirky-jennings-ca8d46` @ `7bc65852`
- Modificações não commitadas no main: `memory/decisions/README.md` + arquivos desta sessão
- 12 worktrees claude/* ativas — não atrapalham mas conferir `git worktree list` antes

## Riscos conhecidos

- **`useForm` reset timing** — maior risco silencioso. Páginas a auditar manualmente:
  - `Essentials/Todo/{Show,Index,Create,Edit}.tsx`
  - `Essentials/{Documents,Messages,Reminders,Holidays,Settings,Knowledge/Create,Knowledge/Edit}/Index.tsx`
  - `Ponto/{BancoHoras/Show,Intercorrencias/Create,Colaboradores/Edit,Escalas/Form,Importacoes/Create,Configuracoes/Reps}.tsx`
  - `MemCofre/{Inbox,Ingest}.tsx`
- **SSR** — `app.tsx` usa `import.meta.env.SSR` + `hydrateRoot`/`createRoot`. Validar `npm run build` antes de commit.
- **Cursor** — conferir `memory/sessions/` antes de começar (criar session nova ao abrir).

## Referências
- ADR: [0023-inertia-v3-upgrade.md](../decisions/0023-inertia-v3-upgrade.md)
- Upgrade guide: https://inertiajs.com/docs/v3/getting-started/upgrade-guide
- Entry point atual: [resources/js/app.tsx](../../resources/js/app.tsx)
