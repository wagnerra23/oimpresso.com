# ADR 0002 · Specs vivem em arquivos .md + DB espelhado

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude

## Contexto

Onde armazenar os requisitos funcionais (user stories, regras Gherkin, decisões)?

**Duas alternativas principais:**
1. **Só no banco** (docs_requirements): fácil de indexar, CRUD via UI.
2. **Só em arquivos .md**: rastreável via git, editável no VS Code.
3. **Ambos sincronizados**: arquivo é a fonte da verdade, DB é cache indexável.

## Decisão

Adotar **opção 3**: arquivos `.md` em `memory/requisitos/{Modulo}/` são a **fonte da verdade**. O banco `docs_requirements` é um cache reconstruído a partir dos arquivos via `php artisan module:requirements`.

- **Leitura rápida na UI**: via `docs_requirements` (com joins).
- **Edição canônica**: sempre no arquivo `.md` (versionado no git).
- **Sincronização**: comando `module:requirements` regera o cache.

## Consequências

**Positivas:**
- Histórico rastreável via `git log` e `git blame`.
- Editável offline, em qualquer editor, sem UI travando deploy.
- Review de spec vira PR review.
- Backup trivial (é só o repo).

**Negativas:**
- Risco de drift entre arquivo e DB se esquecer de rodar o sync.
- UI não pode editar direto o requisito (tem que editar arquivo).

**Mitigação**: o botão "Apply" no Inbox (Fase 3) regrava automaticamente o arquivo .md correspondente — eliminando o drift.

## Alternativas consideradas

- **Só DB**: descartado — perde rastreabilidade git.
- **Só arquivos** (sem cache): descartado — cada request parsearia MD, lento com 29+ módulos.
