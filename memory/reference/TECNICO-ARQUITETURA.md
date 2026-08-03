---
id: reference-tecnico-arquitetura
name: Técnico — Arquitetura e módulos
description: As quatro camadas, a anatomia de um módulo nWidart e os limites que o CI enforça — apontando pro ARCHITECTURE e pro painel gerado em vez de redeclarar a árvore.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: tecnico
nav_order: 10
lente: [construir]
---

# Técnico — Arquitetura e módulos

> **O mapa técnico completo (arc42, C4, trust level por ator) é o
> [`governance/ARCHITECTURE.md`](../governance/ARCHITECTURE.md)** — comece por lá pra o detalhe.
> Aqui fica o suficiente pra saber **onde uma coisa nova deve nascer** e **o que o CI não deixa
> passar**.

## As quatro camadas

Camada de cima **herda** da de baixo e **nunca contradiz**. É o modelo mental que resolve a
maioria dos "onde isso mora?".

| Camada | O que é | Exemplos |
|---|---|---|
| **Governança** | as leis | Constituição v2 ([ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md)), ADRs, skills, trust tiers |
| **Verticais** | produto vendável por setor | Vestuário, ComunicaçãoVisual, OficinaAuto ([ADR 0121](../decisions/0121-oimpresso-modular-especializado-por-vertical.md)) |
| **Núcleo** | comum a todos | Jana, Financeiro, Fiscal, Repair (OS), FSM Pipeline |
| **Kernel** | base multi-tenant | UltimatePOS ([ADR 0001](../decisions/0001-estender-ultimatepos-opcao-c.md)) + `business_id` |

## Anatomia de um módulo

Todo módulo vive em `Modules/<Nome>/` ([ADR 0002](../decisions/0002-nwidart-laravel-modules.md)) e é
auto-contido:

| Pasta | O que mora ali |
|---|---|
| `Config/` | config do módulo, declarada no `module.json` |
| `Database/Migrations/` | schema **próprio** — não altera tabela de outro módulo |
| `Entities/` | Models Eloquent, com `business_id` no global scope |
| `Http/Controllers/` | finos: recebem e delegam |
| `Services/` | onde a regra vive (ex: o serviço que executa ação de estágio) |
| `Resources/views/` | Blade legado; as páginas Inertia ficam em `resources/js/Pages/` |
| `Tests/` | Pest, contra o tenant fictício — ver [testes Pest canônicos](tests-pest-canon.md) |

## Os limites que a máquina cobra

- Módulo **não** importa Entity de outro módulo direto — atravessa contrato ou serviço.
- O kernel não conhece vertical: a dependência é sempre de cima pra baixo.
- Estágio só muda pelo serviço da FSM — ver [Domínio — Estágio](DOMINIO-ESTAGIO.md).
- Runtime é separado por decisão irrevogável ([ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)):
  daemon, fila pesada e teste **não** rodam no shared. Ver [Fluxo — Deploy](FLUXO-DEPLOY.md).

## O que **não** documentar aqui

A **lista de módulos**. Ela é derivada da árvore (`git ls-tree -d --name-only HEAD Modules/`) e
publicada no [`PAINEL-SISTEMA`](PAINEL-SISTEMA.md), gerado por
[`system-map.mjs`](../../scripts/governance/system-map.mjs). Uma segunda lista, escrita à mão,
estaria desatualizada no primeiro módulo novo.
