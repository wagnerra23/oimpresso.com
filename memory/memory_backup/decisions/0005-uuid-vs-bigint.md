# ADR 0005 — UUID para entidades auditáveis, BigInt para lookups

**Status:** ✅ Aceita
**Data:** 2026-04-18

## Contexto

Cada tabela precisa de uma estratégia de chave primária. Opções básicas:

- **BigInt auto-increment**: rápido, índice compacto, sequencial
- **UUID v4**: globalmente único, não adivinhável, bom para sync distribuído
- **ULID**: ordenável como BigInt, único como UUID — melhor dos dois mundos

## Decisão

**Estratégia mista:**

**UUID** para entidades **auditáveis** onde enumeração de IDs seria problema ou onde replicação/sync é plausível:

- `ponto_marcacoes` — append-only, pode ser replicada off-site
- `ponto_intercorrencias` — expostas em APIs, não queremos IDs sequenciais vazando volume
- `ponto_reps` — equipamento pode precisar gerar localmente
- `ponto_banco_horas_movimentos` — ledger, precisa UUID pelos mesmos motivos
- `ponto_importacoes` — identificador público

**BigInt** para tabelas de **lookup/config** internas, baixa cardinalidade, onde velocidade de join importa:

- `ponto_escalas`, `ponto_escala_turnos`
- `ponto_apuracao_dia` — tabela grande mas sempre consultada por `(colaborador_id, data)`
- `ponto_colaborador_config`, `ponto_banco_horas_saldo`

## Consequências

### Positivas

- Performance preservada onde importa (joins frequentes em BigInt)
- Segurança onde importa (UUIDs não vazam contagem)
- Futuro-friendly: UUIDs permitem geração offline (mobile, REP)

### Negativas

- Duas estratégias para dev lembrar
- UUIDs em FKs consomem mais espaço em índice

### Alternativa rejeitada

**ULID** seria ideal (ordenável + único), mas o Eloquent 10 tem suporte nativo melhor a UUID (`HasUuids` trait). Migração para ULID é plausível no futuro sem quebra de API.
