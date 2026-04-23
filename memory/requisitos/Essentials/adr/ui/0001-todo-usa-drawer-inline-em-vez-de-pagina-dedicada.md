# ADR UI-0001 (Essentials) · Todo usa drawer inline em vez de página dedicada

- **Status**: proposed
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: ui

## Contexto

Lista de ToDos do Essentials aparece em várias telas (dashboard, home do usuário, módulo). Abrir uma página nova pra ver/editar cada tarefa é overhead pro fluxo rápido de "ticar item".

## Decisão

UI de Todo usa **drawer lateral** (Sheet do shadcn) em vez de rota `/essentials/todo/{id}`. Clique na task no dashboard abre o drawer com detalhes + comentários + anexos, mantendo contexto do dashboard por trás.

Paginas dedicadas existem só pra:
- Lista (`/essentials/todo`) — grid com filtros
- Criar (`/essentials/todo/create`) — form completo com upload

## Consequências

**Positivas:**
- UX rápida pra ticar/editar item sem perder contexto.
- Mobile-friendly (drawer ocupa full-height em telas pequenas).

**Negativas:**
- Deep linking direto pra item específico mais difícil (exige query param).
- Testes E2E precisam lidar com estado de drawer aberto/fechado.

## Alternativas consideradas

- **Modal centralizado**: rejeitado — quebra fluxo visual em listas longas.
- **Página dedicada por task**: rejeitado — overhead de navegação.
