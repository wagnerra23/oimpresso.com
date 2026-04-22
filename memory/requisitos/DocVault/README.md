---
module: DocVault
alias: docvault
status: ativo
migration_target: N/A (novo)
migration_priority: alta
risk: baixo
areas: [documentacao, evidencia, requisitos, knowledge]
last_generated: 2026-04-22
version: 0.1
---

# DocVault

Cofre de documentação viva. Ingestão de evidências (screenshots, chat logs, erros, arquivos) → classificação (manual ou IA) → virada em requisitos estruturados rastreáveis.

## Propósito

Transformar o caos informal de conversas + erros + anotações em **documentação auditável do sistema**. Cada requisito tem evidência de origem; cada evidência pode virar story ou regra.

## Índice

- **[ARCHITECTURE.md](ARCHITECTURE.md)** — camadas, modelos, visão macro
- **[SPEC.md](SPEC.md)** — user stories + regras Gherkin
- **[CHANGELOG.md](CHANGELOG.md)** — evolução versão a versão
- **[adr/](adr/)** — decisões arquiteturais individuais numeradas (ADRs)

## Rotas principais

- `/docs` — dashboard (KPIs globais + cobertura por módulo)
- `/docs/ingest` — nova evidência (upload / URL / texto)
- `/docs/inbox` — triagem de evidências pendentes
- `/docs/modulos/{Nome}` — viewer de módulo (overview + spec + arq + changelog)

## Status atual

- Fase 1: scaffold + 4 telas React + CRUD de evidências ✅
- Fase 2: estrutura de docs por módulo (este arquivo) 🔄
- Fase 3: IA classificando evidências + apply automático no .md ⏳
- Fase 4: graph viewer + busca semântica (Meilisearch opcional) ⏳
