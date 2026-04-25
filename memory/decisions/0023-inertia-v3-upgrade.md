# ADR 0023 — Upgrade para Inertia.js v3

**Status:** ✅ Aceita
**Data decisão:** 2026-04-24
**Início execução:** 2026-04-25 (Wagner executa; janela ainda com 1 cliente ativo — ROTA LIVRE — para minimizar blast radius)
**Escopo:** Plataforma (afeta todos os módulos que usam React/Inertia)

## Histórico
- **2026-04-24** — Proposta criada. Pre-flight grep: zero uso de `Inertia::lazy`/`LazyProp` no backend; 20+ páginas usam `useForm` (risco principal: semântica nova do reset). Cursor session `consolidacao-final` fechada — sem trabalho paralelo ativo. Wagner aprovou, decidiu executar **antes** de onboardar novos clientes.
- **2026-04-25** — (pendente) execução conforme plano do handoff `memory/sessions/2026-04-25-inertia-v3-handoff.md`.

## Contexto

Inertia.js v3.0 saiu estável em março/2026. Hoje o projeto roda:

| Peça | Versão atual | Requisito v3 | Compatível? |
|---|---|---|---|
| `inertiajs/inertia-laravel` | ^2.0 | ^3.0 | ⬆ upgrade |
| `@inertiajs/react` | ^2.0.0 | ^3.0 | ⬆ upgrade |
| Laravel | ^13.0 | 11+ | ✅ |
| PHP | 8.4 (composer ^8.1) | 8.2+ | ✅ (bump min) |
| React | 19 | 19+ | ✅ |
| Vite | ^6.2 | ok | ✅ |

Superfície atual: **41 páginas** em [resources/js/Pages](resources/js/Pages) + 2 layouts + SSR ativo em [app.tsx](resources/js/app.tsx).
Grep confirmou **zero uso direto de axios/qs/lodash-es** no nosso código — só via dependências Inertia.

## Decisão (proposta)

**Aceitar o upgrade, faseado, em branch próprio** (`feat/inertia-v3`), sem merge em `6.7-bootstrap` até suíte Pest verde + smoke manual passar.

Ordem:
1. Branch isolado + janela combinada com Cursor (checar `memory/sessions/` e `git status`)
2. `composer require inertiajs/inertia-laravel:^3.0` + `npm install @inertiajs/react@^3.0`
3. Aplicar breakings (checklist abaixo)
4. `php artisan vendor:publish --provider="Inertia\\ServiceProvider" --force` + `php artisan view:clear`
5. Rodar Pest (25+ testes do form shim, etc.) + smoke nas 41 páginas
6. Adotar **só em código novo** (Copiloto, Chat IA contextual, Grow) as APIs novas — `useHttp`, optimistic updates, `useLayoutProps`
7. **Não** adotar o Vite plugin oficial do Inertia agora — temos `vite.inertia.config.mjs` custom; avaliar em ADR separado

## Benefícios

### Diretos
- **Bundle ~15–30 KB menor gzip** (axios + qs + lodash-es removidos do runtime Inertia)
- **SSR no `npm run dev`** sem processo Node separado → DX do frontend melhora
- **Menos boilerplate backend** em payloads parciais: `Inertia::optional()/defer()/merge()` funcionam em arrays aninhados com dot-notation

### Estratégicos (casadas com roadmap)
- **`useHttp` hook** resolve chamadas standalone com estado reativo, cancelamento e progresso — casa direto com:
  - Chat IA contextual ([ideia_chat_ia_contextual.md](memory/ideia_chat_ia_contextual.md))
  - Módulo Copiloto ([project_modulo_copiloto.md](memory/project_modulo_copiloto.md))
  - US-FIN-013 (tela unificada Financeiro)
- **Optimistic updates com rollback automático** — melhora percepção em Ponto (marcações, alert inbox) e Copiloto
- **`useLayoutProps/setLayoutProps`** substitui o hack de `Component.layout` + props injetadas por evento → alinha com [preference_persistent_layouts.md](memory/preference_persistent_layouts.md)

## Dificuldades / Riscos

### Breaking changes a revisar (checklist de upgrade)
- [ ] Backend: trocar `Inertia::lazy()` → `Inertia::optional()` (grep no app + modules)
- [ ] Backend: `LazyProp` removido — nenhuma referência conhecida hoje, mas grep
- [ ] Backend: config Inertia republicada (settings migram para namespace `pages`; seção `testing` simplificou)
- [ ] Frontend: eventos `invalid` → `httpException`, `exception` → `networkError` (+ callbacks `onHttpException`, `onNetworkError`)
- [ ] Frontend: `router.cancel()` → `router.cancelAll()`
- [ ] Frontend: `useForm` só reseta `processing`/`progress` no `onFinish` (pode quebrar lógica que contava com reset imediato)
- [ ] Frontend: `<Deferred>` no React não reseta fallback em partial reload — usar novo slot `reloading` (grep `Deferred`)
- [ ] Template raiz: atributo `inertia` → `data-inertia` no root (checar Blade layout)
- [ ] `createInertiaApp`: remover opções `future.*` se houver
- [ ] ESM-only: garantir que nenhum import usa `require()` — já somos `"type": "module"` ✅

### Riscos operacionais
- **Cursor trabalha em paralelo** — coordenar upgrade em janela combinada (senão merge ruim em `6.7-bootstrap`)
- **Sem codemod automático** — todo o checklist acima é manual
- **41 páginas** + form shim Pest suite precisam de smoke pós-upgrade
- **SSR path em [app.tsx](resources/js/app.tsx)** usa `import.meta.env.SSR` — revalidar com build novo
- **Delphi cliente não é afetado** (contrato de API não muda), mas qualquer partial-reload que devolva payload consumido pelo Delphi precisa de regressão — provavelmente nenhum, mas revisar

### Custo estimado
- **2–4 dias** desenvolvedor: upgrade + checklist + smoke + correções
- **Janela de freeze** de ~1 dia combinada com Cursor

## Consequências

### Positivas
- Base técnica atualizada (1 major release atrás de bundler/React atuais)
- Desbloqueia padrões nativos para features IA/realtime sem reinventar HTTP client
- Redução de superfície de deps transitivas (axios/qs/lodash)

### Negativas
- Trabalho puro de manutenção sem feature nova imediata para Wagner/clientes
- Risco de regressão silenciosa em páginas legadas pouco testadas (muitas das 41)
- Adiciona pressão temporal no freeze com Cursor

### Neutras
- Não obriga adotar Vite plugin oficial (decisão separada)
- Não obriga reescrever páginas existentes — só alinhar breakings

## Alternativas consideradas

1. **Ficar em v2.x indefinidamente** — seguro, mas acumula débito; `useHttp` e optimistic updates seriam reimplementados à mão em Copiloto/Chat IA
2. **Upgrade agressivo com refactor das 41 páginas** — custo alto (2–3 semanas), sem ROI imediato; rejeitado
3. **Upgrade faseado (escolhido)** — breakings aplicados em todas as páginas, mas APIs novas só em código novo

## Referências

- [Inertia v3 upgrade guide](https://inertiajs.com/docs/v3/getting-started/upgrade-guide)
- [Laravel News — Inertia v3 release](https://laravel-news.com/inertia-3-0-0)
- [ideia_chat_ia_contextual.md](../ideia_chat_ia_contextual.md) — consumidor futuro de `useHttp`
- [project_modulo_copiloto.md](../project_modulo_copiloto.md) — consumidor futuro de optimistic updates
- [preference_persistent_layouts.md](../preference_persistent_layouts.md) — melhora com `useLayoutProps`
