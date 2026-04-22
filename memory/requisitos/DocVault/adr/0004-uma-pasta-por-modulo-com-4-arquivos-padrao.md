# ADR 0004 · Uma pasta por módulo com README + ARCHITECTURE + SPEC + CHANGELOG + ADRs

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude

## Contexto

Antes: cada módulo tinha um único arquivo `memory/requisitos/{Modulo}.md` misturando frontmatter + áreas funcionais + user stories + regras + mudanças. Para 29 módulos × centenas de stories futuras, isso vira ilegível.

Opções avaliadas:

1. **Um arquivo por módulo** (atual): simples mas não escala.
2. **Três pastas paralelas** (`memory/requisitos/`, `memory/arquitetura/`, `memory/changelog/`): cada módulo com 3 arquivos espalhados — mental load ruim.
3. **Uma pasta por módulo** com 4-5 arquivos padrão: modular, navegável, fácil de zipar/mover/deletar.

## Decisão

Adotar **opção 3**: cada módulo vira uma pasta em `memory/requisitos/{Modulo}/` contendo:

- `README.md` — porta de entrada, frontmatter + índice.
- `ARCHITECTURE.md` — camadas, modelos, fluxos técnicos. Muda pouco.
- `SPEC.md` — user stories (`### US-{AREA}-NNN`) + regras Gherkin (`### R-{AREA}-NNN`). Muda sempre.
- `CHANGELOG.md` — histórico versionado (Keep-a-Changelog + Semver).
- `adr/` — pasta com ADRs numerados `NNNN-slug.md` (esta pasta).

Arquivos planos `memory/requisitos/{Modulo}.md` continuam funcionando — `RequirementsFileReader` faz fallback. Migração gradual.

## Consequências

**Positivas:**
- Separação clara entre arquitetura (estável) e spec (volátil).
- Changelog por módulo facilita release notes granulares.
- ADRs numerados dão rastreabilidade de decisões.
- DocVault UI mostra tabs dedicadas (Overview/Arquitetura/Stories/Regras/Changelog/Decisões/Markdown).
- Onboarding acelera: dev novo abre README e segue o mapa.

**Negativas:**
- 5 arquivos por módulo em vez de 1 — mais cliques pra editar tudo.
- Exige migração gradual dos 29 módulos existentes.

**Mitigação**: fallback no reader mantém retrocompat. Migração pode ser feita 1 módulo por vez, priorizando os mais ativos (PontoWr2, Essentials).

## Alternativas consideradas

- **Tudo em 1 arquivo gigante**: rejeitado — ilegível > 500 linhas.
- **3 pastas paralelas**: rejeitado — mental load + git mv por módulo.
- **Wiki externa (Confluence/Notion)**: rejeitado — perde versionamento atrelado ao código.
