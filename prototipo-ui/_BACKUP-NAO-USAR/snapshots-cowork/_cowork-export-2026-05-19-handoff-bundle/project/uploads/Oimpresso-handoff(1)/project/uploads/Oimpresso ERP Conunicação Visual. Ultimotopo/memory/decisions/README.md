# Architecture Decision Records (ADRs)

Este diretório contém os registros formais de decisões arquiteturais importantes do projeto Ponto WR2.

## O que é uma ADR

Uma **Architecture Decision Record** é um documento curto que captura uma decisão arquitetural significativa, seu contexto e suas consequências. Seguimos o formato do Michael Nygard:

- **Título:** ADR NNNN — título descritivo
- **Status:** Proposta | Aceita | Substituída por NNNN | Obsoleta
- **Contexto:** O problema e as forças que levaram à decisão
- **Decisão:** O que decidimos fazer
- **Consequências:** O que melhora, o que piora, o que muda

## Quando criar uma ADR

Crie uma nova ADR quando:

- A decisão afeta a arquitetura (estrutura, tecnologia, integrações)
- A decisão é difícil/custosa de reverter
- Pessoas novas no time vão perguntar "por que está assim?"
- Substitui uma decisão anterior

**Não crie ADR** para: escolhas de implementação local, naming, formatação, bugs.

## Como numerar

Sequencial, 4 dígitos, zero à esquerda: `0001`, `0002`, ...

Nome do arquivo: `NNNN-titulo-curto-em-kebab.md`

## Índice de ADRs

| # | Título | Status |
|---|---|---|
| [0001](./0001-estender-ultimatepos-opcao-c.md) | Estender UltimatePOS em vez de build ou fork | ✅ Aceita |
| [0002](./0002-nwidart-laravel-modules.md) | Usar nWidart/laravel-modules | ✅ Aceita |
| [0003](./0003-marcacoes-append-only.md) | Marcações append-only via triggers MySQL + app layer | ✅ Aceita |
| [0004](./0004-bridge-colaborador-config.md) | Tabela bridge `ponto_colaborador_config` | ✅ Aceita |
| [0005](./0005-uuid-vs-bigint.md) | UUID para auditáveis, BigInt para lookups | ✅ Aceita |
| [0006](./0006-multi-tenancy-logica.md) | Multi-tenancy lógica via business_id | ✅ Aceita |
| [0007](./0007-banco-horas-ledger.md) | Banco de horas como ledger append-only | ✅ Aceita |
| [0008](./0008-sidebar-unica-tabs-horizontais.md) | Sidebar com 1 item + tabs horizontais | ✅ Aceita |
| [0009](./0009-prototipos-html-puro.md) | Protótipos em HTML+Tailwind+Chart.js (não React) | ✅ Aceita |
| [0010](./0010-sistema-memoria-projeto.md) | Sistema de memória do projeto (CLAUDE.md + /memory/) | ✅ Aceita |
| [0011](./0011-alinhamento-padrao-jana.md) | Alinhamento com padrão Jana (UltimatePOS) | ✅ Aceita |
| [0023](./0023-inertia-v3-upgrade.md) | Upgrade Inertia.js v2 → v3 (faseado) | ✅ Aceita |

---

**Última atualização:** 2026-04-24
