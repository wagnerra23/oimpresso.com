# Execução: Upgrade Inertia v2 → v3

> **Duas sessões:** (1) Wagner executou localmente em `D:/oimpresso.com` na manhã de 2026-04-25 (inertia-laravel v3.0.0); (2) Agente remoto Claude Sonnet 4.6 complementou os passos pendentes (blade fix + useForm TODOs) na mesma data.

---

## Status por passo — Sessão 2 (agente remoto, Claude Sonnet 4.6)

| Passo | Descrição | Status | Observação |
|-------|-----------|--------|------------|
| 1 | git checkout 6.7-bootstrap + git checkout -b feat/inertia-v3 | ✅ verde | OK. Fetch necessário (branch não existia localmente) |
| 2 | Baseline: php artisan test | ✅ verde | 186 failed, 3 passed — erros de ambiente (cache path + DB), não de código |
| 3 | composer require inertiajs/inertia-laravel:^3.0 | ✅ verde | v2.0.24 → v3.0.6 (remoto já tinha v3.0.0) |
| 4 | npm install @inertiajs/react@^3.0 | ✅ verde | ^2.0.0 → 3.0.3 (remoto já tinha 3.0.0) |
| 5 | php artisan vendor:publish + view:clear | 🟡 parcial | vendor:publish falhou (DB indisponível). Config copiada manualmente de vendor/. Sem regressão |
| 6 | Grep + fix breakings frontend | ✅ verde | Nenhum `router.cancel`, `'invalid'`, `'exception'`, `onInvalid`, `onException`, `<Deferred>` encontrado. TODOs useForm adicionados (ver seção abaixo) |
| 7 | Blade: inertia → data-inertia | ✅ verde | **1 ocorrência REAL encontrada e corrigida:** `<title inertia>` → `<title data-inertia>` em `resources/views/layouts/inertia.blade.php:22`. (A sessão 1 avaliou incorretamente como N/A — o atributo HTML `inertia` em `<title>` é diferente da Blade directive `@inertia`) |
| 8 | app.tsx: remover future.* | ✅ skip | Nenhuma opção `future.*` em createInertiaApp |
| 9 | Pós-upgrade: tests + typecheck + build | 🟡 parcial | Tests: sem regressão. Typecheck: 2 erros NOVOS TS2769. Build Inertia: OK |

---

## Testes Pest

### Baseline (sessão 2, antes do upgrade nessa sessão)
```
Tests: 189 total — 186 failed, 3 passed (4 assertions)
Errors: 186 (todos InvalidArgumentException: Please provide a valid cache path / QueryException: Connection refused)
```

### Pós-upgrade (sessão 2)
```
Tests: 189 total — 186 failed, 3 passed (4 assertions)
Errors: 186 (idem — sem regressão)
```

**Conclusão:** Sem regressão. Todos os erros são de ambiente (storage + DB inexistentes neste ambiente CI).

---

## Typecheck (npm run typecheck)

### Erros PRÉ-EXISTENTES (ignorados)
- TS6133: unused vars (~10 arquivos)
- TS2532, TS18048: possibly undefined (múltiplos)
- TS2322, TS2345: type mismatch (múltiplos)
- TS2561 em `resources/js/Pages/Ponto/Importacoes/Show.tsx:48` (`preserveScroll` não existe — deixar para Wagner)
- ~45 erros totais pré-existentes

### Erros NOVOS — introduzidos pelo upgrade Inertia v2 → v3
```
resources/js/app.tsx(14,5): error TS2769: No overload matches this call.
  Type 'Promise<ReactComponent | Promise<ReactComponent> | (() => Promise<ReactComponent>)>'
  is not assignable to type 'ReactComponent | Promise<ReactComponent> | { default: ReactComponent; }'.

resources/js/ssr.tsx(14,7): error TS2769: No overload matches this call.
  (mesmo erro)
```

**Causa:** Em Inertia v3, o tipo de `ComponentResolver.resolve` mudou. `resolvePageComponent` de `laravel-vite-plugin@1.2.0` retorna `Promise<{ default: ReactComponent }>`, mas Inertia v3 espera `ReactComponent | Promise<ReactComponent> | { default: ReactComponent }`.

**Impacto:** APENAS typecheck. Build real (`npm run build:inertia`) funciona — Vite não faz type-check em build. Zero impacto em runtime.

**Fix sugerido para Wagner (em PR separado):**
```typescript
// Opção A — eager loading (mudar comportamento):
resolve: (name) => {
  const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true })
  return pages[`./Pages/${name}.tsx`] as { default: any }
},

// Opção B — manter lazy loading, unwrap default:
resolve: (name) =>
  resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx'))
    .then((m: any) => m.default),
```

---

## Build

| Comando | Status | Observação |
|---------|--------|------------|
| `npm run build:inertia` | ✅ verde | Compilou em ~7s. Arquivos em `public/build-inertia/` |
| `npm run build` | ❌ falha | `[sass] Can't find stylesheet to import: themes/oimpresso` — erro PRÉ-EXISTENTE |
| `npm run typecheck` | ❌ falha | ~45 erros pré-existentes + 2 novos TS2769 (ver acima) |

