# ADR 0006 — Multi-tenancy lógica via `business_id`

**Status:** ✅ Aceita
**Data:** 2026-04-18

## Contexto

UltimatePOS é multi-empresa via tabela `business` + coluna `business_id` em quase toda tabela do sistema. Cada usuário pertence a um business, cada operação é escopada.

Para o Ponto WR2, duas opções:

- **Multi-tenancy lógica (single schema, coluna `business_id`)** — igual UltimatePOS, scope global
- **Multi-tenancy física (um banco por tenant)** — pacotes como `stancl/tenancy`, isolamento total

## Decisão

**Adotar multi-tenancy lógica via `business_id` — igual UltimatePOS.**

Todas as tabelas do módulo têm coluna `business_id NOT NULL` com FK para `business.id`. Todas as queries são filtradas via scope global ou middleware.

## Consequências

### Positivas

- Consistência total com UltimatePOS — mesma regra de isolamento em toda a aplicação
- Backups simples (dump do schema único)
- Migrations rodam uma vez
- Cross-business reports possíveis (não que a gente queira expor, mas técnica-mente simples)

### Negativas

- Uma falha de scope = vazamento entre businesses. Mitigação: scope global em todos os models + testes específicos
- Tabelas crescem rapidamente (todos os clientes no mesmo schema). Mitigação: particionamento por `business_id` em tabelas grandes, fase tardia
- Migrar um cliente para banco dedicado futuramente é trabalhoso

### Porta aberta para futuro

**Fase 12 (opcional)** prevê avaliar multi-tenancy física via `stancl/tenancy` para clientes grandes. Não é MVP.
