# ADR 0004 — Tabela bridge `ponto_colaborador_config`

**Status:** ✅ Aceita
**Data:** 2026-04-18

## Contexto

O UltimatePOS tem tabela `users` compartilhada por todo o sistema (vendas, estoque, compras, RH). Precisamos armazenar dados específicos de ponto para alguns desses usuários: matrícula, PIS, CPF, escala atual, se controla ponto, se usa banco de horas, admissão, desligamento.

Opções:

1. **Adicionar colunas em `users`** — invade o core do UltimatePOS, viola ADR 0001
2. **Estender tabela via herança (STI)** — não é padrão Laravel, complica
3. **Tabela bridge 1:1 opcional** — `ponto_colaborador_config` com `user_id` + `business_id`

## Decisão

**Criar `ponto_colaborador_config` como tabela bridge 1:1 opcional com `users`.**

- `user_id` FK para `users.id`
- `business_id` FK para `business.id` (multi-tenancy lógica)
- Todos os dados específicos de ponto vivem aqui
- Não todo user é colaborador — apenas aqueles registrados via `ponto/colaboradores`
- Não todo colaborador controla ponto — flag `controla_ponto` permite opt-out

## Consequências

### Positivas

- Zero mudança no core
- Um user pode ser "gestor que não bate ponto" (sem config) ou "colaborador que bate" (com config)
- Facilita desligamento: soft delete na bridge, user continua ativo
- Migrations ficam totalmente dentro do módulo

### Negativas

- Uma query extra (ou join) para dados de ponto
- Dev precisa lembrar: `$user->pontoConfig` pode ser null
- Exige relação explícita nos Models (`User::hasOne(Colaborador::class)` via bridge)

### Neutras

- Bridge é padrão comum em Laravel — documentado e reconhecível