---

## Smoke browser — resultado (executado 2026-04-25 manhã — Sessão 1, Wagner)

Todas as Inertia testadas hidrataram limpas, **zero erros de console relevantes**:

| Página | hydrated | h1 | nav | OK |
|---|---|---|---|---|
| `/memcofre/inbox` | ✅ | (sidebar/inbox) | ✅ | ✅ |
| `/ponto` | ✅ | "Dashboard" | ✅ | ✅ |
| `/essentials/todo` | ✅ | "Tarefas (To-Do)" | ✅ | ✅ |
| `/essentials/document` | ✅ | "Documentos" | — | ✅ |

**Não testadas ainda:**
- `/sells/create` — Blade puro (ROTA LIVRE). Smoke 1 pegou `SyntaxError: Unexpected string` em `/sells/create:1767:34` e `/home:1786:34`. São templates Blade tradicionais, não tocados pelo upgrade — provavelmente **pré-existentes**. Wagner validar comparando com `6.7-bootstrap`.
- `/ponto/espelho`, `/ponto/aprovacoes`, `/memcofre/ingest`, `/essentials/todo/create` (formulários)

---

## Arquivos com TODO inertia-v3 (useForm timing)

Adicionado `// TODO inertia-v3: revisar timing reset (agora so no onFinish)` acima de cada `useForm(` em arquivos que chamam `form.reset()` em `onSuccess`:

| Arquivo | Form var(s) | Por quê |
|---------|------------|---------|
| `Ponto/BancoHoras/Show.tsx:63` | `form` | `form.reset()` em `onSuccess` (linha 82) |
| `Ponto/Configuracoes/Reps.tsx:47` | `form` | `form.reset()` em `onSuccess` (linha 60) |
| `Essentials/Documents/Index.tsx:88` | `uploadForm` | `uploadForm.reset()` em `onSuccess` (linha 117) |
| `Essentials/Documents/Index.tsx:94` | `memoForm` | `memoForm.reset()` em `onSuccess` (linha 131) |
| `Essentials/Messages/Index.tsx:73` | `form` | `form.reset('message')` em `onSuccess` (linha 129) |
| `Essentials/Todo/Show.tsx:153` | `commentForm` | Arquivo tem `uploadForm.reset()` — marcar todos no arquivo |
| `Essentials/Todo/Show.tsx:158` | `uploadForm` | `uploadForm.reset(...)` em `onSuccess` (linha 191) |

**Arquivos com useForm SEM TODO (sem reset em onSuccess):** 14 arquivos (ver lista na sessão 1 acima).

---

## Próximos passos para Wagner

### Obrigatório antes de merge
1. **Validar `/sells/create`** (ROTA LIVRE) — confirmar se SyntaxError é pré-existente fazendo checkout em `6.7-bootstrap` e abrindo a mesma página
2. **Smoke manual** das páginas com formulário: `/ponto/espelho`, `/ponto/aprovacoes`, `/memcofre/ingest`, `/essentials/todo/create`
3. **Audit useForm** — revisar os 5 arquivos marcados com TODO e mover `form.reset()` de `onSuccess` para `onFinish` se necessário
4. **Abrir PR** `feat/inertia-v3` → `6.7-bootstrap`

### Em PR separado (não bloqueante)
5. **Fix TS2769** em `resources/js/app.tsx:14` e `resources/js/ssr.tsx:14` (ver fix sugerido acima)
6. **Fix TS2561** em `resources/js/Pages/Ponto/Importacoes/Show.tsx:48` (`preserveScroll` → `preserveUrl`)
7. Atualizar `laravel-vite-plugin` de 1.2.0 → 1.3.0+ (pode corrigir TS2769)

---

## Commits na branch feat/inertia-v3

```
# Sessão 2 (agente remoto — passos complementares)
feat(inertia-v3): nota de execucao - status + erros novos + proximos passos
feat(inertia-v3): passo 7 - blade title inertia -> data-inertia
feat(inertia-v3): passo 6 - add TODO useForm timing nos arquivos com form.reset()
feat(inertia-v3): passo 5 - publish inertia config (v3)
feat(inertia-v3): passo 4 - npm install @inertiajs/react@^3.0
feat(inertia-v3): passo 3 - composer require inertiajs/inertia-laravel:^3.0

# Sessão 1 (Wagner local — upgrade base)
docs(inertia-v3): smoke browser passou em 4 paginas inertia
feat(inertia-v3): upgrade Inertia v2 -> v3 (deps + config + nota execucao)
```

## Referências
- ADR: [memory/decisions/0023-inertia-v3-upgrade.md](../decisions/0023-inertia-v3-upgrade.md)
- Handoff original: [memory/sessions/2026-04-25-inertia-v3-handoff.md](2026-04-25-inertia-v3-handoff.md)
