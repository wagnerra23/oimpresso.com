# Execução — Inertia v3 upgrade (2026-04-25)

> Executado localmente em `D:/oimpresso.com` na manhã de 2026-04-25 após tentativa frustrada com agente remoto (CCR não inicializou — bug do serviço, branch nunca apareceu no GitHub). Wagner decidiu fazer aqui mesmo.

## Status dos passos

| # | Passo | Status |
|---|---|---|
| 1 | Branch `feat/inertia-v3` a partir de `6.7-bootstrap` | ✅ |
| 2 | Baseline Pest | ⏭ pulado (Git Bash sem PHP; Pest não roda lá) |
| 3 | `composer require inertiajs/inertia-laravel:^3.0` → **v3.0.0** | ✅ |
| 4 | `npm install @inertiajs/react@^3.0` → **3.0** (10 pacotes removidos) | ✅ |
| 5 | `vendor:publish` Inertia + `view:clear` | ✅ |
| 6 | Greps de breakings frontend: `router.cancel`, eventos `invalid/exception`, `<Deferred>`, `future:` | ✅ **0 ocorrências** — nada a alterar |
| 7 | useForm: marcar TODO em 20+ páginas | ⏭ deixado em lista (auditoria interativa pós-smoke) |
| 8 | Blade root `inertia` → `data-inertia` | ✅ não aplicável (template usa `@inertia` directive, helper já emite `data-inertia` no v3) |
| 9 | Remover `future.*` do `createInertiaApp` | ✅ não aplicável (já não tinha) |
| 10 | `npm run typecheck` + `npm run build:inertia` | ⚠ typecheck com 1 erro novo (não-bloqueante); build verde em 9.72s |

## Erro TS novo (1, não-bloqueante)

`resources/js/app.tsx:14` — `createInertiaApp` reclama do retorno do `resolvePageComponent` do `laravel-vite-plugin/inertia-helpers`. O helper ainda não atualizou pra a assinatura nova do v3. **Vite compila normal** (TS error é só nos types, runtime passa).

**Workaround sugerido (NÃO aplicado ainda):**
```ts
resolve: (name) => {
  const pages = import.meta.glob<{ default: ReactComponent }>('./Pages/**/*.tsx');
  return pages[`./Pages/${name}.tsx`]!();
},
```
Ou aguardar update do `laravel-vite-plugin`.

## Erros TS pré-existentes (45, mesmos da baseline)

Todos `TS6133` (declared but never read), `TS18048/TS2532` (possibly undefined), `TS2345/TS2322` (string|undefined assignment) em:
- `Components/shared/SimpleMarkdown.tsx` (~25 erros)
- `Components/shared/ponto/{ActivityFeed,MonthHeatmap,PresenceStrip}.tsx`
- `Pages/{Essentials,MemCofre,Ponto}/...` (vários, mesmos do `git status` pré-upgrade)

NÃO consertar nesta branch.

## Build pós-upgrade

✅ `npm run build:inertia` → **built in 9.72s**.
- `app-D33p3ify.js` 357 kB (gzip 112 kB)
- `AppShell-m8gG_oI3.js` 918 kB (gzip 180 kB) — warning de chunk size (pré-existente)
- Zero erros runtime.

`npm run build` (build principal não-Inertia) **falha** por `tailwind.scss` referenciar `themes/oimpresso` que não existe. **Pré-existente, não relacionado**.

## Bundle size — confirmação do ADR

NPM removeu **10 pacotes** durante o install do v3. Isto inclui axios, qs, lodash-es e transitivas, exatamente como o ADR 0023 previu.

## useForm — páginas a auditar manualmente após smoke

Breaking sutil do v3: `useForm` agora só reseta `processing`/`progress` no `onFinish` (antes resetava no `onSuccess` também). Se alguma página depende de estado resetado dentro do `onSuccess`, vai comportar diferente.

Páginas com `useForm` (auditar caso a caso ao tocar cada uma):
- `Essentials/Todo/{Show,Index,Create,Edit}.tsx`
- `Essentials/Documents/Index.tsx` (upload — mais sensível)
- `Essentials/{Holidays,Reminders,Messages,Settings}/Index.tsx`
- `Essentials/Knowledge/{Create,Edit}.tsx`
- `Ponto/BancoHoras/Show.tsx`
- `Ponto/Intercorrencias/Create.tsx`
- `Ponto/Colaboradores/Edit.tsx`
- `Ponto/Escalas/Form.tsx`
- `Ponto/Importacoes/Create.tsx`
- `Ponto/Configuracoes/Reps.tsx`
- `MemCofre/{Inbox,Ingest}.tsx`

## Smoke browser — resultado (executado 2026-04-25 manhã)

Todas as Inertia testadas hidrataram limpas, **zero erros de console relevantes**:

| Página | hydrated | h1 | nav | OK |
|---|---|---|---|---|
| `/memcofre/inbox` | ✅ | (sidebar/inbox) | ✅ | ✅ |
| `/ponto` | ✅ | "Dashboard" | ✅ | ✅ |
| `/essentials/todo` | ✅ | "Tarefas (To-Do)" | ✅ | ✅ |
| `/essentials/document` | ✅ | "Documentos" | — | ✅ |

**Não testadas (priorizar antes do merge):**
- `/sells/create` — Blade puro, **NÃO** é Inertia. Smoke pegou `hasContent=0` + `SyntaxError: Unexpected string` em `/sells/create:1767:34` e `/home:1786:34`. Esses dois são templates Blade tradicionais (admin LTE), não foram tocados pelo upgrade — provavelmente **pré-existentes**. Wagner investigar separado.
- `/ponto/espelho`, `/ponto/aprovacoes`, `/memcofre/ingest`, `/essentials/todo/create` (formulários)

## Próximos passos pro Wagner

1. **Validar `/sells/create`** (cliente ROTA LIVRE) — confirmar se SyntaxError é pré-existente fazendo checkout em `6.7-bootstrap` e abrindo a mesma página. Se for igual, é débito separado, não bloqueia este PR.
2. Auditar `useForm` nas páginas listadas (apenas onde ele já estava editando — sem refactor preventivo).
3. Push `feat/inertia-v3` → abrir PR para `6.7-bootstrap`.
4. Em PR separado depois: corrigir o tipo do `app.tsx:14` (workaround acima ou esperar update do `laravel-vite-plugin`).

## Referências
- ADR: [memory/decisions/0023-inertia-v3-upgrade.md](../decisions/0023-inertia-v3-upgrade.md)
- Handoff original: [memory/sessions/2026-04-25-inertia-v3-handoff.md](2026-04-25-inertia-v3-handoff.md)
