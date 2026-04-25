# ADR ARQ-0003 (LaravelAI) · Inertia v2 + React (não SPA separada)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

LaravelAI tem 4 superfícies de UI:

1. **Chat lateral flutuante** — abre em qualquer tela do oimpresso
2. **Página `/laravel-ai/chat`** — chat full-screen com histórico
3. **Página `/laravel-ai/graph`** — visualização React Flow
4. **Página `/laravel-ai/audit`** — timeline Recharts

Stack do oimpresso: **Inertia v2 + React 19 + Tailwind 4 + shadcn/ui**.

Opções:

1. **SPA separada** (Vite + React standalone, conecta via API REST)
   - Pró: total liberdade, build separado
   - Contra: precisa CORS, auth próprio (token), duplicar rotas
2. **Inertia (mesmo padrão oimpresso)**
   - Pró: rotas Laravel, auth compartilhado, props injetadas
   - Contra: limita biblioteca a coisas que rodam SSR-like

## Decisão

**Inertia + React (mesmo padrão `auto-memória: preference_persistent_layouts.md`)**.

LaravelAI vira páginas Inertia: `/laravel-ai/chat`, `/laravel-ai/graph`, `/laravel-ai/audit`.

## Consequências

**Positivas:**
- Zero atrito de auth: usuário logado já tem session
- Rotas Laravel + middleware Spatie ('can:laravel-ai.*') funcionam direto
- Props injetadas via Inertia (sem fetch initial state)
- Build único do oimpresso (sem 2º Vite/dist)
- Fácil reusar componentes shadcn/ui dos outros módulos
- Persistent Layout (`auto-memória: preference_persistent_layouts.md`) — sidebar não pisca
- TanStack Query pode ser usado pra mutations dinâmicas (chat) sem trocar de stack

**Negativas:**
- Componente "chat flutuante" precisa rodar fora de Inertia page (componente global no Layout)
  - Solução: registrar `<AiContextualChat />` em `app.tsx` (root) — sempre presente
- React Flow é bem grande (~200KB) — code-split por rota (Inertia já faz)
- WebSocket pra streaming respostas (Server-Sent Events ou Pusher) requer setup extra — Onda 5+

## Pattern obrigatório (já é padrão oimpresso)

```tsx
// resources/js/Pages/LaravelAI/Chat/Index.tsx
import AppShell from '@/Layouts/AppShell';

function ChatPage({ initialContext, businessId }: Props) {
  return <ChatInterface context={initialContext} businessId={businessId} />;
}

ChatPage.layout = (page: ReactNode) => <AppShell children={page} />;
// NÃO envolver em <AppShell> manualmente — preference_persistent_layouts.md

export default ChatPage;
```

```tsx
// resources/js/app.tsx (root, fora de Inertia pages)
function App() {
  return (
    <>
      <InertiaApp />
      <AiContextualChat />  {/* sempre presente */}
    </>
  );
}
```

## Tests obrigatórios

- E2E (Playwright): abrir qualquer tela → ver chat flutuante → fazer pergunta → resposta
- Inertia routes test: `/laravel-ai/chat` retorna 200 com props
- Component test: `<AiContextualChat />` envia rota corrente como contexto

## Decisões em aberto

- [ ] Streaming respostas (SSE ou Pusher)? Onda 5
- [ ] Compartilhar conversas entre users (leadership) ou só user-só? Provável user-só + share link
- [ ] Markdown rendering com code highlight? Provável sim (`react-markdown` + `prism`)

## Alternativas consideradas

- **SPA separada Vite** — rejeitado: complexidade de auth + CORS sem retorno claro
- **Filament admin** — rejeitado: oimpresso não usa Filament; mobile não é prioridade pro chat
- **HTMX** — rejeitado: stack Inertia já é o padrão; não vale fragmentar

## Referências

- `auto-memória: preference_persistent_layouts.md`
- `auto-memória: project_shell_nav_architecture.md`
- ARQ-0001 (storage)
- ARQ-0002 (estende MemCofre)
