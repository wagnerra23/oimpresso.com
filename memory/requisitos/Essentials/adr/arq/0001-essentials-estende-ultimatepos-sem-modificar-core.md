# ADR ARQ-0001 (Essentials) · Essentials estende UltimatePOS sem modificar o core

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Essentials é o módulo comercial HRM + features gerais (Todo, Messages, Knowledge Base, Documents, Reminders) do UltimatePOS. Herdado do upstream, precisa conviver com atualizações do core.

## Decisão

Módulo **nunca modifica** tabelas, models ou controllers do UltimatePOS core (`app/`, `Modules/Accounting` etc). Tudo vive em `Modules/Essentials/` isolado. Integração é via:
- **Events/Listeners** pra reagir a eventos do core.
- **Service Providers** que registram rotas/menus sem tocar arquivos do core.
- **Migrations próprias** com tabelas prefixadas `essentials_*`.

## Consequências

**Positivas:**
- Upgrade do UltimatePOS não quebra Essentials.
- Pode ser desativado via `modules_statuses.json` sem lixo residual.
- Testes isolados por módulo.

**Negativas:**
- Duplicação ocasional de dado (ex.: `leave_types` não pode reutilizar enum do core).
- Joins cross-module às vezes pesados.

## Alternativas consideradas

- **Fork patches no core**: rejeitado (vira pesadelo de merge).
