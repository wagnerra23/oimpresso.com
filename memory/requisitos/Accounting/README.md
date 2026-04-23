---
module: Accounting
alias: accounting
status: ativo
migration_target: react
migration_priority: baixa (grande, fazer por último ou dividir)
risk: alto
areas: [Budget, Chart Of Account, Core, Journal Entry, Media, Reconcile, Report, Settings, Transaction]
last_generated: 2026-04-22
scale:
  routes: 69
  controllers: 12
  views: 91
  entities: 70
  permissions: 12
---


# Accounting


1. [Objetivo](#1-objetivo)
2. [Áreas funcionais](#2-áreas-funcionais)
3. [User stories](#3-user-stories)
4. [Regras de negócio (Gherkin)](#4-regras-de-negócio-gherkin)
5. [Integrações](#5-integrações)
6. [Dados e entidades](#6-dados-e-entidades)
7. [Decisões em aberto](#7-decisões-em-aberto)
8. [Histórico e notas](#8-histórico-e-notas)

---

## Índice

- **[ARCHITECTURE.md](ARCHITECTURE.md)** — camadas, modelos, áreas funcionais
- **[SPEC.md](SPEC.md)** — user stories e regras Gherkin
- **[CHANGELOG.md](CHANGELOG.md)** — histórico de mudanças
- **[adr/](adr/)** — decisões arquiteturais numeradas
