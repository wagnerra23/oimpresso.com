# Sprint 2 — Listagem de OS (piloto MWART)

> Primeira migração Blade → React do núcleo `Officeimpresso`. Valida o padrão MWART em produção.

## Objetivo

Migrar a tela **Listagem de Ordens de Serviço** (`/os`, `/officeimpresso/os`) de Blade para React/Inertia, mantendo paridade funcional 100% e zero re-aprendizado pelo cliente final.

Esta é a **prova do padrão MWART** (Module Web App React Transition). Se funcionar aqui, replica nos outros 60+ telas do Officeimpresso na Sprint 3+.

## Não-objetivo

- ❌ Não mexer em Detalhe de OS (Sprint 2.5)
- ❌ Não mexer em Nova OS (Sprint 2.5)
- ❌ Não migrar outros módulos
- ❌ Não refatorar Controller além do necessário pra Inertia

## Deliverables (PR2)

1. `02-adr-mwart-contract.md` — contrato `LegacyMenuAdapter` + flag `inertia`
2. `03-schema-os-indices.sql` — índices novos em `ordens_servico` para listagem rápida
3. `04-spec-os-controller.md` — spec do `OsController@index` Inertia
4. `05-spec-os-index-react.md` — spec do `Pages/Os/Index.tsx`
5. `06-skill-mwart-migrate.md` — skill reutilizável pra próximas migrações
6. `07-checklist-wagner.md` — passos PR + soak 48h
7. `08-rollback-plan.md` — feature flag + plano de reversão

## Critério de aceite

- [ ] Toggle `MWART_OS_INDEX=true` no `.env` faz `/os` renderizar React
- [ ] Toggle `MWART_OS_INDEX=false` reverte pra Blade sem perda de estado
- [ ] Filtros (status/cliente/período/responsável) funcionam idênticos ao Blade
- [ ] Paginação preserva URL bookmarkável (`?page=3&status=aprovacao`)
- [ ] Bulk actions (mudar etapa, arquivar) idênticas ao Blade
- [ ] p95 < 400ms na listagem (medido via Telescope)
- [ ] Zero erros JS no Sentry em 48h soak

## Soak

48h em staging com 3 usuários internos (Wagner, Henrique, Camila) antes de promover pra prod.

## Dependências

- ✅ Sprint 1 mergeada (Daily Brief operacional pra Opus consultar contexto desta sprint)
- ✅ AppShell.tsx canônico em `resources/js/Layouts/`
- ✅ `LegacyMenuAdapter` aceitando flag `inertia` por rota

## Out of scope (próximas sprints)

- Sprint 2.5: Detalhe OS + Nova OS (mesma tabela, mesmo controller)
- Sprint 3: Clientes, Orçamentos, Produtos
- Sprint 4: Operacional de produção
