# ADR ARQ-0002 (Repair) · Portal público consulta status sem login

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Cliente quer saber "meu reparo tá pronto?". Obrigar login pra consulta gera atrito. Mas expor `/repair/{id}` público vaza dados.

## Decisão

Rota `/repair-status` pede **número do reparo + telefone/CPF** (verificação de "quem criou"). Se bate, mostra **só status e data estimada** — sem preço, sem peças, sem assignee.

## Consequências

**Positivas:**
- UX cliente fantástica — zero atrito.
- Dados sensíveis protegidos.

**Negativas:**
- Rate-limiting é crítico (força bruta em números sequenciais).

## Alternativas consideradas

- **Link único por repair (token)**: viável, mas cliente perde o link.
