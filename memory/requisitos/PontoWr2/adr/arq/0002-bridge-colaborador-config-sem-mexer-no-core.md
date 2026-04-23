# ADR ARQ-0002 (PontoWr2) · Bridge `ponto_colaborador_config` em vez de estender `users/employees`

- **Status**: accepted
- **Data**: 2026-04-22 (decisão original ~2024)
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: `memory/decisions/0004-bridge-colaborador-config.md`

## Contexto

PontoWr2 precisa associar dados específicos (PIS, matrícula SEFIP, tipo de jornada, banco de horas ligado) a cada usuário do sistema. Duas abordagens:

1. **Adicionar colunas na tabela `users` do UltimatePOS** — simples, acoplado.
2. **Tabela bridge `ponto_colaborador_config`** com FK pra `users.id` — desacoplado.

## Decisão

Criar tabela **`ponto_colaborador_config`** com FK para `users.id`. NUNCA modificar tabelas do core UltimatePOS (`users`, `business`, `employees`).

## Consequências

**Positivas:**
- Upgrade do UltimatePOS (v6.7 hoje, possíveis v7+ amanhã) não quebra.
- PontoWr2 vira módulo verdadeiramente plugável — pode desligar sem deixar lixo no core.
- Conformidade com regra do CLAUDE.md §5 ("Não modifique tabelas do core").

**Negativas:**
- Join extra em toda query que precisa dos dados de ponto do usuário.
- Sincronização — se user é deletado no core, config de ponto vira órfã (mitigação: FK cascade delete).

## Alternativas consideradas

- **Colunas custom em `users`**: rejeitado pela regra do CLAUDE.md.
- **NoSQL à parte**: overengineering pra ganho marginal.
